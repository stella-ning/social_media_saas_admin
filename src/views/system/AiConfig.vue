<template>
  <div class="ai-config-page">
    <!-- 顶部：Tab 切换 + 租户筛选 -->
    <div class="page-header">
      <el-tabs v-model="activeTab" class="ai-tabs" @tab-change="onTabChange">
        <el-tab-pane label="Prompt模板配置" name="prompt" />
        <el-tab-pane label="租户知识库管理" name="kb" />
      </el-tabs>
      <div v-if="showTenantFilter" class="tenant-filter">
        <span class="filter-label">当前租户:</span>
        <el-select v-model="currentTenantId" style="width: 180px" size="default" @change="onTenantChange">
          <el-option
            v-for="t in tenantOptions"
            :key="t.id"
            :label="t.name"
            :value="t.id"
          />
        </el-select>
      </div>
    </div>

    <!-- ========== Tab1：Prompt模板配置 ========== -->
    <div v-show="activeTab === 'prompt'" v-loading="templatesLoading">
      <el-row :gutter="20">
        <el-col
          :xs="24"
          :sm="12"
          :md="8"
          v-for="tpl in promptTemplates"
          :key="tpl.id"
        >
          <el-card shadow="hover" class="tpl-card">
            <div class="tpl-card-top">
              <el-tag :type="categoryTagType(tpl.category)" size="small" effect="light">{{ tpl.category }}</el-tag>
              <el-dropdown trigger="click">
                <el-icon class="more-btn" :size="16"><MoreFilled /></el-icon>
                <template #dropdown>
                  <el-dropdown-menu>
                    <el-dropdown-item @click="openEdit(tpl)">编辑模板</el-dropdown-item>
                    <el-dropdown-item @click="openTest(tpl)">测试预览</el-dropdown-item>
                    <el-dropdown-item divided @click="duplicateTpl(tpl)">复制模板</el-dropdown-item>
                  </el-dropdown-menu>
                </template>
              </el-dropdown>
            </div>
            <h4 class="tpl-title">{{ tpl.name }}</h4>
            <p class="tpl-desc">{{ tpl.desc || tpl.role }}</p>
            <div class="tpl-footer">
              <span class="tpl-time">最后更新: {{ tpl.updateTime }}</span>
              <div class="tpl-actions">
                <el-button link type="info" size="small" @click="openTest(tpl)">测试预览</el-button>
                <el-button link type="primary" size="small" @click="openEdit(tpl)">编辑模板</el-button>
              </div>
            </div>
          </el-card>
        </el-col>

        <!-- 新增模板卡片 -->
        <el-col :xs="24" :sm="12" :md="8">
          <el-card shadow="hover" class="tpl-card add-card" @click="openEdit(null)">
            <div class="add-inner">
              <el-icon :size="32" color="#c0c4cc"><Plus /></el-icon>
              <p>新建 Prompt 模板</p>
            </div>
          </el-card>
        </el-col>
      </el-row>
    </div>

    <!-- ========== Tab2：租户知识库管理 ========== -->
    <div v-show="activeTab === 'kb'">
      <el-card shadow="hover">
        <div class="kb-toolbar">
          <h4 class="kb-title">私有知识库文档</h4>
          <el-button type="primary" :icon="Upload" @click="uploadVisible = true">上传文档</el-button>
        </div>
        <el-table v-loading="docsLoading" :data="kbDocs" border stripe style="width: 100%">
          <el-table-column label="文档名称" min-width="240">
            <template #default="{ row }">
              <div class="doc-name">
                <el-icon :size="18" :color="docIconColor(row.name)"><Document /></el-icon>
                <span>{{ row.name }}</span>
              </div>
            </template>
          </el-table-column>
          <el-table-column prop="size" label="大小" width="100" />
          <el-table-column label="状态" width="110" align="center">
            <template #default="{ row }">
              <el-tag
                :type="row.status === 'ready' ? 'success' : row.status === 'processing' ? 'warning' : 'info'"
                size="small"
              >
                {{ statusLabel(row.status) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="uploadTime" label="上传时间" width="130" />
          <el-table-column label="操作" width="100" align="right">
            <template #default="{ row }">
              <el-button link type="danger" @click="deleteDoc(row)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>
        <div class="pagination-wrap">
          <span class="total-tip">共 {{ docsTotal }} 条文档</span>
          <el-pagination
            v-model:current-page="docsPage"
            v-model:page-size="docsSize"
            :page-sizes="[10, 20, 50]"
            :total="docsTotal"
            layout="sizes, prev, pager, next"
            background
            @current-change="loadDocs"
            @size-change="onDocsSizeChange"
          />
        </div>
      </el-card>
    </div>

    <!-- ========== 编辑模板弹窗 ========== -->
    <el-dialog
      v-model="editVisible"
      :title="editForm.id ? '编辑模板' : '新建 Prompt 模板'"
      width="640px"
      destroy-on-close
    >
      <el-form :model="editForm" label-width="90px">
        <el-form-item label="模板名称" required>
          <el-input v-model="editForm.name" placeholder="如：国内评论生成默认模板" />
        </el-form-item>
        <el-form-item label="模板类型">
          <el-select v-model="editForm.category" style="width: 100%">
            <el-option label="社媒评论生成" value="社媒评论生成" />
            <el-option label="客户意向打分" value="客户意向打分" />
            <el-option label="私信智能问答" value="私信智能问答" />
          </el-select>
        </el-form-item>
        <el-form-item label="角色设置">
          <el-input v-model="editForm.role" type="textarea" :rows="3" />
        </el-form-item>
        <el-form-item label="约束条件">
          <el-input v-model="editForm.rules" type="textarea" :rows="5" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="editVisible = false">取消</el-button>
        <el-button type="primary" :loading="saveLoading" @click="saveTemplate">保存配置</el-button>
      </template>
    </el-dialog>

    <!-- ========== AI 在线测试弹窗 ========== -->
    <el-dialog v-model="testVisible" title="AI Prompt 在线测试" width="600px" destroy-on-close>
      <el-alert
        :title="`测试模板：${testTpl?.name || ''}`"
        type="info"
        :closable="false"
        show-icon
        class="test-alert"
      />
      <el-form label-position="top" class="test-form">
        <el-form-item label="输入测试内容 (帖子正文)">
          <el-input
            v-model="testInput"
            type="textarea"
            :rows="4"
            placeholder="输入一段小红书或抖音的帖子文案..."
          />
        </el-form-item>
        <el-button type="primary" class="gen-btn" :loading="testing" @click="runTest">
          <el-icon v-if="!testing"><MagicStick /></el-icon>
          开始生成预览
        </el-button>
        <el-form-item label="AI 生成结果" class="result-item">
          <div class="ai-result">{{ testResult || '点击上方按钮开始测试...' }}</div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="testVisible = false">关闭</el-button>
      </template>
    </el-dialog>

    <!-- ========== 上传知识库文档弹窗 ========== -->
    <el-dialog v-model="uploadVisible" title="上传知识库文档" width="480px" destroy-on-close>
      <el-upload
        drag
        action="#"
        :auto-upload="false"
        :limit="5"
        accept=".pdf,.doc,.docx,.txt"
        :on-change="onFileChange"
      >
        <el-icon :size="40" color="#c0c4cc"><UploadFilled /></el-icon>
        <div class="el-upload__text">点击或拖拽文件到此处上传</div>
        <template #tip>
          <div class="el-upload__tip">支持 PDF, Word, TXT 格式（单个文件 &lt; 10MB）</div>
        </template>
      </el-upload>
      <el-form label-width="80px" style="margin-top: 16px">
        <el-form-item label="文档标签">
          <el-input v-model="uploadTag" placeholder="如：产品手册, 话术参考" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="uploadVisible = false">取消</el-button>
        <el-button type="primary" :loading="uploadLoading" @click="handleUpload">开始上传</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
/**
 * AI配置中心
 * - Tab1：Prompt模板配置（卡片列表 + 编辑/测试）
 * - Tab2：租户知识库管理（文档表格 + 上传）
 */
import { ref, reactive, onMounted } from 'vue'
import { Upload, Plus, Document, MoreFilled, MagicStick, UploadFilled } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { aiConfigApi, tenantApi } from '@/api'

const activeTab = ref('prompt')
const showTenantFilter = ref(false)
const tenantOptions = ref([])
const currentTenantId = ref('')

/** 模板列表 */
const promptTemplates = ref([])
const templatesLoading = ref(false)

/** 知识库文档 */
const kbDocs = ref([])
const docsLoading = ref(false)
const docsPage = ref(1)
const docsSize = ref(10)
const docsTotal = ref(0)

const categoryTagType = (category) => {
  const map = { 社媒评论生成: '', 客户意向打分: 'success', 私信智能问答: 'warning' }
  return map[category] || ''
}

const statusLabel = (s) => ({ ready: '已入库', processing: '解析中', failed: '失败' }[s] || s)

const docIconColor = (name) => {
  if (name?.endsWith('.pdf')) return '#f56c6c'
  if (name?.endsWith('.txt')) return '#909399'
  return '#409eff'
}

/** 构建租户筛选参数 */
const tenantParams = () => {
  if (currentTenantId.value) {
    return { tenant_id: currentTenantId.value }
  }
  return {}
}

/** 加载租户选项（超管可见；403 则隐藏筛选，后端按用户租户范围） */
const loadTenants = async () => {
  try {
    const data = await tenantApi.list({ page: 1, size: 100 })
    tenantOptions.value = data?.list || []
    showTenantFilter.value = tenantOptions.value.length > 0
    if (showTenantFilter.value && !currentTenantId.value) {
      currentTenantId.value = tenantOptions.value[0].id
    }
  } catch {
    showTenantFilter.value = false
    currentTenantId.value = ''
  }
}

/** 加载 Prompt 模板（兼容数组或 { list } 响应） */
const loadTemplates = async () => {
  templatesLoading.value = true
  try {
    const data = await aiConfigApi.templates({ page: 1, size: 100, ...tenantParams() })
    promptTemplates.value = Array.isArray(data) ? data : (data?.list || [])
  } catch {
    promptTemplates.value = []
  } finally {
    templatesLoading.value = false
  }
}

/** 加载知识库文档 */
const loadDocs = async () => {
  docsLoading.value = true
  try {
    const data = await aiConfigApi.docs({
      page: docsPage.value,
      size: docsSize.value,
      ...tenantParams()
    })
    kbDocs.value = data?.list || []
    docsTotal.value = data?.total ?? 0
    if (data?.page) docsPage.value = data.page
    if (data?.size) docsSize.value = data.size
  } catch {
    kbDocs.value = []
    docsTotal.value = 0
  } finally {
    docsLoading.value = false
  }
}

const onDocsSizeChange = (s) => {
  docsSize.value = s
  docsPage.value = 1
  loadDocs()
}

const onTenantChange = () => {
  loadTemplates()
  if (activeTab.value === 'kb') loadDocs()
}

const onTabChange = (tab) => {
  if (tab === 'kb') loadDocs()
}

/* ----- 编辑模板 ----- */
const editVisible = ref(false)
const saveLoading = ref(false)
const editForm = reactive({
  id: null,
  name: '',
  category: '社媒评论生成',
  role: '',
  rules: ''
})

const openEdit = (tpl) => {
  if (tpl) {
    Object.assign(editForm, {
      id: tpl.id,
      name: tpl.name,
      category: tpl.category,
      role: tpl.role,
      rules: tpl.rules
    })
  } else {
    Object.assign(editForm, {
      id: null,
      name: '',
      category: '社媒评论生成',
      role: '',
      rules: ''
    })
  }
  editVisible.value = true
}

const saveTemplate = async () => {
  if (!editForm.name.trim()) {
    ElMessage.warning('请填写模板名称')
    return
  }
  saveLoading.value = true
  try {
    const payload = {
      category: editForm.category,
      name: editForm.name,
      desc: (editForm.role || editForm.name).slice(0, 60) + (editForm.role?.length > 60 ? '...' : ''),
      role: editForm.role,
      rules: editForm.rules
    }
    if (editForm.id) payload.id = editForm.id
    if (currentTenantId.value) payload.tenantId = currentTenantId.value
    await aiConfigApi.saveTemplate(payload)
    ElMessage.success('模板已保存')
    editVisible.value = false
    await loadTemplates()
  } catch {
    // 错误已在拦截器提示
  } finally {
    saveLoading.value = false
  }
}

const duplicateTpl = (tpl) => {
  Object.assign(editForm, {
    id: null,
    name: tpl.name + ' (副本)',
    category: tpl.category,
    role: tpl.role,
    rules: tpl.rules
  })
  editVisible.value = true
}

/* ----- 测试预览 ----- */
const testVisible = ref(false)
const testTpl = ref(null)
const testInput = ref('')
const testResult = ref('')
const testing = ref(false)

const openTest = (tpl) => {
  testTpl.value = tpl
  testInput.value = ''
  testResult.value = ''
  testVisible.value = true
}

const runTest = async () => {
  if (!testInput.value.trim()) {
    ElMessage.warning('请输入测试内容')
    return
  }
  testing.value = true
  try {
    const payload = {
      input: testInput.value,
      templateId: testTpl.value?.id,
      templateName: testTpl.value?.name
    }
    if (currentTenantId.value) payload.tenantId = currentTenantId.value
    const data = await aiConfigApi.test(payload)
    testResult.value = data?.reply || ''
    ElMessage.success('生成完成')
  } catch {
    // 错误已在拦截器提示
  } finally {
    testing.value = false
  }
}

/* ----- 知识库上传/删除 ----- */
const uploadVisible = ref(false)
const uploadTag = ref('')
const pendingFile = ref(null)
const uploadLoading = ref(false)

const onFileChange = (file) => {
  pendingFile.value = file
}

const formatFileSize = (bytes) => {
  if (!bytes) return '0 KB'
  if (bytes >= 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
  return (bytes / 1024).toFixed(1) + ' KB'
}

const handleUpload = async () => {
  if (!pendingFile.value) {
    ElMessage.warning('请先选择文件')
    return
  }
  const raw = pendingFile.value.raw || pendingFile.value
  uploadLoading.value = true
  try {
    const payload = {
      name: raw.name,
      size: formatFileSize(raw.size),
      tag: uploadTag.value
    }
    if (currentTenantId.value) payload.tenantId = currentTenantId.value
    await aiConfigApi.uploadDoc(payload)
    ElMessage.success(`文档「${raw.name}」上传成功`)
    uploadVisible.value = false
    uploadTag.value = ''
    pendingFile.value = null
    docsPage.value = 1
    await loadDocs()
  } catch {
    // 错误已在拦截器提示
  } finally {
    uploadLoading.value = false
  }
}

const deleteDoc = (row) => {
  ElMessageBox.confirm(`确认删除文档「${row.name}」？`, '提示', {
    type: 'warning'
  }).then(async () => {
    try {
      await aiConfigApi.deleteDoc(row.id)
      ElMessage.success('已删除')
      await loadDocs()
    } catch {
      // 错误已在拦截器提示
    }
  }).catch(() => {})
}

onMounted(async () => {
  await loadTenants()
  await loadTemplates()
})
</script>

<style scoped>
.ai-config-page {
  min-height: 100%;
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 8px;
}

.ai-tabs {
  flex: 1;
}

.tenant-filter {
  display: flex;
  align-items: center;
  gap: 8px;
  padding-top: 4px;
}

.filter-label {
  font-size: 13px;
  color: #909399;
  white-space: nowrap;
}

.tpl-card {
  margin-bottom: 16px;
  border-radius: 8px;
  min-height: 180px;
}

.tpl-card-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.more-btn {
  cursor: pointer;
  color: #909399;
}
.more-btn:hover {
  color: #409eff;
}

.tpl-title {
  font-size: 15px;
  font-weight: 700;
  color: #303133;
  margin: 0 0 8px;
}

.tpl-desc {
  font-size: 12px;
  color: #909399;
  line-height: 1.6;
  height: 40px;
  overflow: hidden;
  margin: 0 0 16px;
}

.tpl-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.tpl-time {
  font-size: 11px;
  color: #c0c4cc;
}

.tpl-actions {
  display: flex;
  gap: 4px;
}

.add-card {
  cursor: pointer;
  border: 1px dashed #dcdfe6;
  min-height: 180px;
}
.add-card:hover {
  border-color: #409eff;
}

.add-inner {
  height: 140px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  color: #909399;
  font-size: 13px;
}

.kb-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}

.kb-title {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
  color: #303133;
}

.doc-name {
  display: flex;
  align-items: center;
  gap: 8px;
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

.test-alert {
  margin-bottom: 16px;
}

.test-form {
  margin-top: 8px;
}

.gen-btn {
  width: 100%;
  margin-bottom: 16px;
}

.result-item {
  margin-bottom: 0;
}

.ai-result {
  background: #f5f7fa;
  border: 1px solid #e4e7ed;
  border-radius: 4px;
  padding: 14px;
  min-height: 80px;
  font-size: 13px;
  color: #606266;
  line-height: 1.6;
  width: 100%;
}
</style>
