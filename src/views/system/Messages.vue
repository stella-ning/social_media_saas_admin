<template>
  <div class="messages-page">
    <!-- 左侧会话列表 -->
    <div class="chat-list-panel">
      <div class="search-box">
        <el-input
          v-model="searchKey"
          placeholder="搜索会话..."
          clearable
          :prefix-icon="Search"
          @keyup.enter="loadSessions"
          @clear="loadSessions"
        />
        <div class="filter-row">
          <el-select v-model="filterHandle" clearable placeholder="处理状态" size="small" style="width: 48%" @change="loadSessions">
            <el-option label="未读" value="unread" />
            <el-option label="已读" value="read" />
            <el-option label="已处理" value="processed" />
          </el-select>
          <el-select v-model="filterIntent" clearable placeholder="意向" size="small" style="width: 48%" @change="loadSessions">
            <el-option label="高意向" value="high" />
            <el-option label="普通意向" value="normal" />
            <el-option label="无意向" value="none" />
          </el-select>
        </div>
      </div>
      <div v-loading="sessionsLoading" class="chat-list">
        <div
          v-for="chat in chatList"
          :key="chat.id"
          class="chat-item"
          :class="{
            active: activeId === chat.id,
            'is-unread': chat.handleStatus === 'unread' || chat.unread > 0,
            'is-closed': chat.sessionStatus === 'closed'
          }"
          @click="selectChat(chat)"
        >
          <el-avatar :size="40" :src="chat.avatar || defaultAvatar" />
          <div class="chat-meta">
            <div class="chat-top">
              <span class="chat-name">
                {{ chat.name }}
                <el-tag v-if="chat.intentLevel === 'high'" type="danger" size="small" effect="plain">高意向</el-tag>
              </span>
              <span class="chat-time">{{ chat.time }}</span>
            </div>
            <div class="chat-sub">
              <el-tag size="small" effect="plain">{{ chat.platform }}</el-tag>
              <span class="tenant-txt">{{ chat.tenant }}</span>
            </div>
            <div class="chat-preview">{{ chat.lastMsg }}</div>
          </div>
          <el-badge v-if="chat.unread" :value="chat.unread" class="unread-badge" />
        </div>
        <el-empty v-if="!sessionsLoading && !chatList.length" description="暂无会话" :image-size="64" />
      </div>
    </div>

    <!-- 右侧聊天窗口 -->
    <div class="chat-window" v-if="activeChat">
      <div class="chat-header">
        <div class="header-left">
          <span class="header-name">{{ activeChat.name }}</span>
          <el-tag type="success" size="small" effect="light">{{ activeChat.platform }}</el-tag>
          <el-tag type="primary" size="small" effect="light">租户：{{ activeChat.tenant }}</el-tag>
          <el-tag v-if="activeChat.sessionStatus === 'closed'" type="info" size="small">已关闭</el-tag>
          <el-tag v-if="isHumanTakeover" type="warning" size="small">人工接管中</el-tag>
        </div>
        <div class="header-right">
          <div class="ai-switch">
            <span>AI自动回复</span>
            <el-switch
              v-model="aiAutoReply"
              :disabled="isHumanTakeover || activeChat.sessionStatus === 'closed'"
              :loading="settingsLoading"
              @change="onAiSwitchChange"
            />
          </div>
          <el-button type="primary" plain size="small" :loading="settingsLoading" @click="takeOver">
            转人工接管
          </el-button>
          <el-button
            v-if="activeChat.intentLevel === 'high' || activeChat.intentLevel === 'normal'"
            type="danger"
            plain
            size="small"
            :loading="pushingCrm"
            :disabled="!!activeChat.crmLeadId"
            @click="pushCrm"
          >
            {{ activeChat.crmLeadId ? '已推送CRM' : '一键推送CRM' }}
          </el-button>
        </div>
      </div>

      <!-- 会话标签 -->
      <div class="tag-bar">
        <span class="tag-label">会话标签：</span>
        <el-tag
          v-for="t in activeChat.tags || []"
          :key="t"
          size="small"
          class="tag-item"
          closable
          @close="removeTag(t)"
        >
          {{ t }}
        </el-tag>
        <el-select
          v-model="intentDraft"
          size="small"
          placeholder="意向等级"
          style="width: 120px"
          @change="saveIntent"
        >
          <el-option label="无意向" value="none" />
          <el-option label="普通意向" value="normal" />
          <el-option label="高意向" value="high" />
        </el-select>
        <el-input
          v-model="productDraft"
          size="small"
          placeholder="咨询产品"
          style="width: 140px"
          @change="saveProduct"
        />
        <el-button size="small" link type="primary" @click="markProcessed">标为已处理</el-button>
        <el-button
          v-if="activeChat.sessionStatus === 'closed'"
          size="small"
          link
          type="success"
          @click="reopenSession"
        >
          重新打开
        </el-button>
      </div>

      <div v-loading="detailLoading" class="chat-body" ref="chatBodyRef">
        <div class="date-divider">
          <span>{{ activeChat.dateLabel || '会话记录' }}</span>
        </div>
        <div
          v-for="msg in activeChat.messages"
          :key="msg.id || msg.createdAt + msg.content"
          class="msg-row"
          :class="msg.from"
        >
          <el-avatar v-if="msg.from === 'user'" :size="32" :src="activeChat.avatar || defaultAvatar" />
          <div v-else class="ai-avatar">{{ msg.from === 'ai' ? '客' : '人' }}</div>
          <div>
            <div class="msg-bubble" :class="[msg.from, { blocked: msg.isBlocked }]">
              {{ msg.content }}
            </div>
            <div class="msg-meta">{{ msg.createdAt }}</div>
          </div>
        </div>
      </div>

      <div class="chat-input">
        <div class="input-tools">
          <el-popover placement="top" :width="360" trigger="click">
            <template #reference>
              <el-button size="small" text>快捷回复</el-button>
            </template>
            <div class="quick-box">
              <div v-if="!quickReplies.length" class="quick-empty">暂无话术，可在下方新增</div>
              <div
                v-for="q in quickReplies"
                :key="q.id"
                class="quick-item"
                @click="useQuickReply(q)"
              >
                <strong>{{ q.title }}</strong>
                <p>{{ q.content }}</p>
              </div>
              <el-divider style="margin: 8px 0" />
              <el-input v-model="qrForm.title" size="small" placeholder="标题" style="margin-bottom: 6px" />
              <el-input v-model="qrForm.content" type="textarea" :rows="2" size="small" placeholder="话术内容" />
              <el-button size="small" type="primary" style="margin-top: 6px" :loading="qrSaving" @click="saveQuick">
                保存话术
              </el-button>
            </div>
          </el-popover>
          <el-button size="small" text @click="simulateVisitor">模拟访客消息</el-button>
        </div>
        <el-input
          v-model="draft"
          type="textarea"
          :rows="3"
          resize="none"
          :disabled="activeChat.sessionStatus === 'closed'"
          placeholder="按 Ctrl + Enter 发送消息..."
          @keydown="onKeydown"
        />
        <div class="input-footer">
          <el-button
            type="primary"
            :loading="sending"
            :disabled="!draft.trim() || activeChat.sessionStatus === 'closed'"
            @click="sendMessage"
          >
            发送
          </el-button>
        </div>
      </div>
    </div>

    <div v-else class="chat-empty">
      <el-empty description="请选择左侧会话开始沟通" />
    </div>
  </div>
</template>

<script setup>
/**
 * 消息会话管理（迭代）
 * - 标签 / 意向 / 推 CRM / 快捷回复 / 未读样式 / AI 真人话术自动接待
 */
import { ref, reactive, onMounted, nextTick, watch } from 'vue'
import { Search } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { messageApi } from '@/api'
import { getCurrentUser } from '@/utils/auth'

const defaultAvatar = 'https://cube.elemecdn.com/3/7c/3ea6beec64369c2642b92c6726f1epng.png'

const chatList = ref([])
const activeId = ref(null)
const activeChat = ref(null)
const searchKey = ref('')
const filterHandle = ref('')
const filterIntent = ref('')
const aiAutoReply = ref(true)
const isHumanTakeover = ref(false)
const draft = ref('')
const chatBodyRef = ref(null)
const intentDraft = ref('none')
const productDraft = ref('')

const sessionsLoading = ref(false)
const detailLoading = ref(false)
const sending = ref(false)
const settingsLoading = ref(false)
const pushingCrm = ref(false)

const quickReplies = ref([])
const qrForm = reactive({ title: '', content: '' })
const qrSaving = ref(false)

const loadSessions = async () => {
  sessionsLoading.value = true
  try {
    const data = await messageApi.sessions({
      keyword: searchKey.value.trim() || undefined,
      handleStatus: filterHandle.value || undefined,
      intentLevel: filterIntent.value || undefined,
      page: 1,
      size: 100
    })
    chatList.value = data?.list || (Array.isArray(data) ? data : [])
  } catch {
    chatList.value = []
  } finally {
    sessionsLoading.value = false
  }
}

const selectChat = async (chat) => {
  activeId.value = chat.id
  detailLoading.value = true
  try {
    const data = await messageApi.detail(chat.id)
    activeChat.value = { ...data, messages: data.messages || [] }
    aiAutoReply.value = data.aiAutoReply ?? true
    isHumanTakeover.value = data.humanTakeover ?? false
    intentDraft.value = data.intentLevel || 'none'
    productDraft.value = data.consultProduct || ''
    const item = chatList.value.find((c) => c.id === chat.id)
    if (item) {
      item.unread = 0
      item.handleStatus = data.handleStatus
    }
    await loadQuickReplies(data.tenantId)
    nextTick(scrollBottom)
  } catch {
    activeChat.value = null
  } finally {
    detailLoading.value = false
  }
}

const scrollBottom = () => {
  const el = chatBodyRef.value
  if (el) el.scrollTop = el.scrollHeight
}

const syncActiveFromSettings = (data) => {
  activeChat.value = {
    ...activeChat.value,
    ...data,
    messages: data.messages || activeChat.value.messages
  }
  aiAutoReply.value = !!data.aiAutoReply
  isHumanTakeover.value = !!data.humanTakeover
  intentDraft.value = data.intentLevel || intentDraft.value
  productDraft.value = data.consultProduct || productDraft.value
  const idx = chatList.value.findIndex((c) => c.id === data.id)
  if (idx >= 0) {
    chatList.value[idx] = { ...chatList.value[idx], ...data }
  }
}

const onAiSwitchChange = async (val) => {
  if (!activeChat.value) return
  settingsLoading.value = true
  try {
    const data = await messageApi.updateSettings(activeChat.value.id, {
      aiAutoReply: val,
      humanTakeover: val ? false : isHumanTakeover.value
    })
    syncActiveFromSettings(data)
    ElMessage.success('设置已保存')
  } catch {
    aiAutoReply.value = !val
  } finally {
    settingsLoading.value = false
  }
}

const takeOver = async () => {
  if (!activeChat.value) return
  settingsLoading.value = true
  try {
    const data = await messageApi.updateSettings(activeChat.value.id, {
      humanTakeover: true,
      aiAutoReply: false
    })
    syncActiveFromSettings(data)
    ElMessage.success('已切换为人工接管，AI 自动回复已暂停')
  } catch {
    // ignore
  } finally {
    settingsLoading.value = false
  }
}

const saveIntent = async (val) => {
  if (!activeChat.value) return
  try {
    const data = await messageApi.updateSettings(activeChat.value.id, { intentLevel: val })
    syncActiveFromSettings(data)
    ElMessage.success('意向标签已更新')
  } catch {
    // ignore
  }
}

const saveProduct = async () => {
  if (!activeChat.value) return
  try {
    const data = await messageApi.updateSettings(activeChat.value.id, {
      consultProduct: productDraft.value
    })
    syncActiveFromSettings(data)
  } catch {
    // ignore
  }
}

const removeTag = async (tag) => {
  if (!activeChat.value) return
  const next = (activeChat.value.tags || []).filter((t) => t !== tag)
  try {
    const data = await messageApi.updateSettings(activeChat.value.id, { tags: next })
    syncActiveFromSettings(data)
  } catch {
    // ignore
  }
}

const markProcessed = async () => {
  if (!activeChat.value) return
  const data = await messageApi.updateSettings(activeChat.value.id, { handleStatus: 'processed' })
  syncActiveFromSettings(data)
  ElMessage.success('已标记为已处理')
}

const reopenSession = async () => {
  if (!activeChat.value) return
  const data = await messageApi.updateSettings(activeChat.value.id, { sessionStatus: 'open' })
  syncActiveFromSettings(data)
  ElMessage.success('会话已重新打开')
}

const pushCrm = async () => {
  if (!activeChat.value) return
  pushingCrm.value = true
  try {
    const data = await messageApi.pushCrm(activeChat.value.id)
    if (data?.session) syncActiveFromSettings(data.session)
    ElMessage.success(data?.lead ? `已推送线索：${data.lead.nickname}` : '已推送至 CRM')
  } catch {
    // ignore
  } finally {
    pushingCrm.value = false
  }
}

const sendMessage = async () => {
  if (!draft.value.trim() || !activeChat.value) return
  const content = draft.value.trim()
  sending.value = true
  try {
    const data = await messageApi.send(activeChat.value.id, { content, from: 'human' })
    activeChat.value = { ...data, messages: data.messages || [] }
    const idx = chatList.value.findIndex((c) => c.id === data.id)
    if (idx >= 0) chatList.value[idx] = { ...chatList.value[idx], ...data }
    // 人工发送后置顶
    if (idx > 0) {
      const [row] = chatList.value.splice(idx, 1)
      chatList.value.unshift(row)
    }
    draft.value = ''
    nextTick(scrollBottom)
  } catch {
    // ignore
  } finally {
    sending.value = false
  }
}

const onKeydown = (e) => {
  if (e.ctrlKey && e.key === 'Enter') {
    e.preventDefault()
    sendMessage()
  }
}

const loadQuickReplies = async (tenantId) => {
  if (!tenantId) {
    const user = getCurrentUser()
    tenantId = user?.tenantId
  }
  if (!tenantId) {
    quickReplies.value = []
    return
  }
  try {
    const data = await messageApi.quickReplies({ tenantId })
    quickReplies.value = data?.list || []
  } catch {
    quickReplies.value = []
  }
}

const useQuickReply = (q) => {
  draft.value = q.content
}

const saveQuick = async () => {
  if (!qrForm.title.trim() || !qrForm.content.trim()) {
    ElMessage.warning('请填写标题和内容')
    return
  }
  const tenantId = activeChat.value?.tenantId || getCurrentUser()?.tenantId
  if (!tenantId) {
    ElMessage.warning('无法确定租户')
    return
  }
  qrSaving.value = true
  try {
    await messageApi.saveQuickReply({
      tenantId,
      title: qrForm.title,
      content: qrForm.content
    })
    qrForm.title = ''
    qrForm.content = ''
    ElMessage.success('话术已保存')
    await loadQuickReplies(tenantId)
  } catch {
    // ignore
  } finally {
    qrSaving.value = false
  }
}

/** 演示：模拟访客咨询，走完整 AI 接待链路 */
const simulateVisitor = async () => {
  if (!activeChat.value) return
  try {
    const { value } = await ElMessageBox.prompt('输入访客消息内容', '模拟访客消息', {
      inputValue: '你好，这款护肤品怎么代理？需要什么条件？',
      confirmButtonText: '发送并触发AI接待',
      cancelButtonText: '取消'
    })
    if (!value?.trim()) return
    const data = await messageApi.ingest({
      tenantId: activeChat.value.tenantId,
      name: activeChat.value.name,
      platform: activeChat.value.platform || '小红书',
      socialAccountId: activeChat.value.socialAccountId || undefined,
      session_id: activeChat.value.id,
      content: value.trim(),
      avatar: activeChat.value.avatar
    })
    activeChat.value = { ...data, messages: data.messages || [] }
    aiAutoReply.value = !!data.aiAutoReply
    isHumanTakeover.value = !!data.humanTakeover
    intentDraft.value = data.intentLevel || 'none'
    productDraft.value = data.consultProduct || ''
    await loadSessions()
    nextTick(scrollBottom)
    ElMessage.success('访客消息已接入，AI 已自动接待')
  } catch (e) {
    if (e !== 'cancel') {
      /* 错误拦截器已提示 */
    }
  }
}

watch(activeId, () => {
  draft.value = ''
})

onMounted(loadSessions)
</script>

<style scoped>
.messages-page {
  display: flex;
  height: calc(100vh - 100px);
  background: #fff;
  border-radius: 4px;
  border: 1px solid #ebeef5;
  overflow: hidden;
  box-shadow: 0 2px 12px 0 rgba(0, 0, 0, 0.06);
}
.chat-list-panel {
  width: 320px;
  flex-shrink: 0;
  border-right: 1px solid #ebeef5;
  display: flex;
  flex-direction: column;
}
.search-box {
  padding: 12px 14px;
  border-bottom: 1px solid #ebeef5;
}
.filter-row {
  display: flex;
  justify-content: space-between;
  margin-top: 8px;
}
.chat-list {
  flex: 1;
  overflow-y: auto;
}
.chat-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 14px 16px;
  cursor: pointer;
  border-bottom: 1px solid #f2f3f5;
  position: relative;
}
.chat-item:hover {
  background: #f5f7fa;
}
.chat-item.active {
  background: #ecf5ff;
}
.chat-item.is-unread {
  background: #fff7e6;
  border-left: 3px solid #e6a23c;
}
.chat-item.is-unread .chat-name {
  font-weight: 700;
  color: #303133;
}
.chat-item.is-closed {
  opacity: 0.65;
}
.chat-meta {
  flex: 1;
  min-width: 0;
}
.chat-top {
  display: flex;
  justify-content: space-between;
  gap: 8px;
}
.chat-name {
  font-size: 14px;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.chat-time {
  font-size: 12px;
  color: #909399;
  flex-shrink: 0;
}
.chat-sub {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 4px;
}
.tenant-txt {
  font-size: 12px;
  color: #909399;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.chat-preview {
  margin-top: 4px;
  font-size: 12px;
  color: #909399;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.unread-badge {
  position: absolute;
  right: 12px;
  top: 36px;
}
.chat-window {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.chat-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  border-bottom: 1px solid #ebeef5;
  gap: 12px;
  flex-wrap: wrap;
}
.header-left {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.header-name {
  font-size: 16px;
  font-weight: 700;
}
.header-right {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.ai-switch {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: #606266;
}
.tag-bar {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  padding: 8px 16px;
  border-bottom: 1px solid #f0f2f5;
  background: #fafafa;
}
.tag-label {
  font-size: 12px;
  color: #909399;
}
.tag-item {
  margin-right: 0;
}
.chat-body {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
  background: #f5f7fa;
}
.date-divider {
  text-align: center;
  margin-bottom: 16px;
}
.date-divider span {
  font-size: 12px;
  color: #909399;
  background: #e4e7ed;
  padding: 2px 10px;
  border-radius: 10px;
}
.msg-row {
  display: flex;
  gap: 10px;
  margin-bottom: 14px;
  align-items: flex-start;
}
.msg-row.human,
.msg-row.ai {
  flex-direction: row-reverse;
}
.ai-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: #409eff;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  flex-shrink: 0;
}
.msg-row.human .ai-avatar {
  background: #67c23a;
}
.msg-bubble {
  max-width: 420px;
  padding: 10px 12px;
  border-radius: 8px;
  font-size: 14px;
  line-height: 1.5;
  background: #fff;
  color: #303133;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}
.msg-bubble.ai,
.msg-bubble.human {
  background: #409eff;
  color: #fff;
}
.msg-bubble.human {
  background: #67c23a;
}
.msg-bubble.blocked {
  background: #fef0f0;
  color: #f56c6c;
  border: 1px dashed #f56c6c;
}
.msg-meta {
  font-size: 11px;
  color: #c0c4cc;
  margin-top: 4px;
}
.msg-row.human .msg-meta,
.msg-row.ai .msg-meta {
  text-align: right;
}
.chat-input {
  border-top: 1px solid #ebeef5;
  padding: 10px 16px 12px;
}
.input-tools {
  display: flex;
  gap: 8px;
  margin-bottom: 8px;
}
.input-footer {
  display: flex;
  justify-content: flex-end;
  margin-top: 8px;
}
.chat-empty {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
}
.quick-box {
  max-height: 320px;
  overflow-y: auto;
}
.quick-item {
  padding: 8px;
  border-radius: 4px;
  cursor: pointer;
  margin-bottom: 4px;
}
.quick-item:hover {
  background: #f5f7fa;
}
.quick-item strong {
  font-size: 13px;
}
.quick-item p {
  margin: 4px 0 0;
  font-size: 12px;
  color: #909399;
  line-height: 1.4;
}
.quick-empty {
  font-size: 12px;
  color: #c0c4cc;
  margin-bottom: 8px;
}
</style>
