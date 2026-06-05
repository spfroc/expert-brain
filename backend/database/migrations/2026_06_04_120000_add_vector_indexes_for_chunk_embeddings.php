<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS document_chunk_embeddings_model_key_btree_idx ON document_chunk_embeddings (model_key)');
        DB::statement('CREATE INDEX IF NOT EXISTS document_chunk_embeddings_chunk_model_btree_idx ON document_chunk_embeddings (document_chunk_id, model_key)');
        DB::statement('CREATE INDEX IF NOT EXISTS document_chunk_embeddings_ai_model_btree_idx ON document_chunk_embeddings (ai_model_id)');

        // Do not create HNSW indexes here.
        // document_chunk_embeddings.embedding is intentionally dimensionless vector
        // so one table can store embeddings from multiple models with different dimensions.
        // pgvector HNSW indexes require a fixed vector dimension, such as vector(512) or vector(1024).
        // Model-specific vector indexes should be introduced later with fixed-dimension strategy.
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS document_chunk_embeddings_ai_model_btree_idx');
        DB::statement('DROP INDEX IF EXISTS document_chunk_embeddings_chunk_model_btree_idx');
        DB::statement('DROP INDEX IF EXISTS document_chunk_embeddings_model_key_btree_idx');
    }
};
