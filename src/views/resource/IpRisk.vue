<template>
  <div>
    <el-alert type="info" :closable="false" title="专业版起开放：批量检测已分配公共 IP 的风险等级与访问日志" style="margin-bottom: 12px" />
    <TenantScopeBar
      v-model="tenantId"
      :tenants="tenants"
      :loading="!tenantsReady"
      :visible="isAdmin"
      label="检测租户"
    />
    <el-card shadow="hover">
      <div class="toolbar">
        <h3>IP 风险检测</h3>
        <el-button type="primary" :disabled="!tenantId" :loading="checking" @click="runCheck">批量风险检测</el-button>
      </div>
      <el-table v-loading="loading" :data="list" border>
        <el-table-column prop="address" label="地址" min-width="160" />
        <el-table-column prop="riskLevel" label="风险等级" width="100" />
        <el-table-column prop="status" label="状态" width="100" />
        <el-table-column prop="lastRiskAt" label="最近检测" width="160" />
        <el-table-column prop="latencyMs" label="延迟ms" width="90" />
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { proxyIpApi } from '@/api'
import { useTenantScope } from '@/composables/useTenantScope'
import TenantScopeBar from '@/components/TenantScopeBar.vue'

const { tenantId, isAdmin, tenants, tenantsReady, withTenant } = useTenantScope()
const list = ref([])
const checking = ref(false)
const loading = ref(false)

const load = async () => {
  if (!tenantId.value) {
    list.value = []
    return
  }
  loading.value = true
  try {
    const data = await proxyIpApi.allocated(withTenant())
    list.value = data?.list || []
  } catch {
    list.value = []
  } finally {
    loading.value = false
  }
}

watch(tenantId, load, { immediate: true })

const runCheck = async () => {
  if (!tenantId.value) {
    ElMessage.warning('请先选择租户')
    return
  }
  checking.value = true
  try {
    const data = await proxyIpApi.batchRiskCheck(withTenant())
    list.value = data?.list || []
    ElMessage.success('检测完成')
  } finally {
    checking.value = false
  }
}
</script>

<style scoped>
.toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
h3 { margin: 0; }
</style>
