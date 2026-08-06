# 消息会话管理模块

## 业务链路

```
爬虫任务采集同行笔记/视频评论区
        ↓  POST /api/crawler-tasks/{id}/collect-callback
筛选咨询类留言（代理/价格/合作等意向词）
        ↓  MessageService::ingestVisitorMessage
生成/更新会话 → Prompt + 知识库生成真人风格话术
        ↓
意向筛选 → 高意向一键推 CRM
```

演示入口：爬虫任务列表「模拟采集接入」，或 `php artisan crawler:dispatch --ingest-demo`。

## AI 硬性规则

- 话术模仿真人客服，口语化、简短
- 禁止出现 AI / 智能助手 / 机器人 / 系统客服 / 人工智能 等字眼
- 受套餐日 AI 配额管控；基础版仅可用 Level-1 Prompt
- 账号开启独立 AI 时优先账号专属 Prompt
- 转人工后暂停该会话全部 AI 自动回复
- 达日上限自动关闭该租户全部会话的 AI 自动回复

## 核心能力

| 能力 | 说明 |
|------|------|
| 爬虫→会话 | `CrawlerCommentBridge` 筛选咨询留言并 ingest |
| 会话标签 | 意向等级（无/普通/高）、咨询产品、来源平台；可手动或 AI 自动打标 |
| 推 CRM | 高意向会话一键写入客户线索库 |
| 闲置关闭 | `php artisan messages:close-idle`（已挂每小时调度） |
| 快捷回复 | 租户预设话术，人工聊天一键选用 |
| 敏感词 | AI 发送前筛查，违规拦截并写告警日志 |
| 消息状态 | 未读 / 已读 / 已处理；未读列表醒目样式 |
| 套餐平台 | 仅允许套餐绑定平台的会话消息进入接待 |

## 主要 API

### 爬虫 → 留言（本链路关键）

- `POST /api/crawler-tasks/{id}/collect-callback` Worker 提交评论：`{ comments: [{ name, content, avatar? }] }`
- `POST /api/crawler-tasks/{id}/simulate-collect` 演示模拟采集并接入

### 消息会话

- `GET /api/messages/sessions` 会话列表
- `GET /api/messages/sessions/{id}` 详情（打开后标记已读）
- `POST /api/messages/sessions/{id}/send` 人工发送
- `PUT /api/messages/sessions/{id}/settings` AI 开关 / 转人工 / 标签
- `POST /api/messages/sessions/{id}/push-crm` 推送 CRM
- `POST /api/messages/ingest` 直接接入访客消息（触发 AI）
- `GET|POST /api/messages/quick-replies` 快捷话术
- `GET /api/messages/alert-logs` 敏感词告警

## 演示数据

```bash
php artisan db:seed --class=MessageModuleDemoSeeder
php artisan crawler:dispatch --ingest-demo --limit=1
```
