# 领域模型设计

## 1. 核心领域

ExpertBrain V1 包含以下核心领域：

- Identity：用户、角色、权限
- Knowledge：知识库、分类、标签、文档、片段
- Retrieval：向量、全文检索、引用
- Customer：客户、客户画像、客户标签
- Rule：业务规则、规则命中
- Case：成功案例、失败案例、复盘
- Plan：服务方案、方案引用、方案版本
- Conversation：AI 问答会话
- Audit：审计日志

## 2. 核心实体

### 2.1 KnowledgeBase

知识库，是知识资产的最高组织单元。

字段：

- id
- name
- description
- industry
- status
- created_by
- timestamps

### 2.2 KnowledgeCategory

知识分类，支持树形结构。

字段：

- id
- knowledge_base_id
- parent_id
- name
- sort_order

### 2.3 KnowledgeDocument

知识文档，代表法规、平台文档、经验总结、案例资料等。

字段：

- id
- knowledge_base_id
- category_id
- title
- content
- source_type
- source_url
- file_path
- status
- version
- effective_from
- effective_to
- created_by

### 2.4 KnowledgeChunk

文档切片，用于 RAG 检索。

字段：

- id
- document_id
- chunk_index
- title_path
- content
- content_hash
- token_count
- embedding
- metadata

### 2.5 Platform

采购平台。

示例：京东慧采、青慧采、泉E采、政采云。

字段：

- id
- name
- description
- official_url
- status

### 2.6 Customer

客户企业。

字段：

- id
- company_name
- credit_code
- province
- city
- district
- business_scope
- main_products
- qualifications
- target_platforms
- status
- remarks

### 2.7 BusinessRule

业务规则。用于确定性判断。

字段：

- id
- name
- rule_type
- condition_json
- result_json
- priority
- enabled

### 2.8 RuleHit

规则命中记录。

字段：

- id
- business_rule_id
- customer_id
- input_json
- result_json
- hit_at

### 2.9 CaseStudy

业务案例。

字段：

- id
- title
- customer_id
- platform_id
- case_type
- industry
- summary
- process
- result
- lessons
- status

### 2.10 GeneratedPlan

生成的服务方案。

字段：

- id
- customer_id
- title
- input_json
- content
- structured_json
- model_name
- status
- created_by

### 2.11 Citation

引用来源，用于问答和方案生成追溯。

字段：

- id
- owner_type
- owner_id
- document_id
- chunk_id
- quote
- score

## 3. 领域关系

```text
KnowledgeBase 1 - n KnowledgeCategory
KnowledgeBase 1 - n KnowledgeDocument
KnowledgeDocument 1 - n KnowledgeChunk
KnowledgeDocument n - n KnowledgeTag
Customer 1 - n GeneratedPlan
Customer 1 - n RuleHit
Platform 1 - n CaseStudy
GeneratedPlan 1 - n Citation
Conversation 1 - n ConversationMessage
ConversationMessage 1 - n Citation
```

## 4. 重要业务约束

1. 已发布文档更新时必须创建新版本或记录变更日志。
2. expired 文档默认不参与检索，除非用户显式选择。
3. RAG 引用只能引用 published 状态的知识。
4. 规则命中结果必须可追溯。
5. 方案生成必须保存输入快照，避免客户信息变化后无法复盘。
