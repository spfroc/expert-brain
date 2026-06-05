<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_chunk_embeddings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_chunk_id')->constrained('document_chunks')->cascadeOnDelete();
            $table->foreignId('ai_model_id')->nullable()->constrained('ai_models')->nullOnDelete();
            $table->string('model_key')->index();
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->unsignedInteger('dimension');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['document_chunk_id', 'model_key'], 'chunk_embeddings_chunk_model_unique');
        });

        DB::statement('ALTER TABLE document_chunk_embeddings ADD COLUMN embedding vector');
    }

    public function down(): void
    {
        Schema::dropIfExists('document_chunk_embeddings');
    }
};
