<template>
  <!-- 整体侧边栏布局：左侧菜单 + 右侧顶栏/内容 -->
  <el-container class="layout-container">
    <!-- ========== 左侧侧边栏 ========== -->
    <el-aside :width="isCollapse ? '64px' : '240px'" class="layout-aside">
      <!-- Logo 区域 -->
      <div class="logo-area">
        <el-icon :size="24" color="#409eff"><Monitor /></el-icon>
        <span v-show="!isCollapse" class="logo-text">SocialAI SaaS</span>
      </div>

      <!-- 菜单：按业务分组；角色 ∩ 套餐，可见即可得 -->
      <el-menu
        :key="menuScopeKey"
        :default-active="activeMenu"
        :collapse="isCollapse"
        :collapse-transition="false"
        background-color="#304156"
        text-color="#bfcbd9"
        active-text-color="#409eff"
        router
        class="sidebar-menu"
      >
        <!-- 1. 首页 -->
        <el-menu-item v-if="canAccess('/dashboard')" index="/dashboard">
          <el-icon><Odometer /></el-icon>
          <span>首页仪表盘</span>
        </el-menu-item>

        <!-- 2. 资源管理：采集账号 / 任务 / IP -->
        <el-sub-menu v-if="showResourceMenu" index="resource">
          <template #title>
            <el-icon><FolderOpened /></el-icon>
            <span>资源管理</span>
          </template>
          <el-menu-item v-if="canAccess('/resource/social-accounts')" index="/resource/social-accounts">
            社媒账号管理
          </el-menu-item>
          <el-menu-item v-if="canAccess('/resource/crawler-tasks')" index="/resource/crawler-tasks">
            爬虫任务管理
          </el-menu-item>
          <el-menu-item v-if="canAccess('/resource/comment-funnel')" index="/resource/comment-funnel">
            评论引流日志
          </el-menu-item>
          <el-menu-item v-if="canAccess('/system/crawler-behavior')" index="/system/crawler-behavior">
            爬虫真人行为
          </el-menu-item>
          <el-menu-item v-if="canAccess('/system/sensitive-words')" index="/system/sensitive-words">
            敏感词管理
          </el-menu-item>
          <el-menu-item v-if="canAccess('/resource/proxy-ip')" index="/resource/proxy-ip">
            代理IP管理
          </el-menu-item>
          <el-menu-item v-if="canAccess('/resource/ip-risk')" index="/resource/ip-risk">
            IP风险检测
          </el-menu-item>
        </el-sub-menu>

        <!-- 3. 客户运营：会话 / 线索 / 提醒 -->
        <el-sub-menu v-if="showCustomerMenu" index="customer">
          <template #title>
            <el-icon><ChatDotRound /></el-icon>
            <span>客户运营</span>
          </template>
          <el-menu-item v-if="canAccess('/system/messages')" index="/system/messages">
            消息会话管理
          </el-menu-item>
          <el-menu-item v-if="canAccess('/system/crm-leads')" index="/system/crm-leads">
            CRM客户线索
          </el-menu-item>
          <el-menu-item v-if="canAccess('/system/crm-reminders')" index="/system/crm-reminders">
            CRM跟进提醒
          </el-menu-item>
        </el-sub-menu>

        <!-- 4. AI 智能 -->
        <el-sub-menu v-if="showAiMenu" index="ai">
          <template #title>
            <el-icon><MagicStick /></el-icon>
            <span>AI智能</span>
          </template>
          <el-menu-item v-if="canAccess('/system/ai-config')" index="/system/ai-config">
            AI配置中心
          </el-menu-item>
          <el-menu-item v-if="canAccess('/system/industry-prompts')" index="/system/industry-prompts">
            行业Prompt商城
          </el-menu-item>
        </el-sub-menu>

        <!-- 5. 套餐中心（租户侧） -->
        <el-sub-menu v-if="showPackageMenu" index="package">
          <template #title>
            <el-icon><Goods /></el-icon>
            <span>套餐中心</span>
          </template>
          <el-menu-item v-if="canAccess('/system/package-purchase')" index="/system/package-purchase">
            套餐购买
          </el-menu-item>
          <el-menu-item v-if="canAccess('/system/sub-accounts')" index="/system/sub-accounts">
            子账号管理
          </el-menu-item>
        </el-sub-menu>

        <!-- 6. 平台运营（超管） -->
        <el-sub-menu v-if="showPlatformMenu" index="platform">
          <template #title>
            <el-icon><OfficeBuilding /></el-icon>
            <span>平台运营</span>
          </template>
          <el-menu-item v-if="canAccess('/system/tenants')" index="/system/tenants">
            租户管理
          </el-menu-item>
          <el-menu-item v-if="canAccess('/system/package-settings')" index="/system/package-settings">
            套餐权限管理
          </el-menu-item>
          <el-menu-item v-if="canAccess('/system/finance')" index="/system/finance">
            财务报表
          </el-menu-item>
        </el-sub-menu>

        <!-- 7. 系统设置（超管） -->
        <el-menu-item v-if="canAccess('/system/settings')" index="/system/settings">
          <el-icon><Setting /></el-icon>
          <span>系统设置</span>
        </el-menu-item>
      </el-menu>

      <!-- 底部版本号 -->
      <div v-show="!isCollapse" class="aside-footer">v1.0.0 Stable</div>
    </el-aside>

    <!-- ========== 右侧主区域 ========== -->
    <el-container class="layout-main">
      <!-- 顶部栏：折叠、面包屑、角色切换、搜索、刷新、头像 -->
      <el-header class="layout-header" height="60px">
        <div class="header-left">
          <el-icon class="collapse-btn" :size="20" @click="isCollapse = !isCollapse">
            <Fold v-if="!isCollapse" />
            <Expand v-else />
          </el-icon>
          <!-- 面包屑跟随当前路由自动切换 -->
          <el-breadcrumb separator="/">
            <el-breadcrumb-item v-if="route.meta.parent">{{ route.meta.parent }}</el-breadcrumb-item>
            <el-breadcrumb-item>{{ route.meta.title }}</el-breadcrumb-item>
          </el-breadcrumb>
          <el-tag v-if="route.meta.adminOnly" type="primary" size="small" effect="light" class="admin-tag">
            仅超级管理员可见
          </el-tag>
          <!-- 角色数据范围提示 -->
          <span v-if="currentUser?.context" class="role-context">{{ currentUser.context }}</span>
        </div>

        <div class="header-right">
          <!-- 角色切换：仅超级管理员可用（演示多角色） -->
          <div v-if="isSuperAdmin" class="role-switch">
            <span class="role-label">切换账号:</span>
            <el-select
              :model-value="currentRole"
              size="small"
              style="width: 140px"
              placeholder="选择角色类型"
              @change="onRoleSelect"
            >
              <el-option label="超级管理员" value="super_admin" />
              <el-option label="租户管理员" value="tenant_admin" />
              <el-option label="业务员子账号" value="operator" />
            </el-select>
          </div>

          <!-- 搜索 -->
          <el-tooltip content="全局搜索" placement="bottom">
            <el-icon class="header-icon" :size="18"><Search /></el-icon>
          </el-tooltip>

          <!-- 刷新 -->
          <el-tooltip content="刷新页面" placement="bottom">
            <el-icon class="header-icon" :size="18" @click="refreshPage"><Refresh /></el-icon>
          </el-tooltip>

          <!-- 用户头像：显示当前登录用户信息 -->
          <el-dropdown trigger="click" @command="onUserCommand">
            <div class="user-info">
              <el-avatar :size="32" src="https://cube.elemecdn.com/0/88/03b0d39583f48206768a7534e55bcpng.png" />
              <div class="user-meta">
                <span class="user-name">{{ currentUser?.displayName }}</span>
                <div class="user-sub">
                  <span class="user-account">{{ currentUser?.username }}</span>
                  <el-tag :type="currentUser?.roleTagType" size="small" effect="plain">
                    {{ currentUser?.roleLabel }}
                  </el-tag>
                </div>
              </div>
              <el-icon class="user-caret"><ArrowDown /></el-icon>
            </div>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item disabled>
                  账号：{{ currentUser?.username }}
                </el-dropdown-item>
                <el-dropdown-item v-if="currentUser?.tenant" disabled>
                  租户：{{ currentUser.tenant }}
                </el-dropdown-item>
                <el-dropdown-item command="profile">个人中心</el-dropdown-item>
                <el-dropdown-item command="logout" divided>退出登录</el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </div>
      </el-header>

      <!-- 内容滚动区：无权限不渲染页面（可见即可得） -->
      <el-main class="layout-content">
        <router-view v-if="pageAllowed" :key="`${viewKey}-${menuScopeKey}`" />
        <div v-else class="no-permission-placeholder">
          <el-empty description="当前账号无权访问该页面" />
        </div>
      </el-main>
    </el-container>

    <!-- 切换账号：支持输入任意用户名 + 密码 -->
    <el-dialog
      v-model="switchVisible"
      title="切换账号"
      width="440px"
      :close-on-click-modal="false"
      destroy-on-close
      @closed="onSwitchDialogClosed"
    >
      <el-alert
        type="info"
        :closable="false"
        show-icon
        class="switch-alert"
        :title="switchHint"
      />
      <el-form
        ref="switchFormRef"
        :model="switchForm"
        :rules="switchRules"
        label-width="88px"
        class="switch-form"
        @submit.prevent="confirmSwitchRole"
      >
        <el-form-item label="目标角色">
          <el-tag effect="plain">{{ pendingRoleLabel }}</el-tag>
        </el-form-item>
        <el-form-item label="用户名" prop="username">
          <el-input
            ref="usernameInputRef"
            v-model="switchForm.username"
            clearable
            placeholder="请输入要切换的用户名"
            @keyup.enter="focusPassword"
          />
        </el-form-item>
        <el-form-item label="登录密码" prop="password">
          <el-input
            ref="passwordInputRef"
            v-model="switchForm.password"
            type="password"
            show-password
            placeholder="请输入该账号的登录密码"
            @keyup.enter="confirmSwitchRole"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="cancelSwitchRole">取消</el-button>
        <el-button type="primary" :loading="switchLoading" @click="confirmSwitchRole">
          确认切换
        </el-button>
      </template>
    </el-dialog>
  </el-container>
</template>

<script setup>
/**
 * 主布局组件
 * - 菜单按角色权限过滤
 * - 切换角色需调登录 API 验证目标账号密码
 * - 无权限页面自动跳回角色默认首页
 */
import { ref, reactive, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { authApi } from '@/api'
import {
  ROLE_ACCOUNTS,
  getCurrentRole,
  getCurrentUser,
  setLoginSession,
  updateCurrentUser,
  clearLoginSession,
  hasPermission,
  canSeeMenuGroup,
  getDefaultHome,
  getVisibleMenus
} from '@/utils/auth'

const route = useRoute()
const router = useRouter()

const isCollapse = ref(false)
const viewKey = ref(0)

/** 当前角色与用户信息（来自 localStorage，/auth/me 可刷新） */
const currentRole = ref(getCurrentRole())
const currentUser = ref(getCurrentUser())
const isSuperAdmin = computed(() => currentRole.value === 'super_admin')

const activeMenu = computed(() => route.path)
/** 菜单作用域：角色 + 套餐变化时重建侧栏，避免残留无权限项 */
const menuScopeKey = computed(
  () => `${currentRole.value}:${currentUser.value?.package || 'basic'}:${currentUser.value?.id || 0}`
)
const showResourceMenu = computed(() =>
  canSeeMenuGroup('resource', currentRole.value, currentUser.value)
)
const showCustomerMenu = computed(() =>
  canSeeMenuGroup('customer', currentRole.value, currentUser.value)
)
const showAiMenu = computed(() =>
  canSeeMenuGroup('ai', currentRole.value, currentUser.value)
)
const showPackageMenu = computed(() =>
  canSeeMenuGroup('package', currentRole.value, currentUser.value)
)
const showPlatformMenu = computed(() =>
  canSeeMenuGroup('platform', currentRole.value, currentUser.value)
)
/** 用 Set 保证模板对权限列表的响应式依赖稳定 */
const allowedPathSet = computed(
  () => new Set(getVisibleMenus(currentRole.value, currentUser.value))
)
const canAccess = (path) => allowedPathSet.value.has(path)
/** 当前路由是否允许渲染（切换角色瞬间也能挡住无权限页） */
const pageAllowed = computed(() => {
  if (route.path === '/' || !route.path) return true
  return allowedPathSet.value.has(route.path)
})

/** 切换账号弹窗 */
const switchVisible = ref(false)
const switchLoading = ref(false)
const pendingRole = ref('')
const switchFormRef = ref(null)
const usernameInputRef = ref(null)
const passwordInputRef = ref(null)
const switchForm = reactive({ username: '', password: '' })
const switchRules = {
  username: [{ required: true, message: '请输入目标用户名', trigger: 'blur' }],
  password: [{ required: true, message: '请输入登录密码', trigger: 'blur' }]
}

const ROLE_LABELS = {
  super_admin: '超级管理员',
  tenant_admin: '租户管理员',
  operator: '业务员'
}

const pendingRoleLabel = computed(
  () => ROLE_LABELS[pendingRole.value] || pendingRole.value || '目标账号'
)

const switchHint = computed(() => {
  const preset = ROLE_ACCOUNTS[pendingRole.value]?.username
  const tip = preset ? `可改用户名，演示账号可填 ${preset}` : '请输入目标用户名与密码'
  return `切换为「${pendingRoleLabel.value}」：${tip}`
})

/** 从 localStorage 同步角色与用户信息到响应式状态 */
const syncUser = () => {
  currentRole.value = getCurrentRole()
  currentUser.value = getCurrentUser()
}

/**
 * 选择角色：弹出账号切换（用户名可编辑，预填演示账号）
 */
const onRoleSelect = (role) => {
  if (!isSuperAdmin.value) {
    ElMessage.warning('仅超级管理员可切换角色')
    return
  }
  pendingRole.value = role
  const preset = ROLE_ACCOUNTS[role]
  switchForm.username = preset?.username || ''
  switchForm.password = ''
  switchVisible.value = true
  nextTick(() => {
    usernameInputRef.value?.focus?.()
  })
}

/** 取消切换：关闭弹窗，下拉框因 :model-value 仍显示当前角色 */
const cancelSwitchRole = () => {
  switchVisible.value = false
  pendingRole.value = ''
  switchForm.username = ''
  switchForm.password = ''
}

const onSwitchDialogClosed = () => {
  switchForm.username = ''
  switchForm.password = ''
  pendingRole.value = ''
}

const focusPassword = () => {
  passwordInputRef.value?.focus?.()
}

/** 用输入的用户名 + 密码登录切换 */
const confirmSwitchRole = async () => {
  if (!switchFormRef.value || !pendingRole.value) return
  try {
    await switchFormRef.value.validate()
  } catch {
    return
  }

  switchLoading.value = true
  try {
    const username = switchForm.username.trim()
    const data = await authApi.switchRole({
      username,
      password: switchForm.password,
      role: pendingRole.value || undefined
    })
    if (data.roleMatched === false) {
      ElMessage.warning(
        `账号 ${username} 实际角色为「${data.user.roleLabel}」，与所选「${pendingRoleLabel.value}」不一致，已按实际角色进入`
      )
    }
    setLoginSession({ token: data.token, user: data.user })
    syncUser()
    switchVisible.value = false
    switchForm.username = ''
    switchForm.password = ''
    ElMessage.success(`已切换为 ${data.user.roleLabel}（账号：${data.user.username}）`)

    const role = data.user.role
    const user = data.user
    await router.replace(getDefaultHome(role, user))
    viewKey.value++
  } catch {
    // 错误提示由 request 拦截器统一处理
  } finally {
    switchLoading.value = false
  }
}

const refreshPage = () => {
  viewKey.value++
  ElMessage.success('页面已刷新')
}

const onUserCommand = (cmd) => {
  if (cmd === 'profile') {
    ElMessage.info(`当前账号：${currentUser.value?.username}（${currentUser.value?.roleLabel}）`)
    return
  }
  if (cmd === 'logout') {
    ElMessageBox.confirm('确认退出登录？', '提示', {
      type: 'warning',
      confirmButtonText: '退出',
      cancelButtonText: '取消'
    })
      .then(async () => {
        try {
          await authApi.logout()
        } catch {
          // 忽略登出接口错误，仍清除本地会话
        }
        clearLoginSession()
        ElMessage.success('已退出登录')
        router.push('/login')
      })
      .catch(() => {})
  }
}

/** 进入布局时尝试刷新用户信息；会话变更时同步菜单权限 */
const onAuthUserUpdated = () => {
  syncUser()
}

onMounted(async () => {
  window.addEventListener('auth-user-updated', onAuthUserUpdated)
  try {
    const user = await authApi.me()
    if (user) {
      updateCurrentUser(user)
      syncUser()
    }
  } catch {
    // Token 失效等情况由拦截器处理，此处静默忽略
  }
})

onUnmounted(() => {
  window.removeEventListener('auth-user-updated', onAuthUserUpdated)
})

syncUser()

watch(
  () => route.path,
  () => {
    syncUser()
  }
)
</script>

<style scoped>
.layout-container {
  height: 100vh;
  width: 100%;
}

.layout-aside {
  background-color: #304156;
  display: flex;
  flex-direction: column;
  transition: width 0.2s;
  overflow: hidden;
}

.logo-area {
  height: 60px;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 0 20px;
  border-bottom: 1px solid #1f2d3d;
  flex-shrink: 0;
}

.logo-text {
  color: #fff;
  font-weight: 700;
  font-size: 16px;
  letter-spacing: 0.5px;
  white-space: nowrap;
}

.sidebar-menu {
  border-right: none;
  flex: 1;
  min-height: 0;
  overflow-x: hidden;
  overflow-y: auto;
}

/* 系统管理子菜单项较多时保证可滚动可见 */
.sidebar-menu :deep(.el-menu) {
  background-color: #1f2d3d !important;
}
.sidebar-menu :deep(.el-sub-menu .el-menu-item) {
  min-width: 0;
}

.sidebar-menu:not(.el-menu--collapse) {
  width: 240px;
}

.aside-footer {
  padding: 16px;
  text-align: center;
  color: #bfcbd9;
  font-size: 12px;
  border-top: 1px solid #1f2d3d;
  flex-shrink: 0;
}

.layout-main {
  min-width: 0;
  background: #f0f2f5;
}

.layout-header {
  background: #fff;
  border-bottom: 1px solid #e6e6e6;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 20px;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 16px;
  min-width: 0;
  flex-wrap: wrap;
}

.collapse-btn {
  cursor: pointer;
  color: #606266;
}
.collapse-btn:hover {
  color: #409eff;
}

.admin-tag {
  margin-left: 4px;
}

.role-context {
  font-size: 13px;
  color: #409eff;
  white-space: nowrap;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 16px;
  flex-shrink: 0;
}

.role-switch {
  display: flex;
  align-items: center;
  gap: 8px;
  padding-right: 16px;
  border-right: 1px solid #ebeef5;
}

.role-label {
  font-size: 12px;
  color: #909399;
  white-space: nowrap;
}

.header-icon {
  cursor: pointer;
  color: #606266;
}
.header-icon:hover {
  color: #409eff;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  outline: none;
}

.user-meta {
  display: flex;
  flex-direction: column;
  line-height: 1.3;
}

.user-name {
  font-size: 14px;
  font-weight: 500;
  color: #303133;
}

.user-sub {
  display: flex;
  align-items: center;
  gap: 6px;
}

.user-account {
  font-size: 11px;
  color: #909399;
}

.user-caret {
  color: #c0c4cc;
  font-size: 12px;
}

.layout-content {
  padding: 20px;
  overflow-y: auto;
}

.no-permission-placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 360px;
}

.switch-alert {
  margin-bottom: 16px;
}

.switch-form {
  margin-top: 8px;
}
</style>
