import { apiClient } from './client'
import type { ApiResponse } from '@/types/api'
import type {
  StudentKhsData,
  StudentProfileData,
  StudentScheduleItem,
} from '@/types/student-portal'

export const studentPortalApi = {
  /**
   * Get full profile for the authenticated student.
   */
  getProfile(): Promise<ApiResponse<StudentProfileData>> {
    return apiClient.get<StudentProfileData>('/students/me/profile')
  },

  /**
   * Submit profile update request.
   */
  requestUpdate(data: {
    phone_number?: string
    email?: string
    address?: string
    notes?: string
  }): Promise<ApiResponse<StudentProfileData>> {
    return apiClient.post<StudentProfileData>('/students/me/request-update', data)
  },

  /**
   * Get KHS and grades per semester.
   */
  getKhs(semesterId?: number): Promise<ApiResponse<StudentKhsData>> {
    const params = semesterId ? { semester_id: semesterId } : undefined
    return apiClient.get<StudentKhsData>('/students/me/khs', params)
  },

  /**
   * Get enrolled class schedules for student.
   */
  getSchedules(): Promise<ApiResponse<StudentScheduleItem[]>> {
    return apiClient.get<StudentScheduleItem[]>('/students/me/schedules')
  },
}
