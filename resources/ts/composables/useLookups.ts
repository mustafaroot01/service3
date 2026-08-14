import type { ApiResponse } from '@/types/api'

export interface Lookup {
  id: number
  name: string
}

/** Small reference lists used to fill selects; fetched once per page. */
export function useLookup(endpoint: string) {
  const items = ref<Lookup[]>([])
  const loading = ref(false)

  async function load() {
    loading.value = true
    try {
      const res = await $api<ApiResponse<Lookup[]>>(endpoint, { query: { per_page: 200 } })

      items.value = res.data ?? []
    }
    finally {
      loading.value = false
    }
  }

  load()

  return { items, loading, reload: load }
}
