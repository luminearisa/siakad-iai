import type { StudyProgram } from './academic'

export interface LectureSessionType {
  id: number
  name: string
  short_name: string
  category: string
  credit_type: string
  counts_attendance: boolean
  status: 'active' | 'inactive'
  created_at?: string
  updated_at?: string
}

export interface LectureProgram {
  id: number
  name: string
  description?: string | null
  status: 'active' | 'inactive'
  created_at?: string
  updated_at?: string
}

export interface GradingComponent {
  id: number
  name: string
  short_name: string
  evaluation_method: string
  component_group?: string | null
  default_weight?: number | null
  status: 'active' | 'inactive'
  created_at?: string
  updated_at?: string
}

export interface StudentGroup {
  id: number
  study_program_id?: number | null
  study_program?: StudyProgram
  name: string
  description?: string | null
  student_count: number
  status: 'active' | 'inactive'
  created_at?: string
  updated_at?: string
}
