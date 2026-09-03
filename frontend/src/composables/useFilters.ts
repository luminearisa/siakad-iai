import { ref, computed } from 'vue'
import type { QueryParams } from '@/types/api'

export function useFilters(defaultFilters: Record<string, unknown> = {}) {
  const search = ref<string>('')
  const sortBy = ref<string>('')
  const sortDirection = ref<'asc' | 'desc'>('asc')
  const customFilters = ref<Record<string, unknown>>({ ...defaultFilters })

  const queryParams = computed<QueryParams>(() => {
    const params: QueryParams = {}

    if (search.value.trim()) {
      params.search = search.value.trim()
    }

    if (sortBy.value) {
      params.sort = sortBy.value
      params.direction = sortDirection.value
    }

    for (const [key, value] of Object.entries(customFilters.value)) {
      if (value !== '' && value !== null && value !== undefined) {
        params[key] = value as string | number | boolean
      }
    }

    return params
  })

  function setFilter(key: string, value: unknown): void {
    customFilters.value[key] = value
  }

  function setSort(column: string): void {
    if (sortBy.value === column) {
      sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
    } else {
      sortBy.value = column
      sortDirection.value = 'asc'
    }
  }

  function resetFilters(): void {
    search.value = ''
    sortBy.value = ''
    sortDirection.value = 'asc'
    customFilters.value = { ...defaultFilters }
  }

  return {
    search,
    sortBy,
    sortDirection,
    customFilters,
    queryParams,
    setFilter,
    setSort,
    resetFilters,
  }
}
