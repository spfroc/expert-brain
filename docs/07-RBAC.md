# RBAC 权限设计

## 1. 权限模型

V1 推荐使用 `spatie/laravel-permission`。

模型：

- User
- Role
- Permission

## 2. 默认角色

### super-admin

系统最高权限。

拥有所有权限。

### knowledge-admin

知识管理员。

权限：

- knowledge_base.view
- knowledge_base.create
- knowledge_base.update
- knowledge_document.view
- knowledge_document.create
- knowledge_document.update
- knowledge_document.review
- knowledge_document.publish
- knowledge_document.expire
- knowledge_document.reindex
- case.view
- case.create
- case.update

### business-user

业务人员。

权限：

- knowledge_document.view
- rag.ask
- customer.view
- customer.create
- customer.update
- customer.analyze
- plan.generate
- plan.view
- plan.update_own
- case.view

### manager

管理层。

权限：

- dashboard.view
- knowledge_document.view
- customer.view
- plan.view
- report.view
- audit.view

### readonly

只读用户。

权限：

- knowledge_document.view
- customer.view
- case.view

## 3. 权限清单

### 系统

- system.setting.view
- system.setting.update
- user.view
- user.create
- user.update
- user.disable
- role.view
- role.create
- role.update

### 知识库

- knowledge_base.view
- knowledge_base.create
- knowledge_base.update
- knowledge_base.delete

### 文档

- knowledge_document.view
- knowledge_document.create
- knowledge_document.update
- knowledge_document.delete
- knowledge_document.review
- knowledge_document.publish
- knowledge_document.expire
- knowledge_document.reindex

### RAG

- rag.ask
- rag.view_conversation
- rag.delete_conversation

### 客户

- customer.view
- customer.create
- customer.update
- customer.delete
- customer.analyze

### 规则

- business_rule.view
- business_rule.create
- business_rule.update
- business_rule.delete
- business_rule.test

### 方案

- plan.generate
- plan.view
- plan.update_own
- plan.update_all
- plan.delete

### 案例

- case.view
- case.create
- case.update
- case.delete
- case.publish

### 审计

- audit.view

## 4. 数据权限

V1 默认不做复杂部门数据隔离。后续可扩展：

- personal：只能看自己创建的数据。
- department：看本部门数据。
- all：看全部数据。

## 5. 前端菜单权限

菜单显示基于权限控制。

- 知识中心：knowledge_document.view
- RAG 问答：rag.ask
- 客户中心：customer.view
- 规则中心：business_rule.view
- 方案中心：plan.view
- 案例中心：case.view
- 系统设置：system.setting.view

## 6. 审计要求

以下操作必须记录 audit_logs：

- 用户登录失败
- 权限变更
- 知识发布/失效
- 文档删除
- 规则变更
- 客户删除
- 方案删除
