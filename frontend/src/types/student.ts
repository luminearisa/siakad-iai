import type { StudyProgram } from './academic'
import type { User } from './auth'

export type StudentStatus =
  | 'prospective'
  | 'active'
  | 'leave'
  | 'inactive'
  | 'graduated'
  | 'withdrawn'
  | 'dismissed'
  | 'deceased'

export type GenderType = 'male' | 'female'

export type FamilyRelationship = 'father' | 'mother' | 'guardian'

export interface StudentFamily {
  id: number
  student_id: number
  relationship: FamilyRelationship
  full_name: string
  phone?: string | null
  occupation?: string | null
  address?: string | null
  notes?: string | null
  created_at?: string
  updated_at?: string
}

export interface StudentEducation {
  id: number
  student_id: number
  institution_name: string
  level: string
  major?: string | null
  graduation_year?: number | null
  certificate_number?: string | null
  notes?: string | null
  created_at?: string
  updated_at?: string
}

export interface Student {
  id: number
  user_id?: number | null
  study_program_id: number
  student_number: string
  national_student_number?: string | null
  national_id?: string | null
  mother_name?: string | null
  full_name: string
  nickname?: string | null
  gender: GenderType
  birth_place?: string | null
  birth_date?: string | null
  religion?: string | null
  marital_status?: string | null
  phone?: string | null
  email?: string | null
  address?: string | null
  province?: string | null
  city?: string | null
  district?: string | null
  postal_code?: string | null
  status: StudentStatus
  admission_year?: number | null
  entry_date?: string | null
  graduation_date?: string | null
  photo_path?: string | null
  notes?: string | null
  study_program?: StudyProgram
  user?: User | null
  families?: StudentFamily[]
  educations?: StudentEducation[]
  created_at: string
  updated_at: string
}

export interface StudentFamilyPayload {
  relationship: FamilyRelationship
  full_name: string
  phone?: string | null
  occupation?: string | null
  address?: string | null
  notes?: string | null
}

export interface StudentEducationPayload {
  institution_name: string
  level: string
  major?: string | null
  graduation_year?: number | null
  certificate_number?: string | null
  notes?: string | null
}

export interface StudentCreatePayload {
  study_program_id: number
  student_number: string
  full_name: string
  gender: GenderType
  nickname?: string | null
  national_student_number?: string | null
  national_id?: string | null
  mother_name?: string | null
  birth_place?: string | null
  birth_date?: string | null
  religion?: string | null
  marital_status?: string | null
  phone?: string | null
  email?: string | null
  address?: string | null
  province?: string | null
  city?: string | null
  district?: string | null
  postal_code?: string | null
  status?: StudentStatus | null
  admission_year?: number | null
  entry_date?: string | null
  graduation_date?: string | null
  photo_path?: string | null
  notes?: string | null
  families?: StudentFamilyPayload[]
  educations?: StudentEducationPayload[]
}

export interface StudentUpdatePayload {
  study_program_id?: number
  student_number?: string
  full_name?: string
  gender?: GenderType
  nickname?: string | null
  national_student_number?: string | null
  national_id?: string | null
  mother_name?: string | null
  birth_place?: string | null
  birth_date?: string | null
  religion?: string | null
  marital_status?: string | null
  phone?: string | null
  email?: string | null
  address?: string | null
  province?: string | null
  city?: string | null
  district?: string | null
  postal_code?: string | null
  status?: StudentStatus | null
  admission_year?: number | null
  entry_date?: string | null
  graduation_date?: string | null
  photo_path?: string | null
  notes?: string | null
}

export interface StudentChangeStatusPayload {
  status: StudentStatus
  notes?: string
}

export interface StudentFilters {
  search?: string
  status?: StudentStatus | ''
  study_program_id?: number | string | ''
  gender?: GenderType | ''
  admission_year?: number | string | ''
  sort?: string
  direction?: 'asc' | 'desc'
  page?: number
  per_page?: number
}
