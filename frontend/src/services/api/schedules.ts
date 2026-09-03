import { apiClient } from './client'
import type { ApiResponse } from '@/types/api'
import type {
  ClassSchedule,
  CreateSchedulePayload,
  UpdateSchedulePayload,
  ScheduleFilters,
} from '@/types/schedule'

export const scheduleService = {
  list(params?: ScheduleFilters): Promise<ApiResponse<ClassSchedule[]>> {
    return apiClient.get<ClassSchedule[]>('/schedules', params as Record<string, unknown>)
  },

  get(id: number | string): Promise<ApiResponse<ClassSchedule>> {
    return apiClient.get<ClassSchedule>(`/schedules/${id}`)
  },

  create(data: CreateSchedulePayload): Promise<ApiResponse<ClassSchedule>> {
    return apiClient.post<ClassSchedule>('/schedules', data)
  },

  update(id: number | string, data: UpdateSchedulePayload): Promise<ApiResponse<ClassSchedule>> {
    return apiClient.put<ClassSchedule>(`/schedules/${id}`, data)
  },

  delete(id: number | string): Promise<ApiResponse<null>> {
    return apiClient.delete<null>(`/schedules/${id}`)
  },

  getClassSchedules(classId: number | string): Promise<ApiResponse<ClassSchedule[]>> {
    return apiClient.get<ClassSchedule[]>(`/classes/${classId}/schedules`)
  },

  createClassSchedule(classId: number | string, data: CreateSchedulePayload): Promise<ApiResponse<ClassSchedule>> {
    return apiClient.post<ClassSchedule>(`/classes/${classId}/schedules`, data)
  },
}
