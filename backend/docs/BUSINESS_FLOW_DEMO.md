# 完整业务链路演示数据

## 写入命令
```bash
cd backend
php artisan db:seed --class=BusinessFlowDemoSeeder
# 全新环境：php artisan migrate:fresh --seed
```

## 数据链路（端到端）

```
saas_package_setting（套餐权限）
        ↓
tenants（租户配额 / package / 代理IP上限）
        ↓
proxy_ips + saas_tenant_proxy（租户代理池）
        ↓
saas_social_account（一号一IP、状态正常）
        ↓
saas_account_cookie（有效 Cookie）
        ↓
saas_ai_param_template / ai_prompt_templates / knowledge_docs
        ↓（企业版小红书可再绑账号级 AI）
crawler_tasks.social_account_id → 强制专属代理 + Cookie
        ↓ crawler:dispatch / Worker 采集评论区
crawler_task_logs + POST collect-callback（咨询留言筛选）
        ↓ MessageService::ingestVisitorMessage
message_sessions（AI 接待） → crm_leads（高意向推送）
```

演示闭环：
```bash
php artisan crawler:dispatch --ingest-demo --limit=1
# 或在「爬虫任务」页点击「模拟采集接入」
```
应能在「消息会话」看到新访客咨询，并有 AI 自动回复。

## 演示账号
| 账号 | 角色 | 密码 |
|------|------|------|
| admin | 超管 | password123 |
| yx_admin | 悦享租户管理员 | password123 |
| zhangsan | 业务员 | password123 |

## 调度验证
```bash
php artisan crawler:dispatch --dry-run
```
应能看到运行中任务输出 `social_account_id` / `proxy_ip_id` / `has_cookie=true`（日志不含 Cookie 明文）。
