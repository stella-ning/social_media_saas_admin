/**
 * API 模块聚合：与 Laravel routes/api.php 一一对应
 */
import http from '@/utils/request'

/* ========== 鉴权 ========== */
export const authApi = {
  login: (data) => http.post('/auth/login', data),
  logout: () => http.post('/auth/logout'),
  me: () => http.get('/auth/me'),
  /** 超管切换账号：username + password + 可选 role */
  switchRole: (data) => http.post('/auth/switch-role', data)
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
  save: (data) => http.post('/package-setting/save', data),
  tenantQuota: (tenantId) => http.get(`/package-setting/tenant-quota/${tenantId}`)
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
  logs: (id) => http.get(`/crawler-tasks/${id}/logs`),
  /** 可执行社媒账号（状态正常 + 套餐平台；IP 可启动时自动分配） */
  executableAccounts: (tenantId) =>
    http.get('/crawler-tasks/executable-accounts', { params: { tenant_id: tenantId } }),
  /** Worker：评论采集回调 → 咨询留言进会话 */
  collectCallback: (id, data) => http.post(`/crawler-tasks/${id}/collect-callback`, data),
  /** 演示：模拟采集同行评论区并接入消息会话 */
  simulateCollect: (id) => http.post(`/crawler-tasks/${id}/simulate-collect`)
}

/* ========== 评论引流漏斗 / 敏感词 ========== */
export const commentFunnelApi = {
  records: (params) => http.get('/comment-funnel/records', { params }),
  stats: (params) => http.get('/comment-funnel/stats', { params }),
  blacklist: (params) => http.get('/comment-funnel/blacklist', { params })
}

export const sensitiveWordApi = {
  list: (params) => http.get('/sensitive-words', { params }),
  save: (data) => http.post('/sensitive-words', data),
  remove: (id) => http.delete(`/sensitive-words/${id}`)
}

/* ========== 代理IP（平台公共池） ========== */
export const proxyIpApi = {
  list: (params) => http.get('/proxy-ips', { params }),
  allocated: (params) => http.get('/proxy-ips/allocated', { params }),
  import: (data) => http.post('/proxy-ips/import', data),
  check: (id) => http.post(`/proxy-ips/${id}/check`),
  batchRiskCheck: (data) => http.post('/proxy-ips/batch-risk-check', data),
  accessLogs: (id) => http.get(`/proxy-ips/${id}/access-logs`),
  bindTenant: (id, data) => http.put(`/proxy-ips/${id}/bind-tenant`, data),
  remove: (id) => http.delete(`/proxy-ips/${id}`),
  tenantQuota: (tenantId) => http.get(`/proxy-ips/tenant-quota/${tenantId}`)
}

/* ========== 财务 / 套餐购买 ========== */
export const financeApi = {
  catalog: () => http.get('/finance/catalog'),
  purchase: (data) => http.post('/finance/purchase', data),
  orders: (params) => http.get('/finance/orders', { params }),
  overview: (params) => http.get('/finance/overview', { params }),
  consume: (params) => http.get('/finance/consume', { params }),
  premiumLogs: (params) => http.get('/finance/premium-logs', { params }),
  exportConsume: (params) =>
    http.get('/finance/export-consume', { params, responseType: 'blob' })
}

/* ========== 租户增值功能 ========== */
export const premiumApi = {
  subAccounts: (params) => http.get('/premium/sub-accounts', { params }),
  saveSubAccount: (data) => http.post('/premium/sub-accounts', data),
  deleteSubAccount: (id, params) => http.delete(`/premium/sub-accounts/${id}`, { params }),
  industryPrompts: (params) => http.get('/premium/industry-prompts', { params }),
  humanBehavior: (params) => http.get('/premium/human-behavior', { params }),
  saveHumanBehavior: (data) => http.post('/premium/human-behavior', data),
  crmReminders: (params) => http.get('/premium/crm-reminders', { params }),
  saveCrmReminder: (data) => http.post('/premium/crm-reminders', data),
  completeCrmReminder: (id, params) =>
    http.post(`/premium/crm-reminders/${id}/complete`, null, { params }),
  updateIpFlags: (data) => http.put('/premium/ip-flags', data)
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
  updateSettings: (id, data) => http.put(`/messages/sessions/${id}/settings`, data),
  pushCrm: (id) => http.post(`/messages/sessions/${id}/push-crm`),
  ingest: (data) => http.post('/messages/ingest', data),
  quickReplies: (params) => http.get('/messages/quick-replies', { params }),
  saveQuickReply: (data) => http.post('/messages/quick-replies', data),
  deleteQuickReply: (id) => http.delete(`/messages/quick-replies/${id}`),
  alertLogs: (params) => http.get('/messages/alert-logs', { params })
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
