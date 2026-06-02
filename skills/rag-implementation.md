# Skill: RAG Implementation

## Purpose

用于指导 Agent 实现 ExpertBrain 的 RAG 功能。

## Key Rules

1. 只检索 published 文档。
2. expired 文档默认不参与检索。
3. 每个回答必须返回 citations。
4. 没有足够依据时不得强答。
5. embedding 生成前先检查 content_hash。

## Implementation Steps

1. Parse document into Markdown.
2. Split Markdown into chunks.
3. Generate content_hash for each chunk.
4. Generate embedding with BGE-M3.
5. Store chunks in PostgreSQL with pgvector.
6. For a user question, generate query embedding.
7. Run vector search and full-text search.
8. Merge and rank results.
9. Build prompt with numbered contexts.
10. Call LLM.
11. Return answer and citations.

## Retrieval Output Contract

```json
{
  "items": [
    {
      "document_id": 1,
      "chunk_id": 10,
      "title": "",
      "content": "",
      "score": 0.82,
      "source_url": ""
    }
  ]
}
```
