<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE document_chunks ALTER COLUMN embedding TYPE vector');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE document_chunks ALTER COLUMN embedding TYPE vector(1024)');
    }
};
