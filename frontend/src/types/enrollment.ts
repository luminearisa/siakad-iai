import type { Semester } from './academic'
import type { AcademicClass } from './class'
import type { Course } from './course'
import type { Student } from './student'
import type { User } from './auth'

export type EnrollmentStatus =
  | 'draft'
  | 'submitted'
  | 'approved'
  | 'rejected'
  | 'revision_required'
  | 'cancelled'
  | 'locked'

export type EnrollmentItemStatus = 'enrolled' | 'dropped' | 'approved' | 'rejected'

export interface StudentEnrollment {
  id: number
  student_id: number
  semester_id: number
  status: EnrollmentStatus
  total_credits: number
  max_credits?: number
  academic_advisor?: string | null
  academic_advisor_id?: number | null
  submitted_at?: string | null
  approved_at?: string | null
  approved_by?: number | null
  approver?: User | null
  notes?: string | null
  student?: Student | null
  semester?: Semester | null
  items?: StudentEnrollmentItem[]
  items_count?: number
  created_at: string
  updated_at?: string
}

export interface StudentEnrollmentItem {
  id: number
  enrollment_id: number
  class_id: number
  course_id: number
  credits: number
  status: EnrollmentItemStatus
  finalized_at?: string | null
  notes?: string | null
  academic_class?: AcademicClass | null
  course?: Course | null
  created_at: string
  updated_at?: string
}

export interface CreateEnrollmentPayload {
  semester_id: number
  student_id?: number
  notes?: string | null
}

export interface AddEnrollmentItemPayload {
  class_id: number
  notes?: string | null
}

export interface EnrollmentFilters {
  search?: string
  student_id?: number | string | ''
  semester_id?: number | string | ''
  status?: EnrollmentStatus | ''
  sort?: string
  direction?: 'asc' | 'desc'
  page?: number
  per_page?: number
}

export interface KrsPackageItem {
  id: number
  krs_package_id: number
  course_id: number
  credits: number
  course?: Course | null
  created_at?: string
  updated_at?: string
}

export interface KrsPackage {
  id: number
  name: string
  study_program_id: number
  semester_level: number // Tingkat semester mahasiswa (1–14), bukan FK ke tabel semesters
  total_credits: number
  description?: string | null
  study_program?: {
    id: number
    name: string
    code: string
    degree?: string
  } | null
  items?: KrsPackageItem[]
  created_at?: string
  updated_at?: string
}

export interface CreateKrsPackagePayload {
  name: string
  study_program_id: number
  semester_level: number
  description?: string | null
  course_ids?: number[]
}

export interface UpdateKrsPackagePayload {
  name: string
  study_program_id: number
  semester_level: number
  description?: string | null
  course_ids?: number[]
}

export interface LoadPackageResult {
  added: string[]
  failed: Array<{
    course: string
    reason: string
  }>
}
