/**
 * API 模块聚合：与 Laravel routes/api.php 一一对应
 */
import http from '@/utils/request'

/* ========== 鉴权 ========== */
export const authApi = {
  login: (data) => http.post('/auth/login', data),
  logout: () => http.post('/auth/logout'),
  me: () => http.get('/auth/me')
}

/* ========== 仪表盘 ========== */
export const dashboardApi = {
  overview: () => http.get('/dashboard/overview'),
  refresh: () => http.post('/dashboard/refresh')
}

/* ========== 租户 ========== */
export const tenantApi = {
  stats: () => http.get('/tenants/stats'),
  list: (params) => http.get('/tenants', { params }),
  create: (data) => http.post('/tenants', data),
  update: (id, data) => http.put(`/tenants/${id}`, data),
  remove: (id) => http.delete(`/tenants/${id}`),
  toggle: (id, data) => http.post(`/tenants/${id}/toggle`, data),
  updatePackage: (id, data) => http.put(`/tenants/${id}/package`, data),
  export: (params) =>
    http.get('/tenants/export', { params, responseType: 'blob' })
}

/* ========== 套餐权限（超管） ========== */
export const packageSettingApi = {
  list: () => http.get('/package-setting/list'),
  save: (data) => http.post('/package-setting/save', data)
}

/* ========== AI配置 ========== */
export const aiConfigApi = {
  templates: (params) => http.get('/ai-config/templates', { params }),
  saveTemplate: (data) => http.post('/ai-config/templates', data),
  deleteTemplate: (id) => http.delete(`/ai-config/templates/${id}`),
  test: (data) => http.post('/ai-config/test', data),
  docs: (params) => http.get('/ai-config/docs', { params }),
  uploadDoc: (data) => http.post('/ai-config/docs', data),
  deleteDoc: (id) => http.delete(`/ai-config/docs/${id}`),

  /** 租户 AI 参数模板 */
  paramTemplateList: (tenantId) => http.get(`/tenant/${tenantId}/ai-param-template-list`),
  paramTemplateSave: (data) => http.post('/tenant/ai-param-template-save', data),
  paramTemplateSetDefault: (data) => http.post('/tenant/ai-param-template-set-default', data),
  paramTemplateDelete: (data) =>
    http.delete('/tenant/ai-param-template-del', { data }),
  promptList: (tenantId) => http.get(`/tenant/${tenantId}/prompt-list`),

  /** 租户列表弹窗：按套餐筛选模板 / 保存当前启用 / 租户信息 */
  templateListByPackage: (tenantId) =>
    http.get(`/tenant/${tenantId}/ai-template-list-by-package`),
  saveCurrentAiTemplate: (data) => http.post('/tenant/save-current-ai-template', data),
  tenantAiInfo: (tenantId) => http.get(`/tenant/${tenantId}/info`)
}

/* ========== 社媒账号 ========== */
export const socialAccountApi = {
  list: (params) => http.get('/social-accounts', { params }),
  /** 凭据自动登录绑定（推荐） */
  store: (data) => http.post('/social-account/store', data),
  /** 兼容旧路径 */
  create: (data) => http.post('/social-account/store', data),
  remove: (id) => http.delete(`/social-accounts/${id}`),
  refreshStatus: () => http.post('/social-accounts/refresh-status'),
  /** 租户空闲代理 IP */
  freeProxyIps: (tenantId) => http.get(`/tenant/free-proxy-ip/${tenantId}`),
  /** Cookie 会话检测 */
  checkLogin: (accountId) => http.get(`/social-account/check-login/${accountId}`),
  /** 操作日志 */
  logs: (id) => http.get(`/social-accounts/${id}/logs`),
  /** 小红书账号 AI 配置 */
  getAiConfig: (id) => http.get(`/social-account/${id}/ai-config`),
  saveAiConfig: (data) => http.post('/social-account/save-ai-config', data)
}

/* ========== 爬虫任务 ========== */
export const crawlerTaskApi = {
  list: (params) => http.get('/crawler-tasks', { params }),
  create: (data) => http.post('/crawler-tasks', data),
  toggle: (id) => http.post(`/crawler-tasks/${id}/toggle`),
  logs: (id) => http.get(`/crawler-tasks/${id}/logs`)
}

/* ========== 代理IP ========== */
export const proxyIpApi = {
  list: (params) => http.get('/proxy-ips', { params }),
  import: (data) => http.post('/proxy-ips/import', data),
  check: (id) => http.post(`/proxy-ips/${id}/check`),
  bindTenant: (id, data) => http.put(`/proxy-ips/${id}/bind-tenant`, data),
  remove: (id) => http.delete(`/proxy-ips/${id}`)
}

/* ========== CRM ========== */
export const crmLeadApi = {
  list: (params) => http.get('/crm-leads', { params }),
  detail: (id) => http.get(`/crm-leads/${id}`),
  tag: (id, data) => http.put(`/crm-leads/${id}/tag`, data),
  export: (params) =>
    http.get('/crm-leads/export', { params, responseType: 'blob' })
}

/* ========== 消息 ========== */
export const messageApi = {
  sessions: (params) => http.get('/messages/sessions', { params }),
  detail: (id) => http.get(`/messages/sessions/${id}`),
  send: (id, data) => http.post(`/messages/sessions/${id}/send`, data),
  updateSettings: (id, data) => http.put(`/messages/sessions/${id}/settings`, data)
}

/* ========== 系统设置 ========== */
export const settingApi = {
  getBasic: () => http.get('/settings/basic'),
  saveBasic: (data) => http.post('/settings/basic', data),
  getSecurity: () => http.get('/settings/security'),
  saveSecurity: (data) => http.post('/settings/security', data),
  users: (params) => http.get('/settings/users', { params }),
  createUser: (data) => http.post('/settings/users', data),
  updateUser: (id, data) => http.put(`/settings/users/${id}`, data),
  toggleUser: (id) => http.post(`/settings/users/${id}/toggle`)
}
