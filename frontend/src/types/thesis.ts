import type { Student } from './student'
import type { StudyProgram, Semester } from './academic'
import type { Lecturer } from './lecturer'

export type ThesisStatus = 'active' | 'completed' | 'inactive' | 'pending_approval'

export interface ThesisSupervisor {
  id?: number
  thesis_id?: number
  lecturer_id: number
  order: number
  role: string
  status: string
  lecturer?: Lecturer
}

export interface Thesis {
  id: number
  student_id: number
  study_program_id?: number | null
  start_semester_id?: number | null
  completion_semester_id?: number | null
  start_date?: string | null
  submission_date?: string | null
  completion_date?: string | null
  status: ThesisStatus
  title_id: string
  title_en?: string | null
  topic_id?: string | null
  topic_en?: string | null
  proposal_file_path?: string | null
  final_file_path?: string | null
  sk_date?: string | null
  sk_number?: string | null
  final_grade?: number | string | null
  final_grade_letter?: string | null
  student?: Student
  study_program?: StudyProgram
  start_semester?: Semester
  completion_semester?: Semester
  supervisors?: ThesisSupervisor[]
  created_at?: string
  updated_at?: string
}

export interface ThesisStats {
  completed: number
  active: number
  inactive: number
  pending_approval: number
}

export interface ThesisPayload {
  student_id: number
  start_semester_id?: number | null
  completion_semester_id?: number | null
  start_date: string
  submission_date: string
  completion_date?: string | null
  status: ThesisStatus
  title_id: string
  title_en?: string | null
  topic_id?: string | null
  topic_en?: string | null
  proposal_file_path?: string | null
  final_file_path?: string | null
  sk_date?: string | null
  sk_number?: string | null
  final_grade?: number | null
  final_grade_letter?: string | null
  supervisor_ids: number[]
}
