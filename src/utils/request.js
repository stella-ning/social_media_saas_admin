/**
 * 统一 Axios 请求封装
 * - baseURL: /api（开发环境由 Vite 代理到 Laravel）
 * - 统一解析 { code, msg, data }，code!==200 弹错误
 * - 自动携带 Bearer Token；401 跳转登录
 */
import axios from 'axios'
import { ElMessage } from 'element-plus'
import router from '@/router'

const TOKEN_KEY = 'access_token'

/** 读取 / 写入 Token */
export function getToken() {
  return localStorage.getItem(TOKEN_KEY) || ''
}

export function setToken(token) {
  if (token) localStorage.setItem(TOKEN_KEY, token)
  else localStorage.removeItem(TOKEN_KEY)
}

const http = axios.create({
  baseURL: '/api',
  timeout: 30000,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json'
  }
})

/** 请求拦截：附加 Authorization */
http.interceptors.request.use(
  (config) => {
    const token = getToken()
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => Promise.reject(error)
)

/**
 * 响应拦截：
 * - 业务 code===200 → 返回 data
 * - 其它 code → ElMessage 提示并 reject
 * - 网络/HTTP 异常统一友好提示，避免控制台裸报错干扰用户
 */
http.interceptors.response.use(
  (response) => {
    // 文件流（导出）直接返回
    if (response.config.responseType === 'blob') {
      return response
    }

    const res = response.data
    if (res && typeof res.code !== 'undefined') {
      if (res.code === 200) {
        return res.data
      }
      ElMessage.error(res.msg || '请求失败')
      if (res.code === 401) {
        setToken('')
        localStorage.removeItem('isLoggedIn')
        localStorage.removeItem('currentUser')
        router.replace({ path: '/login', query: { redirect: router.currentRoute.value.fullPath } })
      }
      return Promise.reject(new Error(res.msg || '请求失败'))
    }
    return res
  },
  (error) => {
    let msg = '网络异常，请稍后重试'
    if (error.response) {
      const status = error.response.status
      const data = error.response.data
      msg = data?.msg || data?.message || `请求错误(${status})`
      if (status === 401) {
        setToken('')
        localStorage.removeItem('isLoggedIn')
        localStorage.removeItem('currentUser')
        router.replace({ path: '/login' })
        msg = data?.msg || '登录已失效，请重新登录'
      } else if (status === 403) {
        msg = data?.msg || '无权访问'
      } else if (status === 422) {
        // Laravel 校验错误
        const errors = data?.data || data?.errors
        if (errors && typeof errors === 'object') {
          const first = Object.values(errors).flat()?.[0]
          msg = first || data?.msg || '参数校验失败'
        } else {
          msg = data?.msg || '参数校验失败'
        }
      }
    } else if (error.message?.includes('timeout')) {
      msg = '请求超时，请稍后重试'
    }
    ElMessage.error(msg)
    return Promise.reject(error)
  }
)

export default http
