import { apiClient } from './client'
import type { ApiResponse } from '@/types/api'
import type {
  Curriculum,
  CurriculumYear,
  CreditLimit,
  GradeScale,
  CurriculumFilters,
} from '@/types/curriculum'

export const curriculumService = {
  // Legacy / standard Curriculum methods
  list(params?: CurriculumFilters): Promise<ApiResponse<Curriculum[]>> {
    return apiClient.get<Curriculum[]>('/curricula', params as Record<string, unknown>)
  },

  get(id: number | string): Promise<ApiResponse<Curriculum>> {
    return apiClient.get<Curriculum>(`/curricula/${id}`)
  },

  create(data: any): Promise<ApiResponse<Curriculum>> {
    return apiClient.post<Curriculum>('/curricula', data)
  },

  update(id: number | string, data: any): Promise<ApiResponse<Curriculum>> {
    return apiClient.put<Curriculum>(`/curricula/${id}`, data)
  },

  delete(id: number | string): Promise<ApiResponse<void>> {
    return apiClient.delete<void>(`/curricula/${id}`)
  },

  activate(id: number | string): Promise<ApiResponse<Curriculum>> {
    return apiClient.patch<Curriculum>(`/curricula/${id}/activate`)
  },

  archive(id: number | string): Promise<ApiResponse<Curriculum>> {
    return apiClient.patch<Curriculum>(`/curricula/${id}/archive`)
  },

  createSemester(curriculumId: number | string, data: any): Promise<ApiResponse<any>> {
    return apiClient.post<any>(`/curricula/${curriculumId}/semesters`, data)
  },

  addSubject(semesterId: number | string, data: any): Promise<ApiResponse<any>> {
    return apiClient.post<any>(`/curriculum-semesters/${semesterId}/subjects`, data)
  },

  removeSubject(semesterId: number | string, subjectId: number | string): Promise<ApiResponse<void>> {
    return apiClient.delete<void>(`/curriculum-semesters/${semesterId}/subjects/${subjectId}`)
  },

  // 1. Tahun Kurikulum
  getYears(params?: { search?: string }): Promise<ApiResponse<CurriculumYear[]>> {
    return apiClient.get<CurriculumYear[]>('/curriculum-years', params as Record<string, unknown>)
  },

  createYear(data: Partial<CurriculumYear>): Promise<ApiResponse<CurriculumYear>> {
    return apiClient.post<CurriculumYear>('/curriculum-years', data)
  },

  updateYear(id: number, data: Partial<CurriculumYear>): Promise<ApiResponse<CurriculumYear>> {
    return apiClient.put<CurriculumYear>(`/curriculum-years/${id}`, data)
  },

  deleteYear(id: number): Promise<ApiResponse<void>> {
    return apiClient.delete<void>(`/curriculum-years/${id}`)
  },

  // 2. Batas SKS
  getCreditLimits(params?: { search?: string }): Promise<ApiResponse<CreditLimit[]>> {
    return apiClient.get<CreditLimit[]>('/credit-limits', params as Record<string, unknown>)
  },

  createCreditLimit(data: Partial<CreditLimit>): Promise<ApiResponse<CreditLimit>> {
    return apiClient.post<CreditLimit>('/credit-limits', data)
  },

  updateCreditLimit(id: number, data: Partial<CreditLimit>): Promise<ApiResponse<CreditLimit>> {
    return apiClient.put<CreditLimit>(`/credit-limits/${id}`, data)
  },

  deleteCreditLimit(id: number): Promise<ApiResponse<void>> {
    return apiClient.delete<void>(`/credit-limits/${id}`)
  },

  // 3. Skala Nilai
  getGradeScales(params?: { search?: string }): Promise<ApiResponse<GradeScale[]>> {
    return apiClient.get<GradeScale[]>('/grade-scales', params as Record<string, unknown>)
  },

  getGradeScale(id: number): Promise<ApiResponse<GradeScale>> {
    return apiClient.get<GradeScale>(`/grade-scales/${id}`)
  },

  createGradeScale(data: Partial<GradeScale>): Promise<ApiResponse<GradeScale>> {
    return apiClient.post<GradeScale>('/grade-scales', data)
  },

  updateGradeScale(id: number, data: Partial<GradeScale>): Promise<ApiResponse<GradeScale>> {
    return apiClient.put<GradeScale>(`/grade-scales/${id}`, data)
  },

  deleteGradeScale(id: number): Promise<ApiResponse<void>> {
    return apiClient.delete<void>(`/grade-scales/${id}`)
  },

  // 4. Kurikulum Program Studi
  getCurricula(params?: CurriculumFilters): Promise<ApiResponse<Curriculum[]>> {
    return apiClient.get<Curriculum[]>('/curricula', params as Record<string, unknown>)
  },

  getCurriculum(id: number): Promise<ApiResponse<Curriculum>> {
    return apiClient.get<Curriculum>(`/curricula/${id}`)
  },

  createCurriculum(data: any): Promise<ApiResponse<Curriculum>> {
    return apiClient.post<Curriculum>('/curricula', data)
  },

  updateCurriculum(id: number, data: any): Promise<ApiResponse<Curriculum>> {
    return apiClient.put<Curriculum>(`/curricula/${id}`, data)
  },

  deleteCurriculum(id: number): Promise<ApiResponse<void>> {
    return apiClient.delete<void>(`/curricula/${id}`)
  },

  // Semester Subjects
  addSubjectToSemester(semesterId: number, data: any): Promise<ApiResponse<any>> {
    return apiClient.post<any>(`/curriculum-semesters/${semesterId}/subjects`, data)
  },

  removeSubjectFromSemester(semesterId: number, subjectId: number): Promise<ApiResponse<void>> {
    return apiClient.delete<void>(`/curriculum-semesters/${semesterId}/subjects/${subjectId}`)
  },
}
