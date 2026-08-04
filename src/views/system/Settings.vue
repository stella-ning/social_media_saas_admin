<template>
  <div class="settings-page">
    <el-card shadow="hover" class="settings-card">
      <el-tabs v-model="activeTab" @tab-change="onTabChange">
        <!-- ========== 基本设置 ========== -->
        <el-tab-pane label="基本设置" name="basic">
          <el-form v-loading="basicLoading" :model="basicForm" label-width="120px" class="basic-form">
            <el-form-item label="系统名称">
              <el-input v-model="basicForm.name" style="max-width: 420px" />
            </el-form-item>
            <el-form-item label="系统LOGO">
              <div class="logo-row">
                <div class="logo-preview">
                  <el-icon v-if="!basicForm.logo" :size="32" color="#909399"><Monitor /></el-icon>
                  <img v-else :src="basicForm.logo" alt="logo" class="logo-img" />
                </div>
                <el-upload action="#" :auto-upload="false" :show-file-list="false" accept="image/*">
                  <el-button size="small">更换图片</el-button>
                </el-upload>
              </div>
            </el-form-item>
            <el-form-item label="默认备案信息">
              <el-input v-model="basicForm.copyright" style="max-width: 520px" />
            </el-form-item>
            <el-form-item label="系统通知">
              <el-switch v-model="basicForm.notify" active-text="开启" inactive-text="关闭" />
            </el-form-item>
            <el-form-item label="当前版本">
              <el-tag type="success">v1.0.0 Stable</el-tag>
            </el-form-item>
            <el-form-item>
              <el-button type="primary" :loading="basicSaving" @click="saveBasic">保存设置</el-button>
            </el-form-item>
          </el-form>
        </el-tab-pane>

        <!-- ========== 用户管理 ========== -->
        <el-tab-pane label="用户管理" name="user">
          <div class="user-toolbar">
            <h4 class="section-title">系统用户列表</h4>
            <el-button type="primary" :icon="UserFilled" @click="openAddUser">新增用户</el-button>
          </div>
          <el-table v-loading="loading" :data="list" border stripe style="width: 100%">
            <el-table-column prop="username" label="用户名" min-width="120" />
            <el-table-column label="角色" width="140" align="center">
              <template #default="{ row }">
                <el-tag :type="roleTagType(row.role)" size="small" effect="light">
                  {{ roleLabel(row.role) }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="tenant" label="所属租户" min-width="140">
              <template #default="{ row }">
                {{ row.tenant || '平台直属' }}
              </template>
            </el-table-column>
            <el-table-column prop="lastLogin" label="最后登录" width="160">
              <template #default="{ row }">
                {{ row.lastLogin || '-' }}
              </template>
            </el-table-column>
            <el-table-column label="状态" width="90" align="center">
              <template #default="{ row }">
                <el-tag :type="row.status === 1 ? 'success' : 'info'" size="small">
                  {{ row.status === 1 ? '正常' : '禁用' }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="160" align="right">
              <template #default="{ row }">
                <el-button link type="primary" @click="openEditUser(row)">编辑</el-button>
                <el-button
                  link
                  :type="row.status === 1 ? 'danger' : 'success'"
                  :loading="row._toggleLoading"
                  @click="toggleUser(row)"
                  :disabled="row.username === 'admin'"
                >
                  {{ row.status === 1 ? '禁用' : '启用' }}
                </el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="pagination-wrap">
            <span class="total-tip">共 {{ total }} 个用户</span>
            <el-pagination
              v-model:current-page="page"
              v-model:page-size="size"
              :page-sizes="[10, 20, 50]"
              :total="total"
              layout="sizes, prev, pager, next"
              background
              @current-change="handlePageChange"
              @size-change="handleSizeChange"
            />
          </div>
        </el-tab-pane>

        <!-- ========== 安全设置 ========== -->
        <el-tab-pane label="安全设置" name="security">
          <el-form v-loading="securityLoading" :model="securityForm" label-width="140px" class="basic-form">
            <el-form-item label="登录失败锁定">
              <el-switch v-model="securityForm.lockOnFail" />
              <span class="form-tip">连续失败 5 次后锁定账号 30 分钟</span>
            </el-form-item>
            <el-form-item label="强制修改密码周期">
              <el-select v-model="securityForm.pwdDays" style="width: 200px">
                <el-option label="30 天" :value="30" />
                <el-option label="60 天" :value="60" />
                <el-option label="90 天" :value="90" />
                <el-option label="不强制" :value="0" />
              </el-select>
            </el-form-item>
            <el-form-item label="二次验证 (2FA)">
              <el-switch v-model="securityForm.twoFactor" />
            </el-form-item>
            <el-form-item label="会话超时时间">
              <el-input-number v-model="securityForm.sessionMin" :min="10" :max="480" />
              <span class="form-tip">分钟</span>
            </el-form-item>
            <el-form-item label="IP 白名单">
              <el-input
                v-model="securityForm.ipWhitelist"
                type="textarea"
                :rows="3"
                placeholder="每行一个 IP，留空表示不限制"
                style="max-width: 420px"
              />
            </el-form-item>
            <el-form-item>
              <el-button type="primary" :loading="securitySaving" @click="saveSecurity">保存安全设置</el-button>
            </el-form-item>
          </el-form>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 新增/编辑用户弹窗 -->
    <el-dialog
      v-model="userDialogVisible"
      :title="userForm.id ? '编辑系统用户' : '新增系统用户'"
      width="480px"
      destroy-on-close
    >
      <el-form :model="userForm" label-width="110px">
        <el-form-item label="用户名" required>
          <el-input
            v-model="userForm.username"
            placeholder="用于登录的账号"
            :disabled="!!userForm.id"
          />
        </el-form-item>
        <el-form-item label="用户角色">
          <el-select v-model="userForm.role" style="width: 100%">
            <el-option label="超级管理员" value="super_admin" />
            <el-option label="租户管理员" value="tenant_admin" />
            <el-option label="业务员" value="operator" />
          </el-select>
        </el-form-item>
        <el-form-item v-if="!userForm.id" label="初始密码" required>
          <el-input
            v-model="userForm.password"
            type="password"
            show-password
            placeholder="请输入登录密码"
          />
        </el-form-item>
        <el-form-item label="所属租户">
          <el-select v-model="userForm.tenantId" style="width: 100%" clearable placeholder="无 (平台直属)">
            <el-option label="无 (平台直属)" :value="''" />
            <el-option
              v-for="t in tenantOptions"
              :key="t.id"
              :label="t.name"
              :value="t.id"
            />
          </el-select>
          <div class="form-tip">仅租户角色有效</div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="userDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="userSaving" @click="saveUser">
          {{ userForm.id ? '保存修改' : '确认创建' }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
/**
 * 系统设置（仅 super_admin 可访问）
 * - 基本设置 / 用户管理 / 安全设置
 */
import { ref, reactive, onMounted } from 'vue'
import { UserFilled, Monitor } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { settingApi, tenantApi } from '@/api'
import { useListQuery } from '@/composables/useListQuery'

const activeTab = ref('basic')
const tenantOptions = ref([])

/** 基本设置 */
const basicForm = reactive({
  name: '',
  copyright: '',
  notify: true,
  logo: ''
})
const basicLoading = ref(false)
const basicSaving = ref(false)

const loadBasic = async () => {
  basicLoading.value = true
  try {
    const data = await settingApi.getBasic()
    Object.assign(basicForm, {
      name: data?.name ?? '',
      copyright: data?.copyright ?? '',
      notify: data?.notify ?? true,
      logo: data?.logo ?? ''
    })
  } catch {
    // 错误已在拦截器提示
  } finally {
    basicLoading.value = false
  }
}

const saveBasic = async () => {
  basicSaving.value = true
  try {
    const data = await settingApi.saveBasic({ ...basicForm })
    Object.assign(basicForm, data || {})
    ElMessage.success('基本设置已保存')
  } catch {
    // 错误已在拦截器提示
  } finally {
    basicSaving.value = false
  }
}

/** 安全设置 */
const securityForm = reactive({
  lockOnFail: true,
  pwdDays: 90,
  twoFactor: false,
  sessionMin: 60,
  ipWhitelist: ''
})
const securityLoading = ref(false)
const securitySaving = ref(false)

const loadSecurity = async () => {
  securityLoading.value = true
  try {
    const data = await settingApi.getSecurity()
    Object.assign(securityForm, {
      lockOnFail: data?.lockOnFail ?? true,
      pwdDays: data?.pwdDays ?? 90,
      twoFactor: data?.twoFactor ?? false,
      sessionMin: data?.sessionMin ?? 60,
      ipWhitelist: data?.ipWhitelist ?? ''
    })
  } catch {
    // 错误已在拦截器提示
  } finally {
    securityLoading.value = false
  }
}

const saveSecurity = async () => {
  securitySaving.value = true
  try {
    const data = await settingApi.saveSecurity({ ...securityForm })
    Object.assign(securityForm, data || {})
    ElMessage.success('安全设置已保存')
  } catch {
    // 错误已在拦截器提示
  } finally {
    securitySaving.value = false
  }
}

/** 用户列表（useListQuery 分页） */
const {
  loading,
  list,
  total,
  page,
  size,
  fetchList: fetchUsers,
  handlePageChange,
  handleSizeChange
} = useListQuery(settingApi.users, {})

const roleLabel = (r) =>
  ({ super_admin: '超级管理员', tenant_admin: '租户管理员', operator: '业务员' }[r] || r)
const roleTagType = (r) =>
  ({ super_admin: 'primary', tenant_admin: 'success', operator: 'warning' }[r] || 'info')

/** 用户 CRUD */
const userDialogVisible = ref(false)
const userSaving = ref(false)
const userForm = reactive({
  id: null,
  username: '',
  role: 'operator',
  password: '',
  tenantId: ''
})

const openAddUser = () => {
  Object.assign(userForm, {
    id: null,
    username: '',
    role: 'operator',
    password: '',
    tenantId: ''
  })
  userDialogVisible.value = true
}

const openEditUser = (row) => {
  Object.assign(userForm, {
    id: row.id,
    username: row.username,
    role: row.role,
    password: '',
    tenantId: row.tenantId || ''
  })
  userDialogVisible.value = true
}

const saveUser = async () => {
  if (!userForm.username.trim()) {
    ElMessage.warning('请填写用户名')
    return
  }
  if (!userForm.id && !userForm.password) {
    ElMessage.warning('请填写初始密码')
    return
  }
  userSaving.value = true
  try {
    const payload = {
      username: userForm.username,
      role: userForm.role,
      tenantId: userForm.tenantId || null
    }
    if (!userForm.id) {
      payload.password = userForm.password
      await settingApi.createUser(payload)
      ElMessage.success('用户创建成功')
    } else {
      if (userForm.password) payload.password = userForm.password
      await settingApi.updateUser(userForm.id, payload)
      ElMessage.success('用户信息已更新')
    }
    userDialogVisible.value = false
    await fetchUsers()
  } catch {
    // 错误已在拦截器提示
  } finally {
    userSaving.value = false
  }
}

const toggleUser = async (row) => {
  row._toggleLoading = true
  try {
    const data = await settingApi.toggleUser(row.id)
    row.status = data?.status ?? row.status
    ElMessage.success(`用户 ${row.username} 已${row.status ? '启用' : '禁用'}`)
  } catch {
    // 错误已在拦截器提示
  } finally {
    row._toggleLoading = false
  }
}

/** 加载租户选项（用户表单） */
const loadTenants = async () => {
  try {
    const data = await tenantApi.list({ page: 1, size: 100 })
    tenantOptions.value = data?.list || []
  } catch {
    tenantOptions.value = []
  }
}

const onTabChange = (tab) => {
  if (tab === 'basic') loadBasic()
  else if (tab === 'security') loadSecurity()
  else if (tab === 'user') fetchUsers()
}

onMounted(async () => {
  await loadTenants()
  await loadBasic()
  await loadSecurity()
})
</script>

<style scoped>
.settings-page {
  max-width: 960px;
}

.settings-card {
  border-radius: 4px;
}

.basic-form {
  padding-top: 12px;
  max-width: 640px;
}

.logo-row {
  display: flex;
  align-items: center;
  gap: 16px;
}

.logo-preview {
  width: 64px;
  height: 64px;
  background: #f5f7fa;
  border: 1px solid #e4e7ed;
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.logo-img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
}

.user-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}

.section-title {
  margin: 0;
  font-size: 14px;
  font-weight: 700;
  color: #303133;
}

.pagination-wrap {
  margin-top: 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
}

.total-tip {
  font-size: 13px;
  color: #909399;
}

.form-tip {
  margin-left: 10px;
  font-size: 12px;
  color: #c0c4cc;
}
</style>
