import type { Student } from './student'
import type { StudyProgram, Semester } from './academic'
import type { Thesis } from './thesis'

export interface YudisiumPeriod {
  id: number
  semester_id?: number | null
  name: string
  registration_start_date: string
  registration_end_date: string
  yudisium_date: string
  quota?: number | null
  is_active: boolean
  notes?: string | null
  participants_count?: number
  semester?: Semester
  created_at?: string
  updated_at?: string
}

export interface YudisiumRequirement {
  id: number
  study_program_id?: number | null
  name: string
  code?: string | null
  is_document: boolean
  is_mandatory: boolean
  min_credits: number
  min_gpa: number
  study_program?: StudyProgram
  created_at?: string
  updated_at?: string
}

export type YudisiumApplicationStatus =
  | 'submitted'
  | 'under_review'
  | 'needs_revision'
  | 're_review'
  | 'ready'
  | 'passed'
  | 'rejected'

export interface YudisiumParticipant {
  id: number
  yudisium_period_id: number
  student_id: number
  application_date?: string | null
  status: YudisiumApplicationStatus
  rejection_reason?: string | null
  notes?: string | null
  total_credits: number
  gpa: number
  study_duration_days: number
  thesis_id?: number | null
  sk_number?: string | null
  sk_date?: string | null
  is_certificate_taken: boolean
  certificate_taken_at?: string | null
  certificate_taken_by?: string | null
  student?: Student
  period?: YudisiumPeriod
  thesis?: Thesis
  created_at?: string
  updated_at?: string
}

export interface EligibleStudentAudit {
  student_id: number
  student: Student
  study_program?: StudyProgram
  study_duration: string
  study_duration_days: number
  passed_credits: number
  passed_gpa: number
  thesis_status: string
  thesis?: Thesis | null
  is_eligible: boolean
  reasons: string[]
}

export interface ApprovalStats {
  ready: number
  needs_revision: number
  submitted: number
  re_review: number
  under_review: number
}
