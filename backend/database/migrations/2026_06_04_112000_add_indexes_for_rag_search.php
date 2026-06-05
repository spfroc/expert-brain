<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS document_chunk_embeddings_model_key_id_idx ON document_chunk_embeddings (model_key, id)');
        DB::statement('CREATE INDEX IF NOT EXISTS document_chunk_embeddings_chunk_id_idx ON document_chunk_embeddings (document_chunk_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS document_chunks_document_id_id_idx ON document_chunks (knowledge_document_id, id)');
        DB::statement('CREATE INDEX IF NOT EXISTS knowledge_documents_base_id_id_idx ON knowledge_documents (knowledge_base_id, id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS document_chunk_embeddings_model_key_id_idx');
        DB::statement('DROP INDEX IF EXISTS document_chunk_embeddings_chunk_id_idx');
        DB::statement('DROP INDEX IF EXISTS document_chunks_document_id_id_idx');
        DB::statement('DROP INDEX IF EXISTS knowledge_documents_base_id_id_idx');
    }
};
