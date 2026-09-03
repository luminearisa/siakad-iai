import { apiClient } from './client'
import type { ApiResponse } from '@/types/api'

export interface ExamScheduleItem {
  id?: number
  exam_type: 'uts' | 'uas'
  exam_date?: string | null
  start_time?: string | null
  end_time?: string | null
  room_id?: number | null
  room_name?: string | null
  proctor_lecturer_id?: number | null
  proctor_name?: string | null
  is_announced?: boolean
  announced_at?: string | null
  notes?: string | null
}

export interface ExamClassItem {
  class_id: number
  class_code: string
  class_name: string
  curriculum_year: number
  study_program?: {
    id: number
    name: string
    degree?: string
  } | null
  course?: {
    id: number
    code: string
    name: string
    credits: number
  } | null
  student_count: number
  exam_schedule?: ExamScheduleItem | null
}

export const examService = {
  list(params?: { exam_type?: string; study_program_id?: string | number; semester_id?: string | number; search?: string }): Promise<ApiResponse<ExamClassItem[]>> {
    return apiClient.get<ExamClassItem[]>('/exam-schedules', params as Record<string, unknown>)
  },

  updateSchedule(classId: number | string, data: { exam_type: string; exam_date: string; start_time: string; end_time: string; room_id?: number | null; notes?: string }): Promise<ApiResponse<any>> {
    return apiClient.post(`/classes/${classId}/exam-schedules`, data)
  },

  updateProctor(classId: number | string, data: { exam_type: string; proctor_lecturer_id: number }): Promise<ApiResponse<any>> {
    return apiClient.post(`/classes/${classId}/exam-proctor`, data)
  },

  announce(data: { exam_type: string }): Promise<ApiResponse<{ count: number }>> {
    return apiClient.post<{ count: number }>('/exam-schedules/announce', data)
  },
}
