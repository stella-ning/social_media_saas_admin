<template>
  <div class="accounts-page">
    <el-card shadow="hover" class="table-card">
      <!-- 顶部：租户筛选、刷新状态、绑定新账号 -->
      <div class="toolbar">
        <div class="toolbar-left">
          <el-select
            v-if="showTenantFilter"
            v-model="query.tenantId"
            placeholder="所有租户"
            clearable
            style="width: 200px"
            @change="handleSearch"
          >
            <el-option label="所有租户" value="" />
            <el-option
              v-for="t in tenantOptions"
              :key="t.id"
              :label="t.name"
              :value="t.id"
            />
          </el-select>
          <el-button :loading="refreshing" @click="refreshStatus">刷新状态</el-button>
        </div>
        <el-button type="primary" :icon="Plus" @click="openBindDialog">绑定新账号</el-button>
      </div>

      <!-- 表格：账号信息、平台、绑定IP、所属租户、状态、操作 -->
      <el-table v-loading="loading" :data="list" border stripe style="width: 100%">
        <el-table-column label="账号信息" min-width="200">
          <template #default="{ row }">
            <div class="account-cell">
              <el-avatar :size="40" :src="row.avatar || defaultAvatar" />
              <div>
                <div class="account-name">{{ row.name }}</div>
                <div class="account-uid">账号: {{ row.accountName || row.uid }}</div>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="platform" label="平台" width="100" align="center" />
        <el-table-column prop="bindIp" label="绑定IP" width="160">
          <template #default="{ row }">
            <span class="mono">{{ row.bindIp || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="tenant" label="所属租户" min-width="140" />
        <el-table-column label="状态" width="120" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status === 'online' ? 'success' : 'danger'" size="small">
              {{ row.status === 'online' ? '在线' : '离线' }}
            </el-tag>
            <div v-if="row.riskTip" class="risk-tip">{{ row.riskTip }}</div>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="220" align="center">
          <template #default="{ row }">
            <el-button link type="primary" @click="handleUnbind(row)">解绑</el-button>
            <el-button
              v-if="row.supportAccountAiConfig || row.platform === '小红书' || row.platformCode === 1"
              link
              type="primary"
              @click="openAccountAi(row)"
            >
              账号AI配置
            </el-button>
            <el-button link type="primary" @click="handleLog(row)">日志</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrap">
        <span class="total-tip">共 {{ total }} 条记录</span>
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="size"
          :page-sizes="[10, 20, 50]"
          :total="total"
          layout="sizes, prev, pager, next, jumper"
          background
          @current-change="handlePageChange"
          @size-change="handleSizeChange"
        />
      </div>
    </el-card>

    <!-- 绑定新账号：凭据 + 空闲代理，后端 Playwright 自动登录抓 Cookie -->
    <el-dialog
      v-model="bindVisible"
      title="绑定新账号"
      width="520px"
      destroy-on-close
      :close-on-click-modal="!binding"
    >
      <el-form ref="bindFormRef" :model="bindForm" :rules="bindRules" label-width="120px">
        <el-form-item v-if="showTenantFilter" label="所属租户" prop="tenantId">
          <el-select
            v-model="bindForm.tenantId"
            placeholder="选择租户"
            style="width: 100%"
            @change="onTenantChange"
          >
            <el-option
              v-for="t in tenantOptions"
              :key="t.id"
              :label="t.name"
              :value="t.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="选择平台" prop="platform">
          <el-radio-group v-model="bindForm.platform">
            <el-radio-button
              v-for="p in bindPlatformOptions"
              :key="p.code"
              :value="p.label"
              :disabled="p.disabled"
            >
              {{ p.label }}
            </el-radio-button>
          </el-radio-group>
          <div v-if="bindPlatformOptions.some((p) => p.disabled)" class="form-tip">
            灰色平台为当前套餐未开通，请升级套餐后绑定
          </div>
        </el-form-item>
        <el-form-item label="登录账号" prop="accountName">
          <el-input
            v-model="bindForm.accountName"
            placeholder="手机号 / 平台账号"
            maxlength="128"
            clearable
          />
        </el-form-item>
        <el-form-item label="登录密码" prop="password">
          <el-input
            v-model="bindForm.password"
            type="password"
            show-password
            placeholder="输入后将 AES 加密存储，不明文落库"
            maxlength="128"
            autocomplete="new-password"
          />
        </el-form-item>
        <el-form-item label="短信验证码" prop="code">
          <el-input
            v-model="bindForm.code"
            placeholder="可选，登录遇到验证码时填写"
            maxlength="16"
            clearable
          />
        </el-form-item>
        <el-form-item label="分配代理IP" prop="proxyIpId">
          <el-select
            v-model="bindForm.proxyIpId"
            placeholder="从平台已分配公共池中选择"
            style="width: 100%"
            :loading="proxyLoading"
            filterable
          >
            <el-option
              v-for="p in freeProxyOptions"
              :key="p.id"
              :label="`${p.address}${p.location ? ' · ' + p.location : ''}`"
              :value="p.id"
            />
          </el-select>
          <div class="form-tip">平台托管公共池：一号一IP，选中后该 IP 不可再绑其他账号；任务也可启动时自动分配</div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button :disabled="binding" @click="bindVisible = false">取消</el-button>
        <el-button type="primary" :loading="binding" @click="handleBind">开始验证绑定</el-button>
      </template>
    </el-dialog>

    <!-- 操作日志抽屉（后端脱敏日志） -->
    <el-drawer v-model="logVisible" :title="`账号日志 - ${logAccount?.name || ''}`" size="420px">
      <div v-loading="logsLoading">
        <el-empty v-if="!accountLogItems.length" description="暂无日志" />
        <el-timeline v-else>
          <el-timeline-item
            v-for="(item, idx) in accountLogItems"
            :key="idx"
            :timestamp="item.time"
            :type="item.type"
          >
            {{ item.content }}
          </el-timeline-item>
        </el-timeline>
      </div>
    </el-drawer>
    <!-- 小红书账号 AI 配置弹窗 -->
    <AccountAiConfigDialog
      v-model="aiConfigVisible"
      :account-id="aiConfigAccountId"
      @saved="fetchList"
    />
  </div>
</template>

<script setup>
/**
 * 社媒账号管理
 * - 列表 / 解绑 / 日志
 * - 绑定弹窗：租户 + 平台 + 账号密码 + 空闲代理 → POST /api/social-account/store
 * - 废弃 Cookie 粘贴方案，由 Python Playwright 自动登录抓取
 */
import { ref, reactive, onMounted, watch, computed } from 'vue'
import { Plus } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { socialAccountApi, tenantApi, proxyIpApi } from '@/api'
import { useListQuery } from '@/composables/useListQuery'
import { getCurrentUser, getCurrentRole } from '@/utils/auth'
import { platformOptionsFromAllow } from '@/utils/platform'
import AccountAiConfigDialog from '@/components/AccountAiConfigDialog.vue'

const defaultAvatar = 'https://cube.elemecdn.com/3/7c/3ea6beec64369c2642b92c6726f1epng.png'

const {
  loading,
  list,
  total,
  page,
  size,
  query,
  fetchList,
  handleSearch,
  handlePageChange,
  handleSizeChange
} = useListQuery(
  (p) =>
    socialAccountApi.list({
      ...p,
      tenant_id: p.tenantId || undefined,
      tenantId: undefined
    }),
  { tenantId: '', keyword: '' }
)

const showTenantFilter = ref(getCurrentRole() === 'super_admin')
const tenantOptions = ref([])
const freeProxyOptions = ref([])
const proxyLoading = ref(false)
const refreshing = ref(false)
const binding = ref(false)
const bindPlatformOptions = ref(platformOptionsFromAllow([]))

const bindVisible = ref(false)
const bindFormRef = ref(null)
const bindForm = reactive({
  tenantId: '',
  platform: '抖音',
  accountName: '',
  password: '',
  code: '',
  proxyIpId: ''
})

const bindRules = computed(() => {
  const rules = {
    platform: [{ required: true, message: '请选择平台', trigger: 'change' }],
    accountName: [{ required: true, message: '请输入登录账号', trigger: 'blur' }],
    password: [{ required: true, message: '请输入登录密码', trigger: 'blur' }],
    proxyIpId: [{ required: true, message: '请选择空闲代理 IP', trigger: 'change' }]
  }
  if (showTenantFilter.value) {
    rules.tenantId = [{ required: true, message: '请选择所属租户', trigger: 'change' }]
  }
  return rules
})

/** 加载租户下拉（仅超管）；租户/业务员不请求 tenants 接口，避免无权限提示 */
const loadTenantOptions = async () => {
  const user = getCurrentUser()
  if (getCurrentRole() !== 'super_admin') {
    showTenantFilter.value = false
    tenantOptions.value = []
    if (user?.tenantId) {
      bindForm.tenantId = user.tenantId
    }
    return
  }
  try {
    const data = await tenantApi.list({ page: 1, size: 100 })
    tenantOptions.value = (data?.list || []).map((t) => ({ id: t.id, name: t.name }))
    showTenantFilter.value = true
  } catch {
    showTenantFilter.value = false
    tenantOptions.value = []
  }
}

/** 按租户加载空闲可用代理 IP + 套餐平台白名单 */
const loadFreeProxies = async (tenantId) => {
  freeProxyOptions.value = []
  bindForm.proxyIpId = ''
  if (!tenantId) {
    bindPlatformOptions.value = platformOptionsFromAllow([])
    return
  }
  proxyLoading.value = true
  try {
    const [proxyData, quota] = await Promise.all([
      socialAccountApi.freeProxyIps(tenantId),
      // 走 proxy-ips 权限（租户可访问），勿调仅超管的 package-setting
      proxyIpApi.tenantQuota(tenantId).catch(() => null)
    ])
    freeProxyOptions.value = proxyData?.list || []
    const allow = quota?.allowPlatforms || []
    bindPlatformOptions.value = platformOptionsFromAllow(allow)
    const enabled = bindPlatformOptions.value.filter((p) => !p.disabled)
    if (enabled.length && enabled.every((p) => p.label !== bindForm.platform)) {
      bindForm.platform = enabled[0].label
    }
  } catch {
    freeProxyOptions.value = []
  } finally {
    proxyLoading.value = false
  }
}

const onTenantChange = (tid) => {
  loadFreeProxies(tid)
}

const openBindDialog = async () => {
  const user = getCurrentUser()
  bindForm.platform = '抖音'
  bindForm.accountName = ''
  bindForm.password = ''
  bindForm.code = ''
  bindForm.proxyIpId = ''
  if (!showTenantFilter.value && user?.tenantId) {
    bindForm.tenantId = user.tenantId
  } else if (!bindForm.tenantId && tenantOptions.value.length === 1) {
    bindForm.tenantId = tenantOptions.value[0].id
  }
  bindVisible.value = true
  if (bindForm.tenantId) {
    await loadFreeProxies(bindForm.tenantId)
  }
}

watch(
  () => bindVisible.value,
  (v) => {
    if (!v) {
      bindForm.password = ''
      bindForm.code = ''
    }
  }
)

const refreshStatus = async () => {
  refreshing.value = true
  try {
    await socialAccountApi.refreshStatus()
    ElMessage.success('账号状态已刷新')
    await fetchList()
  } catch {
    // 拦截器已提示
  } finally {
    refreshing.value = false
  }
}

const handleUnbind = (row) => {
  ElMessageBox.confirm(`确认解绑账号「${row.name}」？将释放其专属代理 IP。`, '提示', {
    type: 'warning',
    confirmButtonText: '解绑',
    cancelButtonText: '取消'
  })
    .then(async () => {
      try {
        await socialAccountApi.remove(row.id)
        ElMessage.success('已解绑')
        await fetchList()
      } catch {
        // ignore
      }
    })
    .catch(() => {})
}

const logVisible = ref(false)
const logAccount = ref(null)
const accountLogItems = ref([])
const logsLoading = ref(false)

const handleLog = async (row) => {
  logAccount.value = row
  logVisible.value = true
  logsLoading.value = true
  accountLogItems.value = []
  try {
    const data = await socialAccountApi.logs(row.id)
    accountLogItems.value = (data?.list || []).map((item) => ({
      time: item.time,
      type: item.type === 'danger' ? 'danger' : item.type === 'warning' ? 'warning' : item.type === 'success' ? 'success' : 'primary',
      content: item.content
    }))
  } catch {
    accountLogItems.value = []
  } finally {
    logsLoading.value = false
  }
}

/* ----- 小红书账号 AI 配置 ----- */
const aiConfigVisible = ref(false)
const aiConfigAccountId = ref(null)
const openAccountAi = (row) => {
  if (!(row.supportAccountAiConfig || row.platform === '小红书' || row.platformCode === 1)) {
    ElMessage.info('抖音/视频号暂不支持账号级 AI 配置，将沿用租户默认模板')
    return
  }
  aiConfigAccountId.value = row.id
  aiConfigVisible.value = true
}

/** 提交绑定：调用后端自动登录 */
const handleBind = async () => {
  if (!bindFormRef.value) return
  try {
    await bindFormRef.value.validate()
  } catch {
    return
  }

  // 租户管理员无下拉时补齐 tenantId 校验
  if (!bindForm.tenantId) {
    ElMessage.warning('请选择所属租户')
    return
  }

  binding.value = true
  try {
    await socialAccountApi.store({
      tenant_id: bindForm.tenantId,
      platform: bindForm.platform,
      account_name: bindForm.accountName.trim(),
      password: bindForm.password,
      code: bindForm.code?.trim() || undefined,
      proxy_ip_id: bindForm.proxyIpId
    })
    ElMessage.success('账号验证绑定成功')
    bindVisible.value = false
    bindForm.password = ''
    await fetchList()
  } catch {
    // 错误已在拦截器提示（含验证码/风控文案）
  } finally {
    binding.value = false
  }
}

onMounted(async () => {
  await Promise.all([fetchList(), loadTenantOptions()])
  const user = getCurrentUser()
  if (!showTenantFilter.value && user?.tenantId) {
    bindForm.tenantId = user.tenantId
  }
})
</script>

<style scoped>
.table-card {
  border-radius: 4px;
}
.toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
}
.toolbar-left {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}
.account-cell {
  display: flex;
  align-items: center;
  gap: 12px;
}
.account-name {
  font-weight: 600;
  color: #303133;
}
.account-uid {
  font-size: 12px;
  color: #909399;
  margin-top: 2px;
}
.mono {
  font-family: 'SF Mono', Monaco, Menlo, Consolas, monospace;
  font-size: 13px;
}
.risk-tip {
  margin-top: 4px;
  font-size: 12px;
  color: #e6a23c;
  line-height: 1.3;
}
.form-tip {
  margin-top: 6px;
  font-size: 12px;
  color: #909399;
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
</style>
