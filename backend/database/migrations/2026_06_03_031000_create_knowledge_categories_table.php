<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('knowledge_base_id')->constrained('knowledge_bases')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('knowledge_categories')->nullOnDelete();
            $table->string('name', 100);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['knowledge_base_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_categories');
    }
};
