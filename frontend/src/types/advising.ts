import type { Lecturer } from './lecturer'
import type { Student } from './student'
import type { StudentEnrollment } from './enrollment'

export type AdvisorStatus = 'active' | 'inactive' | 'transferred' | 'completed'

export type AdvisingSessionStatus = 'scheduled' | 'completed' | 'cancelled'

export type AdvisingSessionType =
  | 'academic_consultation'
  | 'krs_review'
  | 'study_progress'
  | 'thesis_preparation'
  | 'other'

export interface AcademicAdvisor {
  id: number
  student_id: number
  lecturer_id: number
  start_date: string
  end_date?: string | null
  status: AdvisorStatus
  notes?: string | null
  student?: Student
  lecturer?: Lecturer
  created_at: string
  updated_at?: string
}

export interface AdvisingSession {
  id: number
  student_id: number
  lecturer_id: number
  enrollment_id?: number | null
  session_date: string
  topic?: string | null
  notes: string
  status: AdvisingSessionStatus
  student?: Student
  lecturer?: Lecturer
  enrollment?: StudentEnrollment
  created_at: string
  updated_at?: string
}

export interface AssignAdvisorPayload {
  student_id: number
  lecturer_id: number
  start_date?: string
  notes?: string
}

export interface ChangeAdvisorPayload {
  new_lecturer_id: number
  change_date?: string
  reason?: string
}

export interface EndAdvisorPayload {
  end_date?: string
  notes?: string
}

export interface CreateSessionPayload {
  student_id: number
  lecturer_id: number
  enrollment_id?: number | null
  session_date: string
  topic?: string
  notes: string
  status?: AdvisingSessionStatus
}

export interface UpdateSessionPayload {
  session_date?: string
  topic?: string
  notes?: string
  status?: AdvisingSessionStatus
  enrollment_id?: number | null
}

export interface AdvisorFilters {
  search?: string
  student_id?: number | string | ''
  lecturer_id?: number | string | ''
  study_program_id?: number | string | ''
  status?: AdvisorStatus | ''
  page?: number
  per_page?: number
  [key: string]: unknown
}

export interface SessionFilters {
  search?: string
  student_id?: number | string | ''
  lecturer_id?: number | string | ''
  enrollment_id?: number | string | ''
  status?: AdvisingSessionStatus | ''
  page?: number
  per_page?: number
  [key: string]: unknown
}

export interface AcademicAlertInfo {
  type: 'danger' | 'warning' | 'info' | 'success'
  title: string
  message: string
}
