/**
 * 认证与权限工具（对接 Laravel Sanctum）
 * - Token / 用户信息存 localStorage
 * - 菜单：可见即可得（角色权限 ∩ 套餐权益）
 */
import { setToken, getToken } from '@/utils/request'

/** 角色可访问的路由 path（再与套餐权益取交集） */
export const ROLE_PERMISSIONS = {
  super_admin: [
    '/dashboard',
    '/resource/social-accounts',
    '/resource/crawler-tasks',
    '/resource/proxy-ip',
    '/resource/ip-risk',
    '/resource/comment-funnel',
    '/system/tenants',
    '/system/package-settings',
    '/system/finance',
    '/system/ai-config',
    '/system/crm-leads',
    '/system/messages',
    '/system/settings',
    '/system/package-purchase',
    '/system/sub-accounts',
    '/system/industry-prompts',
    '/system/crawler-behavior',
    '/system/crm-reminders',
    '/system/sensitive-words'
  ],
  tenant_admin: [
    '/dashboard',
    '/resource/social-accounts',
    '/resource/crawler-tasks',
    '/resource/proxy-ip',
    '/resource/ip-risk',
    '/resource/comment-funnel',
    '/system/ai-config',
    '/system/crm-leads',
    '/system/messages',
    '/system/package-purchase',
    '/system/sub-accounts',
    '/system/industry-prompts',
    '/system/crawler-behavior',
    '/system/crm-reminders',
    '/system/sensitive-words'
  ],
  operator: ['/dashboard', '/system/crm-leads', '/system/messages']
}

/**
 * 套餐增值菜单门槛（与后端 PackageSetting 开关对齐）
 * 超管不受限；租户/业务员按当前租户 package 过滤
 */
export const PACKAGE_MENU_GATES = {
  '/resource/ip-risk': ['pro', 'ent'],
  '/system/crawler-behavior': ['pro', 'ent'],
  '/system/crm-reminders': ['pro', 'ent'],
  '/system/sub-accounts': ['ent']
}

/** 快捷登录角色 → 演示账号 */
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
  const role = getCurrentUser()?.role
  if (role && ROLE_PERMISSIONS[role]) return role
  // 未登录或脏数据时不要默认升权成超管
  return role || 'operator'
}

export function setLoginSession(payload) {
  const { token, user } = payload
  setToken(token)
  localStorage.setItem('isLoggedIn', '1')
  localStorage.setItem(USER_KEY, JSON.stringify(user || {}))
  localStorage.setItem('currentRole', user?.role || '')
  localStorage.setItem('currentUsername', user?.username || '')
  notifyAuthUserUpdated(user)
  // 角色切换后清租户作用域缓存，避免超管列表串数据
  try {
    import('@/composables/useTenantScope').then((m) => m.invalidateTenantScopeCache?.())
  } catch {
    /* ignore */
  }
}

export function updateCurrentUser(user) {
  localStorage.setItem(USER_KEY, JSON.stringify(user || {}))
  localStorage.setItem('currentRole', user?.role || '')
  localStorage.setItem('currentUsername', user?.username || '')
  notifyAuthUserUpdated(user)
}

function notifyAuthUserUpdated(user) {
  if (typeof window === 'undefined') return
  window.dispatchEvent(new CustomEvent('auth-user-updated', { detail: user || null }))
}

export function clearLoginSession() {
  setToken('')
  localStorage.removeItem('isLoggedIn')
  localStorage.removeItem(USER_KEY)
  localStorage.removeItem('currentRole')
  localStorage.removeItem('currentUsername')
}

export function normalizePath(path = '') {
  return path.split('?')[0].split('#')[0]
}

/** 仅角色路由白名单 */
export function hasRolePermission(path, role = getCurrentRole()) {
  const list = ROLE_PERMISSIONS[role] || []
  return list.includes(normalizePath(path))
}

/** 套餐是否允许该菜单（超管放行） */
export function hasPackagePermission(path, user = getCurrentUser()) {
  const pure = normalizePath(path)
  const gate = PACKAGE_MENU_GATES[pure]
  if (!gate) return true
  if (!user || user.role === 'super_admin') return true
  const pkg = user.package || 'basic'
  return gate.includes(pkg)
}

/**
 * 菜单/路由最终权限：角色 ∩ 套餐
 * 可见即可得
 */
export function hasPermission(path, role = getCurrentRole(), user = getCurrentUser()) {
  if (!hasRolePermission(path, role)) return false
  return hasPackagePermission(path, user || { role, package: 'basic' })
}

/** 当前角色可见菜单列表（已套餐过滤） */
export function getVisibleMenus(role = getCurrentRole(), user = getCurrentUser()) {
  return (ROLE_PERMISSIONS[role] || []).filter((p) => hasPermission(p, role, user))
}

/**
 * 侧栏菜单分组（路径仍保持 /system|/resource，仅分类展示）
 * - resource  采集资源
 * - customer  客户运营
 * - ai        AI 智能
 * - package   套餐中心
 * - platform  平台运营（超管）
 * - settings  系统设置（超管）
 */
export const MENU_GROUPS = {
  resource: [
    '/resource/social-accounts',
    '/resource/crawler-tasks',
    '/resource/proxy-ip',
    '/resource/ip-risk',
    '/resource/comment-funnel',
    '/system/crawler-behavior',
    '/system/sensitive-words'
  ],
  customer: ['/system/messages', '/system/crm-leads', '/system/crm-reminders'],
  ai: ['/system/ai-config', '/system/industry-prompts'],
  package: ['/system/package-purchase', '/system/sub-accounts'],
  platform: ['/system/tenants', '/system/package-settings', '/system/finance'],
  settings: ['/system/settings']
}

export function canSeeMenuGroup(groupKey, role = getCurrentRole(), user = getCurrentUser()) {
  const paths = MENU_GROUPS[groupKey] || []
  return paths.some((p) => hasPermission(p, role, user))
}

export function canSeeResourceMenu(role = getCurrentRole(), user = getCurrentUser()) {
  return canSeeMenuGroup('resource', role, user)
}

/** @deprecated 旧「系统管理」大组，拆分后请用 canSeeMenuGroup */
export function canSeeSystemMenu(role = getCurrentRole(), user = getCurrentUser()) {
  return ['customer', 'ai', 'package', 'platform', 'settings'].some((g) =>
    canSeeMenuGroup(g, role, user)
  )
}

export function getDefaultHome(role = getCurrentRole(), user = getCurrentUser()) {
  const menus = getVisibleMenus(role, user)
  return menus[0] || '/dashboard'
}

export function getUsernameByRole(role) {
  return ROLE_ACCOUNTS[role]?.username || ''
}
