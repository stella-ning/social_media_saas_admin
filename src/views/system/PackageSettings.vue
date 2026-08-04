<template>
  <div class="package-page" v-loading="pageLoading">
    <div class="page-header">
      <div>
        <h3 class="page-title">套餐权限管理</h3>
        <p class="page-desc">配置基础版 / 专业版 / 企业版的功能配额与开关（仅超级管理员可编辑）</p>
      </div>
    </div>

    <el-card shadow="hover">
      <el-tabs v-model="activeTab" @tab-change="onTabChange">
        <el-tab-pane label="基础版套餐配置" name="1" />
        <el-tab-pane label="专业版套餐配置" name="2" />
        <el-tab-pane label="企业版套餐配置" name="3" />
      </el-tabs>

      <el-form v-if="form" label-width="200px" class="pkg-form">
        <el-divider content-position="left">AI 参数模板等级</el-divider>
        <el-form-item label="允许最高模板等级">
          <el-select v-model="form.maxTemplateLevel" style="width: 280px">
            <el-option
              v-for="lv in levelOptions"
              :key="lv.value"
              :label="lv.label"
              :value="lv.value"
              :disabled="lv.value > Number(activeTab)"
            />
          </el-select>
          <div class="tip">租户列表「AI配置」弹窗按此等级过滤可选模板；可低于套餐默认，不可高于套餐类型本身</div>
        </el-form-item>

        <el-divider content-position="left">配额上限（留空或 -1 表示无限）</el-divider>
        <el-form-item label="Prompt 话术模板上限">
          <el-input-number v-model="form.maxPrompt" :min="-1" :max="999999" controls-position="right" />
        </el-form-item>
        <el-form-item label="知识库文档上限">
          <el-input-number v-model="form.maxKnowledge" :min="-1" :max="999999" controls-position="right" />
        </el-form-item>
        <el-form-item label="每日 AI 调用配额">
          <el-input-number v-model="form.dailyAiLimit" :min="-1" :max="9999999" controls-position="right" />
        </el-form-item>
        <el-form-item label="爬虫任务数量上限">
          <el-input-number v-model="form.maxCrawlerTask" :min="-1" :max="999999" controls-position="right" />
        </el-form-item>
        <el-form-item label="社媒账号绑定上限">
          <el-input-number v-model="form.maxSocialAccount" :min="-1" :max="999999" controls-position="right" />
        </el-form-item>

        <el-divider content-position="left">平台与功能开关</el-divider>
        <el-form-item label="可绑定平台">
          <el-checkbox-group v-model="form.allowPlatforms">
            <el-checkbox label="xiaohongshu">小红书</el-checkbox>
            <el-checkbox label="douyin">抖音</el-checkbox>
            <el-checkbox label="channels">视频号</el-checkbox>
          </el-checkbox-group>
        </el-form-item>
        <el-form-item label="小红书独立 AI 配置">
          <el-switch v-model="form.enableAccountAiConfig" />
          <span class="switch-tip">关闭后账号强制继承租户 AI 模板</span>
        </el-form-item>
        <el-form-item label="账号专属知识库">
          <el-switch v-model="form.enableAccountKnowledge" />
        </el-form-item>
        <el-form-item label="自定义 API-Key">
          <el-switch v-model="form.enableCustomApiKey" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="form.remark" type="textarea" :rows="2" maxlength="255" show-word-limit />
        </el-form-item>

        <el-form-item>
          <el-button type="primary" :loading="saving" @click="handleSave">保存配置</el-button>
          <el-button :loading="resetting" @click="handleReset">重置为系统默认</el-button>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
/**
 * 套餐权限管理
 * - Tab：基础 / 专业 / 企业
 * - 保存写入 saas_package_setting；重置恢复系统默认
 * - 仅超管可访问（路由 + 后端 tenants 权限）
 */
import { ref, reactive, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { packageSettingApi } from '@/api'

const pageLoading = ref(false)
const saving = ref(false)
const resetting = ref(false)
const activeTab = ref('1')
const listMap = ref({})

const levelOptions = [
  { value: 1, label: 'Level 1 · 仅基础模板' },
  { value: 2, label: 'Level 1-2 · 基础+专业' },
  { value: 3, label: 'Level 1-2-3 · 全部' }
]

const form = reactive({
  packageType: 1,
  maxTemplateLevel: 1,
  maxPrompt: 3,
  maxKnowledge: 5,
  dailyAiLimit: 800,
  maxCrawlerTask: 5,
  maxSocialAccount: 3,
  allowPlatforms: ['xiaohongshu'],
  enableAccountAiConfig: false,
  enableAccountKnowledge: false,
  enableCustomApiKey: false,
  remark: ''
})

const unlimitedDisplay = (v) => (v === null || v === undefined ? -1 : v)

const fillForm = (item) => {
  if (!item) return
  form.packageType = item.packageType
  form.maxTemplateLevel = item.maxTemplateLevel
  form.maxPrompt = unlimitedDisplay(item.maxPrompt)
  form.maxKnowledge = unlimitedDisplay(item.maxKnowledge)
  form.dailyAiLimit = unlimitedDisplay(item.dailyAiLimit)
  form.maxCrawlerTask = unlimitedDisplay(item.maxCrawlerTask)
  form.maxSocialAccount = unlimitedDisplay(item.maxSocialAccount)
  form.allowPlatforms = [...(item.allowPlatforms || [])]
  form.enableAccountAiConfig = !!item.enableAccountAiConfig
  form.enableAccountKnowledge = !!item.enableAccountKnowledge
  form.enableCustomApiKey = !!item.enableCustomApiKey
  form.remark = item.remark || ''
}

const loadList = async () => {
  pageLoading.value = true
  try {
    const data = await packageSettingApi.list()
    const map = {}
    ;(data?.list || []).forEach((item) => {
      map[String(item.packageType)] = item
    })
    listMap.value = map
    fillForm(map[activeTab.value])
  } catch {
    listMap.value = {}
  } finally {
    pageLoading.value = false
  }
}

const onTabChange = (tab) => {
  fillForm(listMap.value[String(tab)])
}

const payloadFromForm = () => ({
  package_type: Number(activeTab.value),
  max_template_level: form.maxTemplateLevel,
  max_prompt: form.maxPrompt,
  max_knowledge: form.maxKnowledge,
  daily_ai_limit: form.dailyAiLimit,
  max_crawler_task: form.maxCrawlerTask,
  max_social_account: form.maxSocialAccount,
  allow_platforms: form.allowPlatforms,
  enable_account_ai_config: form.enableAccountAiConfig,
  enable_account_knowledge: form.enableAccountKnowledge,
  enable_custom_api_key: form.enableCustomApiKey,
  remark: form.remark
})

const handleSave = async () => {
  if (!form.allowPlatforms.length) {
    ElMessage.warning('请至少选择一个可绑定平台')
    return
  }
  saving.value = true
  try {
    const saved = await packageSettingApi.save(payloadFromForm())
    listMap.value[String(saved.packageType)] = saved
    fillForm(saved)
    ElMessage.success('套餐权限已保存')
  } catch {
    // ignore
  } finally {
    saving.value = false
  }
}

const handleReset = () => {
  ElMessageBox.confirm('确认将该套餐重置为系统默认参数？已绑定超限模板的租户将自动降级。', '重置确认', {
    type: 'warning'
  })
    .then(async () => {
      resetting.value = true
      try {
        const saved = await packageSettingApi.save({
          package_type: Number(activeTab.value),
          reset: true
        })
        listMap.value[String(saved.packageType)] = saved
        fillForm(saved)
        ElMessage.success('已重置为系统默认套餐配置')
      } catch {
        // ignore
      } finally {
        resetting.value = false
      }
    })
    .catch(() => {})
}

onMounted(loadList)
</script>

<style scoped>
.package-page {
  min-height: 100%;
}
.page-header {
  margin-bottom: 16px;
}
.page-title {
  margin: 0 0 6px;
  font-size: 18px;
  font-weight: 700;
  color: #303133;
}
.page-desc {
  margin: 0;
  font-size: 13px;
  color: #909399;
}
.pkg-form {
  max-width: 820px;
  padding-top: 8px;
}
.tip,
.switch-tip {
  margin-left: 0;
  margin-top: 6px;
  font-size: 12px;
  color: #909399;
  line-height: 1.4;
}
.switch-tip {
  margin-left: 12px;
}
</style>
