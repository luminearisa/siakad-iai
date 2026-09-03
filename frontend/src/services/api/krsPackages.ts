import { apiClient } from './client'
import type { ApiResponse } from '@/types/api'
import type {
  KrsPackage,
  CreateKrsPackagePayload,
  UpdateKrsPackagePayload,
} from '@/types/enrollment'

export const krsPackageService = {
  list(params?: { search?: string; study_program_id?: number | string; semester?: number | string }): Promise<ApiResponse<KrsPackage[]>> {
    return apiClient.get<KrsPackage[]>('/krs-packages', params as Record<string, unknown>)
  },

  get(id: number | string): Promise<ApiResponse<KrsPackage>> {
    return apiClient.get<KrsPackage>(`/krs-packages/${id}`)
  },

  create(data: CreateKrsPackagePayload): Promise<ApiResponse<KrsPackage>> {
    return apiClient.post<KrsPackage>('/krs-packages', data)
  },

  update(id: number | string, data: UpdateKrsPackagePayload): Promise<ApiResponse<KrsPackage>> {
    return apiClient.put<KrsPackage>(`/krs-packages/${id}`, data)
  },

  delete(id: number | string): Promise<ApiResponse<null>> {
    return apiClient.delete<null>(`/krs-packages/${id}`)
  },
}
