# ExpertBrain

企业行业专家系统（Enterprise Industry Expert System）。

ExpertBrain 的目标不是做一个普通知识库，也不是简单的 RAG 问答机器人，而是帮助企业将行业知识、业务经验、平台规则、客户案例和服务流程沉淀为可复用、可检索、可生成方案的行业专家系统。

首期落地场景：山东省政府采购业务咨询、平台入驻、商品发布、服务方案生成。

## 核心定位

- 企业知识资产管理平台
- 行业专家知识库平台
- RAG 问答系统
- 客户画像分析工具
- 服务方案生成系统

## 首期业务范围

- 山东省政府采购相关法规、流程、通知、平台规则
- 京东慧采、青慧采、泉E采等采购平台业务经验
- 供应商经营范围、主营产品、目标平台、资质情况分析
- 入驻、发品、项目跟进、风险提示、成功案例推荐

## 技术栈约束

- Backend: Laravel API
- Frontend: Vue 3 + Element Plus + Tailwind CSS
- Database: PostgreSQL + pgvector
- AI Service: Python FastAPI
- Embedding: BGE-M3
- LLM: 优先使用 API 模型，后续支持本地 Qwen 8B/14B

## 推荐目录

```text
expert-brain/
├── backend/              # Laravel API 服务
├── frontend/             # Vue3 前端
├── ai-service/           # FastAPI AI/RAG 服务
├── deployment/           # Docker、Traefik、部署脚本
├── docs/                 # 产品、架构、数据库、API、RAG 文档
├── prompts/              # RAG、客户分析、方案生成 Prompt
├── skills/               # 给 Agent 使用的项目技能说明
├── .cursor/rules/        # Cursor Agent 规则
├── .github/              # GitHub Copilot / Issue 模板 / Workflow
└── AGENTS.md             # Agent 总规则
```

## 开发原则

1. V1 只做知识管理、RAG 问答、客户分析、服务方案生成，不做自动操作第三方平台。
2. 确定性业务判断优先使用规则引擎，不交给大模型自由发挥。
3. 所有法规、平台规则、业务结论必须尽量给出引用来源。
4. 找不到依据时必须明确说明“不确定”或“知识库中暂无依据”。
5. 项目文档优先于代码，Agent 开发必须先阅读 `AGENTS.md` 和 `docs/12-Agent-Tasks.md`。

## 文档入口

- [MRD](docs/01-MRD.md)
- [PRD](docs/02-PRD.md)
- [系统架构](docs/03-System-Architecture.md)
- [领域模型](docs/04-Domain-Model.md)
- [数据库设计](docs/05-Database-Design.md)
- [API 规范](docs/06-API-Specification.md)
- [权限设计](docs/07-RBAC.md)
- [RAG 架构](docs/08-RAG-Architecture.md)
- [Prompt 设计](docs/09-AI-Prompt-Design.md)
- [前端设计](docs/10-Frontend-Design.md)
- [开发路线图](docs/11-Development-Roadmap.md)
- [Agent 任务拆分](docs/12-Agent-Tasks.md)
