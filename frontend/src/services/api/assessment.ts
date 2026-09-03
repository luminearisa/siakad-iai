import { apiClient } from './client'
import type { ApiResponse } from '@/types/api'
import type {
  AssessmentComponent,
  AssessmentScheme,
  ClassGradeRecap,
  BatchGradePayload,
} from '@/types/assessment'

export const assessmentService = {
  // Class Grades & Recap
  getClassGrades(classId: string | number): Promise<ApiResponse<ClassGradeRecap>> {
    return apiClient.get<ClassGradeRecap>(`/classes/${classId}/grades`)
  },

  // Batch Save Student Grades (Draft / Update)
  saveBatchGrades(classId: string | number, payload: BatchGradePayload): Promise<ApiResponse<{ count: number }>> {
    return apiClient.post<{ count: number }>(`/classes/${classId}/grades`, payload)
  },

  // Submit Class Grades for Approval
  submitClassGrades(classId: string | number): Promise<ApiResponse<{ submitted_count: number }>> {
    return apiClient.post<{ submitted_count: number }>(`/classes/${classId}/grades/submit`)
  },

  // Finalize & Lock Class Grades
  finalizeClassGrades(classId: string | number): Promise<ApiResponse<ClassGradeRecap>> {
    return apiClient.post<ClassGradeRecap>(`/classes/${classId}/grades/finalize`)
  },

  // Class Assessment Scheme
  getClassScheme(classId: string | number): Promise<ApiResponse<AssessmentScheme>> {
    return apiClient.get<AssessmentScheme>(`/classes/${classId}/assessment-scheme`)
  },

  // Class Assessment Components
  getClassComponents(classId: string | number): Promise<ApiResponse<AssessmentComponent[]>> {
    return apiClient.get<AssessmentComponent[]>(`/classes/${classId}/components`)
  },

  // Create Standard Default Scheme for Class
  createClassScheme(classId: string | number, payload: any): Promise<ApiResponse<AssessmentScheme>> {
    return apiClient.post<AssessmentScheme>(`/classes/${classId}/assessment-scheme`, payload)
  },
}
