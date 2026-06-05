#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

printf '\n[ExpertBrain] Preparing .env files...\n'

if [ ! -f backend/.env ]; then
  cp backend/.env.example backend/.env
fi

if [ ! -f ai-service/.env ]; then
  cp ai-service/.env.example ai-service/.env
fi

python3 - <<'PY'
from pathlib import Path

def upsert(path: str, values: dict[str, str]) -> None:
    p = Path(path)
    lines = p.read_text(encoding='utf-8').splitlines() if p.exists() else []
    seen = set()
    out = []
    for line in lines:
        if not line or line.lstrip().startswith('#') or '=' not in line:
            out.append(line)
            continue
        key = line.split('=', 1)[0]
        if key in values:
            out.append(f'{key}={values[key]}')
            seen.add(key)
        else:
            out.append(line)
    for key, value in values.items():
        if key not in seen:
            out.append(f'{key}={value}')
    p.write_text('\n'.join(out) + '\n', encoding='utf-8')

upsert('backend/.env', {
    'APP_NAME': 'ExpertBrain',
    'APP_ENV': 'local',
    'APP_DEBUG': 'true',
    'APP_URL': 'http://localhost:18080',
    'DB_CONNECTION': 'pgsql',
    'DB_HOST': 'postgres',
    'DB_PORT': '5432',
    'DB_DATABASE': 'expert_brain',
    'DB_USERNAME': 'expert_brain',
    'DB_PASSWORD': 'expert_brain',
    'SESSION_DRIVER': 'database',
    'CACHE_STORE': 'database',
    'QUEUE_CONNECTION': 'database',
    'REDIS_CLIENT': 'phpredis',
    'REDIS_HOST': 'redis',
    'REDIS_PORT': '6379',
    'AI_SERVICE_URL': 'http://ai-service:8000',
    'AI_SERVICE_EMBEDDING_TIMEOUT': '180',
    'AI_SERVICE_PARSE_TIMEOUT': '240',
})

upsert('ai-service/.env', {
    'APP_ENV': 'local',
    'POSTGRES_HOST': 'postgres',
    'POSTGRES_PORT': '5432',
    'POSTGRES_DB': 'expert_brain',
    'POSTGRES_USER': 'expert_brain',
    'POSTGRES_PASSWORD': 'expert_brain',
    'REDIS_URL': 'redis://redis:6379/0',
    'EMBEDDING_PROVIDER': 'mock',
    'EMBEDDING_MODEL': 'mock-embedding-1024',
    'EMBEDDING_MODEL_PATH': '',
    'EMBEDDING_DIMENSION': '1024',
    'EMBEDDING_DEVICE': 'cpu',
})
PY

printf '\n[ExpertBrain] Starting containers...\n'
docker compose up -d --build

printf '\n[ExpertBrain] Generating app key if needed...\n'
docker compose exec -T backend php artisan key:generate --force || true

printf '\n[ExpertBrain] Running migrations and seeders...\n'
docker compose exec -T backend php artisan migrate --force
docker compose exec -T backend php artisan db:seed --force || true

printf '\n[ExpertBrain] Installing recommended model registry records...\n'
docker compose exec -T backend php artisan ai-model install-recommended || true

printf '\n[ExpertBrain] Running smoke test...\n'
bash scripts/codespaces-smoke-test.sh

cat <<'EOF'

[ExpertBrain] Codespaces stack is ready.

Open forwarded port 15173 for frontend.
Default login: admin@example.com / password

Useful commands:
  bash scripts/codespaces-smoke-test.sh
  docker compose logs backend --tail=120
  docker compose logs ai-service --tail=120
  docker compose exec backend php artisan embedding:health --warmup --timeout=180
EOF
