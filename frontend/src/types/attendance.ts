import type { AcademicClass } from './class'
import type { Lecturer } from './lecturer'
import type { Room } from './room'
import type { Student } from './student'

export type AttendanceStatusCode = 'present' | 'permit' | 'sick' | 'absent'
export type TeachingMethodType = 'offline' | 'online' | 'hybrid'
export type SessionStatusType = 'scheduled' | 'open' | 'closed' | 'cancelled'

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
  check_in_code?: string | null
  check_in_expires_at?: string | null
  is_check_in_active?: boolean
  academic_class?: AcademicClass
  lecturer?: Lecturer
  room?: Room
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

export interface SessionStudentItem {
  student_id: number
  status: AttendanceStatusCode
  notes?: string
}

export interface BatchAttendancePayload {
  attendances: SessionStudentItem[]
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
  total_meetings: number
  percentage: number
  is_eligible: boolean
  meetings: Record<number, {
    session_id: number
    meeting_number: number
    session_date: string
    status: AttendanceStatusCode | null
    status_code: string
    notes?: string | null
  }>
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
    }
  }
  total_sessions: number
  sessions: TeachingSession[]
  recap: ClassRecapMatrixStudent[]
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
  meetings: Array<{
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
    status: AttendanceStatusCode
    status_label: string
    status_code: string
    notes?: string | null
    is_check_in_active?: boolean
  }>
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
  }
  classes: StudentCourseAttendance[]
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
