# 套餐权限管理

## 表
`saas_package_setting`：三套套餐配额与功能开关

## 接口（超管）
- `GET /api/package-setting/list`
- `POST /api/package-setting/save`（`reset:true` 重置默认）

## 校验工具
`App\Support\PackageQuota`
- Prompt / 知识库 / 爬虫 / 社媒账号上限
- 平台白名单
- 账号独立 AI / 知识库开关
- 套餐变更后 `reconcileTenantAiTemplate` 自动降级

## 模板筛选升级
`GET /api/tenant/{id}/ai-template-list-by-package` 读取 `max_template_level` 过滤

## 前端
系统管理 → **套餐权限管理** `/system/package-settings`
