<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

trait UploadsMedia
{
    /**
     * Lida com o upload de um arquivo, salvando via Storage e gerando o path.
     *
     * @param UploadedFile|string|null $file Arquivo ou caminho do arquivo a ser enviado
     * @param string $directory Diretório base no Storage (ex: 'events/covers')
     * @param string|null $oldPath Caminho do arquivo antigo para ser substituído (opcional)
     * @return string|null Retorna o caminho do arquivo salvo ou null se a imagem for removida/ausente
     */
    protected function handleMediaUpload(UploadedFile|string|null $file, string $directory, ?string $oldPath = null): ?string
    {
        // 1. Se for nulo e havia uma imagem antes, apagamos do disco (intenção de exclusão)
        if ($file === null) {
            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
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
            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
            $path = $file->store($directory, 'public');
            
        } elseif (is_string($file)) {
            $fileData = getFileMetadata($file);
            
            if ($fileData['exists']) {
                if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
                
                // Pega o resource, salva via putFile e fecha o stream
                $resource = $fileData['handler']();
                $path = Storage::disk('public')->putFile($directory, $resource);
                
                if (is_resource($resource)) {
                    fclose($resource);
                }
            }
        }

        return $path;
    }
}
