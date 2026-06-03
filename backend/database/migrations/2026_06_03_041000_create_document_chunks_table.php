<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_chunks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('knowledge_document_id')->constrained('knowledge_documents')->cascadeOnDelete();
            $table->foreignId('document_file_id')->nullable()->constrained('document_files')->nullOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->string('chunk_type', 50)->default('text');
            $table->text('title')->nullable();
            $table->longText('content');
            $table->unsignedInteger('token_count')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['knowledge_document_id', 'chunk_index']);
            $table->index(['knowledge_document_id', 'chunk_type']);
        });

        DB::statement('ALTER TABLE document_chunks ADD COLUMN IF NOT EXISTS embedding vector(1024)');
    }

    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
    }
};
