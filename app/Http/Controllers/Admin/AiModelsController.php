<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiModel;
use App\Models\AiQuery;
use App\Services\AiProvider;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AiModelsController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $provider = $request->input('provider');
        $status = $request->input('status');
        $perPage = $request->input('per_page', 10);

        
        $metrics = AiQuery::select('model', 'provider')
            ->selectRaw('COUNT(*) as total_usage')
            ->selectRaw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as success_count')
            ->selectRaw('SUM(CASE WHEN status = "error" THEN 1 ELSE 0 END) as error_count')
            ->selectRaw('MAX(created_at) as last_used_at')
            ->selectRaw('SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(`usage`, "$.prompt_tokens")) AS UNSIGNED)) as total_prompt_tokens')
            ->selectRaw('SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(`usage`, "$.completion_tokens")) AS UNSIGNED)) as total_completion_tokens')
            ->selectRaw('SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(`usage`, "$.total_tokens")) AS UNSIGNED)) as total_tokens_used')
            ->groupBy('model', 'provider')
            ->when($search, function($query) use ($search) {
                $query->where('model', 'like', "%{$search}%");
            })
            ->when($provider, function($query) use ($provider) {
                $query->where('provider', $provider);
            })
            ->get()
            ->keyBy(function($item) {
                return $item->provider . '_' . $item->model;
            });

        // Traz todos os modelos não removidos ordenados (o escopo global aplica a ordem por provider + position)
        $models = AiModel::where('status', '!=', 'removed')
            ->when($search, function($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('model_id', 'like', "%{$search}%");
            })
            ->when($provider, function($query) use ($provider) {
                $query->where('provider', $provider);
            })
            ->when($status, function($query) use ($status) {
                $query->where('status', $status);
            })
            ->paginate($perPage)->through(function($model) use ($metrics) {
                $key = $model->provider . '_' . $model->model_id;
                $metric = $metrics->get($key);

                $total = $metric->total_usage ?? 0;
                $success = $metric->success_count ?? 0;

                $model->total_usage = $total;
                $model->success_count = $success;
                $model->error_count = $metric->error_count ?? 0;
                $model->last_used_at = $metric->last_used_at ?? null;
                $model->success_rate = $total > 0 ? round(($success / $total) * 100, 2) : 100;
                
                $model->prompt_tokens = (int)($metric->total_prompt_tokens ?? 0);
                $model->completion_tokens = (int)($metric->total_completion_tokens ?? 0);
                $model->total_tokens = (int)($metric->total_tokens_used ?? 0);

                return $model;
            });

        return response()->json($models);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'provider' => 'required|string|in:'. implode(',', AiProvider::getProviders()),
            'model_id' => [
                'required',
                'string',
                Rule::unique('central.ai_models', 'model_id')
                ->where('provider', $request->input('provider'))
                ->where(function($query) {
                    return $query->where('status', '!=', 'removed');
                })
            ],
            'name' => 'nullable|string',
            'status' => 'nullable|string|in:active,inactive,removed',
            'context_length' => 'nullable|integer',
            'input_modalities' => 'nullable|string',
            'output_modalities' => 'nullable|string',
            'price_prompt' => 'nullable|numeric',
            'price_completion' => 'nullable|numeric',
            'position' => [
                'nullable',
                'integer',
                Rule::unique('central.ai_models', 'position')->where(function($query) use ($request) {
                    return $query->where('provider', $request->input('provider'))
                                 ->where('status', '!=', 'removed');
                })
            ],
        ],
        [
            'provider.in' => __('aimodel.invalid_provider'),
            'model_id.unique' => __('aimodel.already_registered'),
            'status.in' => __('aimodel.invalid_status'),
        ]);

        // Define a próxima posição livre para este Provedor específico
        $validated['position'] = AiModel::where('provider', $validated['provider'])->max('position') + 1;

        $model = AiModel::create($validated);
        return response()->json(['message' => __('aimodel.create_success'), 'data' => $model], 201);
    }

    public function update(Request $request, AiModel $aiModel)
    {
        $validated = $request->validate([
            'provider' => 'sometimes|string|in:'. implode(',', AiProvider::getProviders()),
            'model_id' => [
                'sometimes',
                'string',
                Rule::unique('central.ai_models', 'model_id')->ignore($aiModel->id)
                    ->where('provider', $request->input('provider'))
                    ->where(function($query) {
                        return $query->where('status', '!=', 'removed');
                    })
            ],
            'name' => 'sometimes|nullable|string',
            'status' => 'sometimes|nullable|string|in:active,inactive,removed',
            'context_length' => 'sometimes|nullable|integer',
            'input_modalities' => 'sometimes|nullable|string',
            'output_modalities' => 'sometimes|nullable|string',
            'price_prompt' => 'sometimes|nullable|numeric',
            'price_completion' => 'sometimes|nullable|numeric',
            'position' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::unique('central.ai_models', 'position')->where(function($query) use ($request, $aiModel) {
                    return $query->where('provider', $request->input('provider'))
                                 ->where('status', '!=', 'removed')
                                 ->where('id', '!=', $aiModel->id);
                })
            ],
        ],
        [
            'provider.in' => __('aimodel.invalid_provider'),
            'model_id.unique' => __('aimodel.already_registered'),
            'status.in' => __('aimodel.invalid_status'),
        ]);

        if ($request->has('is_active')) {
            $validated['status'] = $request->input('is_active') ? 'active' : 'inactive';
        }

        $aiModel->update($validated);
        return response()->json(['message' => __('aimodel.update_success'), 'data' => $aiModel]);
    }

    /**
     * Atualiza as posições em massa respeitando os limites e ids de cada Provedor
     */
    public function updateOrder(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:central.ai_models,id'
        ]);

        // Ignora temporariamente o escopo global para evitar conflitos na atualização
        foreach ($request->input('ids') as $index => $id) {
            AiModel::withoutGlobalScope('order_provider_position')
                ->where('id', $id)
                ->update(['position' => $index]);
        }

        return response()->json(['message' => __('aimodel.order_update_success')]);
    }

    public function destroy(AiModel $aiModel)
    {
        $aiModel->update(['status' => 'removed']);
        return response()->json(['message' => __('aimodel.model_removed_success')]);
    }

    public function fetchExternalModels(Request $request, string $provider)
    {
        try {
            $aiProvider = new AiProvider($provider);
            $externalData = $aiProvider->getModels();
            
            $models = [];
            if (isset($externalData['data'])) {
                foreach ($externalData['data'] as $m) {
                    $pricePrompt = isset($m['pricing']['prompt']) ? (float)$m['pricing']['prompt'] : 0.0;
                    $priceCompletion = isset($m['pricing']['completion']) ? (float)$m['pricing']['completion'] : 0.0;

                    $isFree = ($pricePrompt == 0 && $priceCompletion == 0);
                    if ($provider === 'gemini' && $isFree && str_contains($m['id'], ':free')) {
                        continue;
                    }

                    $inputModalities = isset($m['architecture']['input_modalities']) 
                        ? implode(',', $m['architecture']['input_modalities']) 
                        : 'text';
                    $outputModalities = isset($m['architecture']['output_modalities']) 
                        ? implode(',', $m['architecture']['output_modalities']) 
                        : 'text';

                    $models[] = [
                        'id' => $m['id'],
                        'name' => $m['name'] ?? $m['id'],
                        'context_length' => $m['context_length'] ?? null,
                        'input_modalities' => $inputModalities,
                        'output_modalities' => $outputModalities,
                        'price_prompt' => $pricePrompt,
                        'price_completion' => $priceCompletion,
                    ];
                }
            }

            return response()->json($models);
        } catch (\Exception $e) {
            return response()->json(['error' => __('aimodel.no_found_models')], 500);
        }
    }
}
