<template>
  <div>
    <el-alert type="info" :closable="false" title="专业版 / 企业版：高意向线索跟进提醒与定时回访" style="margin-bottom: 12px" />
    <TenantScopeBar
      v-model="tenantId"
      :tenants="tenants"
      :loading="!tenantsReady"
      :visible="isAdmin"
      label="所属租户"
    />
    <el-card shadow="hover">
      <div class="toolbar">
        <h3>CRM 跟进提醒</h3>
        <el-button type="primary" :disabled="!tenantId" @click="openCreate">新建提醒</el-button>
      </div>
      <el-table v-loading="loading" :data="list" border>
        <el-table-column prop="remindAt" label="提醒时间" width="160" />
        <el-table-column prop="leadName" label="线索" width="140" />
        <el-table-column prop="title" label="标题" min-width="180" />
        <el-table-column prop="status" label="状态" width="100" />
        <el-table-column label="操作" width="120">
          <template #default="{ row }">
            <el-button v-if="row.status === 'pending'" link type="primary" @click="done(row)">完成</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="visible" title="新建跟进提醒" width="420px">
      <el-form label-width="90px">
        <el-form-item label="线索ID"><el-input-number v-model="form.crmLeadId" :min="1" /></el-form-item>
        <el-form-item label="标题"><el-input v-model="form.title" /></el-form-item>
        <el-form-item label="提醒时间">
          <el-date-picker v-model="form.remindAt" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" style="width: 100%" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="visible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="save">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { premiumApi } from '@/api'
import { useTenantScope } from '@/composables/useTenantScope'
import TenantScopeBar from '@/components/TenantScopeBar.vue'

const { tenantId, isAdmin, tenants, tenantsReady, withTenant } = useTenantScope()
const loading = ref(false)
const list = ref([])
const visible = ref(false)
const saving = ref(false)
const form = ref({ crmLeadId: 1, title: '', remindAt: '' })

const load = async () => {
  if (!tenantId.value) {
    list.value = []
    return
  }
  loading.value = true
  try {
    const data = await premiumApi.crmReminders(withTenant())
    list.value = data?.list || []
  } catch {
    list.value = []
  } finally {
    loading.value = false
  }
}

watch(tenantId, load, { immediate: true })

const openCreate = () => {
  if (!tenantId.value) {
    ElMessage.warning('请先选择租户')
    return
  }
  visible.value = true
}

const save = async () => {
  saving.value = true
  try {
    await premiumApi.saveCrmReminder(withTenant(form.value))
    ElMessage.success('已创建')
    visible.value = false
    await load()
  } finally {
    saving.value = false
  }
}

const done = async (row) => {
  await premiumApi.completeCrmReminder(row.id, withTenant())
  ElMessage.success('已完成')
  await load()
}
</script>

<style scoped>
.toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
h3 { margin: 0; }
</style>
