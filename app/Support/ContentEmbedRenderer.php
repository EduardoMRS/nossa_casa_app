<?php

namespace App\Support;

use App\Models\Event;
use App\Models\Form;
use App\Models\Library;
use App\Models\Media;
use App\Models\Post;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ContentEmbedRenderer
{
    public function render(string $markdown, string $churchId): string
    {
        $html = Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]);

        return (string) preg_replace_callback(
            '/<p>\[\[(form|media|post|event|library):([0-9A-HJKMNP-TV-Z]{26})\]\]<\/p>/i',
            fn (array $matches): string => $this->renderEmbed(strtolower($matches[1]), $matches[2], $churchId) ?? $matches[0],
            $html,
        );
    }

    private function renderEmbed(string $type, string $id, string $churchId): ?string
    {
        $model = match ($type) {
            'form' => Form::query()->where('church_id', $churchId)->find($id),
            'media' => Media::query()->where('church_id', $churchId)->find($id),
            'post' => Post::query()->where('church_id', $churchId)->where('visibility', 'public')->find($id),
            'event' => Event::query()->where('church_id', $churchId)->find($id),
            'library' => Library::query()->where('church_id', $churchId)->find($id),
            default => null,
        };

        if (! $model) {
            return null;
        }

        if ($model instanceof Media) {
            $url = $this->escape($model->url);
            $title = $this->escape($model->title ?: basename($model->file_path));

            return str_starts_with((string) $model->mimetype, 'video/')
                ? "<figure class=\"content-embed content-embed-media\"><video controls preload=\"metadata\" src=\"{$url}\"></video><figcaption>{$title}</figcaption></figure>"
                : "<figure class=\"content-embed content-embed-media\"><img loading=\"lazy\" src=\"{$url}\" alt=\"{$title}\"><figcaption>{$title}</figcaption></figure>";
        }

        $title = $this->escape((string) $model->getAttribute('title'));
        $description = $this->escape(Str::limit(strip_tags((string) ($model->getAttribute('description') ?? $model->getAttribute('content'))), 180));
        $url = $this->embedUrl($model);
        $tag = $url ? 'a' : 'div';
        $href = $url ? ' href="'.$this->escape($url).'"' : '';

        return "<{$tag}{$href} class=\"content-embed content-embed-card\"><strong>{$title}</strong><span>{$description}</span></{$tag}>";
    }

    private function embedUrl(Model $model): ?string
    {
        return match (true) {
            $model instanceof Post => route('posts.public.show', $model->slug),
            $model instanceof Event => route('events.show', $model->slug),
            $model instanceof Library => $model->file_url,
            default => null,
        };
    }

    private function escape(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
