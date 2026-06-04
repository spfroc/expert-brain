<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_import_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->foreignId('knowledge_base_id')->constrained('knowledge_bases')->cascadeOnDelete();
            $table->string('source_path')->nullable();
            $table->string('pattern')->default('*.pdf');
            $table->string('status')->default('pending')->index();
            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedInteger('pending_items')->default(0);
            $table->unsignedInteger('processing_items')->default(0);
            $table->unsignedInteger('completed_items')->default(0);
            $table->unsignedInteger('failed_items')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('knowledge_import_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('knowledge_import_batch_id')->constrained('knowledge_import_batches')->cascadeOnDelete();
            $table->foreignId('knowledge_document_id')->nullable()->constrained('knowledge_documents')->nullOnDelete();
            $table->foreignId('document_file_id')->nullable()->constrained('document_files')->nullOnDelete();
            $table->string('source_path');
            $table->string('filename');
            $table->string('title');
            $table->string('sha256', 64)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('status')->default('pending')->index();
            $table->unsignedInteger('chunk_count')->default(0);
            $table->unsignedInteger('embedded_count')->default(0);
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['knowledge_import_batch_id', 'source_path'], 'import_items_batch_source_unique');
            $table->index(['knowledge_import_batch_id', 'status']);
            $table->index(['title']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_import_items');
        Schema::dropIfExists('knowledge_import_batches');
    }
};
