<template>
  <div class="crawler-page">
    <el-card shadow="hover" class="table-card">
      <!-- 顶部：租户筛选、搜索、新建爬虫任务 -->
      <div class="toolbar">
        <div class="toolbar-left">
          <el-select
            v-if="showTenantFilter"
            v-model="query.tenantId"
            placeholder="所有租户"
            clearable
            style="width: 200px"
          >
            <el-option label="所有租户" value="" />
            <el-option
              v-for="t in tenantOptions"
              :key="t.id"
              :label="t.name"
              :value="t.id"
            />
          </el-select>
          <el-input
            v-model="query.keyword"
            placeholder="搜索任务名称/关键词"
            clearable
            style="width: 220px"
            :prefix-icon="Search"
          />
          <el-button @click="handleSearch">搜索</el-button>
        </div>
        <el-button type="primary" :icon="Plus" @click="dialogVisible = true">新建爬虫任务</el-button>
      </div>

      <!-- 表格：任务名称、平台、关键词/监控对象、所属租户、执行频率、状态、今日采集、操作 -->
      <el-table v-loading="loading" :data="list" border stripe style="width: 100%">
        <el-table-column prop="name" label="任务名称" min-width="160" />
        <el-table-column prop="platform" label="平台" width="100" align="center" />
        <el-table-column prop="target" label="关键词/监控对象" min-width="200" show-overflow-tooltip />
        <el-table-column prop="tenant" label="所属租户" min-width="140" />
        <el-table-column prop="frequency" label="执行频率" width="110" align="center" />
        <el-table-column label="状态" width="110" align="center">
          <template #default="{ row }">
            <span class="status-dot" :class="row.status">
              <i class="dot"></i>
              {{ row.status === 'running' ? '运行中' : '已暂停' }}
            </span>
          </template>
        </el-table-column>
        <el-table-column prop="todayCount" label="今日采集" width="100" align="center">
          <template #default="{ row }">
            <strong>{{ row.todayCount }}</strong>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200" align="center" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="handleLog(row)">日志</el-button>
            <el-button link type="primary" @click="handleEdit(row)">编辑</el-button>
            <el-button
              link
              :type="row.status === 'running' ? 'danger' : 'success'"
              :loading="row._toggleLoading"
              @click="toggleStatus(row)"
            >
              {{ row.status === 'running' ? '停止' : '启动' }}
            </el-button>
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

    <!-- 新建爬虫任务弹窗（独立组件） -->
    <CreateTaskDialog
      v-model="dialogVisible"
      :submitting="creating"
      @submit="onCreateTask"
    />

    <!-- 日志抽屉 -->
    <el-drawer v-model="logVisible" :title="`任务日志 - ${logTask?.name || ''}`" size="420px">
      <div v-loading="logsLoading">
        <el-empty v-if="!taskLogs.length" description="暂无日志" />
        <el-timeline v-else>
          <el-timeline-item
            v-for="(log, i) in taskLogs"
            :key="log.id || i"
            :timestamp="log.time"
            :type="log.type"
            placement="top"
          >
            {{ log.content }}
          </el-timeline-item>
        </el-timeline>
      </div>
    </el-drawer>
  </div>
</template>

<script setup>
/**
 * 爬虫任务管理
 * - useListQuery + crawlerTaskApi.list
 * - 新建/切换状态/查看日志
 */
import { ref, onMounted } from 'vue'
import { Search, Plus } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import CreateTaskDialog from '@/components/CreateTaskDialog.vue'
import { crawlerTaskApi, tenantApi } from '@/api'
import { useListQuery } from '@/composables/useListQuery'

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
    crawlerTaskApi.list({
      ...p,
      tenant_id: p.tenantId || undefined,
      tenantId: undefined
    }),
  { tenantId: '', keyword: '' }
)

const showTenantFilter = ref(true)
const tenantOptions = ref([])
const dialogVisible = ref(false)
const creating = ref(false)

/** 加载租户下拉 */
const loadTenantOptions = async () => {
  try {
    const data = await tenantApi.list({ page: 1, size: 100 })
    tenantOptions.value = (data?.list || []).map((t) => ({ id: t.id, name: t.name }))
  } catch {
    showTenantFilter.value = false
    tenantOptions.value = []
  }
}

/** 切换任务运行/暂停 */
const toggleStatus = async (row) => {
  const wasRunning = row.status === 'running'
  row._toggleLoading = true
  try {
    await crawlerTaskApi.toggle(row.id)
    ElMessage.success(`任务「${row.name}」已${wasRunning ? '停止' : '启动'}`)
    await fetchList()
  } catch {
    // 错误已在拦截器提示
  } finally {
    row._toggleLoading = false
  }
}

/** 编辑（演示提示） */
const handleEdit = (row) => {
  ElMessage.info(`编辑任务「${row.name}」（演示：可扩展为回填弹窗）`)
}

/** 日志抽屉 */
const logVisible = ref(false)
const logTask = ref(null)
const taskLogs = ref([])
const logsLoading = ref(false)

const handleLog = async (row) => {
  logTask.value = row
  logVisible.value = true
  logsLoading.value = true
  taskLogs.value = []
  try {
    const data = await crawlerTaskApi.logs(row.id)
    taskLogs.value = data?.list || []
  } catch {
    // 错误已在拦截器提示
  } finally {
    logsLoading.value = false
  }
}

/** 新建任务回调 */
const onCreateTask = async (form) => {
  creating.value = true
  try {
    await crawlerTaskApi.create(form)
    ElMessage.success('爬虫任务已创建并启动')
    dialogVisible.value = false
    await fetchList()
  } catch {
    // 错误已在拦截器提示
  } finally {
    creating.value = false
  }
}

onMounted(async () => {
  await Promise.all([fetchList(), loadTenantOptions()])
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
.status-dot {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
}
.status-dot .dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  display: inline-block;
}
.status-dot.running {
  color: #67c23a;
}
.status-dot.running .dot {
  background: #67c23a;
}
.status-dot.paused {
  color: #909399;
}
.status-dot.paused .dot {
  background: #909399;
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
