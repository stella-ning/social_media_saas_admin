<template>
  <div class="funnel-page">
    <el-alert
      type="info"
      :closable="false"
      show-icon
      title="评论引流漏斗：采集 → 意向筛选 → 主页核验 → AI闲聊回复 → 敏感词风控 → CRM"
      style="margin-bottom: 12px"
    />

    <TenantScopeBar
      v-model="tenantId"
      :tenants="tenants"
      :loading="!tenantsReady"
      :visible="isAdmin"
      label="查看租户"
    />

    <el-row :gutter="12" class="stat-row">
      <el-col :xs="12" :sm="8" :md="4" v-for="s in statItems" :key="s.label">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-label">{{ s.label }}</div>
          <div class="stat-value">{{ s.value }}</div>
        </el-card>
      </el-col>
    </el-row>

    <el-card shadow="hover">
      <div class="toolbar">
        <el-input v-model="keyword" placeholder="搜索用户/评论/回复" clearable style="width: 220px" />
        <el-select v-model="funnelStage" clearable placeholder="漏斗阶段" style="width: 160px">
          <el-option label="已采集" value="collected" />
          <el-option label="已过滤" value="filtered" />
          <el-option label="主页未通过" value="homepage_failed" />
          <el-option label="黑名单" value="blacklisted" />
          <el-option label="待回复" value="ready_reply" />
          <el-option label="已回复" value="replied" />
          <el-option label="敏感词拦截" value="sensitive_block" />
          <el-option label="已入CRM" value="crm_pushed" />
          <el-option label="失败" value="failed" />
        </el-select>
        <el-button type="primary" @click="load">查询</el-button>
        <el-button @click="loadBlacklist">营销号黑名单</el-button>
      </div>

      <el-table v-loading="loading" :data="list" border stripe>
        <el-table-column prop="createTime" label="时间" width="160" />
        <el-table-column prop="taskName" label="任务" min-width="120" show-overflow-tooltip />
        <el-table-column v-if="isAdmin" prop="tenant" label="租户" width="120" show-overflow-tooltip />
        <el-table-column prop="commentUserName" label="评论用户" width="110" />
        <el-table-column prop="commentContent" label="原始评论" min-width="160" show-overflow-tooltip />
        <el-table-column label="主页核验" width="110">
          <template #default="{ row }">{{ row.homepageCheckLabel }}</template>
        </el-table-column>
        <el-table-column label="漏斗" width="120">
          <template #default="{ row }">
            <el-tag size="small" effect="plain">{{ row.funnelStageLabel }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="aiReplyContent" label="AI回复" min-width="140" show-overflow-tooltip />
        <el-table-column label="敏感词" width="100">
          <template #default="{ row }">
            <el-tag
              v-if="row.sensitiveWordCheckStatus"
              size="small"
              :type="row.sensitiveWordCheckStatus === 'block' ? 'danger' : 'success'"
            >
              {{ row.sensitiveWordCheckStatus }}
            </el-tag>
            <span v-else>—</span>
          </template>
        </el-table-column>
        <el-table-column prop="runStatus" label="状态" width="90" />
        <el-table-column prop="failReason" label="备注" min-width="120" show-overflow-tooltip />
      </el-table>

      <div class="pager">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="size"
          :total="total"
          layout="total, prev, pager, next"
          background
          @current-change="load"
        />
      </div>
    </el-card>

    <el-drawer v-model="blVisible" title="营销号黑名单" size="480px">
      <el-table :data="blacklist" border size="small">
        <el-table-column prop="nickname" label="昵称" width="100" />
        <el-table-column prop="platformUserId" label="用户ID" min-width="100" />
        <el-table-column prop="reason" label="原因" min-width="140" show-overflow-tooltip />
      </el-table>
    </el-drawer>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { commentFunnelApi } from '@/api'
import { useTenantScope } from '@/composables/useTenantScope'
import TenantScopeBar from '@/components/TenantScopeBar.vue'

const { tenantId, isAdmin, tenants, tenantsReady, withTenant } = useTenantScope({
  autoSelect: false
})

const loading = ref(false)
const list = ref([])
const total = ref(0)
const page = ref(1)
const size = ref(20)
const keyword = ref('')
const funnelStage = ref('')
const stats = ref({})
const blVisible = ref(false)
const blacklist = ref([])

const statItems = computed(() => [
  { label: '采集总数', value: stats.value.total ?? 0 },
  { label: '意向留言', value: stats.value.inquiry ?? 0 },
  { label: '真实消费者', value: stats.value.realConsumer ?? 0 },
  { label: '营销号', value: stats.value.marketing ?? 0 },
  { label: '已回复', value: stats.value.replied ?? 0 },
  { label: '敏感词拦截', value: stats.value.sensitiveBlocked ?? 0 }
])

const load = async () => {
  loading.value = true
  try {
    const params = withTenant({
      page: page.value,
      size: size.value,
      keyword: keyword.value || undefined,
      funnelStage: funnelStage.value || undefined
    })
    const [data, st] = await Promise.all([
      commentFunnelApi.records(params),
      commentFunnelApi.stats(withTenant())
    ])
    list.value = data?.list || []
    total.value = data?.total || 0
    stats.value = st || {}
  } finally {
    loading.value = false
  }
}

const loadBlacklist = async () => {
  const data = await commentFunnelApi.blacklist(withTenant({ page: 1, size: 100 }))
  blacklist.value = data?.list || []
  blVisible.value = true
}

watch(tenantId, () => {
  page.value = 1
  load()
}, { immediate: true })
</script>

<style scoped>
.stat-row { margin-bottom: 12px; }
.stat-card { margin-bottom: 8px; }
.stat-label { color: #909399; font-size: 13px; }
.stat-value { font-size: 22px; font-weight: 700; margin-top: 4px; }
.toolbar { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
.pager { display: flex; justify-content: flex-end; margin-top: 12px; }
</style>
