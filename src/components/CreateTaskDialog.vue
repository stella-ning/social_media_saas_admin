<template>
  <!-- 新建爬虫任务弹窗 -->
  <el-dialog
    :model-value="modelValue"
    title="新建爬虫任务"
    width="600px"
    destroy-on-close
    @close="handleCancel"
  >
    <el-form :model="form" :rules="rules" ref="formRef" label-width="110px">
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="任务名称" prop="name">
            <el-input v-model="form.name" placeholder="如：竞品监控" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="目标平台" prop="platform">
            <el-select v-model="form.platform" style="width: 100%" placeholder="请选择平台">
              <el-option label="小红书" value="小红书" />
              <el-option label="抖音" value="抖音" />
              <el-option label="视频号" value="视频号" />
            </el-select>
          </el-form-item>
        </el-col>
      </el-row>

      <!-- 任务类型：全网关键词采集 / 同行笔记监控 -->
      <el-form-item label="任务类型" prop="taskType">
        <el-radio-group v-model="form.taskType">
          <el-radio value="keyword">全网关键词采集</el-radio>
          <el-radio value="monitor">同行笔记监控</el-radio>
        </el-radio-group>
      </el-form-item>

      <el-form-item label="监控关键词" prop="keywords">
        <el-input
          v-model="form.keywords"
          type="textarea"
          :rows="3"
          placeholder="多个用逗号隔开，如：护肤品, 祛痘, 面膜"
        />
      </el-form-item>

      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="执行频率" prop="frequency">
            <el-select v-model="form.frequency" style="width: 100%">
              <el-option label="每1小时" value="每1小时" />
              <el-option label="每2小时" value="每2小时" />
              <el-option label="每6小时" value="每6小时" />
              <el-option label="每天一次" value="每天一次" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="日采集上限" prop="dailyLimit">
            <el-input-number v-model="form.dailyLimit" :min="1" :max="10000" style="width: 100%" />
          </el-form-item>
        </el-col>
      </el-row>
    </el-form>

    <!-- 底部：创建并启动、取消 -->
    <template #footer>
      <el-button @click="handleCancel">取消</el-button>
      <el-button type="primary" :loading="submitting" @click="handleSubmit">创建并启动</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
/**
 * 新建爬虫任务弹窗组件
 * 表单字段：任务名称、目标平台、任务类型、监控关键词、执行频率、日采集上限
 * emit submit 后由父组件调用 API 并在成功后关闭弹窗
 */
import { ref, reactive, watch } from 'vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  /** 父组件 API 提交中状态 */
  submitting: { type: Boolean, default: false }
})

const emit = defineEmits(['update:modelValue', 'submit'])

const formRef = ref(null)

const defaultForm = () => ({
  name: '',
  platform: '小红书',
  taskType: 'keyword',
  keywords: '',
  frequency: '每2小时',
  dailyLimit: 500
})

const form = reactive(defaultForm())

const rules = {
  name: [{ required: true, message: '请输入任务名称', trigger: 'blur' }],
  platform: [{ required: true, message: '请选择目标平台', trigger: 'change' }],
  keywords: [{ required: true, message: '请输入监控关键词', trigger: 'blur' }],
  frequency: [{ required: true, message: '请选择执行频率', trigger: 'change' }]
}

/** 打开时重置表单 */
watch(
  () => props.modelValue,
  (val) => {
    if (val) {
      Object.assign(form, defaultForm())
    }
  }
)

const handleCancel = () => {
  emit('update:modelValue', false)
}

const handleSubmit = async () => {
  if (!formRef.value) return
  await formRef.value.validate((valid) => {
    if (!valid) return
    // 由父组件在 API 成功后关闭弹窗
    emit('submit', { ...form })
  })
}
</script>
