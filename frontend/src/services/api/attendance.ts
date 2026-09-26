import { apiClient } from './client'
import type { ApiResponse, QueryParams } from '@/types/api'
import type {
  AttendanceSheetRow,
  BatchAttendancePayload,
  ClassAttendanceRecap,
  ReopenSessionPayload,
  SelfCheckInPayload,
  SingleAttendancePayload,
  StudentAttendance,
  StudentAttendanceRecap,
  SyncClassSessionsResult,
  TeachingSession,
  TeachingSessionPayload,
} from '@/types/attendance'

export const attendanceService = {
  // Teaching Sessions List & CRUD
  // `check_in_code` / `check_in_expires_at` are only present for the session's
  // lecturer or staff; every role gets `is_check_in_active` and `can_manage`.
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

  /**
   * Reopen a closed session so its attendance may be edited again. The server
   * requires a reason and keeps it on the audit trail.
   */
  reopenSession(id: number, reason: string): Promise<ApiResponse<TeachingSession>> {
    const payload: ReopenSessionPayload = { reason: reason.trim() }
    return apiClient.post<TeachingSession>(`/attendance/sessions/${id}/reopen`, payload)
  },

  // Student Attendances per Session
  // Returns the class roster merged with saved records (see AttendanceSheetRow);
  // an unrecorded row comes back with `status: null` and `is_recorded: false`.
  getSessionStudents(sessionId: number): Promise<ApiResponse<AttendanceSheetRow[]>> {
    return apiClient.get<AttendanceSheetRow[]>(`/attendance/sessions/${sessionId}/students`)
  },

  /**
   * Save the sheet. Rows still marked "belum dicatat" must be filtered out by
   * the caller, and `correction_reason` is mandatory once the session is closed.
   */
  recordBatch(sessionId: number, payload: BatchAttendancePayload): Promise<ApiResponse<AttendanceSheetRow[]>> {
    return apiClient.post<AttendanceSheetRow[]>(`/attendance/sessions/${sessionId}/record-batch`, payload)
  },

  updateStudentAttendance(
    sessionId: number,
    studentId: number,
    payload: SingleAttendancePayload
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

  /**
   * Regenerate a class' sessions from its schedule (missing meetings are
   * created, schedule changes applied). The response body is not part of the
   * stable contract, so callers reload the sessions afterwards.
   */
  syncClassSessions(classId: number): Promise<ApiResponse<SyncClassSessionsResult>> {
    return apiClient.post<SyncClassSessionsResult>(`/attendance/classes/${classId}/sync-sessions`)
  },
}
