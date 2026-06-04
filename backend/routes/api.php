<?php

use App\Http\Controllers\Api\DocumentChunkController;
use App\Http\Controllers\Api\DocumentIngestionController;
use App\Http\Controllers\Api\KnowledgeBaseController;
use App\Http\Controllers\Api\KnowledgeCategoryController;
use App\Http\Controllers\Api\KnowledgeDocumentController;
use App\Http\Controllers\Api\KnowledgeTagController;
use App\Http\Controllers\Api\RagController;
use App\Http\Controllers\Api\SessionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

Route::prefix('v1')->group(function (): void {
    Route::post('/session', [SessionController::class, 'store']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/session', [SessionController::class, 'show']);
        Route::delete('/session', [SessionController::class, 'destroy']);

        Route::apiResource('knowledge-bases', KnowledgeBaseController::class);
        Route::apiResource('knowledge-categories', KnowledgeCategoryController::class);
        Route::apiResource('knowledge-tags', KnowledgeTagController::class);
        Route::apiResource('knowledge-documents', KnowledgeDocumentController::class);
        Route::post('knowledge-documents/{knowledgeDocument}/publish', [KnowledgeDocumentController::class, 'publish']);
        Route::post('knowledge-documents/{knowledgeDocument}/expire', [KnowledgeDocumentController::class, 'expire']);
        Route::post('knowledge-documents/{knowledgeDocument}/archive', [KnowledgeDocumentController::class, 'archive']);
        Route::post('knowledge-documents/{knowledgeDocument}/files', [DocumentIngestionController::class, 'uploadFile']);
        Route::get('knowledge-documents/{knowledgeDocument}/chunks', [DocumentChunkController::class, 'index']);
        Route::post('knowledge-documents/{knowledgeDocument}/embed', [DocumentIngestionController::class, 'embed']);
        Route::post('knowledge-documents/import-url', [DocumentIngestionController::class, 'importUrl']);
        Route::get('document-ingestion-jobs', [DocumentIngestionController::class, 'index']);
        Route::post('document-ingestion-jobs/{documentIngestionJob}/process', [DocumentIngestionController::class, 'process']);
        Route::post('rag/search', [RagController::class, 'search']);
    });
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
