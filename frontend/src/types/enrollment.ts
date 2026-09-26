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

/**
 * Status satu baris mata kuliah di dalam KRS.
 * `dropped` = batal-tambah resmi dari KRS yang sudah disetujui/dikunci,
 * `cancelled` = dibatalkan sewaktu KRS masih berstatus draf. Keduanya disimpan
 * sebagai jejak audit dan tidak lagi dihitung sebagai beban studi.
 */
export type EnrollmentItemStatus = 'enrolled' | 'dropped' | 'cancelled' | 'approved' | 'rejected'

export interface StudentEnrollment {
  id: number
  student_id: number
  semester_id: number
  status: EnrollmentStatus
  total_credits: number
  /**
   * Kuota SKS yang benar-benar berlaku untuk mahasiswa ini: hasil jenjang IPS
   * (`credit_limits.rules`) atau kuota yang disesuaikan Bagian Akademik.
   */
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
  /** Jumlah mata kuliah yang masih aktif (baris batal-tambah tidak dihitung). */
  items_count?: number
  /** Jumlah mata kuliah yang sudah dibatalkan/di-drop. */
  dropped_items_count?: number
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

/**
 * A class offered in the KRS catalog, annotated by the backend with whether the
 * student is actually allowed to take it (curriculum, capacity, schedule,
 * prerequisites, SKS limit, ...).
 */
export interface AvailableClass extends AcademicClass {
  is_eligible?: boolean
  eligibility_reasons?: string[]
  eligibility_reason?: string | null
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
