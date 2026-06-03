<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_document_tags', function (Blueprint $table): void {
            $table->foreignId('document_id')->constrained('knowledge_documents')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('knowledge_tags')->cascadeOnDelete();
            $table->primary(['document_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_document_tags');
    }
};
