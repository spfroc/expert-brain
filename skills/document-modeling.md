# Skill: Document Modeling

## Purpose

指导 Agent 设计和实现知识文档的数据模型、状态流转和索引策略。

## Document Types

- manual: 手工录入经验
- policy: 法规政策
- platform_doc: 平台官方文档
- notice: 通知公告
- case: 案例资料
- faq: FAQ
- url: 网页链接
- file: 上传文件

## Status Flow

```text
draft -> pending -> published -> expired -> archived
```

V1 可以简化为：draft、published、expired、archived。

## Required Metadata

- source_type
- source_url
- file_path
- version
- effective_from
- effective_to
- platform
- region
- tags

## Chunk Metadata

Each chunk should keep enough context:

```json
{
  "document_title": "",
  "category_path": "",
  "title_path": "",
  "source_type": "",
  "platform": "",
  "region": "",
  "version": ""
}
```

## Important Rule

If a document is updated after being published, existing chunks should be marked stale or regenerated. Do not mix old and new chunks silently.
