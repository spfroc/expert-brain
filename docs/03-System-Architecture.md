# 系统架构设计

## 1. 总体架构

ExpertBrain V1 采用前后端分离 + 独立 AI 服务架构。

```text
Vue3 Frontend
    ↓ REST API
Laravel Backend
    ↓ DB / Queue
PostgreSQL + pgvector + Redis
    ↑
FastAPI AI Service
    ↓
Embedding Model / LLM Provider
```

## 2. 模块职责

### 2.1 Frontend

- 登录与权限菜单
- 知识库管理界面
- 文档编辑和上传界面
- AI 问答界面
- 客户中心
- 规则中心
- 方案生成界面
- 案例中心

### 2.2 Laravel Backend

- 用户认证
- RBAC 权限
- 知识库业务模型
- 客户和规则管理
- 方案保存
- 审计日志
- 调用 AI Service
- 文件上传管理

### 2.3 FastAPI AI Service

- 文档解析
- 文档切分
- embedding 生成
- hybrid retrieval
- prompt 构造
- LLM 调用
- 引用整理

### 2.4 PostgreSQL

- 业务主数据
- 知识文档
- chunk 文本
- pgvector embedding
- 规则和方案
- 审计日志

### 2.5 Redis

- Laravel Queue
- 文档处理任务
- embedding 任务
- 方案生成异步任务

## 3. 服务边界

Laravel 不直接做 embedding，不直接调用本地模型。AI Service 不管理业务权限，不直接暴露给前端。

前端只调用 Laravel API，Laravel 作为业务网关调用 AI Service。

## 4. 数据流

### 4.1 文档入库

```text
上传文档 → Laravel 保存文件和 document 记录 → 创建解析任务 → AI Service 解析为 Markdown → chunk → embedding → 写入 knowledge_chunks
```

### 4.2 RAG 问答

```text
用户提问 → Laravel 鉴权 → AI Service 检索 chunks → 构造 Prompt → LLM 回答 → 返回 answer + citations → Laravel 保存 conversation
```

### 4.3 方案生成

```text
客户信息 → 规则命中 → 检索相关知识/案例 → LLM 生成结构化方案 → Laravel 保存 generated_plan
```

## 5. 部署架构

V1 推荐 Docker Compose：

- nginx/traefik
- backend-app
- backend-queue
- frontend
- ai-service
- postgres
- redis

## 6. 可插拔设计

### LLM Provider

统一接口：

- openai-compatible
- deepseek
- qwen
- local-ollama

### Embedding Provider

统一接口：

- local bge-m3
- api embedding

## 7. 安全设计

- 所有业务 API 必须鉴权。
- AI Service 只允许内网访问。
- 文件上传限制类型和大小。
- 重要操作写 audit_logs。
- Prompt 不允许泄露系统密钥。
