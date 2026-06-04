<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE document_chunks DROP CONSTRAINT IF EXISTS document_chunks_knowledge_document_id_chunk_index_unique');
        DB::statement('CREATE INDEX IF NOT EXISTS document_chunks_document_index_idx ON document_chunks (knowledge_document_id, document_file_id, chunk_index)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS document_chunks_document_index_idx');
        DB::statement('ALTER TABLE document_chunks ADD CONSTRAINT document_chunks_knowledge_document_id_chunk_index_unique UNIQUE (knowledge_document_id, chunk_index)');
    }
};
