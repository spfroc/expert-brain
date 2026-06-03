<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_ingestion_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('knowledge_document_id')->constrained('knowledge_documents')->cascadeOnDelete();
            $table->foreignId('document_file_id')->nullable()->constrained('document_files')->nullOnDelete();
            $table->string('job_type', 50);
            $table->string('status', 30)->default('pending');
            $table->unsignedInteger('progress')->default(0);
            $table->text('source_url')->nullable();
            $table->longText('error_message')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['knowledge_document_id', 'status']);
            $table->index(['job_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_ingestion_jobs');
    }
};
