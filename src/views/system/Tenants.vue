<template>
  <div class="tenants-page">
    <!-- ========== 顶部总数据统计 ========== -->
    <el-row :gutter="20" class="stat-row">
      <el-col :xs="12" :sm="6" v-for="s in stats" :key="s.label">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-inner">
            <div>
              <div class="stat-label">{{ s.label }}</div>
              <div class="stat-value">{{ s.value }}</div>
            </div>
            <div class="stat-icon" :style="{ background: s.bg, color: s.color }">
              <el-icon :size="22"><component :is="s.icon" /></el-icon>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- ========== 表格卡片 ========== -->
    <el-card shadow="hover" class="table-card">
      <!-- 顶部操作栏：搜索、重置、批量操作、导出、新增租户 -->
      <div class="toolbar">
        <div class="toolbar-left">
          <el-input
            v-model="query.keyword"
            placeholder="租户名称/联系人"
            clearable
            style="width: 200px"
            :prefix-icon="Search"
          />
          <el-select v-model="query.status" placeholder="所有状态" clearable style="width: 120px">
            <el-option label="已启用" :value="1" />
            <el-option label="已禁用" :value="0" />
          </el-select>
          <el-select v-model="query.package" placeholder="所有套餐" clearable style="width: 120px">
            <el-option label="基础版" value="basic" />
            <el-option label="专业版" value="pro" />
            <el-option label="企业版" value="ent" />
          </el-select>
          <el-button type="primary" @click="handleSearch">搜索</el-button>
          <el-button @click="handleReset">重置</el-button>
        </div>
        <div class="toolbar-right">
          <el-dropdown :disabled="selectedRows.length === 0">
            <el-button :disabled="selectedRows.length === 0" :loading="batchLoading">
              批量操作 <el-icon class="el-icon--right"><ArrowDown /></el-icon>
            </el-button>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item @click="batchEnable">批量启用</el-dropdown-item>
                <el-dropdown-item @click="batchDisable">批量禁用</el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
          <el-button :icon="Download" :loading="exportLoading" @click="handleExport">导出</el-button>
          <el-button type="primary" :icon="Plus" @click="openAddDialog">新增租户</el-button>
        </div>
      </div>

      <!-- 表格列：租户名称/联系人、联系方式、套餐类型、创建时间、状态、操作 -->
      <el-table
        v-loading="loading"
        :data="list"
        border
        stripe
        style="width: 100%"
        @selection-change="onSelectionChange"
      >
        <el-table-column type="selection" width="48" align="center" />
        <el-table-column label="租户名称/联系人" min-width="200">
          <template #default="{ row }">
            <div class="tenant-name">{{ row.name }}</div>
            <div class="tenant-sub">联系人：{{ row.contact }} · ID: {{ row.id }}</div>
          </template>
        </el-table-column>
        <el-table-column label="联系方式" min-width="180">
          <template #default="{ row }">
            <div>{{ row.phone }}</div>
            <div class="tenant-sub">{{ row.email }}</div>
          </template>
        </el-table-column>
        <el-table-column label="套餐类型" width="110" align="center">
          <template #default="{ row }">
            <el-tag :type="packageTagType(row.package)" size="small">
              {{ packageLabel(row.package) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="当前AI参数模板" min-width="160" show-overflow-tooltip>
          <template #default="{ row }">
            <span v-if="row.currentAiParamTemplateName">{{ row.currentAiParamTemplateName }}</span>
            <span v-else class="tenant-sub">继承平台全局</span>
          </template>
        </el-table-column>
        <el-table-column prop="createTime" label="创建时间" width="120" align="center" />
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-switch
              v-model="row.status"
              :active-value="1"
              :inactive-value="0"
              :loading="row._toggleLoading"
              @change="(val) => onStatusChange(row, val)"
            />
          </template>
        </el-table-column>
        <el-table-column label="操作" width="280" fixed="right" align="center">
          <template #default="{ row }">
            <div class="table-actions">
              <!-- 查看配额详情 -->
              <el-popover placement="top" :width="200" trigger="hover">
                <template #reference>
                  <el-button link type="primary" size="small">查看配额详情</el-button>
                </template>
                <p>任务并发：{{ row.concurrent }} 个</p>
                <p>AI调用：{{ row.aiQuota.toLocaleString() }} 次/月</p>
                <p>账号上限：{{ row.binds }} 个</p>
                <p>知识库：{{ row.kb }} GB</p>
                <p>代理IP：{{ row.maxProxyIp == null ? '∞' : row.maxProxyIp }} 个</p>
                <p>日请求：{{ row.dailyProxyRequestLimit == null ? '∞' : row.dailyProxyRequestLimit }} 次</p>
                <p>IP托管：平台公共池</p>
              </el-popover>
              <el-button link type="primary" size="small" @click="openEditDialog(row)">编辑</el-button>
              <el-button link type="primary" size="small" @click="openAiDialog(row)">AI配置</el-button>
              <el-button link type="primary" size="small" @click="openPackageDialog(row)">套餐</el-button>
            </div>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
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

    <!-- ========== 新增 / 编辑租户弹窗 ========== -->
    <el-dialog
      v-model="formVisible"
      :title="formMode === 'add' ? '新增租户' : `编辑租户：${formData.name}`"
      width="520px"
      destroy-on-close
    >
      <el-form :model="formData" label-width="90px">
        <el-form-item label="租户名称" required>
          <el-input v-model="formData.name" placeholder="请输入公司或机构名称" />
        </el-form-item>
        <el-form-item label="联系人" required>
          <el-input v-model="formData.contact" placeholder="负责人姓名" />
        </el-form-item>
        <el-form-item label="联系电话">
          <el-input v-model="formData.phone" placeholder="手机号" />
        </el-form-item>
        <el-form-item label="邮箱">
          <el-input v-model="formData.email" placeholder="email@example.com" />
        </el-form-item>
        <el-form-item v-if="formMode === 'add'" label="选择套餐">
          <el-select v-model="formData.package" style="width: 100%">
            <el-option label="基础版" value="basic" />
            <el-option label="专业版" value="pro" />
            <el-option label="企业版" value="ent" />
          </el-select>
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="formData.remark" type="textarea" :rows="3" placeholder="备注信息" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="formVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="saveTenant">
          {{ formMode === 'add' ? '立即创建' : '保存修改' }}
        </el-button>
      </template>
    </el-dialog>

    <!-- ========== 租户 AI 配置弹窗（按套餐筛选模板） ========== -->
    <TenantAiConfigDialog
      v-model="aiDialogVisible"
      :tenant-id="aiDialogTenantId"
      :tenant-name="aiDialogTenantName"
      @saved="onAiConfigSaved"
    />

    <!-- ========== 套餐配置弹窗 ========== -->
    <el-dialog v-model="pkgVisible" :title="`套餐配置 [${pkgTarget?.name || ''}]`" width="560px" destroy-on-close>
      <el-form label-width="150px">
        <el-form-item label="当前套餐等级">
          <el-select v-model="pkgForm.package" style="width: 100%" @change="onPkgTypeChange">
            <el-option label="基础版" value="basic" />
            <el-option label="专业版" value="pro" />
            <el-option label="企业版" value="ent" />
          </el-select>
        </el-form-item>
        <el-form-item label="任务并发数">
          <el-slider v-model="pkgForm.concurrent" :min="1" :max="100" show-input />
        </el-form-item>
        <el-form-item label="AI调用额度/月">
          <el-slider v-model="pkgForm.aiQuota" :min="1000" :max="100000" :step="1000" show-input />
        </el-form-item>
        <el-form-item label="账号绑定数量">
          <el-slider v-model="pkgForm.binds" :min="1" :max="50" show-input />
        </el-form-item>
        <el-form-item label="知识库容量(GB)">
          <el-slider v-model="pkgForm.kb" :min="1" :max="100" show-input />
        </el-form-item>

        <el-divider content-position="left">代理 IP 配额</el-divider>
        <el-form-item label="最大绑定代理IP">
          <el-input-number
            v-model="pkgForm.maxProxyIp"
            :min="-1"
            :max="999999"
            controls-position="right"
            style="width: 200px"
          />
          <span class="pkg-tip">-1 表示无上限</span>
        </el-form-item>
        <el-form-item label="每日IP请求上限">
          <el-input-number
            v-model="pkgForm.dailyProxyRequestLimit"
            :min="-1"
            :max="9999999"
            controls-position="right"
            style="width: 200px"
          />
          <span class="pkg-tip">达限自动暂停爬虫</span>
        </el-form-item>
        <el-form-item label="IP托管说明">
          <el-tag type="success">平台公共住宅代理池</el-tag>
          <span class="pkg-tip">已全局关闭租户自有代理上传，爬虫自动从公共池分配</span>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="pkgVisible = false">取消</el-button>
        <el-button type="primary" :loading="pkgSaving" @click="savePackage">应用配置</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
/**
 * 租户管理页面（仅超级管理员可见）
 * - useListQuery + tenantApi.list 分页列表
 * - tenantApi.stats 顶部统计卡片
 * - 增删改查、切换状态、套餐配置、导出 CSV
 */
import { ref, reactive, onMounted } from 'vue'
import { Search, Download, Plus, ArrowDown } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { tenantApi, packageSettingApi } from '@/api'
import { useListQuery } from '@/composables/useListQuery'
import { invalidateTenantScopeCache } from '@/composables/useTenantScope'
import TenantAiConfigDialog from '@/components/TenantAiConfigDialog.vue'

/** 顶部统计卡片配置 */
const STAT_META = [
  { label: '总租户数', key: 'total', icon: 'OfficeBuilding', bg: '#ecf5ff', color: '#409eff' },
  { label: '启用租户', key: 'enabled', icon: 'CircleCheck', bg: '#f0f9eb', color: '#67c23a' },
  { label: '今日新增', key: 'todayNew', icon: 'UserFilled', bg: '#fdf6ec', color: '#e6a23c' },
  { label: '本月活跃', key: 'monthActive', icon: 'DataLine', bg: '#f4f4f5', color: '#909399' }
]

const stats = ref(STAT_META.map((s) => ({ ...s, value: 0 })))

const {
  loading,
  list,
  total,
  page,
  size,
  query,
  fetchList,
  handleSearch,
  handleReset,
  handlePageChange,
  handleSizeChange
} = useListQuery(tenantApi.list, { keyword: '', status: '', package: '' })

const selectedRows = ref([])
const saving = ref(false)
const batchLoading = ref(false)
const exportLoading = ref(false)
const pkgSaving = ref(false)

/** 加载顶部统计数据 */
const fetchStats = async () => {
  try {
    const data = await tenantApi.stats()
    stats.value = STAT_META.map((s) => ({
      ...s,
      value: data?.[s.key] ?? 0
    }))
  } catch {
    // 错误已在拦截器提示
  }
}

const packageLabel = (p) => ({ basic: '基础版', pro: '专业版', ent: '企业版' }[p] || p)
const packageTagType = (p) => ({ basic: '', pro: 'success', ent: 'warning' }[p] || 'info')

const onSelectionChange = (rows) => {
  selectedRows.value = rows
}

/** 批量启用 */
const batchEnable = async () => {
  if (!selectedRows.value.length) return
  batchLoading.value = true
  try {
    await Promise.all(selectedRows.value.map((r) => tenantApi.toggle(r.id, { status: 1 })))
    ElMessage.success('批量启用成功')
    await fetchList()
    await fetchStats()
  } catch {
    // 错误已在拦截器提示
  } finally {
    batchLoading.value = false
  }
}

/** 批量禁用 */
const batchDisable = async () => {
  if (!selectedRows.value.length) return
  batchLoading.value = true
  try {
    await Promise.all(selectedRows.value.map((r) => tenantApi.toggle(r.id, { status: 0 })))
    ElMessage.success('批量禁用成功')
    await fetchList()
    await fetchStats()
  } catch {
    // 错误已在拦截器提示
  } finally {
    batchLoading.value = false
  }
}

/** 导出租户 CSV */
const handleExport = async () => {
  exportLoading.value = true
  try {
    const response = await tenantApi.export({
      keyword: query.keyword || undefined,
      status: query.status !== '' ? query.status : undefined,
      package: query.package || undefined
    })
    const blob = new Blob([response.data], { type: 'text/csv;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `tenants_${Date.now()}.csv`
    link.click()
    URL.revokeObjectURL(url)
    ElMessage.success('导出成功')
  } catch {
    // 错误已在拦截器提示
  } finally {
    exportLoading.value = false
  }
}

/** 切换租户启用/禁用 */
const onStatusChange = async (row, val) => {
  const prev = val === 1 ? 0 : 1
  row._toggleLoading = true
  try {
    await tenantApi.toggle(row.id, { status: val })
    ElMessage.success(`${row.name} 已${val ? '启用' : '禁用'}`)
    await fetchStats()
  } catch {
    row.status = prev
  } finally {
    row._toggleLoading = false
  }
}

/* ----- 新增/编辑 ----- */
const formVisible = ref(false)
const formMode = ref('add')
const formData = reactive({
  id: null,
  name: '',
  contact: '',
  phone: '',
  email: '',
  package: 'basic',
  remark: ''
})

const openAddDialog = () => {
  formMode.value = 'add'
  Object.assign(formData, {
    id: null,
    name: '',
    contact: '',
    phone: '',
    email: '',
    package: 'basic',
    remark: ''
  })
  formVisible.value = true
}

const openEditDialog = (row) => {
  formMode.value = 'edit'
  Object.assign(formData, {
    id: row.id,
    name: row.name,
    contact: row.contact,
    phone: row.phone,
    email: row.email,
    package: row.package,
    remark: row.remark || ''
  })
  formVisible.value = true
}

const saveTenant = async () => {
  if (!formData.name || !formData.contact) {
    ElMessage.warning('请填写租户名称和联系人')
    return
  }
  saving.value = true
  try {
    if (formMode.value === 'add') {
      await tenantApi.create({
        name: formData.name,
        contact: formData.contact,
        phone: formData.phone,
        email: formData.email,
        package: formData.package,
        remark: formData.remark
      })
      ElMessage.success('租户创建成功')
    } else {
      await tenantApi.update(formData.id, {
        name: formData.name,
        contact: formData.contact,
        phone: formData.phone,
        email: formData.email,
        remark: formData.remark
      })
      ElMessage.success('修改已保存')
    }
    formVisible.value = false
    await fetchList()
    await fetchStats()
  } catch {
    // 错误已在拦截器提示
  } finally {
    saving.value = false
  }
}

/* ----- AI配置弹窗：按套餐筛选并保存当前启用模板 ----- */
const aiDialogVisible = ref(false)
const aiDialogTenantId = ref(null)
const aiDialogTenantName = ref('')

const openAiDialog = (row) => {
  aiDialogTenantId.value = row.id
  aiDialogTenantName.value = row.name
  aiDialogVisible.value = true
}

const onAiConfigSaved = async () => {
  await fetchList()
  await fetchStats()
}

/* ----- 套餐配置 ----- */
const pkgVisible = ref(false)
const pkgTarget = ref(null)
const packageSettingMap = ref({})
const pkgForm = reactive({
  package: 'basic',
  concurrent: 10,
  aiQuota: 5000,
  binds: 10,
  kb: 5,
  maxProxyIp: 3,
  dailyProxyRequestLimit: 500,
  allowSelfProxy: false
})

/** 各套餐默认配额（与后端 TenantService::packageDefaults 一致）；IP 优先读 saas_package_setting */
const PKG_DEFAULTS = {
  basic: {
    concurrent: 5,
    aiQuota: 800,
    binds: 3,
    kb: 1,
    maxProxyIp: 3,
    dailyProxyRequestLimit: 3000,
    allowSelfProxy: false
  },
  pro: {
    concurrent: 20,
    aiQuota: 8000,
    binds: 15,
    kb: 10,
    maxProxyIp: 15,
    dailyProxyRequestLimit: 20000,
    allowSelfProxy: false
  },
  ent: {
    concurrent: 50,
    aiQuota: 999999,
    binds: 999,
    kb: 50,
    maxProxyIp: -1,
    dailyProxyRequestLimit: -1,
    allowSelfProxy: false
  }
}

const unlimitedDisplay = (v) => (v === null || v === undefined ? -1 : v)

const loadPackageSettings = async () => {
  try {
    const data = await packageSettingApi.list()
    const map = {}
    ;(data?.list || []).forEach((item) => {
      map[item.packageCode] = item
    })
    packageSettingMap.value = map
  } catch {
    packageSettingMap.value = {}
  }
}

/** 切换套餐等级：回填并发 / AI额度 / 账号 / 知识库 / 代理IP */
const applyDefaultsFromPackage = (pkgCode) => {
  const d = PKG_DEFAULTS[pkgCode] || PKG_DEFAULTS.basic
  pkgForm.concurrent = d.concurrent
  pkgForm.aiQuota = d.aiQuota
  pkgForm.binds = d.binds
  pkgForm.kb = d.kb

  const s = packageSettingMap.value[pkgCode]
  if (s) {
    pkgForm.maxProxyIp = unlimitedDisplay(s.maxProxyIp)
    pkgForm.dailyProxyRequestLimit = unlimitedDisplay(s.dailyProxyRequestLimit)
    pkgForm.allowSelfProxy = !!s.allowSelfProxy
  } else {
    pkgForm.maxProxyIp = d.maxProxyIp
    pkgForm.dailyProxyRequestLimit = d.dailyProxyRequestLimit
    pkgForm.allowSelfProxy = d.allowSelfProxy
  }
}

const onPkgTypeChange = (pkg) => {
  applyDefaultsFromPackage(pkg)
}

const openPackageDialog = async (row) => {
  pkgTarget.value = row
  if (!Object.keys(packageSettingMap.value).length) {
    await loadPackageSettings()
  }
  Object.assign(pkgForm, {
    package: row.package,
    concurrent: row.concurrent,
    aiQuota: row.aiQuota,
    binds: row.binds,
    kb: row.kb
  })
  // 有租户有效 IP 数据则用列表返回值，否则按套餐修正
  if (row.maxProxyIp != null || row.dailyProxyRequestLimit != null || row.allowSelfProxy != null) {
    pkgForm.maxProxyIp = unlimitedDisplay(row.maxProxyIp)
    pkgForm.dailyProxyRequestLimit = unlimitedDisplay(row.dailyProxyRequestLimit)
    pkgForm.allowSelfProxy = !!row.allowSelfProxy
  } else {
    const d = PKG_DEFAULTS[row.package] || PKG_DEFAULTS.basic
    const s = packageSettingMap.value[row.package]
    pkgForm.maxProxyIp = unlimitedDisplay(s?.maxProxyIp ?? d.maxProxyIp)
    pkgForm.dailyProxyRequestLimit = unlimitedDisplay(s?.dailyProxyRequestLimit ?? d.dailyProxyRequestLimit)
    pkgForm.allowSelfProxy = s ? !!s.allowSelfProxy : d.allowSelfProxy
  }
  pkgVisible.value = true
}

const savePackage = async () => {
  if (!pkgTarget.value) return
  pkgSaving.value = true
  try {
    await tenantApi.updatePackage(pkgTarget.value.id, { ...pkgForm })
    ElMessage.success('配置已更新')
    pkgVisible.value = false
    invalidateTenantScopeCache()
    await fetchList()
  } catch {
    // 错误已在拦截器提示
  } finally {
    pkgSaving.value = false
  }
}

onMounted(async () => {
  await Promise.all([fetchList(), fetchStats(), loadPackageSettings()])
})
</script>

<style scoped>
.stat-row {
  margin-bottom: 16px;
}
.stat-card {
  margin-bottom: 12px;
  border-radius: 4px;
}
.stat-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.stat-label {
  font-size: 13px;
  color: #909399;
  margin-bottom: 6px;
}
.stat-value {
  font-size: 24px;
  font-weight: 700;
  color: #303133;
}
.stat-icon {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

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
.toolbar-left,
.toolbar-right {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.tenant-name {
  font-weight: 600;
  color: #303133;
}
.tenant-sub {
  font-size: 12px;
  color: #909399;
  margin-top: 2px;
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

.table-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 0;
}
.pkg-tip {
  margin-left: 10px;
  font-size: 12px;
  color: #909399;
}
</style>
