# SocialAI SaaS API - 数据库设计

> 技术栈：PHP 8.2 + Laravel 11 + MySQL 8 + Redis + Sanctum  
> 字段命名：库表 snake_case，API 响应 camelCase（对齐前端 mock）

## 一、ER 关系概览

```
users ──N:1── tenants
tenants ──1:N── social_accounts / crawler_tasks / proxy_ips / ai_prompt_templates
              / knowledge_docs / crm_leads / message_sessions
crawler_tasks ──1:N── crawler_task_logs
message_sessions ──1:N── messages
```

## 二、数据表结构

### 1. users（系统用户）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint PK | 主键 |
| username | varchar(64) unique | 登录账号 admin/yx_admin/zhangsan |
| display_name | varchar(64) | 显示名 |
| password | varchar(255) | 哈希密码 |
| role | enum | super_admin / tenant_admin / operator |
| tenant_id | bigint nullable FK | 所属租户 |
| status | tinyint | 1正常 0禁用 |
| last_login_at | datetime nullable | 最后登录 |
| timestamps / soft_deletes | | |

### 2. tenants（租户）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint PK | |
| name | varchar(128) | 租户名称 |
| contact | varchar(64) | 联系人 |
| phone | varchar(32) | 联系电话 |
| email | varchar(128) | 邮箱 |
| package | varchar(16) | basic/pro/ent |
| status | tinyint | 1启用 0禁用 |
| concurrent | int | 任务并发数 |
| ai_quota | int | AI调用额度/月 |
| binds | int | 账号绑定上限 |
| kb | decimal(8,1) | 知识库 GB |
| remark | text nullable | 备注 |
| timestamps / soft_deletes | | |

### 3. social_accounts（社媒账号）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint PK | |
| name | varchar(64) | 账号昵称 |
| uid | varchar(64) | 平台 UID |
| avatar | varchar(255) | 头像 URL |
| platform | varchar(32) | 小红书/抖音/视频号 |
| bind_ip | varchar(64) | 绑定代理 IP |
| tenant_id | bigint FK | 所属租户 |
| status | varchar(16) | online / offline |
| cookie | text nullable | Cookie/Token |
| timestamps / soft_deletes | | |

### 4. crawler_tasks（爬虫任务）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint PK | |
| name | varchar(128) | 任务名称 |
| platform | varchar(32) | 目标平台 |
| task_type | varchar(32) | keyword / monitor |
| keywords | text | 关键词 |
| target | varchar(255) | 展示用：关键词/监控对象 |
| tenant_id | bigint FK | |
| frequency | varchar(32) | 每2小时 等 |
| status | varchar(16) | running / paused |
| today_count | int | 今日采集 |
| daily_limit | int | 日采集上限 |
| timestamps / soft_deletes | | |

### 5. crawler_task_logs（任务日志）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint PK | |
| task_id | bigint FK | |
| type | varchar(16) | success/primary/warning |
| content | varchar(500) | 日志内容 |
| logged_at | datetime | 时间 |

### 6. proxy_ips（代理 IP）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint PK | |
| address | varchar(128) | 123.56.78.102:8080 |
| location | varchar(64) | 归属地 |
| protocol | varchar(32) | HTTP/HTTPS |
| status | varchar(16) | running/idle/error |
| load | int | 当前负载 |
| capacity | int | 容量默认100 |
| tenant_id | bigint nullable | 绑定租户 |
| latency_ms | int nullable | 最近检测延迟 |
| timestamps / soft_deletes | | |

### 7. ai_prompt_templates（AI Prompt 模板）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint PK | |
| tenant_id | bigint FK | |
| category | varchar(64) | 社媒评论生成等 |
| tag_type | varchar(16) | Element tag 类型 |
| name | varchar(128) | 模板名称 |
| desc | varchar(255) | 简介 |
| role | text | 角色设置 |
| rules | text | 约束条件 |
| timestamps / soft_deletes | | |

### 8. knowledge_docs（知识库文档）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint PK | |
| tenant_id | bigint FK | |
| name | varchar(255) | 文档名 |
| size | varchar(32) | 展示用大小 |
| status | varchar(16) | ready/processing/failed |
| tags | varchar(128) nullable | 标签 |
| icon_color | varchar(16) | |
| timestamps / soft_deletes | | |

### 9. crm_leads（CRM 线索）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint PK | |
| nickname | varchar(64) | 客户昵称 |
| phone | varchar(32) | 联系方式 |
| quote | varchar(255) | 原文 |
| channel | varchar(32) | 小红书/抖音/视频号 |
| tenant_id | bigint FK | |
| score | int | 意向分 |
| intent | varchar(16) | high/mid/low |
| status | varchar(16) | 未处理/已接洽/已成交/已流失 |
| follower | varchar(64) | 跟进人 |
| tags | json | 标签数组 |
| remark | text nullable | |
| timestamps / soft_deletes | | |

### 10. message_sessions（消息会话）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint PK | |
| name | varchar(64) | 会话对方昵称 |
| avatar | varchar(255) | |
| platform | varchar(32) | |
| tenant_id | bigint FK | |
| last_msg | varchar(255) | |
| time_label | varchar(32) | 10:45 / 昨天 |
| unread | int | |
| date_label | varchar(64) | |
| ai_auto_reply | tinyint | AI自动回复 |
| timestamps / soft_deletes | | |

### 11. messages（会话消息）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint PK | |
| session_id | bigint FK | |
| from_type | varchar(16) | user/ai/human |
| content | text | |
| timestamps | | |

### 12. system_settings（系统设置）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint PK | |
| group | varchar(32) | basic/security |
| key | varchar(64) unique | |
| value | json | |
| timestamps | | |

## 三、角色权限矩阵

| 模块 | super_admin | tenant_admin | operator |
|------|:-----------:|:------------:|:--------:|
| 仪表盘 | ✓ | ✓ | ✓ |
| 社媒账号 | ✓ | ✓ | ✗ |
| 爬虫任务 | ✓ | ✓ | ✗ |
| 代理IP | ✓ | ✗ | ✗ |
| 租户管理 | ✓ | ✗ | ✗ |
| AI配置 | ✓ | ✓ | ✗ |
| CRM线索 | ✓ | ✓ | ✓ |
| 消息会话 | ✓ | ✓ | ✓ |
| 系统设置 | ✓ | ✗ | ✗ |
