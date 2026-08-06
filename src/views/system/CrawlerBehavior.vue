<template>
  <div>
    <el-alert
      type="info"
      :closable="false"
      title="专业版 / 企业版：访问主页随机延时、页面停留、滑动动作，降低小红书风控"
      style="margin-bottom: 12px"
    />
    <TenantScopeBar
      v-model="tenantId"
      :tenants="tenants"
      :loading="!tenantsReady"
      :visible="isAdmin"
      label="配置租户"
    />
    <el-card shadow="hover">
      <el-form label-width="160px" style="max-width: 640px" :disabled="!tenantId">
        <el-divider content-position="left">页面浏览</el-divider>
        <el-form-item label="开启滑动动作">
          <el-switch v-model="form.enableScroll" />
        </el-form-item>
        <el-form-item label="滑动时长(ms)">
          <el-input-number v-model="form.scrollMinMs" :min="100" :disabled="!form.enableScroll" /> —
          <el-input-number v-model="form.scrollMaxMs" :min="100" :disabled="!form.enableScroll" />
        </el-form-item>
        <el-form-item label="页面停留(ms)">
          <el-input-number v-model="form.dwellMinMs" :min="100" /> —
          <el-input-number v-model="form.dwellMaxMs" :min="100" />
        </el-form-item>
        <el-form-item label="评论发送间隔(ms)">
          <el-input-number v-model="form.intervalMinMs" :min="100" /> —
          <el-input-number v-model="form.intervalMaxMs" :min="100" />
        </el-form-item>
        <el-divider content-position="left">主页核验</el-divider>
        <el-form-item label="访问主页随机延时(ms)">
          <el-input-number v-model="form.homepageDelayMinMs" :min="200" /> —
          <el-input-number v-model="form.homepageDelayMaxMs" :min="200" />
        </el-form-item>
        <el-form-item label="指纹预设">
          <el-select v-model="form.fingerprintPreset" style="width: 100%">
            <el-option label="桌面 Chrome" value="desktop_chrome" />
            <el-option label="桌面 Safari" value="desktop_safari" />
            <el-option label="移动端 iOS" value="mobile_ios" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :disabled="!tenantId" :loading="saving" @click="save">
            保存配置
          </el-button>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
import { reactive, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { premiumApi } from '@/api'
import { useTenantScope } from '@/composables/useTenantScope'
import TenantScopeBar from '@/components/TenantScopeBar.vue'

const { tenantId, isAdmin, tenants, tenantsReady, withTenant } = useTenantScope()
const saving = ref(false)
const form = reactive({
  scrollMinMs: 800,
  scrollMaxMs: 2400,
  dwellMinMs: 1500,
  dwellMaxMs: 5000,
  intervalMinMs: 3000,
  intervalMaxMs: 8000,
  fingerprintPreset: 'desktop_chrome',
  enableScroll: true,
  homepageDelayMinMs: 800,
  homepageDelayMaxMs: 2500
})

const load = async () => {
  if (!tenantId.value) return
  try {
    const data = await premiumApi.humanBehavior(withTenant())
    if (data) Object.assign(form, data)
  } catch {
    /* 套餐未开通时拦截器提示 */
  }
}

watch(tenantId, load, { immediate: true })

const save = async () => {
  if (!tenantId.value) {
    ElMessage.warning('请先选择租户')
    return
  }
  saving.value = true
  try {
    await premiumApi.saveHumanBehavior(withTenant({ ...form }))
    ElMessage.success('已保存')
  } finally {
    saving.value = false
  }
}
</script>
