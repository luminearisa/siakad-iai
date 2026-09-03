import { apiClient } from './client'
import type { ApiResponse } from '@/types/api'
import type {
  AcademicClass,
  ClassLecturer,
  ClassCreatePayload,
  ClassUpdatePayload,
  AssignClassLecturerPayload,
  ClassFilters,
} from '@/types/class'

export const classService = {
  list(params?: ClassFilters): Promise<ApiResponse<AcademicClass[]>> {
    return apiClient.get<AcademicClass[]>('/classes', params as Record<string, unknown>)
  },

  get(id: number | string): Promise<ApiResponse<AcademicClass>> {
    return apiClient.get<AcademicClass>(`/classes/${id}`)
  },

  create(data: ClassCreatePayload): Promise<ApiResponse<AcademicClass>> {
    return apiClient.post<AcademicClass>('/classes', data)
  },

  update(id: number | string, data: ClassUpdatePayload): Promise<ApiResponse<AcademicClass>> {
    return apiClient.put<AcademicClass>(`/classes/${id}`, data)
  },

  delete(id: number | string): Promise<ApiResponse<null>> {
    return apiClient.delete<null>(`/classes/${id}`)
  },

  open(id: number | string): Promise<ApiResponse<AcademicClass>> {
    return apiClient.patch<AcademicClass>(`/classes/${id}/open`)
  },

  close(id: number | string): Promise<ApiResponse<AcademicClass>> {
    return apiClient.patch<AcademicClass>(`/classes/${id}/close`)
  },

  cancel(id: number | string): Promise<ApiResponse<AcademicClass>> {
    return apiClient.patch<AcademicClass>(`/classes/${id}/cancel`)
  },

  getLecturers(classId: number | string): Promise<ApiResponse<ClassLecturer[]>> {
    return apiClient.get<ClassLecturer[]>(`/classes/${classId}/lecturers`)
  },

  assignLecturer(classId: number | string, payload: AssignClassLecturerPayload): Promise<ApiResponse<ClassLecturer>> {
    return apiClient.post<ClassLecturer>(`/classes/${classId}/lecturers`, payload)
  },

  removeLecturer(classId: number | string, lecturerId: number | string): Promise<ApiResponse<null>> {
    return apiClient.delete<null>(`/classes/${classId}/lecturers/${lecturerId}`)
  },
}
