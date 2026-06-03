# AI Model Plan

This document defines the model choices for the ExpertBrain RAG roadmap.

## Current priority

The next engineering step is not a chat LLM. The next required model is an embedding model.

RAG retrieval depends on embeddings:

1. Parse document into text.
2. Split text into chunks.
3. Convert chunks into vectors.
4. Store vectors in PostgreSQL pgvector.
5. Convert user query into vector.
6. Retrieve similar chunks.
7. Optionally rerank chunks.
8. Send context to LLM for answer generation.

## Recommended MVP model

### Embedding

Use:

```text
BAAI/bge-m3
```

Reasons:

- Strong Chinese and multilingual retrieval support.
- Suitable for government procurement documents that may contain Chinese policies, platform rules, product/service terms, and mixed English technical words.
- Supports long text compared with many older sentence embedding models.
- Embedding dimension: 1024.

Database design currently uses:

```sql
vector(1024)
```

This matches `BAAI/bge-m3`.

## Optional later model

### Reranker

Use later:

```text
BAAI/bge-reranker-v2-m3
```

Reranker is not required for the first vector search MVP. It is useful after the system can already search chunks. It improves result ordering by scoring query-chunk pairs more accurately.

## LLM for answer generation

Do not add a local LLM yet.

The correct order is:

```text
embedding -> vector search -> retrieval evaluation -> reranker -> LLM answer generation
```

When the retrieval pipeline is stable, choose the LLM according to deployment hardware:

- Cloud/API mode: use external LLM API.
- Local GPU mode: use Qwen 7B/8B class model first.
- CPU/NAS mode: do not run a large LLM locally; keep only embedding/search services.

## Suggested local directory

On the host machine, keep Hugging Face / ModelScope models outside the repository:

```text
/mnt/data/models/bge-m3
/mnt/data/models/bge-reranker-v2-m3
```

For the current Docker Compose development environment, a practical host path is:

```text
~/models/bge-m3
~/models/bge-reranker-v2-m3
```

Later mount it into `ai-service`:

```yaml
volumes:
  - ~/models:/models:ro
```

## Environment variables planned

```text
EMBEDDING_MODEL_PATH=/models/bge-m3
EMBEDDING_DIMENSION=1024
RERANKER_MODEL_PATH=/models/bge-reranker-v2-m3
```
