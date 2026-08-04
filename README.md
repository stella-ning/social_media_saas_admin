# SocialAI SaaS 社媒采集运营后台

Vue3 + Vite + Element Plus 后台管理系统（静态 mock 数据，可直接运行）。

## 技术栈

- Vue 3（`<script setup>`）
- Vite 6
- Element Plus
- ECharts + vue-echarts
- Vue Router 4

## 快速开始

```bash
npm install
npm run dev
```

浏览器访问：http://localhost:5173

## 目录结构

```
social_media_saas_admin/
├── index.html
├── package.json
├── vite.config.js
├── prototypes/                 # 原始 HTML 原型（参考）
└── src/
    ├── main.js                 # 入口：Element Plus / ECharts 全局注册
    ├── App.vue
    ├── assets/main.css
    ├── router/index.js         # 完整路由
    ├── layout/MainLayout.vue   # 侧边栏布局
    ├── components/
    │   └── CreateTaskDialog.vue
    └── views/
        ├── dashboard/Index.vue
        ├── resource/
        │   ├── SocialAccounts.vue
        │   ├── CrawlerTasks.vue
        │   └── ProxyIp.vue
        └── system/
            ├── Tenants.vue
            ├── AiConfig.vue
            ├── CrmLeads.vue
            ├── Messages.vue
            └── Settings.vue
```

## 菜单结构

1. 首页仪表盘
2. 资源管理
   - 社媒账号管理
   - 爬虫任务管理
   - 代理IP管理
3. 系统管理
   - 租户管理
   - AI配置中心
   - CRM客户线索
   - 消息会话管理
   - 系统设置
