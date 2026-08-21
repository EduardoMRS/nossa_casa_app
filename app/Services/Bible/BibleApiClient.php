<?php

namespace App\Services\Bible;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class BibleApiClient
{
    /** @return list<array{id: string, name: string, abbreviation: string, language: string, language_code: string, scope: string, copyright: string, offline_available: bool}> */
    public function versions(): array
    {
        try {
            $remoteVersions = Cache::remember('bible-api.versions.v2', $this->cacheTtl(), function (): array {
                $payload = $this->cdn()->get('bibles.json')->throw()->json();

                if (! is_array($payload)) {
                    throw new RuntimeException('The Bible versions response is invalid.');
                }

                return array_values(collect($payload)
                    ->filter(fn (mixed $version): bool => is_array($version) && filled($version['id'] ?? null))
                    ->map(fn (array $version): array => [
                        'id' => (string) $version['id'],
                        'name' => (string) ($version['localVersionName'] ?: $version['version'] ?? $version['id']),
                        'abbreviation' => (string) ($version['localVersionAbbreviation'] ?: Str::upper((string) $version['id'])),
                        'language' => (string) data_get($version, 'language.name', ''),
                        'language_code' => (string) data_get($version, 'language.code', ''),
                        'scope' => (string) ($version['scope'] ?? ''),
                        'copyright' => (string) ($version['copyright'] ?? ''),
                        'offline_available' => false,
                    ])
                    ->values()
                    ->all());
            });
        } catch (Throwable $exception) {
            report($exception);
            $remoteVersions = [];
        }

        return collect($remoteVersions)
            ->keyBy('id')
            ->merge(collect($this->offlineVersions())->mapWithKeys(
                fn (array $version, string $id): array => [$id => [
                    'id' => $id,
                    'name' => (string) $version['name'],
                    'abbreviation' => (string) $version['abbreviation'],
                    'language' => (string) $version['language'],
                    'language_code' => (string) $version['language_code'],
                    'scope' => (string) $version['scope'],
                    'copyright' => isset($version['copyright_key']) ? __((string) $version['copyright_key']) : '',
                    'offline_available' => true,
                ]],
            ))
            ->sortBy([
                ['language', 'asc'],
                ['name', 'asc'],
            ])
            ->values()
            ->all();
    }

    /** @return list<array{slug: string, name: string}> */
    public function books(string $version): array
    {
        if ($this->canDownloadOffline($version)) {
            return array_values(collect($this->offlineBundle($version)['books'])
                ->map(fn (array $book): array => [
                    'slug' => $book['slug'],
                    'name' => $book['name'],
                ])
                ->all());
        }

        return Cache::remember("bible-api.{$version}.books", $this->cacheTtl(), function () use ($version): array {
            $payload = $this->repository()->get($this->encodedPath($version, 'books'))->throw()->json();

            return array_values(collect(is_array($payload) ? $payload : [])
                ->filter(fn (mixed $item): bool => is_array($item) && ($item['type'] ?? null) === 'dir')
                ->map(fn (array $item): array => [
                    'slug' => (string) $item['name'],
                    'name' => Str::ucfirst(str_replace(['-', '_'], ' ', (string) $item['name'])),
                ])
                ->values()
                ->all());
        });
    }

    /** @return list<int> */
    public function chapters(string $version, string $book): array
    {
        if ($this->canDownloadOffline($version)) {
            $matchingBook = $this->offlineBook($version, $book);

            return array_values(array_column($matchingBook['chapters'], 'chapter'));
        }

        return Cache::remember("bible-api.{$version}.{$book}.chapters", $this->cacheTtl(), function () use ($version, $book): array {
            $payload = $this->repository()->get($this->encodedPath($version, 'books', $book, 'chapters'))->throw()->json();

            return array_values(collect(is_array($payload) ? $payload : [])
                ->filter(fn (mixed $item): bool => is_array($item)
                    && ($item['type'] ?? null) === 'file'
                    && preg_match('/^\d+\.json$/', (string) ($item['name'] ?? '')) === 1)
                ->map(fn (array $item): int => (int) pathinfo((string) $item['name'], PATHINFO_FILENAME))
                ->unique()
                ->sort()
                ->values()
                ->all());
        });
    }

    /** @return list<array{book: string, chapter: int, verse: int, text: string}> */
    public function chapter(string $version, string $book, int $chapter): array
    {
        if ($this->canDownloadOffline($version)) {
            $matchingChapter = collect($this->offlineBook($version, $book)['chapters'])
                ->firstWhere('chapter', $chapter);

            if (! is_array($matchingChapter)) {
                throw new RuntimeException('The requested Bible chapter was not found.');
            }

            return $matchingChapter['verses'];
        }

        return Cache::remember("bible-api.{$version}.{$book}.{$chapter}", $this->cacheTtl(), function () use ($version, $book, $chapter): array {
            $payload = $this->cdn()
                ->get($this->encodedPath($version, 'books', $book, 'chapters', "{$chapter}.json"))
                ->throw()
                ->json('data');

            return array_values(collect(is_array($payload) ? $payload : [])
                ->filter(fn (mixed $verse): bool => is_array($verse) && filled($verse['text'] ?? null))
                ->map(fn (array $verse): array => [
                    'book' => (string) ($verse['book'] ?? $book),
                    'chapter' => (int) ($verse['chapter'] ?? $chapter),
                    'verse' => (int) ($verse['verse'] ?? 0),
                    'text' => (string) $verse['text'],
                ])
                ->unique('verse')
                ->sortBy('verse')
                ->values()
                ->all());
        });
    }

    /** @return array{book: string, chapter: int, verse: int, text: string} */
    public function verse(string $version, string $book, int $chapter, int $verse): array
    {
        $matchingVerse = collect($this->chapter($version, $book, $chapter))->firstWhere('verse', $verse);

        if (! is_array($matchingVerse)) {
            throw new RuntimeException('The requested Bible verse was not found.');
        }

        return $matchingVerse;
    }

    public function canDownloadOffline(string $version): bool
    {
        return array_key_exists($version, $this->offlineVersions());
    }

    /** @return array{version: array{id: string, name: string, abbreviation: string, language: string, copyright: string}, books: list<array{slug: string, name: string, chapters: list<array{chapter: int, verses: list<array{book: string, chapter: int, verse: int, text: string}>}>}>} */
    public function offlineBundle(string $version): array
    {
        $configuration = $this->offlineVersions()[$version] ?? null;

        if (! is_array($configuration)) {
            throw new RuntimeException('The requested Bible version does not provide an offline bundle.');
        }

        $cacheKey = "bible-api.offline.{$version}.v1.".app()->getLocale();

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($configuration, $version): array {
            $payload = Http::acceptJson()
                ->connectTimeout(3)
                ->timeout((float) config('bible.timeout', 10))
                ->retry(2, 150)
                ->get((string) $configuration['source_url'])
                ->throw()
                ->json();

            if (! is_array($payload) || ! is_array($payload['books'] ?? null)) {
                throw new RuntimeException('The offline Bible response is invalid.');
            }

            $books = collect($payload['books'])
                ->filter(fn (mixed $book): bool => is_array($book) && filled($book['name'] ?? null) && is_array($book['chapters'] ?? null))
                ->map(function (array $book): array {
                    $bookName = (string) $book['name'];

                    return [
                        'slug' => Str::slug($bookName),
                        'name' => $bookName,
                        'chapters' => array_values(collect($book['chapters'])
                            ->filter(fn (mixed $chapter): bool => is_array($chapter) && isset($chapter['chapter']) && is_array($chapter['verses'] ?? null))
                            ->map(function (array $chapter) use ($bookName): array {
                                $chapterNumber = (int) $chapter['chapter'];

                                return [
                                    'chapter' => $chapterNumber,
                                    'verses' => array_values(collect($chapter['verses'])
                                        ->filter(fn (mixed $verse): bool => is_array($verse) && isset($verse['verse']) && filled($verse['text'] ?? null))
                                        ->map(fn (array $verse): array => [
                                            'book' => $bookName,
                                            'chapter' => $chapterNumber,
                                            'verse' => (int) $verse['verse'],
                                            'text' => (string) $verse['text'],
                                        ])
                                        ->all()),
                                ];
                            })
                            ->all()),
                    ];
                })
                ->values()
                ->all();

            return [
                'version' => [
                    'id' => $version,
                    'name' => (string) $configuration['name'],
                    'abbreviation' => (string) $configuration['abbreviation'],
                    'language' => (string) $configuration['language'],
                    'copyright' => isset($configuration['copyright_key']) ? __((string) $configuration['copyright_key']) : '',
                ],
                'books' => $books,
            ];
        });
    }

    private function cdn(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('bible.cdn_url'), '/'))
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout((float) config('bible.timeout', 10))
            ->retry(2, 150);
    }

    private function repository(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('bible.repository_contents_url'), '/'))
            ->acceptJson()
            ->withUserAgent('NossaCasaApp/1.0')
            ->connectTimeout(3)
            ->timeout((float) config('bible.timeout', 10))
            ->retry(2, 150);
    }

    private function cacheTtl(): int
    {
        return max(60, (int) config('bible.cache_ttl', 86400));
    }

    private function encodedPath(string ...$segments): string
    {
        return implode('/', array_map('rawurlencode', $segments));
    }

    /** @return array<string, array{name: string, abbreviation: string, language: string, language_code: string, scope: string, copyright_key?: string, source_url: string}> */
    private function offlineVersions(): array
    {
        $versions = config('bible.offline_versions', []);

        return is_array($versions) ? $versions : [];
    }

    /** @return array{slug: string, name: string, chapters: list<array{chapter: int, verses: list<array{book: string, chapter: int, verse: int, text: string}>}>} */
    private function offlineBook(string $version, string $book): array
    {
        $matchingBook = collect($this->offlineBundle($version)['books'])->firstWhere('slug', $book);

        if (! is_array($matchingBook)) {
            throw new RuntimeException('The requested Bible book was not found.');
        }

        return $matchingBook;
    }
}
