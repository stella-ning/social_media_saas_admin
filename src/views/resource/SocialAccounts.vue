<template>
  <div class="accounts-page">
    <el-card shadow="hover" class="table-card">
      <!-- 顶部：所有租户筛选、刷新状态、绑定新账号 -->
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
        <el-button type="primary" :icon="Plus" @click="bindVisible = true">绑定新账号</el-button>
      </div>

      <!-- 表格：账号信息(含UID)、平台、绑定IP、所属租户、状态、操作 -->
      <el-table v-loading="loading" :data="list" border stripe style="width: 100%">
        <el-table-column label="账号信息" min-width="200">
          <template #default="{ row }">
            <div class="account-cell">
              <el-avatar :size="40" :src="row.avatar || defaultAvatar" />
              <div>
                <div class="account-name">{{ row.name }}</div>
                <div class="account-uid">UID: {{ row.uid }}</div>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="platform" label="平台" width="100" align="center" />
        <el-table-column prop="bindIp" label="绑定IP" width="140">
          <template #default="{ row }">
            <span class="mono">{{ row.bindIp || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="tenant" label="所属租户" min-width="140" />
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <!-- 区分在线/离线状态标签 -->
            <el-tag :type="row.status === 'online' ? 'success' : 'danger'" size="small">
              {{ row.status === 'online' ? '在线' : '离线' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="160" align="center">
          <template #default="{ row }">
            <el-button link type="primary" @click="handleUnbind(row)">解绑</el-button>
            <el-button link type="primary" @click="handleLog(row)">日志</el-button>
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

    <!-- 绑定新账号弹窗 -->
    <el-dialog v-model="bindVisible" title="绑定新账号" width="480px" destroy-on-close>
      <el-form label-width="120px">
        <el-form-item v-if="showTenantFilter" label="所属租户">
          <el-select v-model="bindForm.tenantId" placeholder="选择租户" clearable style="width: 100%">
            <el-option
              v-for="t in tenantOptions"
              :key="t.id"
              :label="t.name"
              :value="t.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="选择平台">
          <el-radio-group v-model="bindForm.platform">
            <el-radio-button value="小红书">小红书</el-radio-button>
            <el-radio-button value="抖音">抖音</el-radio-button>
            <el-radio-button value="视频号">视频号</el-radio-button>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="账号Cookie">
          <el-input
            v-model="bindForm.token"
            type="textarea"
            :rows="4"
            placeholder="粘贴浏览器获取的账号授权信息"
          />
        </el-form-item>
        <el-form-item label="分配代理IP">
          <el-select v-model="bindForm.proxyIpId" style="width: 100%" clearable>
            <el-option label="自动分配 (推荐)" :value="''" />
            <el-option
              v-for="p in proxyOptions"
              :key="p.id"
              :label="`${p.address}${p.location ? ' · ' + p.location : ''}`"
              :value="p.id"
            />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="bindVisible = false">取消</el-button>
        <el-button type="primary" :loading="binding" @click="handleBind">开始验证绑定</el-button>
      </template>
    </el-dialog>

    <!-- 日志抽屉：后端暂无独立账号日志接口，展示账号关键状态摘要 -->
    <el-drawer v-model="logVisible" :title="`账号日志 - ${logAccount?.name || ''}`" size="400px">
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
    </el-drawer>
  </div>
</template>

<script setup>
/**
 * 社媒账号管理
 * - useListQuery + socialAccountApi.list
 * - 绑定/解绑/刷新状态
 */
import { ref, reactive, computed, onMounted } from 'vue'
import { Plus } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { socialAccountApi, tenantApi, proxyIpApi } from '@/api'
import { useListQuery } from '@/composables/useListQuery'

const defaultAvatar = 'https://cube.elemecdn.com/3/7c/3ea6beec64369c2642b92c6726f1epng.png'

/** 列表查询：tenantId 映射为后端 tenant_id */
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

const showTenantFilter = ref(true)
const tenantOptions = ref([])
const proxyOptions = ref([])
const refreshing = ref(false)
const binding = ref(false)

/** 加载租户下拉（超管可用；403 则隐藏筛选） */
const loadTenantOptions = async () => {
  try {
    const data = await tenantApi.list({ page: 1, size: 100 })
    tenantOptions.value = (data?.list || []).map((t) => ({ id: t.id, name: t.name }))
  } catch {
    showTenantFilter.value = false
    tenantOptions.value = []
  }
}

/** 加载可用代理 IP（绑定弹窗下拉） */
const loadProxyOptions = async () => {
  try {
    const data = await proxyIpApi.list({ page: 1, size: 100 })
    proxyOptions.value = data?.list || []
  } catch {
    proxyOptions.value = []
  }
}

/** 刷新账号在线状态 */
const refreshStatus = async () => {
  refreshing.value = true
  try {
    await socialAccountApi.refreshStatus()
    ElMessage.success('账号状态已刷新')
    await fetchList()
  } catch {
    // 错误已在拦截器提示
  } finally {
    refreshing.value = false
  }
}

/** 解绑账号 */
const handleUnbind = (row) => {
  ElMessageBox.confirm(`确认解绑账号「${row.name}」？`, '提示', {
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
        // 错误已在拦截器提示
      }
    })
    .catch(() => {})
}

const logVisible = ref(false)
const logAccount = ref(null)

/** 根据账号当前状态组装摘要日志（无独立 logs 接口时的兜底展示） */
const accountLogItems = computed(() => {
  const row = logAccount.value
  if (!row) return []
  const items = []
  if (row.bindIp) {
    items.push({
      time: '',
      type: 'primary',
      content: `当前绑定代理：${row.bindIp}`
    })
  }
  items.push({
    time: '',
    type: row.status === 'online' ? 'success' : 'danger',
    content: row.status === 'online' ? '最近心跳：在线' : '最近心跳：离线'
  })
  items.push({
    time: '',
    type: 'success',
    content: `账号已绑定（${row.platform} / UID ${row.uid}）`
  })
  return items
})

const handleLog = (row) => {
  logAccount.value = row
  logVisible.value = true
}

/* ----- 绑定新账号 ----- */
const bindVisible = ref(false)
const bindForm = reactive({
  platform: '抖音',
  token: '',
  proxyIpId: '',
  tenantId: ''
})

const handleBind = async () => {
  if (!bindForm.token.trim()) {
    ElMessage.warning('请粘贴账号授权信息')
    return
  }
  binding.value = true
  try {
    const payload = {
      platform: bindForm.platform,
      cookie: bindForm.token,
      tenantId: bindForm.tenantId || undefined,
      name: `新绑定账号_${bindForm.platform}`
    }
    if (bindForm.proxyIpId) {
      payload.proxyIpId = bindForm.proxyIpId
    } else {
      payload.ip = 'auto'
    }
    await socialAccountApi.create(payload)
    ElMessage.success('账号验证绑定成功')
    bindVisible.value = false
    bindForm.token = ''
    bindForm.proxyIpId = ''
    bindForm.tenantId = ''
    await fetchList()
  } catch {
    // 错误已在拦截器提示
  } finally {
    binding.value = false
  }
}

onMounted(async () => {
  await Promise.all([fetchList(), loadTenantOptions(), loadProxyOptions()])
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
