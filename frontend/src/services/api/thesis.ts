import { apiClient } from './client'
import type { ApiResponse } from '@/types/api'
import type { Thesis, ThesisPayload, ThesisStats } from '@/types/thesis'

export interface ThesisListResponse {
  data: Thesis[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number
    to: number
    stats: ThesisStats
  }
}

export const thesisService = {
  list(params?: {
    page?: number
    per_page?: number
    search?: string
    status?: string
    study_program_id?: number | string
    semester_id?: number | string
  }): Promise<ApiResponse<Thesis[]>> {
    return apiClient.get<Thesis[]>('/theses', params as Record<string, unknown>)
  },

  get(id: number | string): Promise<ApiResponse<Thesis>> {
    return apiClient.get<Thesis>(`/theses/${id}`)
  },

  create(payload: ThesisPayload): Promise<ApiResponse<Thesis>> {
    return apiClient.post<Thesis>('/theses', payload)
  },

  update(id: number | string, payload: Partial<ThesisPayload>): Promise<ApiResponse<Thesis>> {
    return apiClient.put<Thesis>(`/theses/${id}`, payload)
  },

  delete(id: number | string): Promise<ApiResponse<void>> {
    return apiClient.delete<void>(`/theses/${id}`)
  },
}
