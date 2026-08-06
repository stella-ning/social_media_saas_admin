<template>
  <div>
    <el-alert
      type="warning"
      :closable="false"
      show-icon
      title="双层敏感词：平台全局风险词 + 租户自定义屏蔽词。命中 block 级将拦截或改写 AI 评论回复。"
      style="margin-bottom: 12px"
    />
    <TenantScopeBar
      v-model="tenantId"
      :tenants="tenants"
      :loading="!tenantsReady"
      :visible="isAdmin"
      label="管理租户词库"
    />
    <el-card shadow="hover">
      <div class="toolbar">
        <el-radio-group v-model="scope" size="small" @change="load">
          <el-radio-button value="all">全部</el-radio-button>
          <el-radio-button value="global">平台全局</el-radio-button>
          <el-radio-button value="tenant">租户自定义</el-radio-button>
        </el-radio-group>
        <el-input v-model="keyword" placeholder="搜索词" clearable style="width: 180px" />
        <el-button @click="load">刷新</el-button>
        <el-button type="primary" :disabled="!canAdd" @click="openAdd">新增</el-button>
      </div>
      <el-table v-loading="loading" :data="list" border stripe>
        <el-table-column prop="word" label="敏感词" min-width="160" />
        <el-table-column label="级别" width="100">
          <template #default="{ row }">
            <el-tag :type="row.level === 'block' ? 'danger' : 'warning'" size="small">
              {{ row.level === 'block' ? '拦截' : '警告' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="范围" width="100">
          <template #default="{ row }">
            {{ row.scope === 'global' ? '平台全局' : '租户' }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="100" align="center">
          <template #default="{ row }">
            <el-button
              link
              type="danger"
              :disabled="row.scope === 'global' && !isAdmin"
              @click="remove(row)"
            >
              删除
            </el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="addVisible" title="新增敏感词" width="420px">
      <el-form label-width="80px">
        <el-form-item label="词语">
          <el-input v-model="form.word" maxlength="64" />
        </el-form-item>
        <el-form-item label="级别">
          <el-radio-group v-model="form.level">
            <el-radio value="block">拦截</el-radio>
            <el-radio value="warn">警告</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item v-if="isAdmin" label="写入">
          <el-radio-group v-model="form.asGlobal">
            <el-radio :value="false">当前租户</el-radio>
            <el-radio :value="true">平台全局</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="addVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="save">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { sensitiveWordApi } from '@/api'
import { useTenantScope } from '@/composables/useTenantScope'
import TenantScopeBar from '@/components/TenantScopeBar.vue'

const { tenantId, isAdmin, tenants, tenantsReady, withTenant } = useTenantScope()
const loading = ref(false)
const list = ref([])
const keyword = ref('')
const scope = ref('all')
const addVisible = ref(false)
const saving = ref(false)
const form = reactive({ word: '', level: 'block', asGlobal: false })

const canAdd = computed(() => isAdmin.value || !!tenantId.value)

const load = async () => {
  loading.value = true
  try {
    const data = await sensitiveWordApi.list(
      withTenant({ scope: scope.value, keyword: keyword.value || undefined, page: 1, size: 200 })
    )
    list.value = data?.list || []
  } finally {
    loading.value = false
  }
}

const openAdd = () => {
  form.word = ''
  form.level = 'block'
  form.asGlobal = false
  addVisible.value = true
}

const save = async () => {
  if (!form.word.trim()) {
    ElMessage.warning('请输入敏感词')
    return
  }
  if (!form.asGlobal && !tenantId.value) {
    ElMessage.warning('请先选择租户')
    return
  }
  saving.value = true
  try {
    await sensitiveWordApi.save(
      withTenant({
        word: form.word.trim(),
        level: form.level,
        asGlobal: isAdmin.value && form.asGlobal
      })
    )
    ElMessage.success('已保存')
    addVisible.value = false
    await load()
  } finally {
    saving.value = false
  }
}

const remove = (row) => {
  ElMessageBox.confirm(`删除敏感词「${row.word}」？`, '提示', { type: 'warning' })
    .then(async () => {
      await sensitiveWordApi.remove(row.id)
      ElMessage.success('已删除')
      await load()
    })
    .catch(() => {})
}

watch(tenantId, load, { immediate: true })
</script>

<style scoped>
.toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 12px;
  align-items: center;
}
</style>
