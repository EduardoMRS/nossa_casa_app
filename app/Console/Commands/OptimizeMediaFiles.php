<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Services\MediaOptimizer;
use Illuminate\Console\Command;

class OptimizeMediaFiles extends Command
{
    protected $signature = 'media:optimize {--limit=50 : Quantidade máxima de arquivos por execução} {--force= : ID de uma mídia específica para reotimizar} {--force-all : Reotimiza todas as mídias ignorando o limite}';

    protected $description = 'Gera versões otimizadas de mídia para preview rápido na galeria e modal';

    public function handle(): int
    {
        $diskName = (string) config('media.disk', 'media');
        $limit = (int) $this->option('limit');
        $forceOption = $this->option('force');

        if ($forceOption === true) {
            $this->error('Informe o ID da mídia com --force=ID.');

            return self::FAILURE;
        }

        $forceMediaId = $forceOption;
        $forceMediaId = is_string($forceMediaId) && $forceMediaId !== '' ? $forceMediaId : null;
        $forceAll = (bool) $this->option('force-all');

        if ($forceMediaId !== null && $forceAll) {
            $this->error('Use --force ou --force-all, não ambos.');

            return self::FAILURE;
        }

        $query = Media::query()->latest();

        if ($forceMediaId !== null) {
            $query->whereKey($forceMediaId);
        } elseif (! $forceAll) {
            $query->whereNull('optimized_path')->limit($limit);
        }

        $items = $query->get();

        if ($forceMediaId !== null && $items->isEmpty()) {
            $this->error('Mídia não encontrada.');

            return self::FAILURE;
        }

        if ($items->isEmpty()) {
            $this->info('Nenhuma mídia pendente de otimização.');

            return self::SUCCESS;
        }

        $processed = 0;

        $this->info('Mídias selecionadas para otimização: '.$items->count().'.');

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
