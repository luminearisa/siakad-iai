import { apiClient } from './client'
import type { ApiResponse } from '@/types/api'
import type {
  Student,
  StudentCreatePayload,
  StudentUpdatePayload,
  StudentChangeStatusPayload,
  StudentFamily,
  StudentFamilyPayload,
  StudentEducation,
  StudentEducationPayload,
  StudentFilters,
} from '@/types/student'

export const studentService = {
  list(params?: StudentFilters): Promise<ApiResponse<Student[]>> {
    return apiClient.get<Student[]>('/students', params as Record<string, unknown>)
  },

  get(id: number | string): Promise<ApiResponse<Student>> {
    return apiClient.get<Student>(`/students/${id}`)
  },

  create(data: StudentCreatePayload): Promise<ApiResponse<Student>> {
    return apiClient.post<Student>('/students', data)
  },

  update(id: number | string, data: StudentUpdatePayload): Promise<ApiResponse<Student>> {
    return apiClient.put<Student>(`/students/${id}`, data)
  },

  delete(id: number | string): Promise<ApiResponse<null>> {
    return apiClient.delete<null>(`/students/${id}`)
  },

  changeStatus(id: number | string, payload: StudentChangeStatusPayload): Promise<ApiResponse<Student>> {
    return apiClient.patch<Student>(`/students/${id}/status`, payload)
  },

  addFamily(studentId: number | string, payload: StudentFamilyPayload): Promise<ApiResponse<StudentFamily>> {
    return apiClient.post<StudentFamily>(`/students/${studentId}/families`, payload)
  },

  addEducation(studentId: number | string, payload: StudentEducationPayload): Promise<ApiResponse<StudentEducation>> {
    return apiClient.post<StudentEducation>(`/students/${studentId}/educations`, payload)
  },

  createAccount(studentId: number | string, payload: { email: string; password: string }): Promise<ApiResponse<Student>> {
    return apiClient.post<Student>(`/students/${studentId}/create-account`, payload)
  },

  resetPassword(studentId: number | string, payload: { password: string }): Promise<ApiResponse<Student>> {
    return apiClient.post<Student>(`/students/${studentId}/reset-password`, payload)
  },

  toggleAccountStatus(studentId: number | string): Promise<ApiResponse<Student>> {
    return apiClient.patch<Student>(`/students/${studentId}/toggle-account-status`)
  },
}
