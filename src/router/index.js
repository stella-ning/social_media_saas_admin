/**
 * 路由配置 - SocialAI SaaS 社媒采集运营后台
 * 含登录守卫 + 角色权限校验
 */
import { createRouter, createWebHistory } from 'vue-router'
import Layout from '@/layout/MainLayout.vue'
import {
  isLoggedIn,
  getCurrentRole,
  hasPermission,
  getDefaultHome
} from '@/utils/auth'
import { ElMessage } from 'element-plus'

const routes = [
  // ========== 登录页（独立布局） ==========
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/Login.vue'),
    meta: { title: '登录', public: true }
  },

  {
    path: '/',
    component: Layout,
    redirect: '/dashboard',
    children: [
      {
        path: 'dashboard',
        name: 'Dashboard',
        component: () => import('@/views/dashboard/Index.vue'),
        meta: { title: '首页仪表盘', parent: '首页' }
      },
      {
        path: 'resource/social-accounts',
        name: 'SocialAccounts',
        component: () => import('@/views/resource/SocialAccounts.vue'),
        meta: { title: '社媒账号管理', parent: '资源管理' }
      },
      {
        path: 'resource/crawler-tasks',
        name: 'CrawlerTasks',
        component: () => import('@/views/resource/CrawlerTasks.vue'),
        meta: { title: '爬虫任务管理', parent: '资源管理' }
      },
      {
        path: 'resource/proxy-ip',
        name: 'ProxyIp',
        component: () => import('@/views/resource/ProxyIp.vue'),
        meta: { title: '代理IP管理', parent: '资源管理' }
      },
      {
        path: 'system/tenants',
        name: 'Tenants',
        component: () => import('@/views/system/Tenants.vue'),
        meta: { title: '租户管理', parent: '系统管理', adminOnly: true }
      },
      {
        path: 'system/ai-config',
        name: 'AiConfig',
        component: () => import('@/views/system/AiConfig.vue'),
        meta: { title: 'AI配置中心', parent: '系统管理' }
      },
      {
        path: 'system/crm-leads',
        name: 'CrmLeads',
        component: () => import('@/views/system/CrmLeads.vue'),
        meta: { title: 'CRM客户线索', parent: '系统管理' }
      },
      {
        path: 'system/messages',
        name: 'Messages',
        component: () => import('@/views/system/Messages.vue'),
        meta: { title: '消息会话管理', parent: '系统管理' }
      },
      {
        path: 'system/settings',
        name: 'Settings',
        component: () => import('@/views/system/Settings.vue'),
        meta: { title: '系统设置', parent: '系统管理' }
      }
    ]
  },

  {
    path: '/:pathMatch(.*)*',
    redirect: '/dashboard'
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

/**
 * 路由守卫
 * 1. 未登录 → /login
 * 2. 已登录访问登录页 → 角色首页
 * 3. 无页面权限 → 提示并跳转角色首页
 */
router.beforeEach((to, from, next) => {
  if (to.meta.public) {
    if (isLoggedIn() && to.path === '/login') {
      next(getDefaultHome())
    } else {
      next()
    }
    return
  }

  if (!isLoggedIn()) {
    next({ path: '/login', query: { redirect: to.fullPath } })
    return
  }

  const role = getCurrentRole()
  // 业务子页权限校验（排除布局根路径）
  if (to.path !== '/' && !hasPermission(to.path, role)) {
    ElMessage.warning('当前角色无权访问该页面')
    next(getDefaultHome(role))
    return
  }

  next()
})

export default router
