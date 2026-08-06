<template>
  <div class="proxy-page">
    <el-alert
      type="info"
      :closable="false"
      show-icon
      title="全部爬虫网络请求统一使用平台公共住宅代理 IP 池；已关闭租户自有代理上传入口"
      style="margin-bottom: 16px"
    />

    <el-tabs v-model="activeTab">
      <el-tab-pane label="平台公共IP池" name="pool">
        <el-card shadow="hover">
          <div class="toolbar">
            <div class="toolbar-left">
              <el-tag type="success">平台托管</el-tag>
              <span class="hint">{{ isAdmin ? '超管可导入公共池 / 企业专属隔离池' : '仅展示已分配给当前租户的 IP' }}</span>
            </div>
            <div v-if="isAdmin" class="toolbar-right">
              <el-button type="primary" @click="openImport('public')">导入公共池</el-button>
              <el-button @click="openImport('dedicated')">导入专属隔离池</el-button>
            </div>
            <div v-else class="toolbar-right">
              <el-button @click="loadAllocated">刷新分配</el-button>
            </div>
          </div>

          <el-table v-loading="loading" :data="list" border stripe>
            <el-table-column prop="address" label="服务器地址" min-width="160">
              <template #default="{ row }"><span class="mono">{{ row.address }}</span></template>
            </el-table-column>
            <el-table-column prop="poolTypeLabel" label="池类型" width="120" />
            <el-table-column prop="location" label="归属地" width="140" />
            <el-table-column prop="tenant" label="分配租户" width="140">
              <template #default="{ row }">{{ row.tenant || '—' }}</template>
            </el-table-column>
            <el-table-column prop="riskLevel" label="风险" width="90" align="center" />
            <el-table-column label="状态" width="100" align="center">
              <template #default="{ row }">
                {{ row.status === 'running' ? '运行中' : row.status === 'error' ? '异常' : '空闲' }}
              </template>
            </el-table-column>
            <el-table-column label="负载" width="90" align="center">
              <template #default="{ row }">{{ row.load }}/{{ row.capacity }}</template>
            </el-table-column>
            <el-table-column label="操作" width="200" align="center" fixed="right">
              <template #default="{ row }">
                <el-button link type="primary" :loading="checkingId === row.id" @click="handleCheck(row)">检测</el-button>
                <el-button link type="primary" @click="openLogs(row)">访问日志</el-button>
                <el-button v-if="isAdmin" link type="danger" @click="handleRemove(row)">移除</el-button>
              </template>
            </el-table-column>
          </el-table>

          <div class="pagination-wrap">
            <span class="total-tip">共 {{ total }} 条</span>
            <el-pagination
              v-model:current-page="page"
              v-model:page-size="size"
              :total="total"
              layout="sizes, prev, pager, next"
              background
              @current-change="fetchList"
              @size-change="fetchList"
            />
          </div>
        </el-card>

        <el-card v-if="!isAdmin && quota" shadow="hover" style="margin-top: 16px">
          <h3 class="page-title">套餐 IP 权限</h3>
          <el-descriptions :column="2" border>
            <el-descriptions-item label="套餐">{{ quota.packageLabel }}</el-descriptions-item>
            <el-descriptions-item label="日请求">{{ quota.dailyProxyRequestUsed }}/{{ quota.dailyProxyRequestLimit ?? '∞' }}</el-descriptions-item>
            <el-descriptions-item label="已分配">{{ quota.boundProxyIp }}/{{ quota.maxProxyIp ?? '∞' }}</el-descriptions-item>
            <el-descriptions-item label="平台">{{ formatPlatforms(quota.allowPlatforms, '、') }}</el-descriptions-item>
          </el-descriptions>
          <div v-if="quota.enableDedicatedIpPool" class="flags" style="margin-top: 12px">
            <el-switch v-model="dedicatedOn" active-text="专属隔离池" @change="saveFlags" />
            <el-switch
              v-if="quota.enableIpRotate"
              v-model="rotateOn"
              active-text="IP自动轮换"
              style="margin-left: 16px"
              @change="saveFlags"
            />
          </div>
        </el-card>
      </el-tab-pane>
    </el-tabs>

    <el-dialog v-model="importVisible" :title="importPool === 'dedicated' ? '导入企业专属隔离池' : '导入平台公共住宅代理'" width="520px">
      <el-alert type="warning" :closable="false" title="禁止导入租户自有外部代理；仅平台运维资源" style="margin-bottom: 12px" />
      <el-form label-width="100px">
        <el-form-item v-if="importPool === 'dedicated'" label="企业租户">
          <el-select v-model="importTenantId" filterable style="width: 100%">
            <el-option v-for="t in tenants" :key="t.id" :label="t.name" :value="t.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="归属地">
          <el-input v-model="importLocation" placeholder="如：华东住宅" />
        </el-form-item>
        <el-form-item label="IP列表">
          <el-input v-model="importList" type="textarea" :rows="6" placeholder="每行 IP:端口" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="importVisible = false">取消</el-button>
        <el-button type="primary" :loading="importing" @click="submitImport">导入</el-button>
      </template>
    </el-dialog>

    <el-drawer v-model="logVisible" title="IP 访问日志" size="420px">
      <el-timeline>
        <el-timeline-item v-for="log in logs" :key="log.id" :timestamp="log.time" placement="top">
          {{ log.action }} / {{ log.result }} — {{ log.detail }}
        </el-timeline-item>
      </el-timeline>
    </el-drawer>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { proxyIpApi, tenantApi, premiumApi } from '@/api'
import { getCurrentRole, getCurrentUser } from '@/utils/auth'
import { formatPlatforms } from '@/utils/platform'

const isAdmin = computed(() => getCurrentRole() === 'super_admin')
const activeTab = ref('pool')
const loading = ref(false)
const list = ref([])
const total = ref(0)
const page = ref(1)
const size = ref(10)
const checkingId = ref(null)
const quota = ref(null)
const dedicatedOn = ref(false)
const rotateOn = ref(false)

const importVisible = ref(false)
const importPool = ref('public')
const importList = ref('')
const importLocation = ref('平台住宅池')
const importTenantId = ref(null)
const importing = ref(false)
const tenants = ref([])

const logVisible = ref(false)
const logs = ref([])

const fetchList = async () => {
  loading.value = true
  try {
    if (isAdmin.value) {
      const data = await proxyIpApi.list({ page: page.value, size: size.value })
      list.value = data?.list || []
      total.value = data?.total || 0
    } else {
      await loadAllocated()
    }
  } finally {
    loading.value = false
  }
}

const loadAllocated = async () => {
  const uid = getCurrentUser()
  const data = await proxyIpApi.allocated({ tenantId: uid?.tenantId })
  list.value = data?.list || []
  total.value = list.value.length
  quota.value = data?.quota || null
  dedicatedOn.value = !!uid?.dedicatedIpPoolEnabled
  rotateOn.value = !!uid?.ipRotateEnabled
}

const openImport = async (pool) => {
  importPool.value = pool
  importVisible.value = true
  if (pool === 'dedicated' && !tenants.value.length) {
    const data = await tenantApi.list({ page: 1, size: 100 })
    tenants.value = data?.list || []
  }
}

const submitImport = async () => {
  importing.value = true
  try {
    await proxyIpApi.import({
      list: importList.value,
      location: importLocation.value,
      poolType: importPool.value,
      tenantId: importPool.value === 'dedicated' ? importTenantId.value : undefined
    })
    ElMessage.success('导入成功')
    importVisible.value = false
    importList.value = ''
    await fetchList()
  } finally {
    importing.value = false
  }
}

const handleCheck = async (row) => {
  checkingId.value = row.id
  try {
    await proxyIpApi.check(row.id)
    ElMessage.success('检测完成')
    await fetchList()
  } finally {
    checkingId.value = null
  }
}

const handleRemove = async (row) => {
  await ElMessageBox.confirm(`确认移除 ${row.address}？`, '提示')
  await proxyIpApi.remove(row.id)
  ElMessage.success('已移除')
  await fetchList()
}

const openLogs = async (row) => {
  logVisible.value = true
  const data = await proxyIpApi.accessLogs(row.id)
  logs.value = data?.list || []
}

const saveFlags = async () => {
  try {
    await premiumApi.updateIpFlags({
      dedicatedIpPoolEnabled: dedicatedOn.value,
      ipRotateEnabled: rotateOn.value
    })
    ElMessage.success('IP 池策略已更新')
  } catch {
    /* interceptor */
  }
}

onMounted(fetchList)
</script>

<style scoped>
.toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; gap: 12px; flex-wrap: wrap; }
.toolbar-left { display: flex; align-items: center; gap: 8px; }
.hint { color: #909399; font-size: 13px; }
.mono { font-family: Menlo, Monaco, Consolas, monospace; font-size: 12px; }
.pagination-wrap { margin-top: 16px; display: flex; justify-content: space-between; align-items: center; }
.total-tip { color: #909399; font-size: 13px; }
.page-title { margin: 0 0 12px; font-size: 16px; }
</style>
