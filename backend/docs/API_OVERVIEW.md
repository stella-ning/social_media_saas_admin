# SocialAI SaaS 后端 API 目录说明

详见：

1. [数据库设计 DATABASE.md](./DATABASE.md)
2. [启动与接口 README](../README.md)
3. [Postman 集合](./SocialAI_SaaS_API.postman_collection.json)

## 前端联调字段对照（节选）

| 前端页面 | 典型字段 |
|----------|----------|
| 租户 | name, contact, phone, package, createTime, status, concurrent, aiQuota, binds, kb |
| 代理IP | address, location, protocol, status, load, capacity |
| 爬虫 | name, platform, target, tenant, frequency, status, todayCount |
| 社媒账号 | name, uid, platform, bindIp, tenant, status |
| CRM | nickname, phone, quote, channel, score, intent, status, follower, tags |
| 登录用户 | username, displayName, role, roleLabel, tenant, context |
