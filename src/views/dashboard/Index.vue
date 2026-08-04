<template>
  <div class="dashboard-page" v-loading="loading">
    <!-- ========== 顶部 4 个数据统计卡片 ========== -->
    <el-row :gutter="20" class="stat-row">
      <el-col :xs="24" :sm="12" :md="6" v-for="item in statCards" :key="item.title">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-card-inner">
            <div class="stat-info">
              <p class="stat-label">{{ item.title }}</p>
              <h3 class="stat-value">{{ item.value }}</h3>
              <p class="stat-trend" :class="item.trendUp ? 'up' : 'down'">
                <el-icon><Top v-if="item.trendUp" /><Bottom v-else /></el-icon>
                {{ item.percent }} {{ item.compare }}
              </p>
            </div>
            <div class="stat-icon" :style="{ background: item.bg, color: item.color }">
              <el-icon :size="24"><component :is="item.icon" /></el-icon>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- ========== ECharts 图表区域 ========== -->
    <el-row :gutter="20" class="chart-row">
      <!-- 图表1：任务趋势折线图 -->
      <el-col :xs="24" :md="14">
        <el-card shadow="hover" class="chart-card">
          <template #header>
            <span class="chart-title">任务趋势图</span>
          </template>
          <v-chart class="chart" :option="trendOption" autoresize />
        </el-card>
      </el-col>

      <!-- 图表2：客户意向饼图 -->
      <el-col :xs="24" :md="10">
        <el-card shadow="hover" class="chart-card">
          <template #header>
            <span class="chart-title">客户意向分布图</span>
          </template>
          <v-chart class="chart" :option="pieOption" autoresize />
        </el-card>
      </el-col>
    </el-row>

    <!-- 底部版本标注 -->
    <div class="page-version">SocialAI SaaS 社媒采集运营后台 · {{ version }}</div>
  </div>
</template>

<script setup>
/**
 * 首页仪表盘
 * - onMounted 调用 dashboardApi.overview 填充统计卡片与图表
 * - 保留原有卡片/图表布局与样式
 */
import { ref, onMounted } from 'vue'
import { dashboardApi } from '@/api'

const loading = ref(false)
const version = ref('v1.0.0 Stable')

/** 卡片图标与配色（后端不返回，前端按序补齐） */
const CARD_STYLES = [
  { icon: 'List', bg: '#ecf5ff', color: '#409eff' },
  { icon: 'User', bg: '#f0f9eb', color: '#67c23a' },
  { icon: 'Trophy', bg: '#fdf6ec', color: '#e6a23c' },
  { icon: 'OfficeBuilding', bg: '#f4f4f5', color: '#909399' }
]

const statCards = ref([])

/** 任务趋势折线图配置 */
const trendOption = ref({
  tooltip: { trigger: 'axis' },
  legend: { data: [], bottom: 0 },
  grid: { top: 40, left: 40, right: 20, bottom: 40 },
  xAxis: { type: 'category', data: [] },
  yAxis: { type: 'value' },
  series: []
})

/** 客户意向饼图配置 */
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

/** 加载仪表盘概览数据 */
const loadOverview = async () => {
  loading.value = true
  try {
    const data = await dashboardApi.overview()

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
      yAxis: { type: 'value' },
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
    pieOption.value = {
      tooltip: { trigger: 'item' },
      legend: { orient: 'vertical', left: 'left' },
      series: [
        {
          type: 'pie',
          radius: '70%',
          data: pie.map((item) => ({
            value: item.value,
            name: item.name,
            itemStyle: { color: item.color }
          })),
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

onMounted(loadOverview)
</script>

<style scoped>
.dashboard-page {
  min-height: 100%;
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

.page-version {
  text-align: center;
  color: #c0c4cc;
  font-size: 12px;
  padding: 16px 0 8px;
}
</style>
