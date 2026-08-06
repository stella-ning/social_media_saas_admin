<template>
  <div class="dashboard-page" v-loading="loading">
    <div v-if="scopeLabel" class="scope-bar">
      <el-tag type="info" effect="plain">数据范围：{{ scopeLabel }}</el-tag>
    </div>

    <!-- ========== 顶部统计卡片 ========== -->
    <el-row :gutter="20" class="stat-row">
      <el-col :xs="24" :sm="12" :md="6" v-for="item in statCards" :key="item.title">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-card-inner">
            <div class="stat-info">
              <p class="stat-label">{{ item.title }}</p>
              <h3 class="stat-value">{{ item.value }}</h3>
              <p class="stat-trend" :class="item.trendUp ? 'up' : 'down'">
                <template v-if="item.percent && item.percent !== '—'">
                  <el-icon><Top v-if="item.trendUp" /><Bottom v-else /></el-icon>
                  {{ item.percent }} {{ item.compare }}
                </template>
                <template v-else>
                  <span class="stat-sub">{{ item.compare }}</span>
                </template>
              </p>
            </div>
            <div class="stat-icon" :style="{ background: item.bg, color: item.color }">
              <el-icon :size="24"><component :is="item.icon" /></el-icon>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- ========== ECharts ========== -->
    <el-row :gutter="20" class="chart-row">
      <el-col :xs="24" :md="14">
        <el-card shadow="hover" class="chart-card">
          <template #header>
            <span class="chart-title">近 7 日业务趋势</span>
          </template>
          <v-chart class="chart" :option="trendOption" autoresize />
        </el-card>
      </el-col>

      <el-col :xs="24" :md="10">
        <el-card shadow="hover" class="chart-card">
          <template #header>
            <span class="chart-title">{{ pieTitle }}</span>
          </template>
          <v-chart class="chart" :option="pieOption" autoresize />
        </el-card>
      </el-col>
    </el-row>

    <el-row v-if="platformBars.length" :gutter="20" class="chart-row">
      <el-col :span="24">
        <el-card shadow="hover" class="chart-card">
          <template #header>
            <span class="chart-title">社媒平台任务分布</span>
          </template>
          <div class="platform-bars">
            <div v-for="p in platformBars" :key="p.label" class="platform-bar-item">
              <span class="p-label">{{ p.label }}</span>
              <el-progress
                :percentage="platformPercent(p.value)"
                :stroke-width="14"
                :format="() => String(p.value)"
              />
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <div class="page-version">SocialAI SaaS 社媒采集运营后台 · {{ version }}</div>
  </div>
</template>

<script setup>
/**
 * 首页仪表盘：按角色/租户展示真实平台数据
 */
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { dashboardApi } from '@/api'
import { getCurrentRole } from '@/utils/auth'

const loading = ref(false)
const version = ref('v1.0.0 Stable')
const scopeLabel = ref('')
const platformBars = ref([])

const CARD_STYLES = [
  { icon: 'List', bg: '#ecf5ff', color: '#409eff' },
  { icon: 'User', bg: '#f0f9eb', color: '#67c23a' },
  { icon: 'Trophy', bg: '#fdf6ec', color: '#e6a23c' },
  { icon: 'OfficeBuilding', bg: '#f4f4f5', color: '#909399' }
]

const pieTitle = computed(() =>
  getCurrentRole() === 'operator' ? '我的客户意向分布' : '客户意向分布'
)

const platformMax = computed(() =>
  Math.max(1, ...platformBars.value.map((p) => p.value || 0))
)

const platformPercent = (value) =>
  Math.round(((value || 0) / platformMax.value) * 100)

const statCards = ref([])

const trendOption = ref({
  tooltip: { trigger: 'axis' },
  legend: { data: [], bottom: 0 },
  grid: { top: 40, left: 40, right: 20, bottom: 40 },
  xAxis: { type: 'category', data: [] },
  yAxis: { type: 'value', minInterval: 1 },
  series: []
})

const pieOption = ref({
  tooltip: { trigger: 'item' },
  legend: { orient: 'vertical', left: 'left' },
  series: [
    {
      type: 'pie',
      radius: '70%',
      data: [],
      emphasis: {
        itemStyle: {
          shadowBlur: 10,
          shadowOffsetX: 0,
          shadowColor: 'rgba(0, 0, 0, 0.2)'
        }
      }
    }
  ]
})

const loadOverview = async () => {
  loading.value = true
  try {
    const data = await dashboardApi.overview()

    scopeLabel.value = data?.scope?.label || ''
    platformBars.value = data?.platformBreakdown || []

    statCards.value = (data?.statCards || []).map((item, i) => ({
      ...item,
      ...(CARD_STYLES[i] || CARD_STYLES[0])
    }))

    version.value = data?.version || 'v1.0.0 Stable'

    const trend = data?.trendChart || {}
    trendOption.value = {
      tooltip: { trigger: 'axis' },
      legend: {
        data: (trend.series || []).map((s) => s.name),
        bottom: 0
      },
      grid: { top: 40, left: 40, right: 20, bottom: 40 },
      xAxis: { type: 'category', data: trend.xAxis || [] },
      yAxis: { type: 'value', minInterval: 1 },
      series: (trend.series || []).map((s) => ({
        name: s.name,
        type: 'line',
        smooth: true,
        data: s.data || [],
        itemStyle: { color: s.color },
        lineStyle: { color: s.color }
      }))
    }

    const pie = data?.intentPie || []
    const pieData = pie.map((item) => ({
      value: item.value,
      name: item.name,
      itemStyle: { color: item.color }
    }))
    pieOption.value = {
      tooltip: { trigger: 'item' },
      legend: { orient: 'vertical', left: 'left' },
      series: [
        {
          type: 'pie',
          radius: '70%',
          data: pieData.some((d) => d.value > 0)
            ? pieData
            : [{ value: 0, name: '暂无线索', itemStyle: { color: '#dcdfe6' } }],
          emphasis: {
            itemStyle: {
              shadowBlur: 10,
              shadowOffsetX: 0,
              shadowColor: 'rgba(0, 0, 0, 0.2)'
            }
          }
        }
      ]
    }
  } catch {
    // 错误已在 request 拦截器提示
  } finally {
    loading.value = false
  }
}

const onAuthUpdated = () => {
  loadOverview()
}

onMounted(() => {
  loadOverview()
  window.addEventListener('auth-user-updated', onAuthUpdated)
})

onUnmounted(() => {
  window.removeEventListener('auth-user-updated', onAuthUpdated)
})
</script>

<style scoped>
.dashboard-page {
  min-height: 100%;
}

.scope-bar {
  margin-bottom: 12px;
}

.stat-row {
  margin-bottom: 20px;
}

.stat-card {
  border-radius: 4px;
  margin-bottom: 12px;
}

.stat-card-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.stat-label {
  font-size: 14px;
  color: #909399;
  margin-bottom: 8px;
}

.stat-value {
  font-size: 28px;
  font-weight: 700;
  color: #303133;
  line-height: 1.2;
}

.stat-trend {
  font-size: 12px;
  margin-top: 8px;
  display: flex;
  align-items: center;
  gap: 2px;
}
.stat-trend.up {
  color: #67c23a;
}
.stat-trend.down {
  color: #f56c6c;
}
.stat-sub {
  color: #909399;
}

.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.chart-row {
  margin-bottom: 20px;
}

.chart-card {
  border-radius: 4px;
  margin-bottom: 12px;
}

.chart-title {
  font-size: 16px;
  font-weight: 700;
  color: #303133;
}

.chart {
  height: 320px;
  width: 100%;
}

.platform-bars {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 8px 4px 4px;
}

.platform-bar-item {
  display: grid;
  grid-template-columns: 72px 1fr;
  align-items: center;
  gap: 12px;
}

.p-label {
  font-size: 14px;
  color: #606266;
}

.page-version {
  text-align: center;
  color: #c0c4cc;
  font-size: 12px;
  padding: 16px 0 8px;
}
</style>
