# GitHub Codespaces Startup Guide

This repository includes a Dev Container configuration for GitHub Codespaces.

## 1. Create Codespace

Open the repository on GitHub:

```text
spfroc/expert-brain
```

Then:

```text
Code -> Codespaces -> Create codespace on main
```

The first startup may take several minutes because the environment installs Docker, Node.js, PHP/Composer, and Python.

## 2. Start services

After the Codespace terminal opens:

```bash
docker compose up -d --build
```

Run database migrations and seed default RBAC data:

```bash
docker compose exec backend php artisan migrate --seed
```

## 3. Open services

Codespaces will forward these ports:

| Port | Service |
|---:|---|
| 15173 | Frontend |
| 18080 | Laravel Backend API |
| 18000 | AI Service |
| 15432 | PostgreSQL pgvector |
| 16379 | Redis |

Open the forwarded `15173` port to access the frontend.

Default account:

```text
admin@example.com
password
```

## 4. Health checks

Backend:

```bash
curl http://localhost:18080/api/health
```

AI Service:

```bash
curl http://localhost:18000/health
```

Embedding fallback:

```bash
curl -X POST "http://localhost:18000/embeddings/embed" \
  -H "Content-Type: application/json" \
  -d '{"texts":["山东政府采购流程","京东慧采入驻规则"],"normalize":true}'
```

## 5. Local model mount

Codespaces does not persist large model files well. For heavy models, use a local machine or a persistent server.

For local development, mount models through Docker Compose later:

```yaml
volumes:
  - ~/models:/models:ro
```

Recommended embedding model:

```text
BAAI/bge-m3
```

Recommended reranker later:

```text
BAAI/bge-reranker-v2-m3
```

## 6. Notes

The current embedding service has a deterministic mock provider so the pipeline can be tested before real model files are downloaded.

Production-quality retrieval requires switching the AI Service from mock embeddings to `BAAI/bge-m3` or another real embedding model.
