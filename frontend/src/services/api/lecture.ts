import { apiClient } from './client'
import type { ApiResponse } from '@/types/api'
import type {
  LectureSessionType,
  LectureProgram,
  GradingComponent,
  StudentGroup,
} from '@/types/lecture'

export const lectureService = {
  // 1. Jenis Sesi Perkuliahan
  getSessionTypes(params?: { search?: string }): Promise<ApiResponse<LectureSessionType[]>> {
    return apiClient.get<LectureSessionType[]>('/lecture-session-types', params as Record<string, unknown>)
  },

  createSessionType(data: Partial<LectureSessionType>): Promise<ApiResponse<LectureSessionType>> {
    return apiClient.post<LectureSessionType>('/lecture-session-types', data)
  },

  updateSessionType(id: number | string, data: Partial<LectureSessionType>): Promise<ApiResponse<LectureSessionType>> {
    return apiClient.put<LectureSessionType>(`/lecture-session-types/${id}`, data)
  },

  deleteSessionType(id: number | string): Promise<ApiResponse<void>> {
    return apiClient.delete<void>(`/lecture-session-types/${id}`)
  },

  // 2. Program Kuliah
  getPrograms(params?: { search?: string }): Promise<ApiResponse<LectureProgram[]>> {
    return apiClient.get<LectureProgram[]>('/lecture-programs', params as Record<string, unknown>)
  },

  createProgram(data: Partial<LectureProgram>): Promise<ApiResponse<LectureProgram>> {
    return apiClient.post<LectureProgram>('/lecture-programs', data)
  },

  updateProgram(id: number | string, data: Partial<LectureProgram>): Promise<ApiResponse<LectureProgram>> {
    return apiClient.put<LectureProgram>(`/lecture-programs/${id}`, data)
  },

  deleteProgram(id: number | string): Promise<ApiResponse<void>> {
    return apiClient.delete<void>(`/lecture-programs/${id}`)
  },

  // 3. Unsur Nilai
  getGradingComponents(params?: { search?: string }): Promise<ApiResponse<GradingComponent[]>> {
    return apiClient.get<GradingComponent[]>('/grading-components', params as Record<string, unknown>)
  },

  createGradingComponent(data: Partial<GradingComponent>): Promise<ApiResponse<GradingComponent>> {
    return apiClient.post<GradingComponent>('/grading-components', data)
  },

  updateGradingComponent(id: number | string, data: Partial<GradingComponent>): Promise<ApiResponse<GradingComponent>> {
    return apiClient.put<GradingComponent>(`/grading-components/${id}`, data)
  },

  deleteGradingComponent(id: number | string): Promise<ApiResponse<void>> {
    return apiClient.delete<void>(`/grading-components/${id}`)
  },

  // 4. Kelompok Mahasiswa
  getStudentGroups(params?: { search?: string }): Promise<ApiResponse<StudentGroup[]>> {
    return apiClient.get<StudentGroup[]>('/student-groups', params as Record<string, unknown>)
  },

  createStudentGroup(data: Partial<StudentGroup>): Promise<ApiResponse<StudentGroup>> {
    return apiClient.post<StudentGroup>('/student-groups', data)
  },

  updateStudentGroup(id: number | string, data: Partial<StudentGroup>): Promise<ApiResponse<StudentGroup>> {
    return apiClient.put<StudentGroup>(`/student-groups/${id}`, data)
  },

  deleteStudentGroup(id: number | string): Promise<ApiResponse<void>> {
    return apiClient.delete<void>(`/student-groups/${id}`)
  },
}
