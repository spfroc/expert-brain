# AGENTS.md

本文件是 ExpertBrain 项目的 Agent 总规则。Codex、Cursor Agent、Claude Code、Copilot 或其他代码 Agent 在本仓库工作时必须优先阅读本文件。

## 1. 项目目标

ExpertBrain 是企业行业专家系统，目标是帮助企业快速构建面向特定业务领域的专家知识库。

V1 首期场景是山东省政府采购业务，包括：

- 政府采购法规、政策、通知、流程问答
- 京东慧采、青慧采、泉E采等平台知识管理
- 供应商经营范围、主营产品、目标平台、资质情况分析
- 平台推荐、服务流程推荐、发品建议、风险提示、方案生成

## 2. 技术栈约束

除非用户明确要求，否则不要随意替换技术栈。

- Backend: Laravel 11+ API
- Frontend: Vue 3 + Vite + Element Plus + Tailwind CSS
- Database: PostgreSQL 16+ + pgvector
- AI Service: Python 3.11+ + FastAPI
- Queue: Redis + Laravel Queue
- Search: PostgreSQL full-text search + pgvector hybrid retrieval
- Embedding: BGE-M3
- LLM: API 优先，本地模型作为可插拔实现
- Deployment: Docker Compose + Traefik

## 3. 架构原则

1. Laravel 负责业务系统、权限、客户、知识库、规则、方案等核心业务。
2. FastAPI 负责文档解析、chunk、embedding、RAG 检索、Prompt 编排、LLM 调用。
3. PostgreSQL 是主数据库，pgvector 直接承载向量，不引入额外向量库作为 V1 依赖。
4. 确定性业务逻辑必须优先使用规则引擎，不允许完全交给 LLM 判断。
5. LLM 输出必须尽量带引用。没有依据时必须返回“不确定”或“知识库中暂无依据”。

## 4. V1 禁止事项

V1 不做以下事情：

- 不自动登录或操作京东慧采、青慧采、泉E采等第三方平台。
- 不自动提交资质、发布商品、修改客户平台数据。
- 不做模型微调。
- 不做复杂多 Agent 自动工作流。
- 不把法规结论写死在 Prompt 中。
- 不让 LLM 在没有检索依据的情况下输出肯定结论。

## 5. 开发顺序

严格按以下顺序推进：

1. 项目脚手架：Laravel、Vue、FastAPI、Docker Compose。
2. 认证与 RBAC。
3. 知识库基础表、分类、标签、文档管理。
4. 文档解析与入库。
5. chunk 与 embedding。
6. hybrid search。
7. RAG 问答与引用返回。
8. 客户画像与平台规则。
9. 服务方案生成。
10. 案例库、统计、知识生命周期。

## 6. 提交规范

提交信息使用 Conventional Commits：

- feat: 新功能
- fix: 修复
- docs: 文档
- refactor: 重构
- test: 测试
- chore: 工程配置

## 7. 每次开发前必须检查

- 是否阅读了 docs/02-PRD.md？
- 是否阅读了 docs/05-Database-Design.md？
- 是否阅读了 docs/06-API-Specification.md？
- 是否阅读了对应 .cursor/rules 文件？
- 是否破坏了“确定性规则优先于 LLM”的原则？

## 8. 质量要求

- 后端接口必须有请求校验。
- 重要写操作必须记录审计日志。
- RAG 结果必须包含引用来源字段。
- 数据库 migration 必须可重复执行和回滚。
- API 响应结构必须一致。
- 前端页面必须有空状态、加载状态、错误状态。
