<template>
  <div class="crm-page">
    <el-card shadow="hover" class="table-card">
      <!-- 顶部：搜索、租户筛选、意向等级、导出 -->
      <div class="toolbar">
        <div class="toolbar-left">
          <el-input
            v-model="query.keyword"
            placeholder="客户昵称/联系方式"
            clearable
            style="width: 220px"
            :prefix-icon="Search"
            @keyup.enter="handleSearch"
          />
          <el-select
            v-if="tenantOptions.length"
            v-model="query.tenantId"
            placeholder="所有租户"
            clearable
            style="width: 160px"
          >
            <el-option label="所有租户" value="" />
            <el-option
              v-for="t in tenantOptions"
              :key="t.id"
              :label="t.name"
              :value="t.id"
            />
          </el-select>
          <el-select v-model="query.intent" placeholder="意向等级" clearable style="width: 130px">
            <el-option label="高意向" value="high" />
            <el-option label="中意向" value="mid" />
            <el-option label="低意向" value="low" />
          </el-select>
          <el-button type="primary" @click="handleSearch">搜索</el-button>
        </div>
        <el-button type="primary" :icon="Download" :loading="exportLoading" @click="handleExport">
          导出线索
        </el-button>
      </div>

      <!-- 表格：客户昵称、联系方式、来源笔记/渠道、所属租户、意向分、状态、跟进人、操作 -->
      <el-table v-loading="loading" :data="list" border stripe style="width: 100%">
        <el-table-column prop="nickname" label="客户昵称" min-width="130">
          <template #default="{ row }">
            <span class="nick">{{ row.nickname }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="phone" label="联系方式" width="130">
          <template #default="{ row }">
            <span class="mono">{{ row.phone }}</span>
          </template>
        </el-table-column>
        <el-table-column label="来源笔记/渠道" min-width="200">
          <template #default="{ row }">
            <div class="source-quote">"{{ row.quote }}"</div>
            <div class="source-ch">来源：{{ row.channel }}</div>
          </template>
        </el-table-column>
        <el-table-column prop="tenant" label="所属租户" min-width="130" />
        <el-table-column label="意向分" width="90" align="center">
          <template #default="{ row }">
            <span class="score" :class="scoreClass(row.score)">{{ row.score }}</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="statusType(row.status)" size="small" effect="light">
              {{ row.status }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="follower" label="跟进人" width="140" />
        <el-table-column label="操作" width="140" align="center" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="openDetail(row)">详情</el-button>
            <el-button link type="primary" @click="openTag(row)">打标</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrap">
        <span class="total-tip">共 {{ total }} 条线索</span>
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

    <!-- 线索详情抽屉 -->
    <el-drawer
      v-model="detailVisible"
      :title="`线索详情 - ${detailLead?.nickname || ''}`"
      size="440px"
    >
      <div v-loading="detailLoading">
        <template v-if="detailLead">
          <el-descriptions :column="1" border>
            <el-descriptions-item label="客户昵称">{{ detailLead.nickname }}</el-descriptions-item>
            <el-descriptions-item label="联系方式">{{ detailLead.phone }}</el-descriptions-item>
            <el-descriptions-item label="来源渠道">{{ detailLead.channel }}</el-descriptions-item>
            <el-descriptions-item label="所属租户">{{ detailLead.tenant }}</el-descriptions-item>
            <el-descriptions-item label="意向分">
              <span class="score" :class="scoreClass(detailLead.score)">{{ detailLead.score }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="状态">
              <el-tag :type="statusType(detailLead.status)" size="small">{{ detailLead.status }}</el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="跟进人">{{ detailLead.follower }}</el-descriptions-item>
            <el-descriptions-item label="原文">{{ detailLead.quote }}</el-descriptions-item>
            <el-descriptions-item label="标签">
              <el-tag
                v-for="t in detailLead.tags"
                :key="t"
                size="small"
                class="tag-item"
                effect="plain"
              >
                {{ t }}
              </el-tag>
              <span v-if="!detailLead.tags?.length" class="muted">暂无标签</span>
            </el-descriptions-item>
            <el-descriptions-item v-if="detailLead.remark" label="备注">
              {{ detailLead.remark }}
            </el-descriptions-item>
          </el-descriptions>
          <div class="detail-actions">
            <el-button type="primary" @click="openTag(detailLead)">打标</el-button>
            <el-button @click="goMessage">跳转会话</el-button>
          </div>
        </template>
      </div>
    </el-drawer>

    <!-- 打标弹窗 -->
    <el-dialog v-model="tagVisible" title="线索打标" width="420px" destroy-on-close>
      <el-form label-width="80px">
        <el-form-item label="客户">
          <span>{{ tagLead?.nickname }}</span>
        </el-form-item>
        <el-form-item label="意向等级">
          <el-radio-group v-model="tagForm.level">
            <el-radio value="high">高意向</el-radio>
            <el-radio value="mid">中意向</el-radio>
            <el-radio value="low">低意向</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="跟进状态">
          <el-select v-model="tagForm.status" style="width: 100%">
            <el-option label="未处理" value="未处理" />
            <el-option label="已接洽" value="已接洽" />
            <el-option label="已成交" value="已成交" />
            <el-option label="已流失" value="已流失" />
          </el-select>
        </el-form-item>
        <el-form-item label="标签">
          <el-select
            v-model="tagForm.tags"
            multiple
            filterable
            allow-create
            default-first-option
            placeholder="选择或输入标签"
            style="width: 100%"
          >
            <el-option label="代理意向" value="代理意向" />
            <el-option label="询价" value="询价" />
            <el-option label="复购" value="复购" />
            <el-option label="竞品对比" value="竞品对比" />
          </el-select>
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="tagForm.remark" type="textarea" :rows="3" placeholder="跟进备注" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="tagVisible = false">取消</el-button>
        <el-button type="primary" :loading="tagSaving" @click="saveTag">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
/**
 * CRM客户线索
 * - useListQuery + crmLeadApi.list 分页列表
 * - 详情抽屉、打标、导出
 */
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Search, Download } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { crmLeadApi, tenantApi } from '@/api'
import { useListQuery } from '@/composables/useListQuery'

const router = useRouter()

/** 列表 fetcher：tenantId → tenant_id */
const crmListFetcher = (params) => {
  const p = { ...params }
  if (p.tenantId) {
    p.tenant_id = p.tenantId
    delete p.tenantId
  }
  return crmLeadApi.list(p)
}

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
} = useListQuery(crmListFetcher, { keyword: '', tenantId: '', intent: '' })

const tenantOptions = ref([])
const exportLoading = ref(false)

/** 加载租户选项（403 则忽略，不展示筛选） */
const loadTenants = async () => {
  try {
    const data = await tenantApi.list({ page: 1, size: 100 })
    tenantOptions.value = data?.list || []
  } catch {
    tenantOptions.value = []
  }
}

/** 导出线索（blob 下载或 JSON 提示） */
const handleExport = async () => {
  exportLoading.value = true
  try {
    const params = { keyword: query.keyword || undefined, intent: query.intent || undefined }
    if (query.tenantId) params.tenant_id = query.tenantId
    const response = await crmLeadApi.export(params)
    const blob = response.data
    if (blob.type?.includes('json')) {
      const text = await blob.text()
      const json = JSON.parse(text)
      ElMessage.success(json?.message || json?.data?.message || '导出任务已创建')
    } else {
      const url = URL.createObjectURL(new Blob([blob]))
      const link = document.createElement('a')
      link.href = url
      link.download = `crm_leads_${Date.now()}.csv`
      link.click()
      URL.revokeObjectURL(url)
      ElMessage.success('导出成功')
    }
  } catch {
    // 错误已在拦截器提示
  } finally {
    exportLoading.value = false
  }
}

const scoreClass = (score) => {
  if (score >= 90) return 'high'
  if (score >= 75) return 'mid'
  return 'low'
}

const statusType = (status) => {
  const map = { 未处理: '', 已接洽: 'success', 已成交: 'warning', 已流失: 'info' }
  return map[status] || 'info'
}

/* ----- 详情 ----- */
const detailVisible = ref(false)
const detailLead = ref(null)
const detailLoading = ref(false)

const openDetail = async (row) => {
  detailVisible.value = true
  detailLoading.value = true
  detailLead.value = null
  try {
    detailLead.value = await crmLeadApi.detail(row.id)
  } catch {
    detailLead.value = row
  } finally {
    detailLoading.value = false
  }
}

const goMessage = () => {
  detailVisible.value = false
  router.push('/system/messages')
}

/* ----- 打标 ----- */
const tagVisible = ref(false)
const tagLead = ref(null)
const tagSaving = ref(false)
const tagForm = reactive({
  level: 'high',
  status: '未处理',
  tags: [],
  remark: ''
})

const openTag = (row) => {
  tagLead.value = row
  tagForm.level = row.intent || 'high'
  tagForm.status = row.status || '未处理'
  tagForm.tags = [...(row.tags || [])]
  tagForm.remark = row.remark || ''
  tagVisible.value = true
}

const saveTag = async () => {
  if (!tagLead.value) return
  tagSaving.value = true
  try {
    const data = await crmLeadApi.tag(tagLead.value.id, {
      level: tagForm.level,
      status: tagForm.status,
      tags: tagForm.tags,
      remark: tagForm.remark
    })
    ElMessage.success('打标已保存')
    tagVisible.value = false
    if (detailLead.value?.id === tagLead.value.id) {
      detailLead.value = data
    }
    await fetchList()
  } catch {
    // 错误已在拦截器提示
  } finally {
    tagSaving.value = false
  }
}

onMounted(async () => {
  await loadTenants()
  await fetchList()
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

.nick {
  font-weight: 600;
  color: #303133;
}

.mono {
  font-family: 'SF Mono', Monaco, Menlo, Consolas, monospace;
  font-size: 13px;
}

.source-quote {
  font-size: 12px;
  color: #409eff;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 220px;
}

.source-ch {
  font-size: 11px;
  color: #909399;
  margin-top: 2px;
}

.score {
  font-weight: 700;
  font-size: 15px;
}
.score.high {
  color: #f56c6c;
}
.score.mid {
  color: #e6a23c;
}
.score.low {
  color: #409eff;
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

.tag-item {
  margin-right: 6px;
  margin-bottom: 4px;
}

.muted {
  color: #c0c4cc;
  font-size: 13px;
}

.detail-actions {
  margin-top: 24px;
  display: flex;
  gap: 8px;
}
</style>
