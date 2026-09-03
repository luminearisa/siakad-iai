import { ref, computed } from 'vue'
import type { ApiMeta } from '@/types/api'

export function usePagination(initialPerPage: number = 20) {
  const currentPage = ref<number>(1)
  const perPage = ref<number>(initialPerPage)
  const total = ref<number>(0)
  const lastPage = ref<number>(1)
  const from = ref<number | null>(null)
  const to = ref<number | null>(null)

  const hasNextPage = computed(() => currentPage.value < lastPage.value)
  const hasPrevPage = computed(() => currentPage.value > 1)

  function setMeta(meta?: ApiMeta): void {
    if (!meta) return
    currentPage.value = meta.current_page
    perPage.value = meta.per_page
    total.value = meta.total
    lastPage.value = meta.last_page
    from.value = meta.from
    to.value = meta.to
  }

  function nextPage(): void {
    if (hasNextPage.value) {
      currentPage.value++
    }
  }

  function prevPage(): void {
    if (hasPrevPage.value) {
      currentPage.value--
    }
  }

  function setPage(page: number): void {
    if (page >= 1 && page <= lastPage.value) {
      currentPage.value = page
    }
  }

  function reset(): void {
    currentPage.value = 1
  }

  return {
    currentPage,
    perPage,
    total,
    lastPage,
    from,
    to,
    hasNextPage,
    hasPrevPage,
    setMeta,
    nextPage,
    prevPage,
    setPage,
    reset,
  }
}
