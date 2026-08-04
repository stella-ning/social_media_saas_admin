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
      </div>
      <div v-loading="sessionsLoading" class="chat-list">
        <div
          v-for="chat in chatList"
          :key="chat.id"
          class="chat-item"
          :class="{ active: activeId === chat.id }"
          @click="selectChat(chat)"
        >
          <el-avatar :size="40" :src="chat.avatar || defaultAvatar" />
          <div class="chat-meta">
            <div class="chat-top">
              <span class="chat-name">{{ chat.name }}</span>
              <span class="chat-time">{{ chat.time }}</span>
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
      <!-- 会话头部 -->
      <div class="chat-header">
        <div class="header-left">
          <span class="header-name">{{ activeChat.name }}</span>
          <el-tag type="success" size="small" effect="light">{{ activeChat.platform }}用户</el-tag>
          <el-tag type="primary" size="small" effect="light">所属租户：{{ activeChat.tenant }}</el-tag>
        </div>
        <div class="header-right">
          <div class="ai-switch">
            <span>AI自动回复</span>
            <el-switch v-model="aiAutoReply" :loading="settingsLoading" @change="onAiSwitchChange" />
          </div>
          <el-button
            type="primary"
            plain
            size="small"
            :loading="settingsLoading"
            @click="takeOver"
          >
            转人工接管
          </el-button>
        </div>
      </div>

      <!-- 消息记录 -->
      <div v-loading="detailLoading" class="chat-body" ref="chatBodyRef">
        <div class="date-divider">
          <span>{{ activeChat.dateLabel }}</span>
        </div>
        <div
          v-for="msg in activeChat.messages"
          :key="msg.id || msg.time + msg.content"
          class="msg-row"
          :class="msg.from"
        >
          <el-avatar v-if="msg.from === 'user'" :size="32" :src="activeChat.avatar || defaultAvatar" />
          <div v-else class="ai-avatar">{{ msg.from === 'ai' ? 'AI' : '人' }}</div>
          <div class="msg-bubble" :class="msg.from">{{ msg.content }}</div>
        </div>
      </div>

      <!-- 输入区 -->
      <div class="chat-input">
        <div class="input-tools">
          <el-tooltip content="表情"><el-icon :size="20"><Sunny /></el-icon></el-tooltip>
          <el-tooltip content="图片"><el-icon :size="20"><Picture /></el-icon></el-tooltip>
          <el-tooltip content="文件"><el-icon :size="20"><Folder /></el-icon></el-tooltip>
        </div>
        <el-input
          v-model="draft"
          type="textarea"
          :rows="3"
          resize="none"
          placeholder="按 Ctrl + Enter 发送消息..."
          @keydown="onKeydown"
        />
        <div class="input-footer">
          <el-button type="primary" :loading="sending" :disabled="!draft.trim()" @click="sendMessage">
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
 * 消息会话管理
 * - 左侧会话列表 + 搜索
 * - 右侧：头部（AI自动回复开关 / 转人工）、聊天记录、输入发送
 */
import { ref, onMounted, nextTick } from 'vue'
import { Search, Sunny, Picture, Folder } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { messageApi } from '@/api'

const defaultAvatar = 'https://cube.elemecdn.com/3/7c/3ea6beec64369c2642b92c6726f1epng.png'

const chatList = ref([])
const activeId = ref(null)
const activeChat = ref(null)
const searchKey = ref('')
const aiAutoReply = ref(true)
const isHumanTakeover = ref(false)
const draft = ref('')
const chatBodyRef = ref(null)

const sessionsLoading = ref(false)
const detailLoading = ref(false)
const sending = ref(false)
const settingsLoading = ref(false)

/** 加载会话列表 */
const loadSessions = async () => {
  sessionsLoading.value = true
  try {
    const data = await messageApi.sessions({
      keyword: searchKey.value.trim() || undefined,
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

/** 选中会话并加载详情 */
const selectChat = async (chat) => {
  activeId.value = chat.id
  detailLoading.value = true
  try {
    const data = await messageApi.detail(chat.id)
    activeChat.value = {
      ...data,
      messages: data.messages || []
    }
    aiAutoReply.value = data.aiAutoReply ?? true
    isHumanTakeover.value = data.humanTakeover ?? false
    const item = chatList.value.find((c) => c.id === chat.id)
    if (item) item.unread = 0
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

/** AI 自动回复开关 */
const onAiSwitchChange = async (val) => {
  if (!activeChat.value) return
  settingsLoading.value = true
  try {
    const data = await messageApi.updateSettings(activeChat.value.id, { aiAutoReply: val })
    activeChat.value = { ...activeChat.value, ...data, messages: data.messages || activeChat.value.messages }
    aiAutoReply.value = data.aiAutoReply
    isHumanTakeover.value = data.humanTakeover
    ElMessage.success('设置已保存')
  } catch {
    aiAutoReply.value = !val
  } finally {
    settingsLoading.value = false
  }
}

/** 转人工接管 */
const takeOver = async () => {
  if (!activeChat.value) return
  settingsLoading.value = true
  try {
    const data = await messageApi.updateSettings(activeChat.value.id, {
      humanTakeover: true,
      aiAutoReply: false
    })
    activeChat.value = { ...activeChat.value, ...data, messages: data.messages || activeChat.value.messages }
    aiAutoReply.value = data.aiAutoReply
    isHumanTakeover.value = data.humanTakeover
    ElMessage.success('已切换为人工接管模式')
  } catch {
    // 错误已在拦截器提示
  } finally {
    settingsLoading.value = false
  }
}

/** 发送消息 */
const sendMessage = async () => {
  if (!draft.value.trim() || !activeChat.value) return
  const content = draft.value.trim()
  sending.value = true
  try {
    const data = await messageApi.send(activeChat.value.id, { content })
    if (data?.message) {
      activeChat.value.messages.push(data.message)
    }
    if (data?.session) {
      const idx = chatList.value.findIndex((c) => c.id === data.session.id)
      if (idx >= 0) {
        chatList.value[idx] = { ...chatList.value[idx], ...data.session }
      }
      activeChat.value.lastMsg = data.session.lastMsg
      activeChat.value.time = data.session.time
    }
    draft.value = ''
    nextTick(scrollBottom)
  } catch {
    // 错误已在拦截器提示
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

onMounted(() => {
  loadSessions()
})
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

/* ----- 左侧列表 ----- */
.chat-list-panel {
  width: 300px;
  flex-shrink: 0;
  border-right: 1px solid #ebeef5;
  display: flex;
  flex-direction: column;
  background: #fff;
}

.search-box {
  padding: 12px 14px;
  border-bottom: 1px solid #ebeef5;
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
  transition: background 0.15s;
}
.chat-item:hover {
  background: #f5f7fa;
}
.chat-item.active {
  background: #ecf5ff;
  border-right: 3px solid #409eff;
}

.chat-meta {
  flex: 1;
  min-width: 0;
}

.chat-top {
  display: flex;
  justify-content: space-between;
  margin-bottom: 4px;
}

.chat-name {
  font-size: 14px;
  font-weight: 500;
  color: #303133;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.chat-time {
  font-size: 11px;
  color: #c0c4cc;
  flex-shrink: 0;
  margin-left: 8px;
}

.chat-preview {
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

/* ----- 右侧聊天 ----- */
.chat-window {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
  background: #f5f7fa;
}

.chat-header {
  height: 60px;
  background: #fff;
  border-bottom: 1px solid #ebeef5;
  padding: 0 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-shrink: 0;
  gap: 12px;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.header-name {
  font-weight: 600;
  font-size: 15px;
  color: #303133;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 16px;
  flex-shrink: 0;
}

.ai-switch {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  color: #909399;
}

.chat-body {
  flex: 1;
  overflow-y: auto;
  padding: 20px 24px;
}

.date-divider {
  text-align: center;
  margin-bottom: 20px;
}
.date-divider span {
  font-size: 11px;
  color: #909399;
  background: #e4e7ed;
  padding: 2px 10px;
  border-radius: 10px;
}

.msg-row {
  display: flex;
  gap: 10px;
  margin-bottom: 20px;
  align-items: flex-start;
}
.msg-row.ai,
.msg-row.human {
  flex-direction: row-reverse;
}

.ai-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: #409eff;
  color: #fff;
  font-size: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.msg-row.human .ai-avatar {
  background: #67c23a;
}

.msg-bubble {
  max-width: 70%;
  padding: 10px 14px;
  border-radius: 8px;
  font-size: 13px;
  line-height: 1.6;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}
.msg-bubble.user {
  background: #fff;
  color: #303133;
  border-top-left-radius: 2px;
}
.msg-bubble.ai,
.msg-bubble.human {
  background: #409eff;
  color: #fff;
  border-top-right-radius: 2px;
}
.msg-bubble.human {
  background: #67c23a;
}

.chat-input {
  background: #fff;
  border-top: 1px solid #ebeef5;
  padding: 12px 16px;
  flex-shrink: 0;
}

.input-tools {
  display: flex;
  gap: 14px;
  margin-bottom: 8px;
  color: #909399;
}
.input-tools .el-icon {
  cursor: pointer;
}
.input-tools .el-icon:hover {
  color: #409eff;
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
  background: #f5f7fa;
}
</style>
