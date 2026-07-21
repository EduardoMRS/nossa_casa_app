<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AiModel;
use App\Services\AiProvider;

class AiModelsUpdateCommand extends Command
{
    protected $signature = 'ai:check-models';
    protected $description = 'Atualiza dados técnicos dos modelos cadastrados. Inativa como "removed" os indisponíveis.';

    public function handle()
    {
        $providers = AiProvider::getProviders();

        foreach ($providers as $providerName) {
            $this->info("Verificando e sincronizando provedor: {$providerName}");
            
            try {
                $providerInstance = new AiProvider($providerName);
                $apiData = $providerInstance->getModels();
                
                if (!isset($apiData['data'])) {
                    $this->error("Formato inválido retornado pelo provedor {$providerName}");
                    continue;
                }

                $apiModelsCollection = collect($apiData['data']);
                $availableModelIds = $apiModelsCollection->pluck('id')->toArray();

                $localModels = AiModel::withoutGlobalScope('order_provider_position')
                    ->where('provider', 'LIKE', $providerName . '%')
                    ->where('status', '!=', 'removed')
                    ->get();

                $activeCount = $localModels->where('status', 'active')->count();

                // Se todos os modelos do provedor sumiram ou foram inativados/removidos, recria os defaults
                if ($activeCount === 0 && $localModels->isEmpty()) {
                    $this->warn("Sem modelos ativos para o provedor '{$providerName}'. Populando modelos padrão...");
                    $defaultModels = config("services.ia.{$providerName}.models", []);
                    
                    foreach ($defaultModels as $index => $defaultModelId) {
                        $apiMatched = $apiModelsCollection->firstWhere('id', $defaultModelId);
                        
                        $pricePrompt = isset($apiMatched['pricing']['prompt']) ? (float)$apiMatched['pricing']['prompt'] : 0.0;
                        $priceCompletion = isset($apiMatched['pricing']['completion']) ? (float)$apiMatched['pricing']['completion'] : 0.0;
                        
                        $inputModalities = isset($apiMatched['architecture']['input_modalities']) 
                            ? implode(',', $apiMatched['architecture']['input_modalities']) 
                            : 'text';
                        $outputModalities = isset($apiMatched['architecture']['output_modalities']) 
                            ? implode(',', $apiMatched['architecture']['output_modalities']) 
                            : 'text';

                        AiModel::updateOrCreate(
                            ['provider' => $providerName, 'model_id' => $defaultModelId],
                            [
                                'name' => $apiMatched['name'] ?? $defaultModelId,
                                'status' => 'active',
                                'position' => $index, // Posição dedicada deste Provedor
                                'context_length' => $apiMatched['context_length'] ?? 128000,
                                'price_prompt' => $pricePrompt,
                                'price_completion' => $priceCompletion,
                                'input_modalities' => $inputModalities,
                                'output_modalities' => $outputModalities,
                            ]
                        );
                    }
                    continue;
                }

                // Sincronização comum: Apenas atualiza dados ou inativa se não existir na API remota
                foreach ($localModels as $localModel) {
                    if (!in_array($localModel->model_id, $availableModelIds)) {
                        $localModel->update(['status' => 'removed']);
                        $this->warn("O modelo '{$localModel->model_id}' foi marcado como 'removed' (indisponível na API).");
                        continue;
                    }

                    $apiMatched = $apiModelsCollection->firstWhere('id', $localModel->model_id);
                    if ($apiMatched) {
                        $pricePrompt = isset($apiMatched['pricing']['prompt']) ? (float)$apiMatched['pricing']['prompt'] : 0.0;
                        $priceCompletion = isset($apiMatched['pricing']['completion']) ? (float)$apiMatched['pricing']['completion'] : 0.0;
                        
                        $inputModalities = isset($apiMatched['architecture']['input_modalities']) 
                            ? implode(',', $apiMatched['architecture']['input_modalities']) 
                            : 'text';
                        $outputModalities = isset($apiMatched['architecture']['output_modalities']) 
                            ? implode(',', $apiMatched['architecture']['output_modalities']) 
                            : 'text';

                        $localModel->update([
                            'context_length' => $apiMatched['context_length'] ?? $localModel->context_length,
                            'price_prompt' => $pricePrompt,
                            'price_completion' => $priceCompletion,
                            'input_modalities' => $inputModalities,
                            'output_modalities' => $outputModalities,
                        ]);
                    }
                }

            } catch (\Exception $e) {
                $this->error("Erro ao sincronizar provedor {$providerName}: " . $e->getMessage());
            }
        }

        $this->info('Sincronização concluída!');
    }
}
