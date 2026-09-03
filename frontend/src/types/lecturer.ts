import type { StudyProgram } from './academic'
import type { User } from './auth'
import type { GenderType } from './student'

export type LecturerStatus = 'active' | 'inactive' | 'retired' | 'resigned' | 'deceased'

export interface LecturerEducation {
  id: number
  lecturer_id: number
  degree: string
  institution_name: string
  major?: string | null
  graduation_year?: number | null
  created_at?: string
  updated_at?: string
}

export interface LecturerExpertise {
  id: number
  lecturer_id: number
  name: string
  description?: string | null
  created_at?: string
  updated_at?: string
}

export interface Lecturer {
  id: number
  user_id?: number | null
  homebase_study_program_id?: number | null
  lecturer_number?: string | null
  nidn?: string | null
  nidk?: string | null
  nip?: string | null
  full_name: string
  gender: GenderType
  birth_place?: string | null
  birth_date?: string | null
  academic_degree?: string | null
  functional_position?: string | null
  academic_advising_quota?: number
  thesis_supervisor_quota?: number
  thesis_examiner_quota?: number
  active_advising_count?: number
  phone?: string | null
  email?: string | null
  address?: string | null
  status: LecturerStatus
  join_date?: string | null
  photo_path?: string | null
  notes?: string | null
  homebase_study_program?: StudyProgram | null
  user?: User | null
  educations?: LecturerEducation[]
  expertises?: LecturerExpertise[]
  created_at: string
  updated_at: string
}

export interface LecturerEducationPayload {
  degree: string
  institution_name: string
  major?: string | null
  graduation_year?: number | null
}

export interface LecturerExpertisePayload {
  name: string
  description?: string | null
}

export interface LecturerCreatePayload {
  homebase_study_program_id?: number | null
  lecturer_number?: string | null
  nidn?: string | null
  nidk?: string | null
  nip?: string | null
  full_name: string
  gender: GenderType
  birth_place?: string | null
  birth_date?: string | null
  academic_degree?: string | null
  functional_position?: string | null
  phone?: string | null
  email?: string | null
  address?: string | null
  status?: LecturerStatus | null
  join_date?: string | null
  photo_path?: string | null
  notes?: string | null
  educations?: LecturerEducationPayload[]
  expertises?: LecturerExpertisePayload[]
}

export interface LecturerUpdatePayload {
  homebase_study_program_id?: number | null
  lecturer_number?: string | null
  nidn?: string | null
  nidk?: string | null
  nip?: string | null
  full_name?: string
  gender?: GenderType
  birth_place?: string | null
  birth_date?: string | null
  academic_degree?: string | null
  functional_position?: string | null
  phone?: string | null
  email?: string | null
  address?: string | null
  status?: LecturerStatus | null
  join_date?: string | null
  photo_path?: string | null
  notes?: string | null
}

export interface LecturerChangeStatusPayload {
  status: LecturerStatus
  notes?: string
}

export interface LecturerFilters {
  search?: string
  status?: LecturerStatus | ''
  homebase_study_program_id?: number | string | ''
  gender?: GenderType | ''
  functional_position?: string
  sort?: string
  direction?: 'asc' | 'desc'
  page?: number
  per_page?: number
}
