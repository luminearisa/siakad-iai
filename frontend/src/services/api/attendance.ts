import { apiClient } from './client'
import type { ApiResponse, QueryParams } from '@/types/api'
import type {
  AttendanceSheetRow,
  BatchAttendancePayload,
  ClassAttendanceRecap,
  SelfCheckInPayload,
  StudentAttendance,
  StudentAttendanceRecap,
  TeachingSession,
  TeachingSessionPayload,
} from '@/types/attendance'

export const attendanceService = {
  // Teaching Sessions List & CRUD
  listSessions(params?: QueryParams): Promise<ApiResponse<TeachingSession[]>> {
    return apiClient.get<TeachingSession[]>('/attendance/sessions', params)
  },

  getSessions(params?: QueryParams): Promise<ApiResponse<TeachingSession[]>> {
    return apiClient.get<TeachingSession[]>('/attendance/sessions', params)
  },

  getSession(id: number): Promise<ApiResponse<TeachingSession>> {
    return apiClient.get<TeachingSession>(`/attendance/sessions/${id}`)
  },

  createSession(payload: TeachingSessionPayload): Promise<ApiResponse<TeachingSession>> {
    return apiClient.post<TeachingSession>('/attendance/sessions', payload)
  },

  updateSession(id: number, payload: Partial<TeachingSessionPayload>): Promise<ApiResponse<TeachingSession>> {
    return apiClient.put<TeachingSession>(`/attendance/sessions/${id}`, payload)
  },

  deleteSession(id: number): Promise<ApiResponse<void>> {
    return apiClient.delete<void>(`/attendance/sessions/${id}`)
  },

  // Open & Close Sessions
  openCheckIn(id: number, durationMinutes = 15): Promise<ApiResponse<TeachingSession>> {
    return apiClient.post<TeachingSession>(`/attendance/sessions/${id}/open-checkin`, {
      duration_minutes: durationMinutes,
    })
  },

  closeSession(id: number): Promise<ApiResponse<TeachingSession>> {
    return apiClient.post<TeachingSession>(`/attendance/sessions/${id}/close`)
  },

  // Student Attendances per Session
  // Returns the class roster merged with saved records (see AttendanceSheetRow).
  getSessionStudents(sessionId: number): Promise<ApiResponse<AttendanceSheetRow[]>> {
    return apiClient.get<AttendanceSheetRow[]>(`/attendance/sessions/${sessionId}/students`)
  },

  recordBatch(sessionId: number, payload: BatchAttendancePayload): Promise<ApiResponse<AttendanceSheetRow[]>> {
    return apiClient.post<AttendanceSheetRow[]>(`/attendance/sessions/${sessionId}/record-batch`, payload)
  },

  updateStudentAttendance(
    sessionId: number,
    studentId: number,
    payload: { status: string; notes?: string }
  ): Promise<ApiResponse<StudentAttendance>> {
    return apiClient.patch<StudentAttendance>(`/attendance/sessions/${sessionId}/students/${studentId}`, payload)
  },

  // Student Self-Service Check-in
  selfCheckIn(payload: SelfCheckInPayload): Promise<ApiResponse<StudentAttendance>> {
    return apiClient.post<StudentAttendance>('/attendance/self-checkin', payload)
  },

  getMyAttendance(params?: { semester_id?: number }): Promise<ApiResponse<StudentAttendanceRecap>> {
    return apiClient.get<StudentAttendanceRecap>('/attendance/my-attendance', params as QueryParams)
  },

  // Class & Student Recaps
  getClassRecap(classId: number): Promise<ApiResponse<ClassAttendanceRecap>> {
    return apiClient.get<ClassAttendanceRecap>(`/attendance/classes/${classId}/recap`)
  },

  getStudentRecap(studentId: number, params?: { semester_id?: number }): Promise<ApiResponse<StudentAttendanceRecap>> {
    return apiClient.get<StudentAttendanceRecap>(`/attendance/students/${studentId}/recap`, params as QueryParams)
  },
}
