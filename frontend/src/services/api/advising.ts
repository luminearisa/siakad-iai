import { apiClient } from './client'
import type { ApiResponse, QueryParams } from '@/types/api'
import type {
  AcademicAdvisor,
  AdvisingSession,
  AssignAdvisorPayload,
  ChangeAdvisorPayload,
  EndAdvisorPayload,
  CreateSessionPayload,
  UpdateSessionPayload,
  AdvisorFilters,
  SessionFilters,
} from '@/types/advising'

export const advisingService = {
  // Advisor Distribution
  getDistribution(params?: { search?: string; admission_year?: string | number; study_program_id?: string | number; status?: string }): Promise<ApiResponse<any[]>> {
    return apiClient.get<any[]>('/advising/distribution', params as Record<string, unknown>)
  },

  batchAssign(data: { student_ids: number[]; lecturer_id: number; notes?: string }): Promise<ApiResponse<{ updated_count: number }>> {
    return apiClient.post<{ updated_count: number }>('/advising/batch-assign', data)
  },

  generateDistribution(): Promise<ApiResponse<{ assigned_count: number }>> {
    return apiClient.post<{ assigned_count: number }>('/advising/generate')
  },

  // Advisors
  listAdvisors(params?: AdvisorFilters | QueryParams): Promise<ApiResponse<AcademicAdvisor[]>> {
    return apiClient.get<AcademicAdvisor[]>('/advisors', params)
  },

  getAdvisor(id: number | string): Promise<ApiResponse<AcademicAdvisor>> {
    return apiClient.get<AcademicAdvisor>(`/advisors/${id}`)
  },

  assignAdvisor(data: AssignAdvisorPayload): Promise<ApiResponse<AcademicAdvisor>> {
    return apiClient.post<AcademicAdvisor>('/advisors', data)
  },

  endAdvisorAssignment(advisorId: number | string, data?: EndAdvisorPayload): Promise<ApiResponse<AcademicAdvisor>> {
    return apiClient.patch<AcademicAdvisor>(`/advisors/${advisorId}/end`, data || {})
  },

  getStudentAdvisor(studentId: number | string): Promise<ApiResponse<AcademicAdvisor>> {
    return apiClient.get<AcademicAdvisor>(`/students/${studentId}/advisor`)
  },

  getStudentAdvisorHistory(studentId: number | string): Promise<ApiResponse<AcademicAdvisor[]>> {
    return apiClient.get<AcademicAdvisor[]>(`/students/${studentId}/advisor-history`)
  },

  changeStudentAdvisor(studentId: number | string, data: ChangeAdvisorPayload): Promise<ApiResponse<AcademicAdvisor>> {
    return apiClient.post<AcademicAdvisor>(`/students/${studentId}/advisor`, data)
  },

  // Sessions
  listSessions(params?: SessionFilters | QueryParams): Promise<ApiResponse<AdvisingSession[]>> {
    return apiClient.get<AdvisingSession[]>('/advising-sessions', params)
  },

  getSession(id: number | string): Promise<ApiResponse<AdvisingSession>> {
    return apiClient.get<AdvisingSession>(`/advising-sessions/${id}`)
  },

  createSession(data: CreateSessionPayload): Promise<ApiResponse<AdvisingSession>> {
    return apiClient.post<AdvisingSession>('/advising-sessions', data)
  },

  updateSession(id: number | string, data: UpdateSessionPayload): Promise<ApiResponse<AdvisingSession>> {
    return apiClient.put<AdvisingSession>(`/advising-sessions/${id}`, data)
  },

  deleteSession(id: number | string): Promise<ApiResponse<null>> {
    return apiClient.delete<null>(`/advising-sessions/${id}`)
  },
}
