import { apiClient } from './client'
import type { ApiResponse } from '@/types/api'
import type {
  StudentEnrollment,
  StudentEnrollmentItem,
  CreateEnrollmentPayload,
  EnrollmentFilters,
} from '@/types/enrollment'

export const enrollmentService = {
  list(params?: EnrollmentFilters): Promise<ApiResponse<StudentEnrollment[]>> {
    return apiClient.get<StudentEnrollment[]>('/enrollments', params as Record<string, unknown>)
  },

  generate(semesterId?: number | string): Promise<ApiResponse<{ generated_count: number }>> {
    return apiClient.post<{ generated_count: number }>('/enrollments/generate', { semester_id: semesterId })
  },

  updateAdvisorAndQuota(id: number | string, data: { lecturer_id?: number | null; max_credits: number }): Promise<ApiResponse<StudentEnrollment>> {
    return apiClient.put<StudentEnrollment>(`/enrollments/${id}/advisor-quota`, data)
  },

  get(id: number | string): Promise<ApiResponse<StudentEnrollment>> {
    return apiClient.get<StudentEnrollment>(`/enrollments/${id}`)
  },

  create(data: CreateEnrollmentPayload): Promise<ApiResponse<StudentEnrollment>> {
    return apiClient.post<StudentEnrollment>('/enrollments', data)
  },

  getStudentEnrollments(studentId: number | string): Promise<ApiResponse<StudentEnrollment[]>> {
    return apiClient.get<StudentEnrollment[]>(`/students/${studentId}/enrollments`)
  },

  storeStudentEnrollment(studentId: number | string, data: CreateEnrollmentPayload): Promise<ApiResponse<StudentEnrollment>> {
    return apiClient.post<StudentEnrollment>(`/students/${studentId}/enrollments`, data)
  },

  // Items
  getItems(enrollmentId: number | string): Promise<ApiResponse<StudentEnrollmentItem[]>> {
    return apiClient.get<StudentEnrollmentItem[]>(`/enrollments/${enrollmentId}/items`)
  },

  addItem(enrollmentId: number | string, classId: number | string, notes?: string): Promise<ApiResponse<StudentEnrollmentItem>> {
    return apiClient.post<StudentEnrollmentItem>(`/enrollments/${enrollmentId}/items`, {
      class_id: Number(classId),
      notes,
    })
  },

  removeItem(enrollmentId: number | string, itemId: number | string): Promise<ApiResponse<null>> {
    return apiClient.delete<null>(`/enrollments/${enrollmentId}/items/${itemId}`)
  },

  // Workflow
  submit(id: number | string): Promise<ApiResponse<StudentEnrollment>> {
    return apiClient.post<StudentEnrollment>(`/enrollments/${id}/submit`)
  },

  approve(id: number | string, notes?: string): Promise<ApiResponse<StudentEnrollment>> {
    return apiClient.post<StudentEnrollment>(`/enrollments/${id}/approve`, { notes })
  },

  reject(id: number | string, reason: string): Promise<ApiResponse<StudentEnrollment>> {
    return apiClient.post<StudentEnrollment>(`/enrollments/${id}/reject`, { reason })
  },

  requestRevision(id: number | string, notes: string): Promise<ApiResponse<StudentEnrollment>> {
    return apiClient.post<StudentEnrollment>(`/enrollments/${id}/request-revision`, { notes })
  },

  lock(id: number | string): Promise<ApiResponse<StudentEnrollment>> {
    return apiClient.post<StudentEnrollment>(`/enrollments/${id}/lock`)
  },
}
