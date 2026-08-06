<template>
  <div class="purchase-page">
    <el-alert
      type="success"
      :closable="false"
      show-icon
      title="所有套餐的 IP 资源均由平台统一托管的公共住宅代理池提供，禁止租户上传自有代理。支持升级、降级与续费。"
      style="margin-bottom: 16px"
    />

    <TenantScopeBar
      v-model="tenantId"
      :tenants="tenants"
      :loading="!tenantsReady"
      :visible="isAdmin"
      label="变更套餐目标租户"
    />

    <el-row :gutter="16">
      <el-col v-for="pkg in list" :key="pkg.packageCode" :xs="24" :md="8">
        <el-card shadow="hover" class="pkg-card" :class="cardClass(pkg)">
          <div class="pkg-head">
            <h3>
              {{ pkg.packageLabel }}
              <el-tag v-if="pkg.packageCode === currentPackage" size="small" type="success" style="margin-left: 6px">当前</el-tag>
            </h3>
            <div class="price">¥{{ pkg.priceMonthly }}<small>/月</small></div>
          </div>
          <el-tag size="small" type="info">最高模板 Level-{{ pkg.maxTemplateLevel }}</el-tag>
          <ul class="quota">
            <li>Prompt {{ fmt(pkg.maxPrompt) }} · 知识库 {{ fmt(pkg.maxKnowledge) }}</li>
            <li>日 AI {{ fmt(pkg.dailyAiLimit) }} · 爬虫 {{ fmt(pkg.maxCrawlerTask) }}</li>
            <li>社媒账号 {{ fmt(pkg.maxSocialAccount) }}</li>
            <li>日公共IP请求 {{ fmt(pkg.dailyProxyRequestLimit) }}</li>
            <li>平台：{{ formatPlatforms(pkg.allowPlatforms) }}</li>
          </ul>
          <h4>增值权益</h4>
          <ul class="features">
            <li v-for="(f, i) in pkg.premiumFeatures || []" :key="i">{{ f }}</li>
          </ul>
          <el-button
            :type="actionType(pkg)"
            style="width: 100%"
            :disabled="!tenantId"
            :loading="buying === pkg.packageCode"
            @click="changePackage(pkg)"
          >
            {{ actionLabel(pkg) }}
          </el-button>
        </el-card>
      </el-col>
    </el-row>

    <el-card shadow="hover" style="margin-top: 16px">
      <h3>订单记录</h3>
      <el-table :data="orders" border>
        <el-table-column prop="orderNo" label="订单号" min-width="160" />
        <el-table-column prop="tenant" label="租户" width="140" />
        <el-table-column prop="packageLabel" label="套餐" width="100" />
        <el-table-column prop="remark" label="变更说明" min-width="200" show-overflow-tooltip />
        <el-table-column prop="amount" label="金额" width="90" />
        <el-table-column prop="expiresAt" label="到期" width="120" />
        <el-table-column prop="status" label="状态" width="90" />
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { financeApi, authApi } from '@/api'
import { updateCurrentUser } from '@/utils/auth'
import { formatPlatforms } from '@/utils/platform'
import { useTenantScope } from '@/composables/useTenantScope'
import TenantScopeBar from '@/components/TenantScopeBar.vue'

const RANK = { basic: 1, pro: 2, ent: 3 }

const {
  tenantId,
  isAdmin,
  tenants,
  tenantsReady,
  currentPackageCode,
  withTenant,
  reloadTenants,
  patchTenantPackage
} = useTenantScope()

const list = ref([])
const orders = ref([])
const buying = ref('')

/** 与 UI 绑定的当前套餐（独立 ref，升降级即时生效） */
const currentPackage = currentPackageCode

const fmt = (v) => (v == null ? '不限' : v)

const changeKind = (pkg) => {
  const cur = RANK[currentPackage.value] || 0
  const next = RANK[pkg.packageCode] || 0
  if (!cur) return 'buy'
  if (next > cur) return 'upgrade'
  if (next < cur) return 'downgrade'
  return 'renew'
}

const actionLabel = (pkg) => {
  switch (changeKind(pkg)) {
    case 'upgrade':
      return '升级到此套餐'
    case 'downgrade':
      return '降级到此套餐'
    case 'renew':
      return '续费当前套餐'
    default:
      return '购买此套餐'
  }
}

const actionType = (pkg) => {
  switch (changeKind(pkg)) {
    case 'upgrade':
      return 'primary'
    case 'downgrade':
      return 'warning'
    case 'renew':
      return 'success'
    default:
      return 'primary'
  }
}

const cardClass = (pkg) => ({
  current: pkg.packageCode === currentPackage.value,
  downgrade: changeKind(pkg) === 'downgrade'
})

const loadCatalog = async () => {
  const data = await financeApi.catalog()
  list.value = data?.list || []
}

const loadOrders = async () => {
  if (!tenantId.value) {
    orders.value = []
    return
  }
  const o = await financeApi.orders(withTenant())
  orders.value = o?.list || []
}

watch(tenantId, () => {
  loadOrders()
}, { immediate: true })

const confirmText = (pkg, kind) => {
  const base = `目标套餐：${pkg.packageLabel}（¥${pkg.priceMonthly}/月）`
  if (kind === 'downgrade') {
    return [
      `确认降级到「${pkg.packageLabel}」？`,
      base,
      '',
      '降级后系统将自动：',
      '· 超出上限的运行中爬虫任务暂停',
      '· AI 模板等级降至新套餐允许范围',
      '· 关闭更高档专属能力（专属IP池/轮换/白标等）',
      '· 平台与增值功能按新套餐重新鉴权'
    ].join('\n')
  }
  if (kind === 'upgrade') {
    return [
      `确认升级到「${pkg.packageLabel}」？`,
      base,
      '',
      '升级后将解锁：',
      ...((pkg.premiumFeatures || []).slice(0, 4).map((f) => `· ${f}`))
    ].join('\n')
  }
  if (kind === 'renew') {
    return `确认续费「${pkg.packageLabel}」1 个月？\n${base}\n有效期将在当前基础上延长。`
  }
  return `确认购买「${pkg.packageLabel}」？\n${base}`
}

const changePackage = async (pkg) => {
  if (!tenantId.value) {
    ElMessage.warning('请先选择目标租户')
    return
  }
  const kind = changeKind(pkg)
  await ElMessageBox.confirm(confirmText(pkg, kind), actionLabel(pkg), {
    type: kind === 'downgrade' ? 'warning' : 'info',
    confirmButtonText: kind === 'downgrade' ? '确认降级' : '确认',
    cancelButtonText: '取消'
  })
  buying.value = pkg.packageCode
  try {
    const data = await financeApi.purchase(withTenant({ packageCode: pkg.packageCode, months: 1 }))
    // 立即刷新本页套餐状态（无需整页刷新）
    patchTenantPackage(tenantId.value, pkg.packageCode)
    await reloadTenants()
    currentPackage.value = pkg.packageCode

    const paused = data?.pausedCrawlerCount || 0
    if (kind === 'downgrade' && paused > 0) {
      ElMessage.warning(`降级成功，已自动暂停 ${paused} 个超额爬虫任务`)
    } else {
      ElMessage.success(
        kind === 'upgrade' ? '升级成功' : kind === 'downgrade' ? '降级成功' : '续费成功'
      )
    }

    // 租户账号同步本地会话中的 package
    try {
      const me = await authApi.me()
      if (me) updateCurrentUser(me)
    } catch {
      /* ignore */
    }
    await loadOrders()
  } finally {
    buying.value = ''
  }
}

onMounted(loadCatalog)
</script>

<style scoped>
.pkg-card { margin-bottom: 16px; min-height: 480px; }
.pkg-card.current { border-color: #67c23a; }
.pkg-card.downgrade { border-color: #e6a23c; }
.pkg-head { display: flex; justify-content: space-between; align-items: baseline; }
.price { font-size: 28px; font-weight: 700; color: #303133; }
.price small { font-size: 13px; color: #909399; }
.quota, .features { padding-left: 18px; color: #606266; font-size: 13px; line-height: 1.7; }
h4 { margin: 12px 0 6px; font-size: 14px; }
</style>
