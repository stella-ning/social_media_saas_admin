# 套餐权限 · 平台公共代理托管（2026.08）

## 硬性规则

- **全部套餐禁止租户上传自有代理 IP**
- 爬虫网络请求统一使用平台公共住宅代理池；企业版可启用专属隔离公共池
- 启动爬虫时由 `PlatformProxyAllocator` 自动分配

## 定价

| 套餐 | 月费 | 平台 | 日公共IP请求 |
|------|------|------|--------------|
| 基础版 | ¥139 | 仅小红书 | 3000 |
| 专业版 | ¥399 | 小红书+抖音 | 20000 |
| 企业版 | ¥1099 | 三平台 | 不限 |

## 新表

- `tenant_order` 套餐订单（含 package_version）
- `tenant_resource_consume` 日资源消耗与五项硬性成本
- `tenant_sub_account` 企业版子账号
- `industry_prompt` 行业话术商城
- `proxy_ip_access_logs` / `premium_feature_usage_logs`
- `crawler_human_behavior` / `crm_follow_reminders`

## 关键命令

```bash
php artisan migrate --force
php artisan db:seed --class=PackagePlatformProxySeeder
php artisan finance:daily-cost
```

## 页面

- 租户：套餐购买、代理IP（只读已分配）、子账号、行业Prompt、真人行为、IP风险、CRM提醒
- 超管：套餐权限、财务报表（ECharts）、公共池导入
