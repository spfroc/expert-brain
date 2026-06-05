<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiModel;
use App\Services\AiModel\AiModelRegistryService;
use App\Services\AiModel\EmbeddingCoverageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AiModelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AiModel::query()->latest('id');

        if ($taskType = $request->string('task_type')->toString()) {
            $query->where('task_type', $taskType);
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate($request->integer('per_page', 50)),
            'message' => 'ok',
            'errors' => null,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'model_key' => ['required', 'string', 'max:255', 'unique:ai_models,model_key'],
            'task_type' => ['required', Rule::in(['embedding', 'reranker', 'llm', 'ocr'])],
            'provider' => ['required', 'string', 'max:100'],
            'model_id' => ['nullable', 'string', 'max:255'],
            'local_path' => ['nullable', 'string', 'max:500'],
            'dimension' => ['nullable', 'integer', 'min:1'],
            'device' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'download_command' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        $model = AiModel::query()->create(array_merge($validated, [
            'status' => 'registered',
            'created_by' => $request->user()?->id,
        ]));

        $model->events()->create([
            'event_type' => 'created',
            'message' => 'Model registered.',
            'created_by' => $request->user()?->id,
        ]);

        return response()->json(['success' => true, 'data' => $model, 'message' => 'ok', 'errors' => null], 201);
    }

    public function update(Request $request, AiModel $aiModel): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'task_type' => ['sometimes', 'required', Rule::in(['embedding', 'reranker', 'llm', 'ocr'])],
            'provider' => ['sometimes', 'required', 'string', 'max:100'],
            'model_id' => ['nullable', 'string', 'max:255'],
            'local_path' => ['nullable', 'string', 'max:500'],
            'dimension' => ['nullable', 'integer', 'min:1'],
            'device' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'download_command' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        $aiModel->update($validated);
        $aiModel->events()->create([
            'event_type' => 'updated',
            'message' => 'Model updated.',
            'created_by' => $request->user()?->id,
        ]);

        return response()->json(['success' => true, 'data' => $aiModel->refresh(), 'message' => 'ok', 'errors' => null]);
    }

    public function activate(Request $request, AiModel $aiModel, AiModelRegistryService $service): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $service->activate($aiModel, $request->user()?->id),
            'message' => 'ok',
            'errors' => null,
        ]);
    }

    public function check(Request $request, AiModel $aiModel, AiModelRegistryService $service): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $service->check($aiModel, $request->user()?->id),
            'message' => 'ok',
            'errors' => null,
        ]);
    }

    public function coverage(Request $request, AiModel $aiModel, EmbeddingCoverageService $coverageService): JsonResponse
    {
        $knowledgeBaseId = $request->integer('knowledge_base_id') ?: null;

        return response()->json([
            'success' => true,
            'data' => $coverageService->knowledgeBaseCoverage($aiModel, $knowledgeBaseId),
            'message' => 'ok',
            'errors' => null,
        ]);
    }

    public function missingDocuments(Request $request, AiModel $aiModel, EmbeddingCoverageService $coverageService): JsonResponse
    {
        $knowledgeBaseId = $request->integer('knowledge_base_id') ?: null;
        $limit = max(1, min(200, $request->integer('limit', 50)));

        return response()->json([
            'success' => true,
            'data' => $coverageService->documentCoverage($aiModel, $knowledgeBaseId, $limit, true),
            'message' => 'ok',
            'errors' => null,
        ]);
    }

    public function installRecommended(AiModelRegistryService $service): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['installed_count' => $service->installRecommended()],
            'message' => 'ok',
            'errors' => null,
        ]);
    }

    public function events(AiModel $aiModel): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $aiModel->events()->latest('id')->limit(50)->get(),
            'message' => 'ok',
            'errors' => null,
        ]);
    }
}
