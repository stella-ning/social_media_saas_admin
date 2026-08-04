/**
 * 认证与权限工具（对接 Laravel Sanctum）
 * - Token / 用户信息存 localStorage
 * - 角色菜单权限白名单仍前端控制（与后端中间件双重校验）
 */
import { setToken, getToken } from '@/utils/request'

/** 角色菜单权限（路由 path） */
export const ROLE_PERMISSIONS = {
  super_admin: [
    '/dashboard',
    '/resource/social-accounts',
    '/resource/crawler-tasks',
    '/resource/proxy-ip',
    '/system/tenants',
    '/system/ai-config',
    '/system/crm-leads',
    '/system/messages',
    '/system/settings'
  ],
  tenant_admin: [
    '/dashboard',
    '/resource/social-accounts',
    '/resource/crawler-tasks',
    '/system/ai-config',
    '/system/crm-leads',
    '/system/messages'
  ],
  operator: ['/dashboard', '/system/crm-leads', '/system/messages']
}

/** 快捷登录角色 → 演示账号（仅用于弹窗预填，密码需用户输入后调登录接口） */
export const ROLE_ACCOUNTS = {
  super_admin: {
    role: 'super_admin',
    username: 'admin',
    displayName: 'Admin',
    roleLabel: '超级管理员',
    roleTagType: 'primary'
  },
  tenant_admin: {
    role: 'tenant_admin',
    username: 'yx_admin',
    displayName: '悦享管理员',
    roleLabel: '租户管理员',
    roleTagType: 'success'
  },
  operator: {
    role: 'operator',
    username: 'zhangsan',
    displayName: '张三',
    roleLabel: '业务员',
    roleTagType: 'warning'
  }
}

const USER_KEY = 'currentUser'

export function isLoggedIn() {
  return !!getToken() && localStorage.getItem('isLoggedIn') === '1'
}

export function getCurrentUser() {
  try {
    const raw = localStorage.getItem(USER_KEY)
    if (raw) return JSON.parse(raw)
  } catch {
    /* ignore */
  }
  return null
}

export function getCurrentRole() {
  return getCurrentUser()?.role || 'super_admin'
}

/**
 * 登录成功后写入会话
 * @param {{ token:string, user:object }} payload
 */
export function setLoginSession(payload) {
  const { token, user } = payload
  setToken(token)
  localStorage.setItem('isLoggedIn', '1')
  localStorage.setItem(USER_KEY, JSON.stringify(user || {}))
  localStorage.setItem('currentRole', user?.role || '')
  localStorage.setItem('currentUsername', user?.username || '')
}

/** 用 /auth/me 返回刷新本地用户缓存 */
export function updateCurrentUser(user) {
  localStorage.setItem(USER_KEY, JSON.stringify(user || {}))
  localStorage.setItem('currentRole', user?.role || '')
  localStorage.setItem('currentUsername', user?.username || '')
}

export function clearLoginSession() {
  setToken('')
  localStorage.removeItem('isLoggedIn')
  localStorage.removeItem(USER_KEY)
  localStorage.removeItem('currentRole')
  localStorage.removeItem('currentUsername')
}

export function hasPermission(path, role = getCurrentRole()) {
  const list = ROLE_PERMISSIONS[role] || []
  const pure = (path || '').split('?')[0].split('#')[0]
  return list.includes(pure)
}

export function canSeeResourceMenu(role = getCurrentRole()) {
  return (ROLE_PERMISSIONS[role] || []).some((p) => p.startsWith('/resource/'))
}

export function canSeeSystemMenu(role = getCurrentRole()) {
  return (ROLE_PERMISSIONS[role] || []).some((p) => p.startsWith('/system/'))
}

export function getDefaultHome(role = getCurrentRole()) {
  return (ROLE_PERMISSIONS[role] || [])[0] || '/dashboard'
}

export function getUsernameByRole(role) {
  return ROLE_ACCOUNTS[role]?.username || ''
}
