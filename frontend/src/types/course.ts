export type CourseType = 'theory' | 'practical' | 'mixed'
export type CourseStatus = 'active' | 'inactive'

export interface CourseTypeItem {
  id: number
  code: string
  name: string
  description?: string | null
  status?: string
  created_at?: string
  updated_at?: string
}

export interface CourseGroupItem {
  id: number
  code: string
  name: string
  description?: string | null
  status?: string
  created_at?: string
  updated_at?: string
}

export interface CoursePrerequisitePivot {
  minimum_grade?: string | null
}

export interface CoursePrerequisiteItem {
  course_id: number
  minimum_grade?: string | null
}

export interface Course {
  id: number
  study_program_id?: number | null
  study_program?: {
    id: number
    name: string
    code: string
    faculty?: {
      id: number
      name: string
    }
  } | null
  course_type_id?: number | null
  course_type?: CourseTypeItem | null
  course_group_id?: number | null
  course_group?: CourseGroupItem | null
  code: string
  name: string
  short_name?: string | null
  description?: string | null
  credits: number
  theory_credits: number
  practical_credits: number
  field_practical_credits?: number
  simulation_credits?: number
  seminar_credits?: number
  type: CourseType
  category?: string | null
  status: CourseStatus
  prerequisites?: Course[]
  dependents?: Course[]
  pivot?: CoursePrerequisitePivot
  created_at: string
  updated_at: string
}

export interface CourseCreatePayload {
  study_program_id?: number | null
  course_type_id?: number | null
  course_group_id?: number | null
  code: string
  name: string
  short_name?: string | null
  description?: string | null
  credits?: number
  theory_credits?: number
  practical_credits?: number
  field_practical_credits?: number
  simulation_credits?: number
  seminar_credits?: number
  type?: CourseType
  category?: string | null
  status?: CourseStatus
  prerequisites?: CoursePrerequisiteItem[]
}

export interface CourseUpdatePayload {
  study_program_id?: number | null
  course_type_id?: number | null
  course_group_id?: number | null
  code?: string
  name?: string
  short_name?: string | null
  description?: string | null
  credits?: number
  theory_credits?: number
  practical_credits?: number
  field_practical_credits?: number
  simulation_credits?: number
  seminar_credits?: number
  type?: CourseType
  category?: string | null
  status?: CourseStatus
}

export interface CourseFilters {
  search?: string
  study_program_id?: number | string | ''
  course_type_id?: number | string | ''
  course_group_id?: number | string | ''
  type?: CourseType | ''
  category?: string | ''
  status?: CourseStatus | ''
  credits?: number | string | ''
  sort?: string
  direction?: 'asc' | 'desc'
  page?: number
  per_page?: number
}
