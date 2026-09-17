import { apiClient } from './client'
import type { ApiResponse } from '@/types/api'
import type {
  SurveyTemplate,
  SurveyTopic,
  SurveyQuestion,
  CreateSurveyTemplatePayload,
  CreateSurveyTopicPayload,
  CreateSurveyQuestionPayload,
} from '@/types/surveyTemplate'

export const surveyTemplateService = {
  // --- Templates ---
  getTemplates(params?: { search?: string; is_active?: boolean }): Promise<ApiResponse<SurveyTemplate[]>> {
    return apiClient.get<SurveyTemplate[]>('/course-survey-templates', { params })
  },

  getTemplate(id: number | string): Promise<ApiResponse<SurveyTemplate>> {
    return apiClient.get<SurveyTemplate>(`/course-survey-templates/${id}`)
  },

  createTemplate(data: CreateSurveyTemplatePayload): Promise<ApiResponse<SurveyTemplate>> {
    return apiClient.post<SurveyTemplate>('/course-survey-templates', data)
  },

  updateTemplate(id: number | string, data: Partial<CreateSurveyTemplatePayload>): Promise<ApiResponse<SurveyTemplate>> {
    return apiClient.put<SurveyTemplate>(`/course-survey-templates/${id}`, data)
  },

  deleteTemplate(id: number | string): Promise<ApiResponse<void>> {
    return apiClient.delete<void>(`/course-survey-templates/${id}`)
  },

  // --- Assign to Courses from Template side ---
  assignCourses(templateId: number | string, courseIds: number[]): Promise<ApiResponse<void>> {
    return apiClient.post<void>(`/course-survey-templates/${templateId}/assign-courses`, { course_ids: courseIds })
  },

  unassignCourse(templateId: number | string, courseId: number | string): Promise<ApiResponse<void>> {
    return apiClient.delete<void>(`/course-survey-templates/${templateId}/courses/${courseId}`)
  },

  // --- Topics (Judul) ---
  createTopic(templateId: number | string, data: CreateSurveyTopicPayload): Promise<ApiResponse<SurveyTopic>> {
    return apiClient.post<SurveyTopic>(`/course-survey-templates/${templateId}/topics`, data)
  },

  updateTopic(topicId: number | string, data: Partial<CreateSurveyTopicPayload>): Promise<ApiResponse<SurveyTopic>> {
    return apiClient.put<SurveyTopic>(`/course-survey-topics/${topicId}`, data)
  },

  deleteTopic(topicId: number | string): Promise<ApiResponse<void>> {
    return apiClient.delete<void>(`/course-survey-topics/${topicId}`)
  },

  // --- Questions (Pertanyaan Yes/No atau Scale) ---
  createQuestion(topicId: number | string, data: CreateSurveyQuestionPayload): Promise<ApiResponse<SurveyQuestion>> {
    return apiClient.post<SurveyQuestion>(`/course-survey-topics/${topicId}/questions`, data)
  },

  updateQuestion(questionId: number | string, data: Partial<CreateSurveyQuestionPayload>): Promise<ApiResponse<SurveyQuestion>> {
    return apiClient.put<SurveyQuestion>(`/course-survey-questions/${questionId}`, data)
  },

  deleteQuestion(questionId: number | string): Promise<ApiResponse<void>> {
    return apiClient.delete<void>(`/course-survey-questions/${questionId}`)
  },

  // --- Course-side Survey Assignment ---
  getCourseSurveyAssignment(courseId: number | string): Promise<ApiResponse<{
    course: { id: number; code: string; name: string }
    assigned_templates: SurveyTemplate[]
    available_templates: Array<{ id: number; name: string; description: string | null; topics_count: number; questions_count: number }>
  }>> {
    return apiClient.get(`/courses/${courseId}/survey-assignment`)
  },

  assignToCourse(courseId: number | string, surveyTemplateId: number, replaceExisting = true): Promise<ApiResponse<SurveyTemplate>> {
    return apiClient.post(`/courses/${courseId}/survey-assignment`, {
      survey_template_id: surveyTemplateId,
      replace_existing: replaceExisting,
    })
  },

  unassignFromCourse(courseId: number | string, surveyTemplateId: number): Promise<ApiResponse<void>> {
    return apiClient.delete(`/courses/${courseId}/survey-assignment/${surveyTemplateId}`)
  },
}
