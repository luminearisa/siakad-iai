import { apiClient } from './client'
import type { ApiResponse } from '@/types/api'
import type {
  GraduateProfile,
  LearningOutcome,
  CourseLearningOutcome,
  SubCourseLearningOutcome,
  AiSuggestionItem,
} from '@/types/outcomes'

export const outcomeService = {
  // 1. Profil Lulusan (PL)
  listGraduateProfiles(params?: Record<string, unknown>): Promise<ApiResponse<GraduateProfile[]>> {
    return apiClient.get<GraduateProfile[]>('/graduate-profiles', params)
  },
  getGraduateProfile(id: number | string): Promise<ApiResponse<GraduateProfile>> {
    return apiClient.get<GraduateProfile>(`/graduate-profiles/${id}`)
  },
  createGraduateProfile(data: Partial<GraduateProfile>): Promise<ApiResponse<GraduateProfile>> {
    return apiClient.post<GraduateProfile>('/graduate-profiles', data)
  },
  updateGraduateProfile(id: number | string, data: Partial<GraduateProfile>): Promise<ApiResponse<GraduateProfile>> {
    return apiClient.put<GraduateProfile>(`/graduate-profiles/${id}`, data)
  },
  deleteGraduateProfile(id: number | string): Promise<ApiResponse<null>> {
    return apiClient.delete<null>(`/graduate-profiles/${id}`)
  },

  // 2. Capaian Pembelajaran Lulusan (CPL)
  listLearningOutcomes(params?: Record<string, unknown>): Promise<ApiResponse<LearningOutcome[]>> {
    return apiClient.get<LearningOutcome[]>('/learning-outcomes', params)
  },
  getLearningOutcome(id: number | string): Promise<ApiResponse<LearningOutcome>> {
    return apiClient.get<LearningOutcome>(`/learning-outcomes/${id}`)
  },
  createLearningOutcome(data: Partial<LearningOutcome>): Promise<ApiResponse<LearningOutcome>> {
    return apiClient.post<LearningOutcome>('/learning-outcomes', data)
  },
  updateLearningOutcome(id: number | string, data: Partial<LearningOutcome>): Promise<ApiResponse<LearningOutcome>> {
    return apiClient.put<LearningOutcome>(`/learning-outcomes/${id}`, data)
  },
  deleteLearningOutcome(id: number | string): Promise<ApiResponse<null>> {
    return apiClient.delete<null>(`/learning-outcomes/${id}`)
  },

  // 3. CPMK
  listCpmk(params?: Record<string, unknown>): Promise<ApiResponse<CourseLearningOutcome[]>> {
    return apiClient.get<CourseLearningOutcome[]>('/course-learning-outcomes', params)
  },
  getCpmk(id: number | string): Promise<ApiResponse<CourseLearningOutcome>> {
    return apiClient.get<CourseLearningOutcome>(`/course-learning-outcomes/${id}`)
  },
  createCpmk(data: Partial<CourseLearningOutcome>): Promise<ApiResponse<CourseLearningOutcome>> {
    return apiClient.post<CourseLearningOutcome>('/course-learning-outcomes', data)
  },
  updateCpmk(id: number | string, data: Partial<CourseLearningOutcome>): Promise<ApiResponse<CourseLearningOutcome>> {
    return apiClient.put<CourseLearningOutcome>(`/course-learning-outcomes/${id}`, data)
  },
  deleteCpmk(id: number | string): Promise<ApiResponse<null>> {
    return apiClient.delete<null>(`/course-learning-outcomes/${id}`)
  },

  // 4. Sub-CPMK
  listSubCpmk(params?: Record<string, unknown>): Promise<ApiResponse<SubCourseLearningOutcome[]>> {
    return apiClient.get<SubCourseLearningOutcome[]>('/sub-cpmks', params)
  },
  getSubCpmk(id: number | string): Promise<ApiResponse<SubCourseLearningOutcome>> {
    return apiClient.get<SubCourseLearningOutcome>(`/sub-cpmks/${id}`)
  },
  createSubCpmk(data: Partial<SubCourseLearningOutcome>): Promise<ApiResponse<SubCourseLearningOutcome>> {
    return apiClient.post<SubCourseLearningOutcome>('/sub-cpmks', data)
  },
  updateSubCpmk(id: number | string, data: Partial<SubCourseLearningOutcome>): Promise<ApiResponse<SubCourseLearningOutcome>> {
    return apiClient.put<SubCourseLearningOutcome>(`/sub-cpmks/${id}`, data)
  },
  deleteSubCpmk(id: number | string): Promise<ApiResponse<null>> {
    return apiClient.delete<null>(`/sub-cpmks/${id}`)
  },

  // 5. AI Assistant Generator
  generateAi(payload: { type: 'pl' | 'cpl' | 'cpmk' | 'sub_cpmk'; study_program_id?: number | null; topic?: string }): Promise<ApiResponse<AiSuggestionItem[]>> {
    return apiClient.post<AiSuggestionItem[]>('/outcomes/generate-ai', payload)
  },
}
