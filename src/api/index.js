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

/* ========== 社媒账号 ========== */
export const socialAccountApi = {
  list: (params) => http.get('/social-accounts', { params }),
  create: (data) => http.post('/social-accounts', data),
  remove: (id) => http.delete(`/social-accounts/${id}`),
  refreshStatus: () => http.post('/social-accounts/refresh-status')
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

/* ========== AI配置 ========== */
export const aiConfigApi = {
  templates: (params) => http.get('/ai-config/templates', { params }),
  saveTemplate: (data) => http.post('/ai-config/templates', data),
  test: (data) => http.post('/ai-config/test', data),
  docs: (params) => http.get('/ai-config/docs', { params }),
  uploadDoc: (data) => http.post('/ai-config/docs', data),
  deleteDoc: (id) => http.delete(`/ai-config/docs/${id}`)
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
