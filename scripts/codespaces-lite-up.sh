#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

COMPOSE="docker compose -f docker-compose.codespaces.yml"

printf '\n[ExpertBrain Lite] Preparing env files...\n'

if [ ! -f backend/.env ]; then
  cp backend/.env.example backend/.env
fi

if [ ! -f ai-service/.env ]; then
  cp ai-service/.env.example ai-service/.env
fi

cat >> backend/.env <<'EOF'

APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:18080
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=expert_brain
DB_USERNAME=expert_brain
DB_PASSWORD=expert_brain
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PORT=6379
AI_SERVICE_URL=http://ai-service:8000
AI_SERVICE_EMBEDDING_TIMEOUT=180
AI_SERVICE_PARSE_TIMEOUT=240
NO_PROXY=localhost,127.0.0.1,postgres,redis,backend,ai-service
no_proxy=localhost,127.0.0.1,postgres,redis,backend,ai-service
EOF

cat >> ai-service/.env <<'EOF'

POSTGRES_HOST=postgres
POSTGRES_PORT=5432
POSTGRES_DB=expert_brain
POSTGRES_USER=expert_brain
POSTGRES_PASSWORD=expert_brain
REDIS_URL=redis://redis:6379/0
EMBEDDING_PROVIDER=mock
EMBEDDING_MODEL=mock-embedding-1024
EMBEDDING_MODEL_PATH=
EMBEDDING_DIMENSION=1024
EMBEDDING_DEVICE=cpu
EMBEDDING_PRELOAD=false
NO_PROXY=localhost,127.0.0.1,postgres,redis,backend,ai-service
no_proxy=localhost,127.0.0.1,postgres,redis,backend,ai-service
EOF

printf '\n[ExpertBrain Lite] Starting backend stack only. No frontend container.\n'
$COMPOSE up -d --build postgres redis backend ai-service

printf '\n[ExpertBrain Lite] Waiting for services...\n'
for i in $(seq 1 60); do
  if curl -fsS http://localhost:18080/api/health >/dev/null 2>&1 && curl -fsS http://localhost:18000/health >/dev/null 2>&1; then
    break
  fi
  echo "waiting... $i"
  sleep 2
done

printf '\n[ExpertBrain Lite] Init Laravel...\n'
$COMPOSE exec -T backend php artisan key:generate --force || true
$COMPOSE exec -T backend php artisan migrate --force
$COMPOSE exec -T backend php artisan db:seed --force || true
$COMPOSE exec -T backend php artisan ai-model install-recommended || true

printf '\n[ExpertBrain Lite] Smoke test...\n'
BACKEND_URL=http://localhost:18080 AI_URL=http://localhost:18000 FRONTEND_URL=http://localhost:18080 bash scripts/codespaces-smoke-test.sh || true

cat <<'EOF'

[ExpertBrain Lite] Ready.

This lite mode does not start the frontend container.
Use backend API at forwarded port 18080.
Use AI service at forwarded port 18000.

Useful commands:
  docker compose -f docker-compose.codespaces.yml ps
  docker compose -f docker-compose.codespaces.yml logs backend --tail=100
  docker compose -f docker-compose.codespaces.yml exec backend php artisan embedding:health --warmup --timeout=180
EOF
