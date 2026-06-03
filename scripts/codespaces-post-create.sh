#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

echo "[ExpertBrain] Preparing Codespaces development environment..."

if [ -f backend/.env.example ] && [ ! -f backend/.env ]; then
  cp backend/.env.example backend/.env
fi

if [ -f ai-service/.env.example ] && [ ! -f ai-service/.env ]; then
  cp ai-service/.env.example ai-service/.env
fi

mkdir -p "$HOME/models"

cat <<'EOF'

[ExpertBrain] Codespaces setup complete.

Next commands:

  docker compose up -d --build
  docker compose exec backend php artisan migrate --seed

Open ports:

  Frontend:   15173
  Backend:    18080
  AI Service: 18000

Default account after seed:

  admin@example.com / password

EOF
