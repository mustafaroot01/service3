import { watchDebounced } from '@vueuse/core'
import { themeConfig } from '@themeConfig'
import type { ApiResponse } from '@/types/api'

export interface ServerTableOptions {
  /** Column the API sorts by when the user has not chosen one. */
  defaultSort?: string
  defaultOrder?: 'asc' | 'desc'
  perPage?: number
  /** Extra query parameters sent on every request, e.g. { is_active: null }. */
  filters?: Record<string, any>
  /** Skip the first automatic fetch; call refresh() yourself. */
  immediate?: boolean
}

export function useServerTable<T = any>(endpoint: string, options: ServerTableOptions = {}) {
  const {
    defaultSort,
    defaultOrder = 'asc',
    perPage = themeConfig.table.pagination.defaultPerPage,
    filters: initialFilters = {},
    immediate = true,
  } = options

  const route = useRoute()

  /**
   * A deep link such as /admin/orders?status=assigned should land on an already
   * filtered table. Only declared filter keys are honoured, and the value is
   * numbered when it looks numeric so select options still match by identity.
   */
  function seedFromQuery(defaults: Record<string, any>) {
    const seeded = { ...defaults }

    for (const key of Object.keys(defaults)) {
      const raw = route.query[key]
      const value = Array.isArray(raw) ? raw[0] : raw

      if (value === undefined || value === null || value === '')
        continue

      seeded[key] = Number.isNaN(Number(value)) ? value : Number(value)
    }

    return seeded
  }

  const items = ref<T[]>([]) as Ref<T[]>
  const total = ref(0)
  const lastPage = ref(1)
  const loading = ref(false)
  const error = ref<string | null>(null)

  const page = ref(1)
  const itemsPerPage = ref(perPage)
  const search = ref('')
  const sortBy = ref<string | undefined>(defaultSort)
  const orderBy = ref<'asc' | 'desc'>(defaultOrder)
  const filters = ref<Record<string, any>>(seedFromQuery(initialFilters))

  /** Only send parameters that carry a value, so the API sees a clean query. */
  const query = computed(() => {
    const params: Record<string, any> = {
      page: page.value,
      per_page: itemsPerPage.value,
    }

    if (search.value.trim())
      params.q = search.value.trim()

    if (sortBy.value) {
      params.sortBy = sortBy.value
      params.orderBy = orderBy.value
    }

    for (const [key, value] of Object.entries(filters.value)) {
      if (value !== null && value !== undefined && value !== '')
        params[key] = value
    }

    return params
  })

  async function refresh() {
    loading.value = true
    error.value = null

    try {
      const response = await $api<ApiResponse<T[]>>(endpoint, { query: query.value })

      items.value = response.data ?? []
      total.value = response.meta?.total ?? items.value.length
      lastPage.value = response.meta?.last_page ?? 1

      // A deletion can empty the last page; step back instead of showing nothing.
      if (page.value > lastPage.value && lastPage.value >= 1) {
        page.value = lastPage.value
        await refresh()
      }
    }
    catch (e: any) {
      error.value = e?.data?.message ?? themeConfig.table.labels.loadFailed
      items.value = []
      total.value = 0
    }
    finally {
      loading.value = false
    }
  }

  /** Vuetify hands back its own sort array; translate it to our query shape. */
  function updateOptions(vuetifyOptions: any) {
    const sort = vuetifyOptions?.sortBy?.[0]

    sortBy.value = sort?.key ?? defaultSort
    orderBy.value = sort?.order ?? defaultOrder
  }

  function resetFilters() {
    filters.value = { ...initialFilters }
    search.value = ''
  }

  const activeFilterCount = computed(() =>
    Object.values(filters.value).filter(v => v !== null && v !== undefined && v !== '').length)

  // Anything that changes the result set sends the reader back to page one.
  watch([search, filters], () => {
    page.value = 1
  }, { deep: true })

  watchDebounced(search, refresh, { debounce: themeConfig.table.search.debounce })
  watch([page, itemsPerPage, sortBy, orderBy], refresh)
  watch(filters, refresh, { deep: true })

  if (immediate)
    refresh()

  return {
    items,
    total,
    lastPage,
    loading,
    error,
    page,
    itemsPerPage,
    search,
    sortBy,
    orderBy,
    filters,
    activeFilterCount,
    query,
    refresh,
    updateOptions,
    resetFilters,
  }
}

export type ServerTable<T = any> = ReturnType<typeof useServerTable<T>>
