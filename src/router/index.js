/**
 * 路由配置 - SocialAI SaaS 社媒采集运营后台
 * 含登录守卫 + 角色权限校验
 */
import { createRouter, createWebHistory } from 'vue-router'
import Layout from '@/layout/MainLayout.vue'
import {
  isLoggedIn,
  getCurrentRole,
  getCurrentUser,
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
        path: 'resource/ip-risk',
        name: 'IpRisk',
        component: () => import('@/views/resource/IpRisk.vue'),
        meta: { title: 'IP风险检测', parent: '资源管理' }
      },
      {
        path: 'resource/comment-funnel',
        name: 'CommentFunnel',
        component: () => import('@/views/resource/CommentFunnel.vue'),
        meta: { title: '评论引流日志', parent: '资源管理' }
      },
      {
        path: 'system/sensitive-words',
        name: 'SensitiveWords',
        component: () => import('@/views/system/SensitiveWords.vue'),
        meta: { title: '敏感词管理', parent: '资源管理' }
      },
      {
        path: 'system/tenants',
        name: 'Tenants',
        component: () => import('@/views/system/Tenants.vue'),
        meta: { title: '租户管理', parent: '平台运营', adminOnly: true }
      },
      {
        path: 'system/package-settings',
        name: 'PackageSettings',
        component: () => import('@/views/system/PackageSettings.vue'),
        meta: { title: '套餐权限管理', parent: '平台运营', adminOnly: true }
      },
      {
        path: 'system/finance',
        name: 'FinanceReport',
        component: () => import('@/views/system/FinanceReport.vue'),
        meta: { title: '财务报表', parent: '平台运营', adminOnly: true }
      },
      {
        path: 'system/package-purchase',
        name: 'PackagePurchase',
        component: () => import('@/views/system/PackagePurchase.vue'),
        meta: { title: '套餐购买', parent: '套餐中心' }
      },
      {
        path: 'system/sub-accounts',
        name: 'SubAccounts',
        component: () => import('@/views/system/SubAccounts.vue'),
        meta: { title: '子账号管理', parent: '套餐中心' }
      },
      {
        path: 'system/industry-prompts',
        name: 'IndustryPrompts',
        component: () => import('@/views/system/IndustryPrompts.vue'),
        meta: { title: '行业Prompt商城', parent: 'AI智能' }
      },
      {
        path: 'system/crawler-behavior',
        name: 'CrawlerBehavior',
        component: () => import('@/views/system/CrawlerBehavior.vue'),
        meta: { title: '爬虫真人行为', parent: '资源管理' }
      },
      {
        path: 'system/crm-reminders',
        name: 'CrmReminders',
        component: () => import('@/views/system/CrmReminders.vue'),
        meta: { title: 'CRM跟进提醒', parent: '客户运营' }
      },
      {
        path: 'system/ai-config',
        name: 'AiConfig',
        component: () => import('@/views/system/AiConfig.vue'),
        meta: { title: 'AI配置中心', parent: 'AI智能' }
      },
      {
        path: 'system/crm-leads',
        name: 'CrmLeads',
        component: () => import('@/views/system/CrmLeads.vue'),
        meta: { title: 'CRM客户线索', parent: '客户运营' }
      },
      {
        path: 'system/messages',
        name: 'Messages',
        component: () => import('@/views/system/Messages.vue'),
        meta: { title: '消息会话管理', parent: '客户运营' }
      },
      {
        path: 'system/settings',
        name: 'Settings',
        component: () => import('@/views/system/Settings.vue'),
        meta: { title: '系统设置', parent: '系统' }
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
  const user = getCurrentUser()
  // 业务子页权限校验（排除布局根路径）：角色 ∩ 套餐
  if (to.path !== '/' && !hasPermission(to.path, role, user)) {
    ElMessage.warning('当前账号无权访问该页面')
    next({ path: getDefaultHome(role, user), replace: true })
    return
  }

  next()
})

export default router
