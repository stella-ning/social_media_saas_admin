<template>
  <div>
    <el-alert type="info" :closable="false" title="仅企业版可管理子账号（客服 / 爬虫运维分级权限）" style="margin-bottom: 12px" />
    <TenantScopeBar
      v-model="tenantId"
      :tenants="tenants"
      :loading="!tenantsReady"
      :visible="isAdmin"
      label="所属租户"
    />
    <el-card shadow="hover">
      <div class="toolbar">
        <h3>子账号管理</h3>
        <el-button type="primary" :disabled="!tenantId" @click="openCreate">新增子账号</el-button>
      </div>
      <el-table v-loading="loading" :data="list" border>
        <el-table-column prop="username" label="用户名" />
        <el-table-column prop="displayName" label="显示名" />
        <el-table-column prop="roleLabel" label="角色" width="120" />
        <el-table-column label="状态" width="90">
          <template #default="{ row }">{{ row.status === 1 ? '启用' : '停用' }}</template>
        </el-table-column>
        <el-table-column label="操作" width="120">
          <template #default="{ row }">
            <el-button link type="danger" @click="remove(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="visible" title="新增子账号" width="420px">
      <el-form label-width="90px">
        <el-form-item label="用户名"><el-input v-model="form.username" /></el-form-item>
        <el-form-item label="显示名"><el-input v-model="form.displayName" /></el-form-item>
        <el-form-item label="角色">
          <el-select v-model="form.role" style="width: 100%">
            <el-option label="客服" value="cs" />
            <el-option label="爬虫运维" value="crawler_ops" />
            <el-option label="业务员" value="operator" />
          </el-select>
        </el-form-item>
        <el-form-item label="初始密码"><el-input v-model="form.password" placeholder="默认 password123" /></el-form-item>
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
import { ElMessage, ElMessageBox } from 'element-plus'
import { premiumApi } from '@/api'
import { useTenantScope } from '@/composables/useTenantScope'
import TenantScopeBar from '@/components/TenantScopeBar.vue'

const { tenantId, isAdmin, tenants, tenantsReady, withTenant } = useTenantScope()
const loading = ref(false)
const list = ref([])
const visible = ref(false)
const saving = ref(false)
const form = ref({ username: '', displayName: '', role: 'cs', password: 'password123' })

const load = async () => {
  if (!tenantId.value) {
    list.value = []
    return
  }
  loading.value = true
  try {
    const data = await premiumApi.subAccounts(withTenant())
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
  form.value = { username: '', displayName: '', role: 'cs', password: 'password123' }
  visible.value = true
}

const save = async () => {
  saving.value = true
  try {
    await premiumApi.saveSubAccount(withTenant(form.value))
    ElMessage.success('已保存')
    visible.value = false
    await load()
  } finally {
    saving.value = false
  }
}

const remove = async (row) => {
  await ElMessageBox.confirm(`删除子账号 ${row.username}？`)
  await premiumApi.deleteSubAccount(row.id, withTenant())
  ElMessage.success('已删除')
  await load()
}
</script>

<style scoped>
.toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
h3 { margin: 0; }
</style>
