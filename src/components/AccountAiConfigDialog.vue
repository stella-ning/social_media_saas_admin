<template>
  <el-dialog
    :model-value="modelValue"
    title="账号 AI 配置（仅小红书）"
    width="560px"
    destroy-on-close
    @update:model-value="emit('update:modelValue', $event)"
  >
    <div v-loading="loading">
      <el-alert
        type="info"
        :closable="false"
        show-icon
        class="mb-12"
        title="留空则继承租户默认配置；优先级：账号绑定 > 租户默认 > 平台全局"
      />

      <el-form label-width="130px">
        <el-form-item label="AI 参数模板">
          <el-select
            v-model="form.bindParamTemplateId"
            clearable
            placeholder="继承租户默认 AI 参数模板"
            style="width: 100%"
          >
            <el-option
              v-for="item in paramOptions"
              :key="item.id"
              :label="item.label"
              :value="item.id"
            />
          </el-select>
        </el-form-item>

        <el-form-item label="Prompt 话术模板">
          <el-select
            v-model="form.bindPromptId"
            clearable
            placeholder="继承租户默认 Prompt"
            style="width: 100%"
          >
            <el-option
              v-for="item in promptOptions"
              :key="item.id"
              :label="item.label"
              :value="item.id"
            />
          </el-select>
        </el-form-item>

        <el-form-item label="专属知识库">
          <el-select
            v-model="form.knowledgeIds"
            multiple
            clearable
            filterable
            collapse-tags
            collapse-tags-tooltip
            placeholder="不选则使用租户全部知识库"
            style="width: 100%"
          >
            <el-option
              v-for="doc in knowledgeOptions"
              :key="doc.id"
              :label="doc.name"
              :value="doc.id"
            />
          </el-select>
        </el-form-item>

        <el-form-item v-if="preview" label="当前生效预览">
          <div class="preview-box">
            <div>来源：{{ preview.source }}</div>
            <div>模型：{{ preview.params?.ai_model || '-' }}</div>
            <div>Prompt：{{ preview.prompt?.name || '（未解析到）' }}</div>
            <div>知识库：{{ preview.knowledgeCount }} 篇（{{ preview.knowledgeSource }}）</div>
          </div>
        </el-form-item>
      </el-form>
    </div>

    <template #footer>
      <el-button :disabled="saving" @click="handleReset">重置为继承默认</el-button>
      <el-button @click="emit('update:modelValue', false)">取消</el-button>
      <el-button type="primary" :loading="saving" @click="handleSave">保存</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
/**
 * 小红书账号 AI 配置弹窗
 * - 绑定 AI 参数模板 / Prompt / 知识库多选
 * - 重置：全部清空，继承租户层配置
 */
import { reactive, ref, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { socialAccountApi } from '@/api'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  accountId: { type: [Number, String], default: null }
})

const emit = defineEmits(['update:modelValue', 'saved'])

const loading = ref(false)
const saving = ref(false)
const paramOptions = ref([])
const promptOptions = ref([])
const knowledgeOptions = ref([])
const preview = ref(null)

const form = reactive({
  bindParamTemplateId: null,
  bindPromptId: null,
  knowledgeIds: []
})

const loadConfig = async () => {
  if (!props.accountId) return
  loading.value = true
  try {
    const data = await socialAccountApi.getAiConfig(props.accountId)
    paramOptions.value = data?.paramTemplates || []
    promptOptions.value = data?.promptTemplates || []
    knowledgeOptions.value = data?.knowledgeDocs || []
    form.bindParamTemplateId = data?.bindParamTemplateId || null
    form.bindPromptId = data?.bindPromptId || null
    form.knowledgeIds = data?.knowledgeIds || []
    preview.value = data?.resolvedPreview || null
  } catch {
    emit('update:modelValue', false)
  } finally {
    loading.value = false
  }
}

watch(
  () => [props.modelValue, props.accountId],
  ([visible]) => {
    if (visible && props.accountId) loadConfig()
  }
)

const handleSave = async () => {
  saving.value = true
  try {
    await socialAccountApi.saveAiConfig({
      social_account_id: props.accountId,
      bind_param_template_id: form.bindParamTemplateId || null,
      bind_prompt_id: form.bindPromptId || null,
      knowledge_ids: form.knowledgeIds || []
    })
    ElMessage.success('账号 AI 配置已保存')
    emit('saved')
    emit('update:modelValue', false)
  } catch {
    // 拦截器已提示
  } finally {
    saving.value = false
  }
}

const handleReset = () => {
  ElMessageBox.confirm('确认清空账号绑定，全部恢复为继承租户默认配置？', '重置确认', {
    type: 'warning'
  })
    .then(async () => {
      saving.value = true
      try {
        await socialAccountApi.saveAiConfig({
          social_account_id: props.accountId,
          reset: true
        })
        ElMessage.success('已重置为继承租户配置')
        emit('saved')
        emit('update:modelValue', false)
      } catch {
        // ignore
      } finally {
        saving.value = false
      }
    })
    .catch(() => {})
}
</script>

<style scoped>
.mb-12 {
  margin-bottom: 12px;
}
.preview-box {
  width: 100%;
  background: #f5f7fa;
  border: 1px solid #e4e7ed;
  border-radius: 4px;
  padding: 10px 12px;
  font-size: 12px;
  color: #606266;
  line-height: 1.7;
}
</style>
