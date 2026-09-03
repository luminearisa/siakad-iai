import { apiClient } from './client'
import type { ApiResponse } from '@/types/api'
import type {
  Course,
  CourseCreatePayload,
  CourseUpdatePayload,
  CourseFilters,
  CoursePrerequisiteItem,
  CourseTypeItem,
  CourseGroupItem,
} from '@/types/course'

export const courseService = {
  // Course Master
  list(params?: CourseFilters): Promise<ApiResponse<Course[]>> {
    return apiClient.get<Course[]>('/courses', params as Record<string, unknown>)
  },

  get(id: number | string): Promise<ApiResponse<Course>> {
    return apiClient.get<Course>(`/courses/${id}`)
  },

  create(data: CourseCreatePayload): Promise<ApiResponse<Course>> {
    return apiClient.post<Course>('/courses', data)
  },

  update(id: number | string, data: CourseUpdatePayload): Promise<ApiResponse<Course>> {
    return apiClient.put<Course>(`/courses/${id}`, data)
  },

  delete(id: number | string): Promise<ApiResponse<null>> {
    return apiClient.delete<null>(`/courses/${id}`)
  },

  getPrerequisites(id: number | string): Promise<ApiResponse<Course[]>> {
    return apiClient.get<Course[]>(`/courses/${id}/prerequisites`)
  },

  setPrerequisites(id: number | string, prerequisites: CoursePrerequisiteItem[]): Promise<ApiResponse<Course>> {
    return apiClient.post<Course>(`/courses/${id}/prerequisites`, { prerequisites })
  },

  // Course Types (Jenis Mata Kuliah)
  listTypes(params?: Record<string, unknown>): Promise<ApiResponse<CourseTypeItem[]>> {
    return apiClient.get<CourseTypeItem[]>('/course-types', params)
  },

  getType(id: number | string): Promise<ApiResponse<CourseTypeItem>> {
    return apiClient.get<CourseTypeItem>(`/course-types/${id}`)
  },

  createType(data: Partial<CourseTypeItem>): Promise<ApiResponse<CourseTypeItem>> {
    return apiClient.post<CourseTypeItem>('/course-types', data)
  },

  updateType(id: number | string, data: Partial<CourseTypeItem>): Promise<ApiResponse<CourseTypeItem>> {
    return apiClient.put<CourseTypeItem>(`/course-types/${id}`, data)
  },

  deleteType(id: number | string): Promise<ApiResponse<null>> {
    return apiClient.delete<null>(`/course-types/${id}`)
  },

  // Course Groups (Kelompok Mata Kuliah)
  listGroups(params?: Record<string, unknown>): Promise<ApiResponse<CourseGroupItem[]>> {
    return apiClient.get<CourseGroupItem[]>('/course-groups', params)
  },

  getGroup(id: number | string): Promise<ApiResponse<CourseGroupItem>> {
    return apiClient.get<CourseGroupItem>(`/course-groups/${id}`)
  },

  createGroup(data: Partial<CourseGroupItem>): Promise<ApiResponse<CourseGroupItem>> {
    return apiClient.post<CourseGroupItem>('/course-groups', data)
  },

  updateGroup(id: number | string, data: Partial<CourseGroupItem>): Promise<ApiResponse<CourseGroupItem>> {
    return apiClient.put<CourseGroupItem>(`/course-groups/${id}`, data)
  },

  deleteGroup(id: number | string): Promise<ApiResponse<null>> {
    return apiClient.delete<null>(`/course-groups/${id}`)
  },
}
