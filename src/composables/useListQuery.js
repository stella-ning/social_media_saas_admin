/**
 * 列表查询通用组合式函数
 * 统一 page / size、搜索、重置、loading
 */
import { reactive, ref } from 'vue'

/**
 * @param {Function} fetcher - (params) => Promise<{ list, total, page, size }>
 * @param {object} [defaultQuery] - 默认筛选条件（不含 page/size）
 */
export function useListQuery(fetcher, defaultQuery = {}) {
  const loading = ref(false)
  const list = ref([])
  const total = ref(0)
  const page = ref(1)
  const size = ref(10)

  const query = reactive({ ...defaultQuery })
  /** 已生效的筛选（点搜索后才写入） */
  const applied = reactive({ ...defaultQuery })

  const buildParams = () => {
    const params = { page: page.value, size: size.value }
    Object.keys(applied).forEach((k) => {
      const v = applied[k]
      if (v !== '' && v !== null && typeof v !== 'undefined') {
        params[k] = v
      }
    })
    return params
  }

  const fetchList = async () => {
    loading.value = true
    try {
      const data = await fetcher(buildParams())
      list.value = data?.list || []
      total.value = data?.total ?? 0
      if (data?.page) page.value = data.page
      if (data?.size) size.value = data.size
    } catch {
      // 错误已在 request 拦截器提示
      list.value = []
      total.value = 0
    } finally {
      loading.value = false
    }
  }

  const handleSearch = () => {
    Object.keys(query).forEach((k) => {
      applied[k] = query[k]
    })
    page.value = 1
    return fetchList()
  }

  const handleReset = () => {
    Object.keys(defaultQuery).forEach((k) => {
      query[k] = defaultQuery[k]
      applied[k] = defaultQuery[k]
    })
    // 清掉额外字段
    Object.keys(query).forEach((k) => {
      if (!(k in defaultQuery)) {
        query[k] = ''
        applied[k] = ''
      }
    })
    page.value = 1
    return fetchList()
  }

  const handlePageChange = (p) => {
    page.value = p
    return fetchList()
  }

  const handleSizeChange = (s) => {
    size.value = s
    page.value = 1
    return fetchList()
  }

  return {
    loading,
    list,
    total,
    page,
    size,
    query,
    fetchList,
    handleSearch,
    handleReset,
    handlePageChange,
    handleSizeChange
  }
}
