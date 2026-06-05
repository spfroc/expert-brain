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

mkdir -p "$HOME/models" models
chmod +x scripts/codespaces-up.sh scripts/codespaces-smoke-test.sh || true

cat <<'EOF'

[ExpertBrain] Codespaces setup complete.

Start the full stack with:

  bash scripts/codespaces-up.sh

This will:

  1. Prepare backend/.env and ai-service/.env
  2. Start Docker Compose services
  3. Run migrations and seeders
  4. Install recommended model registry records
  5. Run smoke tests

Open ports:

  Frontend:   15173
  Backend:    18080
  AI Service: 18000

Default account after seed:

  admin@example.com / password

EOF
