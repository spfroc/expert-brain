# RAG 架构设计

## 1. RAG 目标

RAG 不是让模型凭记忆回答，而是让模型基于企业知识库检索结果回答。

核心目标：

- 能查到相关知识。
- 能引用来源。
- 能区分确定和不确定。
- 能避免编造法规、平台规则和业务结论。

## 2. 文档处理流程

```text
原始文档
  ↓
文件解析
  ↓
统一 Markdown
  ↓
清洗与规范化
  ↓
Chunk 切分
  ↓
Embedding
  ↓
写入 knowledge_chunks
```

## 3. 文档类型处理

### PDF

优先提取文本。扫描版 PDF 后续接 OCR。

### Word

提取标题层级和正文。

### Excel

按 Sheet、表头、行记录转为 Markdown 表格或结构化文本。

### HTML/URL

抽取正文、标题、发布时间、来源 URL。

### 手工经验

直接作为 Markdown 入库。

## 4. Chunk 策略

### 法规政策

按章、节、条切分。每个 chunk 保留法规名称、章节、条款号。

### 平台手册

按标题层级切分。保留菜单路径、平台名称、操作模块。

### 经验总结

按段落或问题-答案切分。保留作者、适用平台、适用客户类型。

### 案例

按客户背景、过程、结果、经验、风险点切分。

## 5. Embedding

V1 默认：BGE-M3。

embedding 维度：1024。

必须记录：

- embedding_model
- embedding_dimension
- content_hash

如果 content_hash 未变化，不重复生成 embedding。

## 6. 检索策略

V1 使用 hybrid search：

```text
向量检索
+
全文检索
+
元数据过滤
```

过滤条件：

- knowledge_base_id
- document status = published
- platform
- tag
- category
- effective date

## 7. 排序策略

V1 基础分：

```text
final_score = vector_score * 0.7 + keyword_score * 0.3
```

V2 可接入 reranker。

## 8. Answer 生成

Prompt 输入：

- 用户问题
- 检索片段
- 引用编号
- 系统规则

输出：

- answer
- citations
- confidence
- missing_info

## 9. 不确定性规则

以下情况必须返回不确定：

- 检索结果为空。
- 检索结果分数低于阈值。
- 检索结果互相矛盾。
- 问题要求当前最新政策但知识库没有更新时间依据。

## 10. 引用格式

每个引用至少包含：

- document_id
- document_title
- chunk_id
- quote
- score
- source_url

## 11. RAG API 内部流程

```text
POST /internal/rag/answer
  ↓
validate question
  ↓
embed question
  ↓
hybrid search
  ↓
filter + rank
  ↓
build prompt
  ↓
call LLM
  ↓
parse answer
  ↓
return answer + citations
```

## 12. 质量评估

建立测试集：

- 高频平台问题 50 条。
- 法规政策问题 50 条。
- 客户分析问题 30 条。
- 方案生成问题 20 条。

评估指标：

- 检索命中率。
- 引用准确率。
- 回答可用率。
- 幻觉率。
