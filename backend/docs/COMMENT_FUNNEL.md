# 小红书评论引流闭环（Comment Funnel）

## 业务流程
1. 爬虫任务采集同行笔记评论（`enable_comment_collect`）
2. 第一层：过滤广告/水军/表情灌水，筛出咨询意向
3. 第二层：主页核验区分真实消费者 / 营销号（营销号入黑名单）
4. Prompt 生成生活化闲聊回复（禁 AI/客服/硬广字眼）
5. 双层敏感词：平台全局 + 租户自定义
6. Playwright 模拟滑动/停留后发送（默认 dry-run）
7. 成功后写入 `spider_comment_record` 并推 CRM

## 表
- `crawler_tasks` 扩展字段（对应需求 spider_task）
- `spider_comment_record` 评论操作日志
- `marketing_account_blacklist` 营销号黑名单

## API
- `GET /api/comment-funnel/records|stats|blacklist`
- `GET/POST/DELETE /api/sensitive-words`
- 任务创建支持评论引流开关字段
- `POST /api/crawler-tasks/{id}/simulate-collect` 演示全链路

## Python
- `POST /api/check-homepage`
- `POST /api/reply-comment`（`PYTHON_COMMENT_DRY_RUN=true` 默认）

## 前端
- 资源管理 → 评论引流日志 / 敏感词管理 / 爬虫真人行为
- 新建任务弹窗：采集评论 / 校验主页 / AI 自动回复
