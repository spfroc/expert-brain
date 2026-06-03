<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentChunkResource;
use App\Models\DocumentChunk;
use App\Models\KnowledgeDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DocumentChunkController extends Controller
{
    public function index(Request $request, KnowledgeDocument $knowledgeDocument): AnonymousResourceCollection
    {
        $query = DocumentChunk::query()
            ->where('knowledge_document_id', $knowledgeDocument->id)
            ->orderBy('chunk_index');

        if ($keyword = $request->string('keyword')->toString()) {
            $query->where('content', 'like', "%{$keyword}%");
        }

        return DocumentChunkResource::collection($query->paginate($request->integer('per_page', 20)));
    }
}
