<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait UploadsMedia
{
    /**
     * Lida com o upload de um arquivo, salvando via Storage e gerando o path.
     *
     * @param  UploadedFile|string|null  $file  Arquivo ou caminho do arquivo a ser enviado
     * @param  string  $directory  Diretório base no Storage (ex: 'events/covers')
     * @param  string|null  $oldPath  Caminho do arquivo antigo para ser substituído (opcional)
     * @return string|null Retorna o caminho do arquivo salvo ou null se a imagem for removida/ausente
     */
    protected function handleMediaUpload(UploadedFile|string|null $file, string $directory, ?string $oldPath = null): ?string
    {
        $disk = Storage::disk((string) config('media.disk'));

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
            $path = $file->store($directory, (string) config('media.disk'));
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
                    $filename .= '.'.$extension;
                }

                $path = $directory.'/'.$filename;
                $stored = $disk->put($path, $resource);

                if (! $stored) {
                    $path = null;
                }

                if (is_resource($resource)) {
                    fclose($resource);
                }
            }
        }

        return $path;
    }
}
