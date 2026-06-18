<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_base_relations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_knowledge_base_id')->constrained('knowledge_bases')->cascadeOnDelete();
            $table->foreignId('related_knowledge_base_id')->constrained('knowledge_bases')->cascadeOnDelete();
            $table->string('relation_type')->default('related');
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['source_knowledge_base_id', 'related_knowledge_base_id'], 'kb_relations_unique_pair');
            $table->index(['source_knowledge_base_id', 'is_active']);
            $table->index(['related_knowledge_base_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_base_relations');
    }
};
