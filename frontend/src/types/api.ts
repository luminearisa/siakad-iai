export interface ApiResponse<T> {
  success: boolean
  message: string
  data: T
  meta?: ApiMeta
  errors?: Record<string, string[]> | null
}

export interface ApiMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
  from: number | null
  to: number | null
}

export interface QueryParams {
  page?: number
  per_page?: number
  search?: string
  sort?: string
  direction?: 'asc' | 'desc'
  [key: string]: string | number | boolean | undefined
}
