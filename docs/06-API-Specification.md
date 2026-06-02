# API 规范 V1

## 1. 通用约定

Base URL: `/api/v1`

认证：Bearer Token 或 Laravel Sanctum。

统一响应：

```json
{
  "success": true,
  "data": {},
  "message": "ok",
  "errors": null
}
```

分页响应：

```json
{
  "success": true,
  "data": {
    "items": [],
    "pagination": {
      "page": 1,
      "per_page": 20,
      "total": 100
    }
  }
}
```

## 2. Auth

### POST /auth/login

请求：

```json
{"email":"user@example.com","password":"secret"}
```

### POST /auth/logout

退出登录。

### GET /auth/me

返回当前用户、角色和权限。

## 3. Knowledge Bases

### GET /knowledge-bases

获取知识库列表。

### POST /knowledge-bases

创建知识库。

```json
{
  "name":"政府采购知识库",
  "description":"山东省政府采购业务知识",
  "industry":"government-procurement"
}
```

### GET /knowledge-bases/{id}

知识库详情。

### PUT /knowledge-bases/{id}

更新知识库。

## 4. Categories

### GET /knowledge-bases/{baseId}/categories

获取分类树。

### POST /knowledge-bases/{baseId}/categories

创建分类。

```json
{"parent_id":null,"name":"平台规则","sort_order":10}
```

## 5. Documents

### GET /documents

参数：

- knowledge_base_id
- category_id
- status
- keyword
- tag

### POST /documents

创建在线文档。

```json
{
  "knowledge_base_id":1,
  "category_id":2,
  "title":"京东慧采入驻经验",
  "content":"...",
  "source_type":"manual",
  "status":"draft"
}
```

### POST /documents/upload

上传文档文件。

multipart/form-data:

- file
- knowledge_base_id
- category_id
- title
- source_type

### GET /documents/{id}

文档详情。

### PUT /documents/{id}

更新文档。

### POST /documents/{id}/publish

发布文档。

### POST /documents/{id}/expire

标记失效。

### POST /documents/{id}/reindex

重新解析、切分、向量化。

## 6. RAG 问答

### POST /ai/rag/ask

请求：

```json
{
  "knowledge_base_id":1,
  "question":"青慧采如何入驻？",
  "filters": {
    "platforms":["青慧采"],
    "tags":["入驻"]
  },
  "top_k":8
}
```

响应：

```json
{
  "answer":"...",
  "citations":[
    {
      "document_id":1,
      "chunk_id":10,
      "title":"青慧采操作手册",
      "quote":"...",
      "score":0.82
    }
  ],
  "confidence":"medium"
}
```

## 7. Customers

### GET /customers

客户列表。

### POST /customers

创建客户。

```json
{
  "company_name":"山东XX商贸有限公司",
  "province":"山东省",
  "city":"济南市",
  "business_scope":"办公用品、劳保用品、计算机耗材",
  "main_products":"A4纸、硒鼓、鼠标键盘",
  "target_platforms":["京东慧采"]
}
```

### GET /customers/{id}

客户详情。

### PUT /customers/{id}

更新客户。

### POST /customers/{id}/analyze

客户画像分析。

响应：

```json
{
  "tags":["办公用品","劳保用品"],
  "matched_rules":[],
  "recommended_platforms":[],
  "risks":[]
}
```

## 8. Business Rules

### GET /business-rules

规则列表。

### POST /business-rules

创建规则。

```json
{
  "name":"办公用品推荐平台",
  "rule_type":"platform_recommendation",
  "condition_json": {
    "business_scope_contains_any":["办公用品","办公耗材"]
  },
  "result_json": {
    "recommended_platforms":["京东慧采","青慧采"],
    "suggested_services":["平台入驻","商品发布"]
  },
  "priority":10,
  "enabled":true
}
```

### POST /business-rules/test

测试规则。

## 9. Generated Plans

### POST /plans/generate

请求：

```json
{
  "customer_id":1,
  "goal":"办理政府采购平台入驻并发布办公用品",
  "target_platforms":["京东慧采"],
  "extra_requirements":"优先输出资料清单和办理流程"
}
```

响应：

```json
{
  "plan_id":1,
  "content":"...",
  "structured_json":{},
  "citations":[]
}
```

### GET /plans/{id}

方案详情。

### PUT /plans/{id}

人工修订方案。

## 10. Case Studies

### GET /case-studies

案例列表。

### POST /case-studies

创建案例。

### GET /case-studies/{id}

案例详情。

## 11. AI Service 内部接口

Laravel 调用，前端不直接访问。

### POST /internal/documents/parse

### POST /internal/documents/chunk

### POST /internal/embeddings/batch

### POST /internal/retrieval/search

### POST /internal/rag/answer

### POST /internal/plans/generate
