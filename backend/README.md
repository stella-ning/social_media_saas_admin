# SocialAI SaaS API（Laravel 11）

PHP 8.2 + Laravel 11 + MySQL 8 + Redis + Sanctum  
前后端分离，字段 camelCase 对齐 Vue3 前端 mock。

## 目录结构

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/     # 控制器（瘦）
│   │   ├── Middleware/          # 角色权限中间件
│   │   └── Requests/            # FormRequest 校验
│   ├── Models/                  # Eloquent + SoftDeletes
│   ├── Services/                # 业务服务层
│   └── Support/ApiResponse.php  # 统一响应
├── bootstrap/app.php
├── config/permission.php
├── database/
│   ├── migrations/              # 12 张业务表
│   └── seeders/DatabaseSeeder.php
├── docs/DATABASE.md             # 库表设计说明
├── postman/                     # Postman 导入文件
├── routes/api.php               # RESTful 路由
├── composer.json
└── .env.example
```

## 环境要求

- PHP >= 8.2（含 ext-redis 或 predis）
- Composer 2
- MySQL 8
- Redis

> 当前机器若未安装 PHP，请先安装后再执行下列命令。

## 快速启动

推荐用官方骨架挂载本仓库业务代码：

```bash
# 1. 创建 Laravel 11 骨架到临时目录并安装 Sanctum
composer create-project laravel/laravel:^11.0 social-ai-api
cd social-ai-api
composer require laravel/sanctum

# 2. 将本 backend 的业务代码覆盖进去（保留 vendor）
#    复制 app/、routes/api.php、database/migrations、database/seeders、
#    config/permission.php、bootstrap/app.php、docs/、postman/

# 或直接在本目录（需已有完整 Laravel 骨架文件）：
cp .env.example .env
composer install
php artisan key:generate

# 3. 配置 .env 中 DB_* / REDIS_* 
# 4. 迁移 + 填充
php artisan migrate --seed

# 5. 启动
php artisan serve --port=8000
```

API 根地址：`http://localhost:8000/api`

## 演示账号

| 账号 | 密码 | 角色 |
|------|------|------|
| admin | password123 | 超级管理员 |
| yx_admin | password123 | 租户管理员 |
| zhangsan | password123 | 业务员 |

## 统一响应

```json
{ "code": 200, "msg": "操作成功", "data": {} }
```

列表：

```json
{
  "code": 200,
  "msg": "操作成功",
  "data": { "list": [], "total": 0, "page": 1, "size": 10 }
}
```

## 主要接口

| 方法 | 路径 | 说明 |
|------|------|------|
| POST | /api/auth/login | 登录 |
| POST | /api/auth/logout | 退出 |
| GET | /api/auth/me | 当前用户 |
| POST | /api/auth/switch-role | 切换角色（需密码） |
| GET | /api/dashboard/overview | 仪表盘 |
| GET/POST/PUT/DELETE | /api/tenants | 租户 CRUD |
| GET/POST/DELETE | /api/social-accounts | 社媒账号 |
| GET/POST | /api/crawler-tasks | 爬虫任务 |
| GET/POST | /api/proxy-ips | 代理 IP |
| GET/POST | /api/ai-config/* | AI 配置 |
| GET/POST | /api/crm-leads | CRM 线索 |
| GET/POST | /api/messages/sessions | 消息会话 |
| GET/PUT | /api/settings/* | 系统设置 |

完整示例见 `postman/SocialAI_SaaS_API.postman_collection.json`（可直接导入 Postman）。

## 前端联调

Vite 代理示例（`vite.config.js`）：

```js
server: {
  proxy: {
    '/api': {
      target: 'http://localhost:8000',
      changeOrigin: true,
    }
  }
}
```

请求头：`Authorization: Bearer {token}`
