import type { ApiError } from '@/services/api/client'

export function getErrorMessage(error: unknown, fallback: string = 'Terjadi kesalahan'): string {
  if (!error) return fallback
  if (typeof error === 'string') return error
  if (typeof error === 'object' && 'message' in error) {
    return (error as ApiError).message || fallback
  }
  return fallback
}

export function getFieldError(error: unknown, field: string): string | null {
  if (!error || typeof error !== 'object') return null
  const apiErr = error as ApiError
  if (apiErr.errors && apiErr.errors[field] && apiErr.errors[field].length > 0) {
    return apiErr.errors[field][0]
  }
  return null
}
