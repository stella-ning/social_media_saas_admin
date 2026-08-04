<template>
  <el-dialog
    :model-value="modelValue"
    :title="`AI配置 · ${tenantName || ''}`"
    width="520px"
    destroy-on-close
    @update:model-value="emit('update:modelValue', $event)"
  >
    <div v-loading="loading">
      <el-alert
        :closable="false"
        type="info"
        show-icon
        class="mb-12"
        :title="`当前套餐：${packageLabel}（下拉仅显示本套餐可用等级的模板）`"
      />

      <el-form label-width="140px">
        <el-form-item label="启用 AI 参数模板">
          <el-select
            v-model="selectedId"
            clearable
            filterable
            placeholder="留空则继承平台全局默认配置"
            style="width: 100%"
          >
            <el-option
              v-for="item in options"
              :key="item.id"
              :label="optionLabel(item)"
              :value="item.id"
            />
          </el-select>
          <div class="tip">
            优先级：小红书账号绑定 &gt; 此处选定模板 &gt; 平台全局。完整模板管理请前往
            <el-button link type="primary" @click="goAiCenter">AI配置中心</el-button>
          </div>
        </el-form-item>
      </el-form>
    </div>

    <template #footer>
      <el-button :disabled="saving" @click="emit('update:modelValue', false)">取消</el-button>
      <el-button type="primary" :loading="saving" @click="handleSave">保存</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
/**
 * 租户列表「AI配置」弹窗
 * - 按套餐等级筛选可选 AI 参数模板
 * - 保存 current_ai_param_template_id
 */
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { aiConfigApi } from '@/api'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  tenantId: { type: [Number, String], default: null },
  tenantName: { type: String, default: '' }
})

const emit = defineEmits(['update:modelValue', 'saved'])

const router = useRouter()
const loading = ref(false)
const saving = ref(false)
const options = ref([])
const selectedId = ref(null)
const packageLabel = ref('-')

const optionLabel = (item) => {
  const level = item.templateLevelLabel || ''
  return `${item.templateName}${level ? `（${level}）` : ''}${item.isDefault ? ' · 当前默认' : ''}`
}

const loadData = async () => {
  if (!props.tenantId) return
  loading.value = true
  try {
    const data = await aiConfigApi.templateListByPackage(props.tenantId)
    options.value = data?.list || []
    packageLabel.value = data?.packageLabel || '-'
    selectedId.value = data?.currentAiParamTemplateId || null
  } catch {
    options.value = []
    emit('update:modelValue', false)
  } finally {
    loading.value = false
  }
}

watch(
  () => [props.modelValue, props.tenantId],
  ([visible]) => {
    if (visible && props.tenantId) loadData()
  }
)

const handleSave = async () => {
  saving.value = true
  try {
    await aiConfigApi.saveCurrentAiTemplate({
      tenant_id: props.tenantId,
      template_id: selectedId.value || null
    })
    ElMessage.success('已更新租户当前 AI 参数模板')
    emit('saved')
    emit('update:modelValue', false)
  } catch {
    // 拦截器已提示
  } finally {
    saving.value = false
  }
}

/** 跳转完整 AI 配置中心（保留入口） */
const goAiCenter = () => {
  emit('update:modelValue', false)
  router.push({
    path: '/system/ai-config',
    query: { tenantId: props.tenantId, tab: 'params' }
  })
}
</script>

<style scoped>
.mb-12 {
  margin-bottom: 12px;
}
.tip {
  margin-top: 8px;
  font-size: 12px;
  color: #909399;
  line-height: 1.5;
}
</style>
