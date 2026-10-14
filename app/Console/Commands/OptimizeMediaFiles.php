<?php
namespace App\Console\Commands;

use App\Models\Media;
use App\Services\MediaOptimizer;
use Illuminate\Console\Command;

class OptimizeMediaFiles extends Command
{
    protected $signature = 'media:optimize {--limit=50 : Quantidade máxima de arquivos por execução}';
    protected $description = 'Gera versões otimizadas de mídia para preview rápido na galeria e modal';

    public function handle(): int
    {
        $diskName = (string) config('media.disk', 'media');
        $limit = (int) $this->option('limit');

        $items = Media::query()
            ->whereNull('optimized_path')
            ->latest()
            ->limit($limit)
            ->get();

        if ($items->isEmpty()) {
            $this->info('Nenhuma mídia pendente de otimização.');
            return self::SUCCESS;
        }

        $processed = 0;

        foreach ($items as $media) {
            $originalPath = parse_url($media->file_path, PHP_URL_PATH) ?: $media->file_path;

            $optimizedPath = MediaOptimizer::createPreview($diskName, $originalPath);

            if ($optimizedPath) {
                $media->update(['optimized_path' => $optimizedPath]);
                $processed++;
            }
        }

        $this->info("Otimização concluída: {$processed} arquivo(s) processado(s).");

        return self::SUCCESS;
    }
}
