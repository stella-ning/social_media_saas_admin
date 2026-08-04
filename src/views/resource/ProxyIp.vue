<template>
  <div class="proxy-page">
    <el-card shadow="hover" class="table-card">
      <!-- 顶部：导入代理IP -->
      <div class="toolbar">
        <h3 class="page-title">代理IP池</h3>
        <el-button type="primary" :icon="Plus" @click="importVisible = true">导入代理IP</el-button>
      </div>

      <!-- 表格字段：服务器地址、归属地、协议类型、状态、当前负载、操作 -->
      <el-table v-loading="loading" :data="list" border stripe style="width: 100%">
        <el-table-column prop="address" label="服务器地址" min-width="180">
          <template #default="{ row }">
            <span class="mono">{{ row.address }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="location" label="归属地" width="140" />
        <el-table-column prop="protocol" label="协议类型" width="140" />
        <el-table-column label="状态" width="120" align="center">
          <template #default="{ row }">
            <span class="status-dot" :class="row.status">
              <i class="dot"></i>
              {{ row.status === 'running' ? '运行中' : row.status === 'error' ? '异常' : '空闲' }}
            </span>
          </template>
        </el-table-column>
        <el-table-column label="当前负载" width="120" align="center">
          <template #default="{ row }">
            {{ row.load }}/{{ row.capacity }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="160" align="center">
          <template #default="{ row }">
            <el-button
              link
              type="primary"
              :loading="checkingId === row.id"
              @click="handleCheck(row)"
            >
              检测
            </el-button>
            <el-button link type="danger" @click="handleRemove(row)">移除</el-button>
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

    <!-- 导入代理IP弹窗 -->
    <el-dialog v-model="importVisible" title="导入代理IP" width="480px" destroy-on-close>
      <el-form label-width="100px">
        <el-form-item label="IP列表">
          <el-input
            v-model="importForm.list"
            type="textarea"
            :rows="6"
            placeholder="格式：IP:端口:用户名:密码（一行一个）"
          />
          <div class="form-tip">支持 HTTP/HTTPS 协议，批量导入请用换行分隔</div>
        </el-form-item>
        <el-form-item label="归属地备注">
          <el-input v-model="importForm.location" placeholder="如：深圳机房" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="importVisible = false">取消</el-button>
        <el-button type="primary" :loading="importing" @click="handleImport">执行导入并检测</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
/**
 * 代理IP管理
 * - useListQuery + proxyIpApi.list
 * - 导入/检测/移除
 */
import { ref, reactive, onMounted } from 'vue'
import { Plus } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { proxyIpApi } from '@/api'
import { useListQuery } from '@/composables/useListQuery'

const {
  loading,
  list,
  total,
  page,
  size,
  fetchList,
  handlePageChange,
  handleSizeChange
} = useListQuery(proxyIpApi.list)

const importVisible = ref(false)
const importing = ref(false)
const checkingId = ref(null)

const importForm = reactive({
  list: '',
  location: ''
})

/** 检测代理连通性 */
const handleCheck = async (row) => {
  checkingId.value = row.id
  try {
    const result = await proxyIpApi.check(row.id)
    ElMessage.success(result?.message || `${row.address} 检测完成`)
    await fetchList()
  } catch {
    // 错误已在拦截器提示
  } finally {
    checkingId.value = null
  }
}

/** 移除代理 */
const handleRemove = (row) => {
  ElMessageBox.confirm(`确认移除代理 ${row.address} ？`, '提示', {
    type: 'warning',
    confirmButtonText: '移除',
    cancelButtonText: '取消'
  })
    .then(async () => {
      try {
        await proxyIpApi.remove(row.id)
        ElMessage.success('已移除')
        await fetchList()
      } catch {
        // 错误已在拦截器提示
      }
    })
    .catch(() => {})
}

/** 批量导入 */
const handleImport = async () => {
  if (!importForm.list.trim()) {
    ElMessage.warning('请输入IP列表')
    return
  }
  importing.value = true
  try {
    const result = await proxyIpApi.import({
      list: importForm.list,
      location: importForm.location
    })
    ElMessage.success(`成功导入 ${result?.count || 0} 条代理并完成检测`)
    importVisible.value = false
    importForm.list = ''
    importForm.location = ''
    await fetchList()
  } catch {
    // 错误已在拦截器提示
  } finally {
    importing.value = false
  }
}

onMounted(fetchList)
</script>

<style scoped>
.table-card {
  border-radius: 4px;
}
.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}
.page-title {
  font-size: 16px;
  font-weight: 700;
  color: #303133;
  margin: 0;
}
.mono {
  font-family: 'SF Mono', Monaco, Menlo, Consolas, monospace;
  font-size: 13px;
  color: #606266;
}
.status-dot {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
}
.status-dot .dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  display: inline-block;
}
.status-dot.running {
  color: #67c23a;
}
.status-dot.running .dot {
  background: #67c23a;
}
.status-dot.idle {
  color: #909399;
}
.status-dot.idle .dot {
  background: #909399;
}
.status-dot.error {
  color: #f56c6c;
}
.status-dot.error .dot {
  background: #f56c6c;
}
.form-tip {
  font-size: 12px;
  color: #c0c4cc;
  margin-top: 4px;
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
