# GitHub Copilot Instructions

You are working on ExpertBrain, an enterprise industry expert system.

## Core Context

ExpertBrain V1 targets Shandong government procurement consulting scenarios.

The system manages:

- knowledge bases
- policy/platform documents
- business experience
- customer profiles
- business rules
- RAG QA
- generated service plans

## Tech Stack

- Backend: Laravel 11+ API
- Frontend: Vue 3 + Vite + TypeScript + Element Plus + Tailwind
- AI Service: FastAPI + Python 3.11+
- Database: PostgreSQL + pgvector
- Queue: Redis

## Important Principles

- Do not implement third-party platform automation in V1.
- Deterministic business logic belongs in rules, not LLM free-form output.
- AI answers must include citations where possible.
- If sources are insufficient, return uncertainty.
- Keep LLM provider and embedding provider replaceable.

## Start Here

Read these files first:

1. `AGENTS.md`
2. `docs/02-PRD.md`
3. `docs/03-System-Architecture.md`
4. `docs/05-Database-Design.md`
5. `docs/12-Agent-Tasks.md`
