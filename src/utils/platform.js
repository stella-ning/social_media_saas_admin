/**
 * 社媒平台展示工具：统一英文 key / 数字 / 中文 → 中文标签
 */
export const PLATFORM_LABELS = {
  xiaohongshu: '小红书',
  xhs: '小红书',
  douyin: '抖音',
  channels: '视频号',
  wechat_channels: '视频号',
  小红书: '小红书',
  抖音: '抖音',
  视频号: '视频号',
  1: '小红书',
  2: '抖音',
  3: '视频号'
}

export const PLATFORM_OPTIONS = [
  { code: 'xiaohongshu', label: '小红书' },
  { code: 'douyin', label: '抖音' },
  { code: 'channels', label: '视频号' }
]

/** 单个平台 → 中文名 */
export function formatPlatform(platform) {
  if (platform === null || platform === undefined || platform === '') return '未知平台'
  const key = String(platform).trim()
  return PLATFORM_LABELS[key] || PLATFORM_LABELS[key.toLowerCase()] || key
}

/** 平台列表 →「小红书 / 抖音」 */
export function formatPlatforms(list, sep = ' / ') {
  if (!Array.isArray(list) || !list.length) return '—'
  return list.map(formatPlatform).join(sep)
}

/** 套餐允许的平台选项（用于绑定/筛选） */
export function platformOptionsFromAllow(allowPlatforms = []) {
  const allow = new Set((allowPlatforms || []).map((p) => String(p).toLowerCase()))
  if (!allow.size) {
    return PLATFORM_OPTIONS.map((o) => ({ ...o, disabled: false }))
  }
  return PLATFORM_OPTIONS.map((o) => ({
    ...o,
    disabled: !allow.has(o.code)
  }))
}
