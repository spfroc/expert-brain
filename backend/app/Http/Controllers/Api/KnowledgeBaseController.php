<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Knowledge\KnowledgeBaseRequest;
use App\Http\Resources\KnowledgeBaseResource;
use App\Models\KnowledgeBase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class KnowledgeBaseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = KnowledgeBase::query()->latest('id');

        if ($keyword = $request->string('keyword')->toString()) {
            $query->where('name', 'like', "%{$keyword}%");
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        return KnowledgeBaseResource::collection($query->paginate($request->integer('per_page', 15)));
    }

    public function store(KnowledgeBaseRequest $request): KnowledgeBaseResource
    {
        $payload = collect($request->validated())
            ->filter(fn ($value) => $value !== null)
            ->all();

        $payload['status'] ??= 'active';
        $payload['created_by'] = $request->user()?->id;

        $base = KnowledgeBase::query()->create($payload);

        return new KnowledgeBaseResource($base);
    }

    public function show(KnowledgeBase $knowledgeBase): KnowledgeBaseResource
    {
        return new KnowledgeBaseResource($knowledgeBase);
    }

    public function update(KnowledgeBaseRequest $request, KnowledgeBase $knowledgeBase): KnowledgeBaseResource
    {
        $payload = collect($request->validated())
            ->filter(fn ($value) => $value !== null)
            ->all();

        $knowledgeBase->update($payload);

        return new KnowledgeBaseResource($knowledgeBase->refresh());
    }

    public function destroy(KnowledgeBase $knowledgeBase): JsonResponse
    {
        $knowledgeBase->delete();

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'ok',
            'errors' => null,
        ]);
    }
}
