<template>
  <div class="finance-page">
    <el-card shadow="hover">
      <div class="toolbar">
        <h3 class="title">平台财务报表</h3>
        <div>
          <el-date-picker
            v-model="range"
            type="daterange"
            value-format="YYYY-MM-DD"
            start-placeholder="开始"
            end-placeholder="结束"
            style="margin-right: 8px"
          />
          <el-button type="primary" @click="load">刷新</el-button>
          <el-button @click="exportCsv">导出消耗明细</el-button>
        </div>
      </div>

      <el-row :gutter="12" class="kpi-row">
        <el-col :span="6" v-for="k in kpis" :key="k.label">
          <div class="kpi">
            <div class="kpi-label">{{ k.label }}</div>
            <div class="kpi-value">{{ k.value }}</div>
          </div>
        </el-col>
      </el-row>

      <el-alert
        v-if="overview?.note"
        :title="overview.note"
        type="warning"
        :closable="false"
        style="margin: 12px 0"
      />

      <el-row :gutter="16">
        <el-col :span="14">
          <div ref="chartRef" class="chart"></div>
        </el-col>
        <el-col :span="10">
          <h4>三档套餐营收占比</h4>
          <el-table :data="overview?.revenueByPackage || []" border size="small">
            <el-table-column prop="packageLabel" label="套餐" />
            <el-table-column prop="revenue" label="营收(元)" />
            <el-table-column prop="share" label="占比%">
              <template #default="{ row }">{{ row.share }}%</template>
            </el-table-column>
          </el-table>
        </el-col>
      </el-row>
    </el-card>

    <el-card shadow="hover" style="margin-top: 16px">
      <h3 class="title">增值功能使用日志</h3>
      <el-table :data="premiumLogs" border stripe max-height="320">
        <el-table-column prop="usedAt" label="时间" width="150" />
        <el-table-column prop="tenant" label="租户" width="140" />
        <el-table-column prop="featureLabel" label="功能" width="160" />
        <el-table-column prop="detail" label="明细" min-width="200" />
      </el-table>
    </el-card>

    <el-card shadow="hover" style="margin-top: 16px">
      <h3 class="title">租户公共资源消耗明细</h3>
      <el-table :data="consume" border stripe max-height="420">
        <el-table-column prop="statDate" label="日期" width="110" />
        <el-table-column prop="tenant" label="租户" width="140" />
        <el-table-column prop="proxyRequestCount" label="公共IP请求" width="110" />
        <el-table-column prop="aiCallCount" label="AI调用" width="90" />
        <el-table-column prop="proxyIpCost" label="代理成本" width="90" />
        <el-table-column prop="aiTokenCost" label="AI成本" width="90" />
        <el-table-column prop="totalCost" label="总成本" width="90" />
        <el-table-column prop="revenue" label="营收" width="90" />
        <el-table-column prop="netProfit" label="净利润" width="90" />
        <el-table-column prop="grossMargin" label="毛利率%" width="90" />
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import * as echarts from 'echarts'
import { financeApi } from '@/api'
import { ElMessage } from 'element-plus'

const range = ref([])
const overview = ref(null)
const consume = ref([])
const premiumLogs = ref([])
const chartRef = ref(null)
let chart

const kpis = computed(() => {
  const o = overview.value || {}
  return [
    { label: '总收入', value: `¥${o.totalRevenue ?? 0}` },
    { label: '公共IP成本', value: `¥${o.proxyIpCost ?? 0}` },
    { label: 'AI-Token成本', value: `¥${o.aiTokenCost ?? 0}` },
    { label: '净利润 / 毛利率', value: `¥${o.netProfit ?? 0} / ${o.grossMargin ?? 0}%` }
  ]
})

const renderChart = () => {
  if (!chartRef.value) return
  if (!chart) chart = echarts.init(chartRef.value)
  const trend = overview.value?.trend || []
  chart.setOption({
    tooltip: { trigger: 'axis' },
    legend: { data: ['营收', '成本', '利润'] },
    grid: { left: 40, right: 20, top: 40, bottom: 30 },
    xAxis: { type: 'category', data: trend.map((t) => t.date) },
    yAxis: { type: 'value' },
    series: [
      { name: '营收', type: 'line', smooth: true, data: trend.map((t) => t.revenue) },
      { name: '成本', type: 'line', smooth: true, data: trend.map((t) => t.cost) },
      { name: '利润', type: 'line', smooth: true, data: trend.map((t) => t.profit) }
    ]
  })
}

const load = async () => {
  const params = {}
  if (range.value?.length === 2) {
    params.from = range.value[0]
    params.to = range.value[1]
  }
  overview.value = await financeApi.overview(params)
  const c = await financeApi.consume(params)
  consume.value = c?.list || []
  const p = await financeApi.premiumLogs()
  premiumLogs.value = p?.list || []
  await nextTick()
  renderChart()
}

const exportCsv = async () => {
  const params = {}
  if (range.value?.length === 2) {
    params.from = range.value[0]
    params.to = range.value[1]
  }
  const blob = await financeApi.exportConsume(params)
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'resource-consume.csv'
  a.click()
  URL.revokeObjectURL(url)
  ElMessage.success('已导出')
}

onMounted(load)
</script>

<style scoped>
.toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px; }
.title { margin: 0 0 12px; font-size: 16px; }
.kpi-row { margin-bottom: 8px; }
.kpi { background: #f5f7fa; border-radius: 8px; padding: 14px; }
.kpi-label { color: #909399; font-size: 13px; }
.kpi-value { font-size: 20px; font-weight: 600; margin-top: 6px; }
.chart { height: 320px; width: 100%; }
</style>
