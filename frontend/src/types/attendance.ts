import type { AcademicClass } from './class'
import type { Lecturer } from './lecturer'
import type { Room } from './room'
import type { Student } from './student'

export type AttendanceStatusCode = 'present' | 'permit' | 'sick' | 'absent'

/**
 * Status on the attendance sheet / a student's meeting history.
 *
 * `null` is a real state meaning "belum dicatat": the student is on the roster
 * but no record has been saved for that meeting yet. The backend used to fake
 * this as `present` (sheet) or `absent` (student history); never infer a value
 * for a null status again.
 */
export type AttendanceSheetStatus = AttendanceStatusCode | null

export type TeachingMethodType = 'offline' | 'online' | 'hybrid'
export type SessionStatusType = 'scheduled' | 'open' | 'closed' | 'cancelled'

/** Which exam the attendance threshold gates: UTS (mid) or UAS (final). */
export type AttendanceThresholdStage = 'uts' | 'uas'

/**
 * Statuses whose server-side validation demands a note: a permit, sick leave or
 * absence without an explanation is rejected with 422. Kept here so the input
 * sheet, the badges and the tests share one definition.
 */
export const NOTE_REQUIRED_STATUSES: readonly AttendanceStatusCode[] = [
  'permit',
  'sick',
  'absent',
]

export function requiresNotes(status: AttendanceSheetStatus): boolean {
  return status !== null && NOTE_REQUIRED_STATUSES.includes(status)
}

export const ATTENDANCE_STATUS_LABELS: Record<AttendanceStatusCode, string> = {
  present: 'Hadir',
  permit: 'Izin',
  sick: 'Sakit',
  absent: 'Alpa',
}

export const UNRECORDED_LABEL = 'Belum dicatat'

export interface TeachingSession {
  id: number
  academic_class_id: number
  schedule_id?: number | null
  lecturer_id: number
  meeting_number: number
  session_date: string
  start_time?: string | null
  end_time?: string | null
  topic?: string | null
  notes?: string | null
  teaching_method: TeachingMethodType
  teaching_method_label?: string
  room_id?: number | null
  status: SessionStatusType
  status_label?: string
  /**
   * Only sent to the session's lecturer or to staff. Students must not read a
   * code: gate every "show / copy the token" affordance on `can_manage`.
   */
  check_in_code?: string | null
  /** Only sent to the session's lecturer or to staff, see `check_in_code`. */
  check_in_expires_at?: string | null
  /** Sent to every role: is the self check-in window open right now? */
  is_check_in_active?: boolean
  /** Sent to every role: may the current user record/manage this session? */
  can_manage?: boolean
  academic_class?: AcademicClass
  lecturer?: Lecturer
  room?: Room
  /** Number of saved attendance rows, excluding unrecorded roster members. */
  attendances_count?: number
  present_count?: number
  permit_count?: number
  sick_count?: number
  absent_count?: number
  attendances?: StudentAttendance[]
  created_at: string
  updated_at: string
}

export interface StudentAttendance {
  id: number
  teaching_session_id: number
  student_id: number
  academic_class_id: number
  status: AttendanceStatusCode
  status_label?: string
  status_code?: string
  notes?: string | null
  attachment_path?: string | null
  recorded_by?: number | null
  recorded_at?: string | null
  student?: Student
  teaching_session?: TeachingSession
  created_at: string
  updated_at: string
}

/**
 * One row of the lecturer's attendance sheet: the class roster merged with any
 * records already saved for the session, so every enrolled student is present
 * even when the meeting was opened before they enrolled.
 */
export interface AttendanceSheetRow {
  /** Null until the row has been saved for the first time. */
  id: number | null
  teaching_session_id: number
  student_id: number
  academic_class_id: number
  /** Null means the meeting has not been recorded for this student yet. */
  status: AttendanceSheetStatus
  status_label?: string | null
  status_code?: string | null
  notes?: string | null
  attachment_path?: string | null
  recorded_by?: number | null
  recorded_at?: string | null
  /** False when this student has no saved record yet for this session. */
  is_recorded: boolean
  /** False for students recorded earlier but no longer on the class roster. */
  is_enrolled: boolean
  student?: Student
  created_at?: string | null
  updated_at?: string | null
}

/** One entry of the `record-batch` payload. Unrecorded rows are never sent. */
export interface BatchAttendanceItem {
  student_id: number
  status: AttendanceStatusCode
  notes?: string | null
}

export interface BatchAttendancePayload {
  attendances: BatchAttendanceItem[]
  /**
   * Mandatory once the session is `closed`; the server answers 422 without it.
   * Omitted for open/scheduled sessions.
   */
  correction_reason?: string
}

/** Body of `PATCH sessions/{s}/students/{st}`. */
export interface SingleAttendancePayload {
  status: AttendanceStatusCode
  notes?: string | null
}

/** Body of `POST sessions/{id}/reopen`. The reason is mandatory server-side. */
export interface ReopenSessionPayload {
  reason: string
}

/**
 * Result of `POST classes/{id}/sync-sessions`: sessions regenerated from the
 * class schedule. The response body is not fixed by the contract, so callers
 * reload their data instead of trusting these fields.
 */
export interface SyncClassSessionsResult {
  sessions?: TeachingSession[]
  created_count?: number
  total_sessions?: number
}

export interface TeachingSessionPayload {
  academic_class_id: number
  schedule_id?: number | null
  lecturer_id: number
  meeting_number: number
  session_date: string
  start_time?: string | null
  end_time?: string | null
  topic?: string | null
  notes?: string | null
  teaching_method?: TeachingMethodType
  room_id?: number | null
  status?: SessionStatusType
}

export interface SelfCheckInPayload {
  teaching_session_id: number
  check_in_code: string
}

export interface ClassRecapMeetingCell {
  session_id: number
  meeting_number: number
  session_date: string
  status: AttendanceSheetStatus
  status_code: string
  notes?: string | null
}

export interface ClassRecapMatrixStudent {
  student: {
    id: number
    student_number: string
    full_name: string
    photo_path?: string | null
    study_program?: string
  }
  present_count: number
  permit_count: number
  sick_count: number
  absent_count: number
  /** Meetings of this student that still have no record at all. */
  unrecorded_count: number
  total_meetings: number
  percentage: number
  is_eligible: boolean
  meetings: Record<number, ClassRecapMeetingCell>
}

export interface ClassAttendanceRecap {
  class: {
    id: number
    code: string
    name: string
    section: string
    course?: {
      id: number
      code: string
      name: string
      credits: number
    }
    semester?: {
      id: number
      name: string
      /** Teaching weeks configured on the semester; drives the meeting target. */
      total_teaching_weeks?: number
      min_attendance_uts_percentage?: number
      min_attendance_uas_percentage?: number
    }
  }
  /** Sessions created for the class, including meetings that have not run yet. */
  total_sessions: number
  /** Sessions that actually took place, i.e. the denominator of the recap. */
  held_sessions: number
  /** Server-owned threshold for exam eligibility. Never hardcode a number. */
  min_attendance_percentage: number
  /** Which threshold (UTS / UAS) the server applied. */
  threshold_stage: AttendanceThresholdStage
  sessions: TeachingSession[]
  recap: ClassRecapMatrixStudent[]
}

export interface StudentMeetingRecord {
  session_id: number
  meeting_number: number
  session_date?: string | null
  start_time?: string | null
  end_time?: string | null
  topic?: string | null
  teaching_method?: string
  teaching_method_label?: string
  lecturer_name?: string
  room?: string
  /** Null when the meeting has not been recorded (or has not run) yet. */
  status: AttendanceSheetStatus
  status_label?: string | null
  status_code: string
  notes?: string | null
  /** The student may still check themselves into this meeting. */
  is_check_in_active?: boolean
}

export interface StudentCourseAttendance {
  class_id: number
  class_code: string
  class_name: string
  section: string
  course_code?: string
  course_name?: string
  credits?: number
  semester_name?: string
  lecturers?: Lecturer[]
  total_sessions: number
  present_count: number
  permit_count: number
  sick_count: number
  absent_count: number
  percentage: number
  is_eligible: boolean
  /** Per-course threshold applied by the server for `is_eligible`. */
  min_attendance_percentage: number
  meetings: StudentMeetingRecord[]
}

export interface StudentAttendanceRecap {
  student: {
    id: number
    student_number: string
    full_name: string
    study_program?: string
  }
  summary: {
    total_classes: number
    total_sessions: number
    total_present: number
    total_permit: number
    total_sick: number
    total_absent: number
    overall_percentage: number
    is_eligible_overall: boolean
    /** Server-owned threshold behind `is_eligible_overall`. */
    min_attendance_percentage: number
    /** Which threshold (UTS / UAS) the server applied. */
    threshold_stage: AttendanceThresholdStage
  }
  classes: StudentCourseAttendance[]
}

/**
 * A meeting the student can still check into by themselves, derived from
 * `my-attendance` so the student never has to type a session id and never sees
 * a check-in code (students do not receive one).
 */
export interface CheckInOption {
  session_id: number
  class_id: number
  course_label: string
  section?: string
  meeting_number: number
  session_date?: string | null
  start_time?: string | null
  end_time?: string | null
  topic?: string | null
}

export interface AttendanceFilters {
  search?: string
  academic_class_id?: number | string | ''
  lecturer_id?: number | string | ''
  status?: string | ''
  date?: string | ''
  page?: number
  per_page?: number
  [key: string]: string | number | boolean | undefined
}
