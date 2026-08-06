# 套餐权限管理

## 表
`saas_package_setting`：三套套餐配额与功能开关

### 代理 IP 配额字段
| 字段 | 说明 |
|------|------|
| max_proxy_ip | 最大绑定代理 IP 数（null=无限） |
| daily_proxy_request_limit | 每日成功请求上限（null=无限） |
| allow_self_proxy | 是否允许租户配置自有代理 |

默认：基础版关闭自有IP（3/500）；专业版开启（15/5000）；企业版开启（无限）

## 接口（超管）
- `GET /api/package-setting/list`
- `POST /api/package-setting/save`（`reset:true` 重置默认）
- `GET /api/package-setting/tenant-quota/{tenantId}`
- `GET /api/proxy-ips/tenant-quota/{tenantId}`（代理页门禁）

## 校验工具
`App\Support\PackageQuota`
- Prompt / 知识库 / 爬虫 / 社媒账号上限
- 代理 IP 绑定上限、自有代理开关、日请求计数（Cache）
- 日请求达限 → 自动暂停租户 running 爬虫
- 平台白名单 / 账号独立 AI / 知识库开关
- 套餐变更后 `reconcileTenantAiTemplate` 自动降级

## 模板筛选升级
`GET /api/tenant/{id}/ai-template-list-by-package` 读取 `max_template_level` 过滤

## 前端
- 系统管理 → **套餐权限管理** `/system/package-settings`
- 资源管理 → **代理IP** `/resource/proxy-ip`（自有代理表单按套餐开关展示）
