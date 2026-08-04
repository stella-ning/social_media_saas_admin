<template>
  <div class="login-page">
    <!-- 主登录卡片：左展示 + 右表单 -->
    <div class="login-card">
      <!-- 左侧展示区 -->
      <div class="login-brand">
        <div class="brand-content">
          <div class="brand-logo">
            <div class="logo-icon">
              <el-icon :size="24" color="#fff"><Monitor /></el-icon>
            </div>
            <span class="logo-text">SocialAI SaaS</span>
          </div>
          <h1 class="brand-title">全渠道社媒<br />AI自动化管理平台</h1>
          <p class="brand-desc">
            集成内容采集、AI生成、自动回复与CRM转化，为企业提供一站式智能营销解决方案。
          </p>
        </div>
        <div class="brand-footer">© 2026 SocialAI Technology Co., Ltd.</div>
        <div class="decor decor-1"></div>
        <div class="decor decor-2"></div>
      </div>

      <!-- 右侧登录区 -->
      <div class="login-form-wrap">
        <div class="form-header">
          <h2>欢迎登录</h2>
          <p>社媒AI自动化获客SaaS系统</p>
        </div>

        <el-form
          ref="formRef"
          :model="form"
          :rules="rules"
          size="large"
          @submit.prevent="handleLogin"
        >
          <el-form-item prop="username">
            <el-input
              v-model="form.username"
              placeholder="请输入管理员账号"
              :prefix-icon="User"
              clearable
            />
          </el-form-item>
          <el-form-item prop="password">
            <el-input
              v-model="form.password"
              type="password"
              placeholder="请输入登录密码"
              :prefix-icon="Lock"
              show-password
              @keyup.enter="handleLogin"
            />
          </el-form-item>
          <el-form-item>
            <div class="form-extra">
              <el-checkbox v-model="form.remember">记住账号</el-checkbox>
              <el-button link type="primary">忘记密码？</el-button>
            </div>
          </el-form-item>
          <el-button
            type="primary"
            class="login-btn"
            :loading="loading"
            native-type="submit"
            @click="handleLogin"
          >
            立即登录
          </el-button>
        </el-form>

        <div class="other-login">
          <p>其他登录方式</p>
          <div class="other-icons">
            <el-tooltip content="微信（演示）">
              <button type="button" class="social-btn" @click="tipSocial('微信')">
                <el-icon :size="18"><ChatDotRound /></el-icon>
              </button>
            </el-tooltip>
            <el-tooltip content="钉钉（演示）">
              <button type="button" class="social-btn" @click="tipSocial('钉钉')">
                <el-icon :size="18"><Bell /></el-icon>
              </button>
            </el-tooltip>
          </div>
        </div>
      </div>
    </div>

    <!-- 角色切换演示：快捷登录（需输入密码） -->
    <div class="quick-login">
      <div class="quick-divider">
        <span>切换角色演示 (快捷登录)</span>
      </div>
      <el-row :gutter="20">
        <el-col :xs="24" :sm="8" v-for="role in roleCards" :key="role.value">
          <div class="role-card" @click="openQuickLogin(role.value)">
            <div class="role-head">
              <div class="role-icon" :style="{ background: role.bg, color: role.color }">
                <el-icon :size="20"><component :is="role.icon" /></el-icon>
              </div>
              <div>
                <h3>{{ role.title }}</h3>
                <span class="role-account">{{ role.account }}</span>
              </div>
            </div>
            <p>{{ role.desc }}</p>
          </div>
        </el-col>
      </el-row>
    </div>

    <!-- 快捷角色登录密码确认 -->
    <el-dialog
      v-model="quickVisible"
      title="角色快捷登录"
      width="420px"
      :close-on-click-modal="false"
      destroy-on-close
      @closed="onQuickClosed"
    >
      <el-alert
        type="info"
        :closable="false"
        show-icon
        class="quick-alert"
        :title="`以「${quickAccount?.roleLabel || ''}」身份登录，请输入账号密码`"
      />
      <el-form
        ref="quickFormRef"
        :model="quickForm"
        :rules="quickRules"
        label-width="80px"
        class="quick-form"
        @submit.prevent="confirmQuickLogin"
      >
        <el-form-item label="登录账号">
          <el-input :model-value="quickAccount?.username" disabled />
        </el-form-item>
        <el-form-item label="显示名称">
          <el-input :model-value="quickAccount?.displayName" disabled />
        </el-form-item>
        <el-form-item label="登录密码" prop="password">
          <el-input
            ref="quickPwdRef"
            v-model="quickForm.password"
            type="password"
            show-password
            placeholder="请输入该账号的登录密码"
            @keyup.enter="confirmQuickLogin"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="quickVisible = false">取消</el-button>
        <el-button type="primary" :loading="quickLoading" @click="confirmQuickLogin">
          确认登录
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
/**
 * 登录页
 * - 账号密码登录（调用 Laravel /auth/login）
 * - 底部三角色快捷登录（需输入对应账号密码）
 */
import { ref, reactive, onMounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { User, Lock } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { authApi } from '@/api'
import {
  ROLE_ACCOUNTS,
  setLoginSession,
  getDefaultHome,
  hasPermission
} from '@/utils/auth'

const router = useRouter()
const formRef = ref(null)
const loading = ref(false)

const form = reactive({
  username: '',
  password: '',
  remember: true
})

const rules = {
  username: [{ required: true, message: '请输入账号', trigger: 'blur' }],
  password: [{ required: true, message: '请输入密码', trigger: 'blur' }]
}

/** 三角色快捷登录卡片（展示 ROLE_ACCOUNTS 对应账号） */
const roleCards = [
  {
    value: 'super_admin',
    title: '超级管理员',
    account: ROLE_ACCOUNTS.super_admin.username,
    icon: 'Avatar',
    bg: '#ecf5ff',
    color: '#409eff',
    desc: `账号 ${ROLE_ACCOUNTS.super_admin.username} · 管理租户、套餐、IP池及系统全局配置。`
  },
  {
    value: 'tenant_admin',
    title: '租户管理员',
    account: ROLE_ACCOUNTS.tenant_admin.username,
    icon: 'UserFilled',
    bg: '#f0f9eb',
    color: '#67c23a',
    desc: `账号 ${ROLE_ACCOUNTS.tenant_admin.username} · 管理社媒账号、爬虫任务、AI模板及知识库。`
  },
  {
    value: 'operator',
    title: '业务员子账号',
    account: ROLE_ACCOUNTS.operator.username,
    icon: 'Headset',
    bg: '#fdf6ec',
    color: '#e6a23c',
    desc: `账号 ${ROLE_ACCOUNTS.operator.username} · 专注 CRM 线索跟进与消息会话处理。`
  }
]

/** 快捷登录密码弹窗 */
const quickVisible = ref(false)
const quickLoading = ref(false)
const quickRole = ref('')
const quickAccount = ref(null)
const quickFormRef = ref(null)
const quickPwdRef = ref(null)
const quickForm = reactive({ password: '' })
const quickRules = {
  password: [{ required: true, message: '请输入登录密码', trigger: 'blur' }]
}

onMounted(() => {
  const remembered = localStorage.getItem('rememberedUsername')
  if (remembered) {
    form.username = remembered
    form.remember = true
  }
})

/** 记住账号写入 localStorage */
const persistRememberUsername = (username) => {
  if (form.remember && username) {
    localStorage.setItem('rememberedUsername', username)
  } else if (!form.remember) {
    localStorage.removeItem('rememberedUsername')
  }
}

/** 登录成功后按权限跳转 */
const redirectAfterLogin = (user) => {
  const role = user.role
  const redirect = router.currentRoute.value.query.redirect
  let target = getDefaultHome(role)
  if (typeof redirect === 'string' && redirect.startsWith('/') && hasPermission(redirect, role)) {
    target = redirect
  }
  router.push(target)
}

/** 主表单登录 */
const handleLogin = async () => {
  if (!formRef.value) return
  try {
    await formRef.value.validate()
  } catch {
    return
  }

  loading.value = true
  try {
    const username = form.username.trim()
    const data = await authApi.login({ username, password: form.password })
    setLoginSession({ token: data.token, user: data.user })
    persistRememberUsername(username)
    form.password = ''
    ElMessage.success(`登录成功：${data.user.displayName}（${data.user.username}）`)
    redirectAfterLogin(data.user)
  } catch {
    // 错误提示由 request 拦截器统一处理
  } finally {
    loading.value = false
  }
}

/** 点击角色卡片：弹出密码确认，不直接登录 */
const openQuickLogin = (role) => {
  quickRole.value = role
  quickAccount.value = ROLE_ACCOUNTS[role]
  quickForm.password = ''
  form.username = ROLE_ACCOUNTS[role].username
  quickVisible.value = true
  nextTick(() => {
    quickPwdRef.value?.focus?.()
  })
}

const onQuickClosed = () => {
  quickForm.password = ''
  quickRole.value = ''
  quickAccount.value = null
}

/** 快捷角色登录：调 API 校验密码后进入系统 */
const confirmQuickLogin = async () => {
  if (!quickFormRef.value || !quickRole.value || !quickAccount.value) return
  try {
    await quickFormRef.value.validate()
  } catch {
    return
  }

  quickLoading.value = true
  try {
    const username = quickAccount.value.username
    const data = await authApi.login({ username, password: quickForm.password })
    setLoginSession({ token: data.token, user: data.user })
    persistRememberUsername(username)
    form.password = ''
    quickForm.password = ''
    quickVisible.value = false
    ElMessage.success(`登录成功：${data.user.displayName}（${data.user.username}）`)
    redirectAfterLogin(data.user)
  } catch {
    // 错误提示由 request 拦截器统一处理
  } finally {
    quickLoading.value = false
  }
}

const tipSocial = (name) => {
  ElMessage.info(`${name}登录为演示功能`)
}
</script>

<style scoped>
.login-page {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 32px 16px;
  background: #f0f2f5;
}

.login-card {
  display: flex;
  width: 100%;
  max-width: 900px;
  height: 550px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);
  overflow: hidden;
  margin-bottom: 32px;
}

/* ----- 左侧品牌区 ----- */
.login-brand {
  width: 50%;
  background: #304156;
  padding: 48px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  color: #fff;
  position: relative;
  overflow: hidden;
}

.brand-content {
  position: relative;
  z-index: 1;
}

.brand-logo {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 32px;
}

.logo-icon {
  width: 40px;
  height: 40px;
  background: #409eff;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.logo-text {
  font-size: 20px;
  font-weight: 700;
  letter-spacing: 1px;
}

.brand-title {
  font-size: 28px;
  font-weight: 700;
  line-height: 1.35;
  margin: 0 0 16px;
}

.brand-desc {
  font-size: 13px;
  color: #a0aab8;
  line-height: 1.7;
  margin: 0;
}

.brand-footer {
  position: relative;
  z-index: 1;
  font-size: 12px;
  color: #6b7785;
}

.decor {
  position: absolute;
  border-radius: 50%;
  background: #409eff;
  filter: blur(40px);
  pointer-events: none;
}
.decor-1 {
  width: 256px;
  height: 256px;
  top: -10%;
  right: -10%;
  opacity: 0.12;
}
.decor-2 {
  width: 128px;
  height: 128px;
  bottom: 5%;
  left: 5%;
  opacity: 0.06;
}

/* ----- 右侧表单 ----- */
.login-form-wrap {
  width: 50%;
  padding: 48px;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.form-header {
  text-align: center;
  margin-bottom: 36px;
}
.form-header h2 {
  margin: 0;
  font-size: 24px;
  font-weight: 700;
  color: #303133;
}
.form-header p {
  margin: 8px 0 0;
  font-size: 13px;
  color: #909399;
}

.form-extra {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.login-btn {
  width: 100%;
  height: 44px;
  font-size: 15px;
  font-weight: 500;
  box-shadow: 0 6px 16px rgba(64, 158, 255, 0.35);
}

.other-login {
  margin-top: 28px;
  text-align: center;
}
.other-login p {
  font-size: 12px;
  color: #c0c4cc;
  margin: 0 0 12px;
}

.other-icons {
  display: flex;
  justify-content: center;
  gap: 16px;
}

.social-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: 1px solid #e4e7ed;
  background: #fff;
  color: #909399;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s;
}
.social-btn:hover {
  background: #f5f7fa;
  color: #409eff;
  border-color: #c6e2ff;
}

/* ----- 快捷角色登录 ----- */
.quick-login {
  width: 100%;
  max-width: 900px;
}

.quick-divider {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 16px;
}
.quick-divider::before,
.quick-divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: #dcdfe6;
}
.quick-divider span {
  font-size: 13px;
  color: #909399;
  font-weight: 500;
  white-space: nowrap;
}

.role-card {
  background: #fff;
  padding: 20px;
  border-radius: 8px;
  border: 1px solid #e4e7ed;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  cursor: pointer;
  transition: all 0.2s;
  margin-bottom: 12px;
  height: 100%;
}
.role-card:hover {
  border-color: #409eff;
  background: #f5f7fa;
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(64, 158, 255, 0.12);
}

.role-head {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 10px;
}

.role-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.role-head h3 {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
  color: #303133;
}

.role-account {
  font-size: 11px;
  color: #909399;
  font-family: 'SF Mono', Monaco, Menlo, Consolas, monospace;
}

.role-card p {
  margin: 0;
  font-size: 12px;
  color: #909399;
  line-height: 1.6;
}

.quick-alert {
  margin-bottom: 16px;
}

.quick-form {
  margin-top: 8px;
}

/* 移动端适配 */
@media (max-width: 768px) {
  .login-card {
    flex-direction: column;
    height: auto;
  }
  .login-brand,
  .login-form-wrap {
    width: 100%;
  }
  .login-brand {
    padding: 32px 24px;
    min-height: 220px;
  }
  .brand-title {
    font-size: 22px;
  }
  .login-form-wrap {
    padding: 32px 24px;
  }
}
</style>
