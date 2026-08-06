<template>
  <!-- 新建爬虫任务弹窗 -->
  <el-dialog
    :model-value="modelValue"
    title="新建爬虫任务"
    width="640px"
    destroy-on-close
    @close="handleCancel"
  >
    <el-form :model="form" :rules="rules" ref="formRef" label-width="120px">
      <el-form-item v-if="showTenantSelect" label="所属租户" prop="tenantId">
        <el-select
          v-model="form.tenantId"
          filterable
          placeholder="请选择租户"
          style="width: 100%"
          @change="onTenantChange"
        >
          <el-option v-for="t in tenantOptions" :key="t.id" :label="t.name" :value="t.id" />
        </el-select>
      </el-form-item>

      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="任务名称" prop="name">
            <el-input v-model="form.name" placeholder="如：竞品监控" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="目标平台" prop="platform">
            <el-select
              v-model="form.platform"
              style="width: 100%"
              placeholder="请选择平台"
              @change="onPlatformChange"
            >
              <el-option
                v-for="p in platformOptions"
                :key="p.value"
                :label="p.label"
                :value="p.value"
                :disabled="p.disabled"
              />
            </el-select>
          </el-form-item>
        </el-col>
      </el-row>

      <!-- 必填：执行社媒账号（按套餐平台 + 状态正常过滤） -->
      <el-form-item label="执行社媒账号" prop="socialAccountId">
        <el-select
          v-model="form.socialAccountId"
          filterable
          clearable
          :loading="accountLoading"
          placeholder="请选择状态正常的社媒账号"
          style="width: 100%"
          @change="onAccountChange"
        >
          <el-option
            v-for="a in filteredAccounts"
            :key="a.id"
            :label="accountOptionLabel(a)"
            :value="a.id"
          />
        </el-select>
        <div class="form-tip">
          仅展示当前租户「状态正常」且套餐允许平台的账号；任务启动时由平台公共代理池自动分配 IP
        </div>
      </el-form-item>

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

      <el-divider content-position="left">评论引流</el-divider>
      <el-form-item label="功能开关">
        <div class="switch-row">
          <el-checkbox v-model="form.enableCommentCollect">采集评论</el-checkbox>
          <el-checkbox v-model="form.enableUserHomepageCheck">校验用户主页</el-checkbox>
          <el-checkbox v-model="form.autoCommentReply">AI自动回复</el-checkbox>
        </div>
        <div class="form-tip">自动回复强制真人闲聊口吻，并经全局+租户敏感词双层检测</div>
      </el-form-item>
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="回复间隔(秒)">
            <el-input-number v-model="form.replyInterval" :min="30" :max="3600" style="width: 100%" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="日回复上限">
            <el-input-number v-model="form.dailyReplyMax" :min="1" :max="500" style="width: 100%" />
          </el-form-item>
        </el-col>
      </el-row>
    </el-form>

    <template #footer>
      <el-button @click="handleCancel">取消</el-button>
      <el-button type="primary" :loading="submitting" @click="handleSubmit">创建并启动</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
/**
 * 新建爬虫任务弹窗
 * - 必填执行社媒账号（套餐平台过滤 + 状态正常）
 * - 绑定 socialAccountId → 后端强制用账号 Cookie / 专属代理
 */
import { ref, reactive, computed, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { crawlerTaskApi, tenantApi } from '@/api'
import { getCurrentUser, getCurrentRole } from '@/utils/auth'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  submitting: { type: Boolean, default: false }
})

const emit = defineEmits(['update:modelValue', 'submit'])

const formRef = ref(null)
const showTenantSelect = ref(getCurrentRole() === 'super_admin')
const tenantOptions = ref([])
const accountOptions = ref([])
const accountLoading = ref(false)
const allowPlatforms = ref(['xiaohongshu'])

const PLATFORM_MAP = {
  小红书: 'xiaohongshu',
  抖音: 'douyin',
  视频号: 'channels'
}

const defaultForm = () => ({
  tenantId: '',
  name: '',
  platform: '小红书',
  socialAccountId: '',
  taskType: 'keyword',
  keywords: '',
  frequency: '每2小时',
  dailyLimit: 500,
  enableCommentCollect: true,
  enableUserHomepageCheck: true,
  autoCommentReply: false,
  replyInterval: 90,
  dailyReplyMax: 30
})

const form = reactive(defaultForm())

const rules = computed(() => {
  const r = {
    name: [{ required: true, message: '请输入任务名称', trigger: 'blur' }],
    platform: [{ required: true, message: '请选择目标平台', trigger: 'change' }],
    socialAccountId: [{ required: true, message: '请选择执行社媒账号', trigger: 'change' }],
    keywords: [{ required: true, message: '请输入监控关键词', trigger: 'blur' }],
    frequency: [{ required: true, message: '请选择执行频率', trigger: 'change' }]
  }
  if (showTenantSelect.value) {
    r.tenantId = [{ required: true, message: '请选择所属租户', trigger: 'change' }]
  }
  return r
})

const platformOptions = computed(() =>
  Object.keys(PLATFORM_MAP).map((label) => ({
    label,
    value: label,
    disabled: !allowPlatforms.value.includes(PLATFORM_MAP[label])
  }))
)

/** 按当前目标平台再筛一层账号 */
const filteredAccounts = computed(() => {
  const code = PLATFORM_MAP[form.platform]
  if (!code) return accountOptions.value
  const label = form.platform
  return accountOptions.value.filter(
    (a) => a.platform === label || a.platformCodeLabel === label
  )
})

const accountOptionLabel = (a) => {
  const proxy = a.bindIp ? ` · 平台IP ${a.bindIp}` : ' · 启动自动分配IP'
  return `${a.name || a.accountName}（${a.platform}）${proxy}`
}

const loadTenants = async () => {
  const user = getCurrentUser()
  if (getCurrentRole() !== 'super_admin') {
    showTenantSelect.value = false
    tenantOptions.value = []
    if (user?.tenantId) {
      form.tenantId = user.tenantId
    }
    return
  }
  try {
    const data = await tenantApi.list({ page: 1, size: 200 })
    tenantOptions.value = (data?.list || []).map((t) => ({ id: t.id, name: t.name }))
    showTenantSelect.value = true
  } catch {
    showTenantSelect.value = false
    tenantOptions.value = []
    if (user?.tenantId) {
      form.tenantId = user.tenantId
    }
  }
}

const loadQuotaAndAccounts = async (tenantId) => {
  accountOptions.value = []
  form.socialAccountId = ''
  if (!tenantId) return

  accountLoading.value = true
  try {
    const accData = await crawlerTaskApi.executableAccounts(tenantId)
    allowPlatforms.value = accData?.allowPlatforms?.length
      ? accData.allowPlatforms
      : ['xiaohongshu']
    // 若当前平台不在套餐白名单，切到第一个可用平台
    const curCode = PLATFORM_MAP[form.platform]
    if (!allowPlatforms.value.includes(curCode)) {
      const first = Object.entries(PLATFORM_MAP).find(([, c]) => allowPlatforms.value.includes(c))
      if (first) form.platform = first[0]
    }
    accountOptions.value = accData?.list || []
  } catch {
    accountOptions.value = []
    allowPlatforms.value = ['xiaohongshu']
  } finally {
    accountLoading.value = false
  }
}

const onTenantChange = (tid) => {
  loadQuotaAndAccounts(tid)
}

const onPlatformChange = () => {
  form.socialAccountId = ''
}

const onAccountChange = (id) => {
  const acc = accountOptions.value.find((a) => a.id === id)
  if (acc?.platform) {
    form.platform = acc.platform
  }
}

watch(
  () => props.modelValue,
  async (val) => {
    if (!val) return
    Object.assign(form, defaultForm())
    accountOptions.value = []
    await loadTenants()
    const user = getCurrentUser()
    if (!showTenantSelect.value && user?.tenantId) {
      form.tenantId = user.tenantId
      await loadQuotaAndAccounts(user.tenantId)
    } else if (tenantOptions.value.length === 1) {
      form.tenantId = tenantOptions.value[0].id
      await loadQuotaAndAccounts(form.tenantId)
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
    if (!form.tenantId) {
      ElMessage.warning('请选择所属租户')
      return
    }
    if (!form.socialAccountId) {
      ElMessage.warning('请选择执行社媒账号')
      return
    }
    emit('submit', {
      name: form.name,
      platform: form.platform,
      taskType: form.taskType,
      keywords: form.keywords,
      frequency: form.frequency,
      dailyLimit: form.dailyLimit,
      tenantId: form.tenantId,
      socialAccountId: form.socialAccountId,
      enableCommentCollect: form.enableCommentCollect,
      enableUserHomepageCheck: form.enableUserHomepageCheck,
      autoCommentReply: form.autoCommentReply,
      replyInterval: form.replyInterval,
      dailyReplyMax: form.dailyReplyMax
    })
  })
}
</script>

<style scoped>
.form-tip {
  margin-top: 4px;
  font-size: 12px;
  color: #909399;
  line-height: 1.4;
}
.switch-row {
  display: flex;
  flex-wrap: wrap;
  gap: 12px 20px;
}
</style>
