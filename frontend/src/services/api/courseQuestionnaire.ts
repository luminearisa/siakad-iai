import { apiClient } from './client'
import type { ApiResponse } from '@/types/api'
import type { CourseQuestionnaireTopic, CourseQuestionnaireQuestion } from '@/types/courseQuestionnaire'

export const courseQuestionnaireService = {
  getQuestionnaires(courseId: number | string): Promise<ApiResponse<CourseQuestionnaireTopic[]>> {
    return apiClient.get<CourseQuestionnaireTopic[]>(`/courses/${courseId}/questionnaires`)
  },

  resetTemplate(courseId: number | string): Promise<ApiResponse<CourseQuestionnaireTopic[]>> {
    return apiClient.post<CourseQuestionnaireTopic[]>(`/courses/${courseId}/questionnaires/reset-template`)
  },

  createTopic(courseId: number | string, data: Partial<CourseQuestionnaireTopic>): Promise<ApiResponse<CourseQuestionnaireTopic>> {
    return apiClient.post<CourseQuestionnaireTopic>(`/courses/${courseId}/questionnaire-topics`, data)
  },

  updateTopic(topicId: number | string, data: Partial<CourseQuestionnaireTopic>): Promise<ApiResponse<CourseQuestionnaireTopic>> {
    return apiClient.put<CourseQuestionnaireTopic>(`/course-questionnaire-topics/${topicId}`, data)
  },

  deleteTopic(topicId: number | string): Promise<ApiResponse<void>> {
    return apiClient.delete<void>(`/course-questionnaire-topics/${topicId}`)
  },

  createQuestion(topicId: number | string, data: Partial<CourseQuestionnaireQuestion>): Promise<ApiResponse<CourseQuestionnaireQuestion>> {
    return apiClient.post<CourseQuestionnaireQuestion>(`/course-questionnaire-topics/${topicId}/questions`, data)
  },

  updateQuestion(questionId: number | string, data: Partial<CourseQuestionnaireQuestion>): Promise<ApiResponse<CourseQuestionnaireQuestion>> {
    return apiClient.put<CourseQuestionnaireQuestion>(`/course-questionnaire-questions/${questionId}`, data)
  },

  deleteQuestion(questionId: number | string): Promise<ApiResponse<void>> {
    return apiClient.delete<void>(`/course-questionnaire-questions/${questionId}`)
  },
}
