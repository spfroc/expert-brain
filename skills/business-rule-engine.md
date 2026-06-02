# Skill: Business Rule Engine

## Purpose

用于指导 Agent 实现客户分析中的确定性规则系统。

## Why

经营范围、资质、平台适配等确定性判断不应完全交给大模型。

## Rule Example

```json
{
  "name": "办公用品推荐平台",
  "rule_type": "platform_recommendation",
  "condition_json": {
    "business_scope_contains_any": ["办公用品", "办公耗材"]
  },
  "result_json": {
    "recommended_platforms": ["京东慧采", "青慧采"],
    "suggested_services": ["平台入驻", "商品发布"]
  },
  "priority": 10,
  "enabled": true
}
```

## V1 Supported Conditions

- business_scope_contains_any
- business_scope_contains_all
- main_products_contains_any
- target_platforms_contains_any
- qualifications_missing_any
- region_in

## Output

Rule execution should return matched rules and merged suggestions.

```json
{
  "matched_rules": [],
  "recommended_platforms": [],
  "suggested_services": [],
  "risks": [],
  "required_qualifications": []
}
```
