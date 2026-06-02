# Phase 0 Checklist

Phase 0 的目标是让项目具备可运行的本地开发环境。

## 已完成

- [x] 项目文档骨架
- [x] Agent rules
- [x] Issue 拆分
- [x] Docker Compose 基础服务：PostgreSQL、Redis、AI Service、Frontend
- [x] PostgreSQL extensions: vector, pg_trgm
- [x] FastAPI AI Service `/health`
- [x] Vue3 Frontend 基础页面
- [x] Makefile

## 待完成

- [ ] 初始化 Laravel Backend 项目
- [ ] 实现 Backend `/api/health`
- [ ] 配置 Backend PostgreSQL / Redis
- [ ] 将 Backend service 合并进 docker-compose.yml
- [ ] 完成 `docker compose up -d` 全服务启动

## Recommended commands

```bash
bash scripts/init-backend.sh
cp backend/Dockerfile.example backend/Dockerfile
# Merge deployment/docker-compose.backend.example.yml into docker-compose.yml
make up
curl http://localhost:8000/health
curl http://localhost:8080/api/health
```

## Backend .env key values

```env
APP_URL=http://localhost:8080
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=expert_brain
DB_USERNAME=expert_brain
DB_PASSWORD=expert_brain
REDIS_CLIENT=predis
REDIS_HOST=redis
REDIS_PORT=6379
QUEUE_CONNECTION=redis
```

## Backend health route suggestion

`routes/api.php`:

```php
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});
```

If API routes are mounted under `/api`, the endpoint is `/api/health`. If the project later adopts `/api/v1`, keep `/api/health` as an infrastructure endpoint and place business APIs under `/api/v1`.
