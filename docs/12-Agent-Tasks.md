# Agent 开发任务拆分

本文件用于给 Codex、Cursor Agent、Claude Code 等开发 Agent 分配任务。

## 总规则

Agent 开发前必须阅读：

1. `AGENTS.md`
2. `docs/02-PRD.md`
3. `docs/03-System-Architecture.md`
4. `docs/05-Database-Design.md`
5. 当前任务涉及的 `.cursor/rules/*.mdc`

## Epic 0：项目脚手架

### Task 0.1 初始化 Laravel Backend

目标：在 `backend/` 下创建 Laravel API 项目。

要求：

- Laravel 11+
- PostgreSQL
- Sanctum
- spatie/laravel-permission
- 基础健康检查接口 `/api/health`

验收：

- `php artisan test` 通过
- `/api/health` 返回 ok

### Task 0.2 初始化 Vue Frontend

目标：在 `frontend/` 下创建 Vue3 项目。

要求：

- Vite
- TypeScript
- Element Plus
- Tailwind CSS
- Pinia
- Vue Router

验收：

- `npm run build` 通过
- 登录页和后台布局可访问

### Task 0.3 初始化 FastAPI AI Service

目标：在 `ai-service/` 下创建 FastAPI 项目。

要求：

- Python 3.11+
- FastAPI
- pydantic
- health endpoint `/health`

验收：

- `pytest` 通过
- `/health` 返回 ok

### Task 0.4 Docker Compose

目标：本地一键启动。

服务：

- backend
- frontend
- ai-service
- postgres
- redis

验收：

- `docker compose up -d` 成功

## Epic 1：认证与 RBAC

### Task 1.1 用户登录

实现登录、退出、当前用户接口。

### Task 1.2 角色权限

实现默认角色和权限 Seeder。

### Task 1.3 前端权限菜单

根据 permissions 控制菜单显示。

## Epic 2：知识管理

### Task 2.1 knowledge_bases CRUD

### Task 2.2 knowledge_categories 树形 CRUD

### Task 2.3 knowledge_tags CRUD

### Task 2.4 knowledge_documents CRUD

### Task 2.5 文件上传

### Task 2.6 文档发布、失效、归档

## Epic 3：文档解析与向量化

### Task 3.1 FastAPI 文档解析接口

### Task 3.2 Markdown 清洗

### Task 3.3 Chunk 切分

### Task 3.4 BGE-M3 embedding

### Task 3.5 写入 pgvector

### Task 3.6 文档 reindex

## Epic 4：RAG 问答

### Task 4.1 hybrid search

### Task 4.2 LLM Provider 抽象

### Task 4.3 RAG answer 接口

### Task 4.4 citations 保存

### Task 4.5 前端聊天页面

## Epic 5：客户与规则

### Task 5.1 customers CRUD

### Task 5.2 customer_tags 自动生成

### Task 5.3 business_rules CRUD

### Task 5.4 规则执行器

### Task 5.5 客户分析接口

## Epic 6：服务方案

### Task 6.1 generated_plans 表和模型

### Task 6.2 方案生成编排

### Task 6.3 方案引用保存

### Task 6.4 前端方案生成页面

### Task 6.5 方案编辑和保存

## Epic 7：案例和统计

### Task 7.1 case_studies CRUD

### Task 7.2 案例参与检索

### Task 7.3 dashboard 统计接口

### Task 7.4 知识引用统计

## Agent 输出要求

每个任务完成后输出：

- 修改文件列表
- 实现说明
- 本地测试命令
- 是否存在未完成 TODO
- 下一步建议
