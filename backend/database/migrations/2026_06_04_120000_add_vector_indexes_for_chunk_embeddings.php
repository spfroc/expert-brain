<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS document_chunk_embeddings_model_key_btree_idx ON document_chunk_embeddings (model_key)');
        DB::statement('CREATE INDEX IF NOT EXISTS document_chunk_embeddings_vector_hnsw_idx ON document_chunk_embeddings USING hnsw (embedding vector_cosine_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS document_chunks_embedding_hnsw_idx ON document_chunks USING hnsw (embedding vector_cosine_ops)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS document_chunk_embeddings_vector_hnsw_idx');
        DB::statement('DROP INDEX IF EXISTS document_chunks_embedding_hnsw_idx');
        DB::statement('DROP INDEX IF EXISTS document_chunk_embeddings_model_key_btree_idx');
    }
};
