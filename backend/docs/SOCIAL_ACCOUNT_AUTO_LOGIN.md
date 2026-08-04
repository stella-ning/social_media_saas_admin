# 社媒账号自动登录绑定（改造说明）

## 业务硬性约束
1. **一号一IP**：`saas_social_account.bind_proxy_id` 唯一索引
2. **租户隔离**：代理归属通过 `proxy_ips.tenant_id` + `saas_tenant_proxy`
3. **密码 AES-256** 存 `encrypt_pwd`，明文用完即 `unset`，日志只记 account_hash
4. **Cookie** 独立表 `saas_account_cookie`，禁止写入业务日志
5. **固定指纹**：`browser_user_agent` + `1920x1080`，刷新/爬虫复用
6. **代理登录频率**：Redis/Cache `proxy_login_rate:{id}`，1 小时 1 次
7. **爬虫强制代理**：`crawler_tasks.social_account_id` → 读取账号专属 IP，不可更换

## 核心接口
| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/tenant/free-proxy-ip/{tenantId}` | 租户空闲代理 |
| POST | `/api/social-account/store` | 凭据绑定 + 自动登录 |
| GET | `/api/social-account/check-login/{id}` | 会话检测 |
| GET | `/api/social-accounts/{id}/logs` | 操作日志 |
| CLI | `php artisan cookie:refresh-all` | 每 6 小时调度刷新 |

## 启动清单
```bash
# MySQL 迁移
cd backend && php artisan migrate --seed

# Laravel
php artisan serve
php artisan schedule:work   # 或 crontab schedule:run

# Python 登录服务
cd python-login-service
uvicorn app.main:app --port 8100

# 前端
npm run dev
```

## 后期可扩展
- 视频号扫码登录通道
- 平台选择器配置中心热更新
- 登录失败人工工单/通知
- Cookie 加密存储（当前 JSON 明文入库，可再套 AES）
- 爬虫 Worker 读取 `resolveBoundProxy()` 真正挂载代理执行
