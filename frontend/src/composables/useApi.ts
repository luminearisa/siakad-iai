import { ref } from 'vue'
import type { ApiError } from '@/services/api/client'

export function useApi<T, A extends unknown[]>(
  apiFn: (...args: A) => Promise<T>
) {
  const data = ref<T | null>(null)
  const loading = ref<boolean>(false)
  const error = ref<ApiError | null>(null)

  async function execute(...args: A): Promise<T> {
    loading.value = true
    error.value = null
    try {
      const result = await apiFn(...args)
      data.value = result
      return result
    } catch (err: unknown) {
      error.value = err as ApiError
      throw err
    } finally {
      loading.value = false
    }
  }

  return {
    data,
    loading,
    error,
    execute,
  }
}
