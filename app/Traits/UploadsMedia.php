<?php

namespace App\Traits;

use App\Services\MediaOptimizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait UploadsMedia
{
    /**
     * Lida com o upload de um arquivo, salvando via Storage e gerando o path.
     *
     * @param UploadedFile|string|null $file Arquivo ou caminho do arquivo a ser enviado
     * @param string $directory Diretório base no Storage (ex: 'events/covers')
     * @param string|null $oldPath Caminho do arquivo antigo para ser substituído (opcional)
     * @return array{path: string|null, optimized_path: string|null}|string|null
     */
    protected function handleMediaUpload(UploadedFile|string|null $file, string $directory, ?string $oldPath = null): ?string
    {
        $diskName = (string) config('media.disk');
        $disk = Storage::disk($diskName);

        // 1. Se for nulo e havia uma imagem antes, apagamos do disco (intenção de exclusão)
        if ($file === null) {
            if ($oldPath && $disk->exists($oldPath)) {
                $disk->delete($oldPath);
            }

            return null;
        }

        // 2. Se for string e for exatamente o path antigo, não fazemos nada, só devolvemos o path
        if (is_string($file) && $file === $oldPath) {
            return $oldPath;
        }

        $path = null;

        // 3. Processamento de UploadedFile ou String (Helper)
        if ($file instanceof UploadedFile) {
            if ($oldPath && $disk->exists($oldPath)) {
                $disk->delete($oldPath);
            }
            $path = $file->store($directory, $diskName);
        } elseif (is_string($file)) {
            $fileData = getFileMetadata($file);

            if ($fileData['exists']) {
                if ($oldPath && $disk->exists($oldPath)) {
                    $disk->delete($oldPath);
                }

                $resource = $fileData['handler']();
                $extension = strtolower(pathinfo((string) $fileData['name'], PATHINFO_EXTENSION));
                $filename = Str::uuid()->toString();

                if ($extension !== '') {
                    $filename .= '.' . $extension;
                }

                $path = $directory . '/' . $filename;
                $stored = $disk->put($path, $resource);

                if (!$stored) {
                    $path = null;
                }

                if (is_resource($resource)) {
                    fclose($resource);
                }
            }
        }

        return $path;
    }

    /**
     * Auxiliar para gerar o preview otimizado após o upload.
     */
    protected function generateMediaPreview(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $diskName = (string) config('media.disk');

        return MediaOptimizer::createPreview($diskName, $path);
    }
}
