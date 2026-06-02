#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND_DIR="${ROOT_DIR}/backend"

if ! command -v composer >/dev/null 2>&1; then
  echo "composer is required but not installed."
  exit 1
fi

if [ -f "${BACKEND_DIR}/artisan" ]; then
  echo "Laravel project already exists in backend/."
  exit 0
fi

if [ -n "$(find "${BACKEND_DIR}" -mindepth 1 -maxdepth 1 ! -name README.md 2>/dev/null)" ]; then
  echo "backend/ is not empty. Please inspect it before initialization."
  exit 1
fi

TMP_DIR="${ROOT_DIR}/.tmp-laravel-backend"
rm -rf "${TMP_DIR}"

composer create-project laravel/laravel "${TMP_DIR}"
rsync -a --exclude='.git' "${TMP_DIR}/" "${BACKEND_DIR}/"
rm -rf "${TMP_DIR}"

cd "${BACKEND_DIR}"
composer require laravel/sanctum spatie/laravel-permission predis/predis
php artisan install:api

cp .env.example .env
php artisan key:generate

echo "Laravel backend initialized. Next: configure backend/.env for PostgreSQL and Redis."
