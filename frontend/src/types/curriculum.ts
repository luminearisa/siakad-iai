import type { StudyProgram } from './academic'
import type { Course } from './course'

export type CurriculumStatus = 'draft' | 'active' | 'inactive' | 'archived'

export interface CurriculumYear {
  id: number
  year: number
  name: string
  start_date: string
  end_date: string
  status: 'active' | 'inactive'
  created_at?: string
  updated_at?: string
}

export interface CreditLimitRule {
  min_gpa: number
  max_gpa: number
  max_sks: number
}

export interface CreditLimit {
  id: number
  name: string
  description?: string | null
  rules?: CreditLimitRule[]
  status: 'active' | 'inactive'
  curricula_count?: number
  created_at?: string
  updated_at?: string
}

export interface GradeScaleItem {
  id?: number
  grade_scale_id?: number
  grade_letter: string
  grade_point: number
  min_score: number
  max_score: number
  is_whitewash: boolean
}

export interface GradeScale {
  id: number
  name: string
  description?: string | null
  status: 'active' | 'inactive'
  items?: GradeScaleItem[]
  curricula_count?: number
  created_at?: string
  updated_at?: string
}

export interface CurriculumSubject {
  id: number
  curriculum_semester_id: number
  course_id: number
  course?: Course
  is_mandatory: boolean
  subject_type?: 'wajib' | 'pilihan'
  is_package?: boolean
  credits_override?: number | null
  effective_credits?: number
  minimum_grade?: string
  prerequisites_text?: string | null
  notes?: string | null
  created_at?: string
  updated_at?: string
}

export interface CurriculumSemester {
  id: number
  curriculum_id: number
  semester_number: number
  name: string
  description?: string | null
  total_credits?: number
  recommended_credits?: number
  subjects?: CurriculumSubject[]
  created_at?: string
  updated_at?: string
}

export interface Curriculum {
  id: number
  study_program_id: number
  study_program?: StudyProgram
  curriculum_year_id?: number | null
  curriculum_year?: CurriculumYear
  credit_limit_id?: number | null
  credit_limit?: CreditLimit
  grade_scale_id?: number | null
  grade_scale?: GradeScale
  code: string
  name: string
  version: string
  description?: string | null
  start_year?: number | null
  end_year?: number | null
  status: CurriculumStatus
  effective_date?: string | null
  expiry_date?: string | null
  total_credits?: number
  semesters?: CurriculumSemester[]
  created_at?: string
  updated_at?: string
}

export interface CurriculumCreatePayload {
  study_program_id: number
  curriculum_year_id?: number | null
  credit_limit_id?: number | null
  grade_scale_id?: number | null
  code: string
  name: string
  version?: string
  description?: string | null
  start_year?: number | null
  end_year?: number | null
  status?: CurriculumStatus
  effective_date?: string | null
  expiry_date?: string | null
}

export interface CurriculumUpdatePayload {
  study_program_id?: number
  curriculum_year_id?: number | null
  credit_limit_id?: number | null
  grade_scale_id?: number | null
  code?: string
  name?: string
  version?: string
  description?: string | null
  start_year?: number | null
  end_year?: number | null
  status?: CurriculumStatus
  effective_date?: string | null
  expiry_date?: string | null
}

export interface CreateCurriculumSemesterPayload {
  semester_number: number
  name: string
  description?: string | null
  recommended_credits?: number | null
}

export interface AddCurriculumSubjectPayload {
  course_id: number
  is_mandatory?: boolean
  subject_type?: 'wajib' | 'pilihan'
  is_package?: boolean
  credits_override?: number | null
  minimum_grade?: string
  prerequisites_text?: string | null
  notes?: string | null
}

export interface CurriculumFilters {
  search?: string
  study_program_id?: number | string | ''
  status?: string
  start_year?: number | string | ''
  end_year?: number | string | ''
  sort?: string
  direction?: 'asc' | 'desc'
  page?: number
  per_page?: number
}
