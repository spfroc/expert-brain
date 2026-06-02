# 前端设计 V1

## 1. 技术栈

- Vue 3
- Vite
- TypeScript
- Pinia
- Vue Router
- Element Plus
- Tailwind CSS
- Axios

## 2. 页面结构

```text
/login
/dashboard
/knowledge/bases
/knowledge/documents
/knowledge/documents/:id
/rag/chat
/customers
/customers/:id
/rules
/plans
/plans/:id
/cases
/cases/:id
/settings/users
/settings/roles
```

## 3. 布局

后台管理布局：

- 左侧菜单
- 顶部用户信息
- 主内容区
- 面包屑

## 4. 核心页面

### 4.1 Dashboard

显示：

- 知识库数量
- 文档数量
- chunk 数量
- 客户数量
- 今日问答次数
- 最近生成方案

### 4.2 知识中心

左侧：知识库和分类树。

右侧：文档列表。

功能：

- 新建文档
- 上传文档
- 标签筛选
- 状态筛选
- 发布/失效
- 重新索引

### 4.3 文档详情

- 基本信息
- Markdown 内容
- 标签
- 版本
- chunk 列表
- 引用统计

### 4.4 RAG 问答

类似 ChatGPT。

左侧：会话列表。

中间：问答消息。

右侧：引用来源。

每条 AI 消息显示：

- 回答内容
- 引用编号
- 置信等级
- 缺失信息提示

### 4.5 客户中心

客户列表字段：

- 企业名称
- 地区
- 经营范围摘要
- 标签
- 目标平台
- 状态

客户详情：

- 基础信息
- 自动标签
- 规则命中
- 推荐平台
- 风险提示
- 方案记录

### 4.6 规则中心

规则列表：

- 名称
- 类型
- 优先级
- 是否启用
- 命中次数

规则编辑器：

V1 可直接编辑 JSON。

后续优化为可视化规则构建器。

### 4.7 服务方案

左侧输入：

- 客户
- 服务目标
- 目标平台
- 特殊要求

右侧输出：

- 方案正文
- 引用依据
- 待确认事项
- 保存/导出

### 4.8 案例中心

支持案例列表、详情、编辑、发布。

## 5. 前端状态要求

每个页面必须具备：

- loading 状态
- empty 状态
- error 状态
- permission denied 状态

## 6. API 封装规范

建议目录：

```text
src/api/auth.ts
src/api/knowledge.ts
src/api/documents.ts
src/api/rag.ts
src/api/customers.ts
src/api/rules.ts
src/api/plans.ts
src/api/cases.ts
```

## 7. 组件建议

```text
KnowledgeTree.vue
DocumentStatusTag.vue
CitationList.vue
ChatMessage.vue
CustomerTagList.vue
RuleJsonEditor.vue
PlanPreview.vue
```

## 8. 权限控制

前端基于后端返回 permissions 控制菜单和按钮。

后端仍必须进行最终权限校验。
