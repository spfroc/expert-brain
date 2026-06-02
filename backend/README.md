# Backend

Laravel API service.

## Recommended initialization

Run this from the repository root after cloning:

```bash
rm -rf backend/*
composer create-project laravel/laravel backend
cd backend
composer require laravel/sanctum spatie/laravel-permission predis/predis
php artisan install:api
```

Then configure `.env` for PostgreSQL and Redis.

## Required baseline

- Laravel 11+
- PHP 8.2+
- PostgreSQL
- Redis Queue
- Laravel Sanctum
- spatie/laravel-permission

## First endpoint

Implement:

```text
GET /api/health
```

Expected response:

```json
{"status":"ok"}
```

## Agent instruction

Before implementing backend code, read:

- `AGENTS.md`
- `docs/06-API-Specification.md`
- `docs/07-RBAC.md`
- `.cursor/rules/backend-laravel.mdc`

Start from GitHub Issue #2.
