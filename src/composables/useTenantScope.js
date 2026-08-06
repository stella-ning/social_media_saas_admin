/**
 * 租户作用域：超管需选择租户；租户账号固定自身 tenantId
 * - 租户列表进程内缓存，可主动 invalidate / reload
 * - 套餐变更后必须 reload，避免页面展示旧套餐
 */
import { ref, computed, watch, onMounted } from 'vue'
import { tenantApi } from '@/api'
import { getCurrentRole, getCurrentUser } from '@/utils/auth'

let tenantsCache = null
let tenantsLoading = null

async function fetchTenantsOnce(force = false) {
  if (force) {
    tenantsCache = null
    tenantsLoading = null
  }
  if (tenantsCache) return tenantsCache
  if (tenantsLoading) return tenantsLoading
  tenantsLoading = tenantApi
    .list({ page: 1, size: 100 })
    .then((data) => {
      tenantsCache = data?.list || []
      return tenantsCache
    })
    .finally(() => {
      tenantsLoading = null
    })
  return tenantsLoading
}

/** 清除缓存（套餐变更 / 租户增删后调用） */
export function invalidateTenantScopeCache() {
  tenantsCache = null
  tenantsLoading = null
}

export function useTenantScope(options = {}) {
  const { autoSelect = true } = options
  const isAdmin = computed(() => getCurrentRole() === 'super_admin')
  const tenantId = ref(
    isAdmin.value ? null : getCurrentUser()?.tenantId || null
  )
  const tenants = ref([])
  const tenantsReady = ref(!isAdmin.value)
  const hasTenant = computed(() => !!tenantId.value)

  const selectedTenant = computed(
    () => tenants.value.find((t) => t.id === tenantId.value) || null
  )

  /** 当前选中租户套餐，独立 ref，保证升降级后 UI 立刻刷新 */
  const currentPackageCode = ref(
    isAdmin.value ? '' : getCurrentUser()?.package || ''
  )

  const syncPackageFromSelection = () => {
    if (selectedTenant.value?.package) {
      currentPackageCode.value = selectedTenant.value.package
      return
    }
    if (!isAdmin.value) {
      currentPackageCode.value = getCurrentUser()?.package || ''
    }
  }

  const withTenant = (params = {}) => {
    if (!tenantId.value) return { ...params }
    return { ...params, tenantId: tenantId.value, tenant_id: tenantId.value }
  }

  const reloadTenants = async () => {
    if (!isAdmin.value) {
      syncPackageFromSelection()
      return tenants.value
    }
    const keepId = tenantId.value
    const list = await fetchTenantsOnce(true)
    tenants.value = list.map((t) => ({ ...t }))
    if (keepId && list.some((t) => t.id === keepId)) {
      tenantId.value = keepId
    } else if (autoSelect && list.length && !tenantId.value) {
      tenantId.value = list[0].id
    }
    syncPackageFromSelection()
    return tenants.value
  }

  const patchTenantPackage = (id, packageCode) => {
    const list = tenants.value.map((t) =>
      t.id === id ? { ...t, package: packageCode } : t
    )
    tenants.value = list
    if (tenantsCache) {
      tenantsCache = tenantsCache.map((t) =>
        t.id === id ? { ...t, package: packageCode } : t
      )
    }
    if (tenantId.value === id) {
      currentPackageCode.value = packageCode
    }
  }

  const init = async () => {
    if (!isAdmin.value) {
      const tid = getCurrentUser()?.tenantId || null
      if (tid) tenantId.value = tid
      currentPackageCode.value = getCurrentUser()?.package || ''
      tenantsReady.value = true
      return
    }
    try {
      const list = await fetchTenantsOnce(false)
      tenants.value = list.map((t) => ({ ...t }))
      if (autoSelect && !tenantId.value && tenants.value.length) {
        tenantId.value = tenants.value[0].id
      }
      syncPackageFromSelection()
    } finally {
      tenantsReady.value = true
    }
  }

  onMounted(init)

  watch(tenantId, () => {
    syncPackageFromSelection()
  })

  watch(
    () => getCurrentUser()?.package,
    (pkg) => {
      if (!isAdmin.value && pkg) currentPackageCode.value = pkg
    }
  )

  watch(
    () => getCurrentUser()?.tenantId,
    (tid) => {
      if (!isAdmin.value && tid) tenantId.value = tid
    }
  )

  return {
    isAdmin,
    tenantId,
    tenants,
    tenantsReady,
    selectedTenant,
    currentPackageCode,
    hasTenant,
    withTenant,
    init,
    reloadTenants,
    patchTenantPackage
  }
}
