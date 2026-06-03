<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('knowledge_base_id')->constrained('knowledge_bases')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('knowledge_categories')->nullOnDelete();
            $table->string('title', 255);
            $table->text('summary')->nullable();
            $table->longText('content')->nullable();
            $table->string('source_type', 50)->default('manual');
            $table->text('source_url')->nullable();
            $table->text('file_path')->nullable();
            $table->string('file_mime', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('version', 50)->default('1.0');
            $table->string('status', 30)->default('draft');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['knowledge_base_id', 'status']);
            $table->index('category_id');
            $table->index('source_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_documents');
    }
};
