# Deployment

V1 先支持本地 Docker Compose 开发环境。

## Services

- postgres: PostgreSQL 16 + pgvector
- redis: Redis 7
- ai-service: FastAPI AI/RAG service
- frontend: Vue 3 development server
- backend: Laravel API service，待 Issue #2 初始化完成后接入 Compose

## Start

From repository root:

```bash
cp .env.example .env
make up
```

Or:

```bash
docker compose up -d
```

## Check

```bash
docker compose ps
curl http://localhost:8000/health
open http://localhost:5173
```

## Logs

```bash
make logs
```

## Stop

```bash
make down
```

## PostgreSQL Extensions

The init script enables:

```sql
CREATE EXTENSION IF NOT EXISTS vector;
CREATE EXTENSION IF NOT EXISTS pg_trgm;
```

## Backend note

The backend service is not wired into Docker Compose yet because Laravel should be initialized by Composer first.

After Issue #2 is completed, add a backend service to `docker-compose.yml` and expose it on port 8080.
