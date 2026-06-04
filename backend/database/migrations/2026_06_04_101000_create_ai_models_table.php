<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_models', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('model_key')->unique();
            $table->string('task_type')->index(); // embedding, reranker, llm, ocr
            $table->string('provider')->default('local'); // sentence-transformers, ollama, openai-compatible, modelscope, huggingface
            $table->string('model_id')->nullable();
            $table->string('local_path')->nullable();
            $table->unsignedInteger('dimension')->nullable();
            $table->string('device')->nullable();
            $table->string('status')->default('registered')->index(); // registered, downloading, ready, failed, disabled
            $table->boolean('is_active')->default(false)->index();
            $table->text('description')->nullable();
            $table->text('download_command')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['task_type', 'is_active']);
        });

        Schema::create('ai_model_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_model_id')->constrained('ai_models')->cascadeOnDelete();
            $table->string('event_type');
            $table->text('message')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['ai_model_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_model_events');
        Schema::dropIfExists('ai_models');
    }
};
