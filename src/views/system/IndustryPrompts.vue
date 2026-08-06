<template>
  <div>
    <el-alert type="info" :closable="false" title="行业话术模板按套餐解锁：基础版可见 Level-1，更高套餐解锁更多素材" style="margin-bottom: 12px" />
    <TenantScopeBar
      v-model="tenantId"
      :tenants="tenants"
      :loading="!tenantsReady"
      :visible="isAdmin"
      label="查看租户"
    />
    <el-empty v-if="!tenantId" description="请先选择租户" />
    <el-row v-else :gutter="16">
      <el-col v-for="item in list" :key="item.id" :xs="24" :md="12" :lg="8">
        <el-card shadow="hover" class="card" v-loading="loading">
          <div class="head">
            <strong>{{ item.title }}</strong>
            <el-tag size="small" :type="item.unlocked ? 'success' : 'info'">{{ item.minPackageLabel }}</el-tag>
          </div>
          <div class="meta">{{ item.industry }} · Level-{{ item.templateLevel }}</div>
          <p class="content">{{ item.content }}</p>
          <el-tag v-if="!item.unlocked" type="warning" size="small">升级套餐后解锁全文</el-tag>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { premiumApi } from '@/api'
import { useTenantScope } from '@/composables/useTenantScope'
import TenantScopeBar from '@/components/TenantScopeBar.vue'

const { tenantId, isAdmin, tenants, tenantsReady, withTenant } = useTenantScope()
const list = ref([])
const loading = ref(false)

const load = async () => {
  if (!tenantId.value) {
    list.value = []
    return
  }
  loading.value = true
  try {
    const data = await premiumApi.industryPrompts(withTenant())
    list.value = data?.list || []
  } catch {
    list.value = []
  } finally {
    loading.value = false
  }
}

watch(tenantId, load, { immediate: true })
</script>

<style scoped>
.card { margin-bottom: 16px; min-height: 180px; }
.head { display: flex; justify-content: space-between; gap: 8px; }
.meta { color: #909399; font-size: 12px; margin: 8px 0; }
.content { color: #606266; font-size: 13px; line-height: 1.6; min-height: 60px; }
</style>
