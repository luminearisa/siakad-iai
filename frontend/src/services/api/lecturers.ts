import { apiClient } from './client'
import type { ApiResponse } from '@/types/api'
import type {
  Lecturer,
  LecturerCreatePayload,
  LecturerUpdatePayload,
  LecturerChangeStatusPayload,
  LecturerFilters,
} from '@/types/lecturer'

export const lecturerService = {
  list(params?: LecturerFilters): Promise<ApiResponse<Lecturer[]>> {
    return apiClient.get<Lecturer[]>('/lecturers', params as Record<string, unknown>)
  },

  get(id: number | string): Promise<ApiResponse<Lecturer>> {
    return apiClient.get<Lecturer>(`/lecturers/${id}`)
  },

  create(data: LecturerCreatePayload): Promise<ApiResponse<Lecturer>> {
    return apiClient.post<Lecturer>('/lecturers', data)
  },

  update(id: number | string, data: LecturerUpdatePayload): Promise<ApiResponse<Lecturer>> {
    return apiClient.put<Lecturer>(`/lecturers/${id}`, data)
  },

  delete(id: number | string): Promise<ApiResponse<null>> {
    return apiClient.delete<null>(`/lecturers/${id}`)
  },

  changeStatus(id: number | string, payload: LecturerChangeStatusPayload): Promise<ApiResponse<Lecturer>> {
    return apiClient.patch<Lecturer>(`/lecturers/${id}/status`, payload)
  },

  updateQuotas(id: number | string, payload: { academic_advising_quota: number; thesis_supervisor_quota: number; thesis_examiner_quota: number }): Promise<ApiResponse<Lecturer>> {
    return apiClient.put<Lecturer>(`/lecturers/${id}/quotas`, payload)
  },

  createAccount(lecturerId: number | string, payload: { email: string; password: string }): Promise<ApiResponse<Lecturer>> {
    return apiClient.post<Lecturer>(`/lecturers/${lecturerId}/create-account`, payload)
  },

  resetPassword(lecturerId: number | string, payload: { password: string }): Promise<ApiResponse<Lecturer>> {
    return apiClient.post<Lecturer>(`/lecturers/${lecturerId}/reset-password`, payload)
  },

  toggleAccountStatus(lecturerId: number | string): Promise<ApiResponse<Lecturer>> {
    return apiClient.patch<Lecturer>(`/lecturers/${lecturerId}/toggle-account-status`)
  },
}
