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
        <el-divider content-position="left">定价</el-divider>
        <el-form-item label="月费（元）">
          <el-input-number v-model="form.priceMonthly" :min="0" :max="999999" controls-position="right" />
        </el-form-item>

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
        <el-form-item label="公共池可分配IP数">
          <el-input-number v-model="form.maxProxyIp" :min="-1" :max="999999" controls-position="right" />
        </el-form-item>
        <el-form-item label="每日公共IP请求上限">
          <el-input-number v-model="form.dailyProxyRequestLimit" :min="-1" :max="9999999" controls-position="right" />
          <div class="tip">达限后系统自动暂停该套餐租户的运行中爬虫任务</div>
        </el-form-item>

        <el-divider content-position="left">平台与增值开关</el-divider>
        <el-alert
          type="warning"
          :closable="false"
          title="IP 托管：全部套餐强制使用平台公共住宅代理池，已全局关闭租户自有代理上传"
          style="margin-bottom: 12px"
        />
        <el-form-item label="可绑定平台">
          <el-checkbox-group v-model="form.allowPlatforms">
            <el-checkbox label="xiaohongshu">小红书</el-checkbox>
            <el-checkbox label="douyin">抖音</el-checkbox>
            <el-checkbox label="channels">视频号</el-checkbox>
          </el-checkbox-group>
        </el-form-item>
        <el-form-item label="小红书独立 AI 配置">
          <el-switch v-model="form.enableAccountAiConfig" />
        </el-form-item>
        <el-form-item label="账号专属知识库">
          <el-switch v-model="form.enableAccountKnowledge" />
        </el-form-item>
        <el-form-item label="自定义 API-Key">
          <el-switch v-model="form.enableCustomApiKey" />
        </el-form-item>
        <el-form-item label="子账号管理">
          <el-switch v-model="form.enableSubAccount" />
        </el-form-item>
        <el-form-item label="爬虫真人行为">
          <el-switch v-model="form.enableHumanBehavior" />
        </el-form-item>
        <el-form-item label="CRM自动提醒">
          <el-switch v-model="form.enableCrmAutoRemind" />
        </el-form-item>
        <el-form-item label="Excel导出">
          <el-switch v-model="form.enableExcelExport" />
        </el-form-item>
        <el-form-item label="专属隔离IP池">
          <el-switch v-model="form.enableDedicatedIpPool" />
        </el-form-item>
        <el-form-item label="IP风险检测">
          <el-switch v-model="form.enableIpRiskCheck" />
        </el-form-item>
        <el-form-item label="IP自动轮换">
          <el-switch v-model="form.enableIpRotate" />
        </el-form-item>
        <el-form-item label="白标去标识">
          <el-switch v-model="form.enableWhiteLabel" />
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
  priceMonthly: 139,
  maxTemplateLevel: 1,
  maxPrompt: 3,
  maxKnowledge: 5,
  dailyAiLimit: 800,
  maxCrawlerTask: 5,
  maxSocialAccount: 3,
  maxProxyIp: 3,
  dailyProxyRequestLimit: 3000,
  allowPlatforms: ['xiaohongshu'],
  enableAccountAiConfig: false,
  enableAccountKnowledge: false,
  enableCustomApiKey: false,
  enableSubAccount: false,
  enableHumanBehavior: false,
  enableCrmAutoRemind: false,
  enableExcelExport: false,
  enableDedicatedIpPool: false,
  enableIpRiskCheck: false,
  enableIpRotate: false,
  enableWhiteLabel: false,
  remark: ''
})

const unlimitedDisplay = (v) => (v === null || v === undefined ? -1 : v)

const fillForm = (item) => {
  if (!item) return
  form.packageType = item.packageType
  form.priceMonthly = item.priceMonthly ?? 0
  form.maxTemplateLevel = item.maxTemplateLevel
  form.maxPrompt = unlimitedDisplay(item.maxPrompt)
  form.maxKnowledge = unlimitedDisplay(item.maxKnowledge)
  form.dailyAiLimit = unlimitedDisplay(item.dailyAiLimit)
  form.maxCrawlerTask = unlimitedDisplay(item.maxCrawlerTask)
  form.maxSocialAccount = unlimitedDisplay(item.maxSocialAccount)
  form.maxProxyIp = unlimitedDisplay(item.maxProxyIp)
  form.dailyProxyRequestLimit = unlimitedDisplay(item.dailyProxyRequestLimit)
  form.allowPlatforms = [...(item.allowPlatforms || [])]
  form.enableAccountAiConfig = !!item.enableAccountAiConfig
  form.enableAccountKnowledge = !!item.enableAccountKnowledge
  form.enableCustomApiKey = !!item.enableCustomApiKey
  form.enableSubAccount = !!item.enableSubAccount
  form.enableHumanBehavior = !!item.enableHumanBehavior
  form.enableCrmAutoRemind = !!item.enableCrmAutoRemind
  form.enableExcelExport = !!item.enableExcelExport
  form.enableDedicatedIpPool = !!item.enableDedicatedIpPool
  form.enableIpRiskCheck = !!item.enableIpRiskCheck
  form.enableIpRotate = !!item.enableIpRotate
  form.enableWhiteLabel = !!item.enableWhiteLabel
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
  price_monthly: form.priceMonthly,
  max_template_level: form.maxTemplateLevel,
  max_prompt: form.maxPrompt,
  max_knowledge: form.maxKnowledge,
  daily_ai_limit: form.dailyAiLimit,
  max_crawler_task: form.maxCrawlerTask,
  max_social_account: form.maxSocialAccount,
  max_proxy_ip: form.maxProxyIp,
  daily_proxy_request_limit: form.dailyProxyRequestLimit,
  allow_self_proxy: false,
  allow_platforms: form.allowPlatforms,
  enable_account_ai_config: form.enableAccountAiConfig,
  enable_account_knowledge: form.enableAccountKnowledge,
  enable_custom_api_key: form.enableCustomApiKey,
  enable_sub_account: form.enableSubAccount,
  enable_human_behavior: form.enableHumanBehavior,
  enable_crm_auto_remind: form.enableCrmAutoRemind,
  enable_excel_export: form.enableExcelExport,
  enable_dedicated_ip_pool: form.enableDedicatedIpPool,
  enable_ip_risk_check: form.enableIpRiskCheck,
  enable_ip_rotate: form.enableIpRotate,
  enable_white_label: form.enableWhiteLabel,
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
