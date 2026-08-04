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

      <!-- 菜单：按角色权限过滤 -->
      <el-menu
        :default-active="activeMenu"
        :collapse="isCollapse"
        :collapse-transition="false"
        background-color="#304156"
        text-color="#bfcbd9"
        active-text-color="#409eff"
        router
        class="sidebar-menu"
      >
        <!-- 1. 首页仪表盘 -->
        <el-menu-item v-if="canAccess('/dashboard')" index="/dashboard">
          <el-icon><Odometer /></el-icon>
          <span>首页仪表盘</span>
        </el-menu-item>

        <!-- 2. 资源管理 -->
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
          <el-menu-item v-if="canAccess('/resource/proxy-ip')" index="/resource/proxy-ip">
            代理IP管理
          </el-menu-item>
        </el-sub-menu>

        <!-- 3. 系统管理 -->
        <el-sub-menu v-if="showSystemMenu" index="system">
          <template #title>
            <el-icon><Setting /></el-icon>
            <span>系统管理</span>
          </template>
          <el-menu-item v-if="canAccess('/system/tenants')" index="/system/tenants">
            租户管理
          </el-menu-item>
          <el-menu-item v-if="canAccess('/system/ai-config')" index="/system/ai-config">
            AI配置中心
          </el-menu-item>
          <el-menu-item v-if="canAccess('/system/crm-leads')" index="/system/crm-leads">
            CRM客户线索
          </el-menu-item>
          <el-menu-item v-if="canAccess('/system/messages')" index="/system/messages">
            消息会话管理
          </el-menu-item>
          <el-menu-item v-if="canAccess('/system/settings')" index="/system/settings">
            系统设置
          </el-menu-item>
        </el-sub-menu>
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
          <!-- 角色切换：需输入目标账号密码后确认 -->
          <div class="role-switch">
            <span class="role-label">切换角色:</span>
            <el-select
              :model-value="currentRole"
              size="small"
              style="width: 140px"
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

      <!-- 内容滚动区 -->
      <el-main class="layout-content">
        <router-view :key="viewKey" />
      </el-main>
    </el-container>

    <!-- 切换角色密码确认弹窗 -->
    <el-dialog
      v-model="switchVisible"
      title="切换角色验证"
      width="420px"
      :close-on-click-modal="false"
      destroy-on-close
      @closed="onSwitchDialogClosed"
    >
      <el-alert
        type="info"
        :closable="false"
        show-icon
        class="switch-alert"
        :title="`即将切换为「${pendingAccount?.roleLabel || ''}」，请输入账号密码确认`"
      />
      <el-form
        ref="switchFormRef"
        :model="switchForm"
        :rules="switchRules"
        label-width="80px"
        class="switch-form"
        @submit.prevent="confirmSwitchRole"
      >
        <el-form-item label="目标账号">
          <el-input :model-value="pendingAccount?.username" disabled />
        </el-form-item>
        <el-form-item label="显示名称">
          <el-input :model-value="pendingAccount?.displayName" disabled />
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
import { ref, reactive, computed, watch, nextTick, onMounted } from 'vue'
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
  canSeeResourceMenu,
  canSeeSystemMenu,
  getDefaultHome
} from '@/utils/auth'

const route = useRoute()
const router = useRouter()

const isCollapse = ref(false)
const viewKey = ref(0)

/** 当前角色与用户信息（来自 localStorage，/auth/me 可刷新） */
const currentRole = ref(getCurrentRole())
const currentUser = ref(getCurrentUser())

const activeMenu = computed(() => route.path)
const showResourceMenu = computed(() => canSeeResourceMenu(currentRole.value))
const showSystemMenu = computed(() => canSeeSystemMenu(currentRole.value))
const canAccess = (path) => hasPermission(path, currentRole.value)

/** 切换角色密码弹窗 */
const switchVisible = ref(false)
const switchLoading = ref(false)
const pendingRole = ref('')
const pendingAccount = ref(null)
const switchFormRef = ref(null)
const passwordInputRef = ref(null)
const switchForm = reactive({ password: '' })
const switchRules = {
  password: [{ required: true, message: '请输入登录密码', trigger: 'blur' }]
}

/** 从 localStorage 同步角色与用户信息到响应式状态 */
const syncUser = () => {
  currentRole.value = getCurrentRole()
  currentUser.value = getCurrentUser()
}

/**
 * 选择角色：不立刻切换，弹出密码验证
 * 下拉框使用 :model-value 绑定，取消时保持原角色
 */
const onRoleSelect = (role) => {
  if (role === currentRole.value) return
  pendingRole.value = role
  pendingAccount.value = ROLE_ACCOUNTS[role]
  switchForm.password = ''
  switchVisible.value = true
  nextTick(() => {
    passwordInputRef.value?.focus?.()
  })
}

/** 取消切换：关闭弹窗，下拉框因 :model-value 仍显示当前角色 */
const cancelSwitchRole = () => {
  switchVisible.value = false
  pendingRole.value = ''
  pendingAccount.value = null
  switchForm.password = ''
}

const onSwitchDialogClosed = () => {
  switchForm.password = ''
  pendingRole.value = ''
  pendingAccount.value = null
}

/** 调登录 API 验证密码后切换角色 */
const confirmSwitchRole = async () => {
  if (!switchFormRef.value || !pendingRole.value || !pendingAccount.value) return
  try {
    await switchFormRef.value.validate()
  } catch {
    return
  }

  switchLoading.value = true
  try {
    const username = pendingAccount.value.username
    const data = await authApi.login({ username, password: switchForm.password })
    setLoginSession({ token: data.token, user: data.user })
    syncUser()
    switchVisible.value = false
    switchForm.password = ''
    ElMessage.success(`已切换为 ${data.user.roleLabel}（账号：${data.user.username}）`)

    const role = data.user.role
    if (!hasPermission(route.path, role)) {
      await router.replace(getDefaultHome(role))
    }
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

/** 进入布局时尝试刷新用户信息 */
onMounted(async () => {
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
  overflow-y: auto;
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

.switch-alert {
  margin-bottom: 16px;
}

.switch-form {
  margin-top: 8px;
}
</style>
