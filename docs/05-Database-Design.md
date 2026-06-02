# 数据库设计 V1

数据库：PostgreSQL 16+。向量扩展：pgvector。

## 1. Extensions

```sql
CREATE EXTENSION IF NOT EXISTS vector;
CREATE EXTENSION IF NOT EXISTS pg_trgm;
```

## 2. 命名规范

- 表名使用复数 snake_case。
- 主键统一 `id`。
- 外键命名 `{table_singular}_id`。
- JSON 字段使用 `jsonb`。
- 状态字段使用 varchar，枚举值由应用层约束。

## 3. 核心表

### users

Laravel 默认用户表，扩展字段：

- id
- name
- email
- password
- status
- last_login_at
- timestamps

### roles / permissions / role_user / permission_role

用于 RBAC。可使用 spatie/laravel-permission。

### knowledge_bases

```sql
id bigserial primary key,
name varchar(100) not null,
description text,
industry varchar(100),
status varchar(30) default 'active',
created_by bigint,
created_at timestamp,
updated_at timestamp
```

### knowledge_categories

```sql
id bigserial primary key,
knowledge_base_id bigint not null,
parent_id bigint null,
name varchar(100) not null,
sort_order int default 0,
created_at timestamp,
updated_at timestamp
```

### knowledge_documents

```sql
id bigserial primary key,
knowledge_base_id bigint not null,
category_id bigint null,
title varchar(255) not null,
summary text,
content text,
source_type varchar(50) not null,
source_url text,
file_path text,
file_mime varchar(100),
file_size bigint,
version varchar(50) default '1.0',
status varchar(30) default 'draft',
effective_from date,
effective_to date,
metadata jsonb,
created_by bigint,
reviewed_by bigint,
reviewed_at timestamp,
published_at timestamp,
created_at timestamp,
updated_at timestamp
```

索引：

```sql
CREATE INDEX idx_documents_base_status ON knowledge_documents(knowledge_base_id, status);
CREATE INDEX idx_documents_category ON knowledge_documents(category_id);
CREATE INDEX idx_documents_title_trgm ON knowledge_documents USING gin (title gin_trgm_ops);
```

### knowledge_chunks

BGE-M3 常用 embedding 维度为 1024。如后续更换模型，需要迁移或新增 embedding profile。

```sql
id bigserial primary key,
document_id bigint not null,
knowledge_base_id bigint not null,
chunk_index int not null,
title_path text,
content text not null,
content_hash varchar(64) not null,
token_count int,
embedding vector(1024),
metadata jsonb,
created_at timestamp,
updated_at timestamp
```

索引：

```sql
CREATE INDEX idx_chunks_document ON knowledge_chunks(document_id);
CREATE INDEX idx_chunks_base ON knowledge_chunks(knowledge_base_id);
CREATE INDEX idx_chunks_content_tsv ON knowledge_chunks USING gin (to_tsvector('simple', content));
CREATE INDEX idx_chunks_embedding ON knowledge_chunks USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100);
```

### knowledge_tags

```sql
id bigserial primary key,
name varchar(100) not null unique,
tag_type varchar(50),
created_at timestamp,
updated_at timestamp
```

### knowledge_document_tags

```sql
document_id bigint not null,
tag_id bigint not null,
primary key(document_id, tag_id)
```

### platforms

```sql
id bigserial primary key,
name varchar(100) not null unique,
description text,
official_url text,
status varchar(30) default 'active',
metadata jsonb,
created_at timestamp,
updated_at timestamp
```

### customers

```sql
id bigserial primary key,
company_name varchar(255) not null,
credit_code varchar(50),
province varchar(50),
city varchar(50),
district varchar(50),
business_scope text,
main_products text,
qualifications jsonb,
target_platforms jsonb,
status varchar(30) default 'potential',
remarks text,
created_by bigint,
created_at timestamp,
updated_at timestamp
```

### customer_tags

```sql
id bigserial primary key,
customer_id bigint not null,
tag varchar(100) not null,
source varchar(50) default 'system',
created_at timestamp
```

### business_rules

```sql
id bigserial primary key,
name varchar(255) not null,
rule_type varchar(50) not null,
condition_json jsonb not null,
result_json jsonb not null,
priority int default 100,
enabled boolean default true,
description text,
created_by bigint,
created_at timestamp,
updated_at timestamp
```

### rule_hits

```sql
id bigserial primary key,
business_rule_id bigint not null,
customer_id bigint null,
input_json jsonb not null,
result_json jsonb not null,
hit_at timestamp not null
```

### case_studies

```sql
id bigserial primary key,
title varchar(255) not null,
customer_id bigint null,
platform_id bigint null,
case_type varchar(30) not null,
industry varchar(100),
summary text,
process text,
result text,
lessons text,
status varchar(30) default 'draft',
created_by bigint,
created_at timestamp,
updated_at timestamp
```

### generated_plans

```sql
id bigserial primary key,
customer_id bigint not null,
title varchar(255) not null,
input_json jsonb not null,
content text not null,
structured_json jsonb,
model_name varchar(100),
status varchar(30) default 'draft',
created_by bigint,
created_at timestamp,
updated_at timestamp
```

### conversations

```sql
id bigserial primary key,
user_id bigint not null,
knowledge_base_id bigint null,
title varchar(255),
created_at timestamp,
updated_at timestamp
```

### conversation_messages

```sql
id bigserial primary key,
conversation_id bigint not null,
role varchar(30) not null,
content text not null,
metadata jsonb,
created_at timestamp
```

### citations

```sql
id bigserial primary key,
owner_type varchar(50) not null,
owner_id bigint not null,
document_id bigint null,
chunk_id bigint null,
quote text,
score numeric(8,6),
metadata jsonb,
created_at timestamp
```

### audit_logs

```sql
id bigserial primary key,
user_id bigint null,
action varchar(100) not null,
object_type varchar(100),
object_id bigint,
before_json jsonb,
after_json jsonb,
ip_address varchar(64),
user_agent text,
created_at timestamp
```

## 4. V1 Migration 建议顺序

1. users / roles / permissions
2. knowledge_bases / categories / tags
3. knowledge_documents
4. knowledge_chunks
5. platforms
6. customers / customer_tags
7. business_rules / rule_hits
8. case_studies
9. conversations / messages / citations
10. generated_plans
11. audit_logs
