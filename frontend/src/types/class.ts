import type { Semester, StudyProgram } from './academic'
import type { Course } from './course'
import type { Lecturer } from './lecturer'

export type ClassStatus = 'draft' | 'open' | 'closed' | 'cancelled' | 'completed'
export type ClassLecturerRole = 'primary' | 'assistant' | 'co_lecturer'

export interface ClassLecturer {
  id: number
  class_id: number
  lecturer_id: number
  role: ClassLecturerRole
  lecturer?: Lecturer | null
  created_at?: string
  updated_at?: string
}

export interface AcademicClass {
  id: number
  semester_id: number
  course_id: number
  study_program_id?: number | null
  code: string
  name?: string | null
  section: string
  capacity: number
  enrolled_count?: number
  status: ClassStatus
  notes?: string | null
  course?: Course | null
  semester?: Semester | null
  study_program?: StudyProgram | null
  lecturers?: Lecturer[]
  class_lecturers?: ClassLecturer[]
  schedules?: any[]
  created_at: string
  updated_at: string
}

export interface ClassLecturerPayload {
  lecturer_id: number
  role?: ClassLecturerRole
}

export interface ClassCreatePayload {
  semester_id: number
  course_id: number
  study_program_id?: number | null
  code: string
  name?: string | null
  section: string
  capacity: number
  status?: ClassStatus
  notes?: string | null
  lecturers?: ClassLecturerPayload[]
}

export interface ClassUpdatePayload {
  semester_id?: number
  course_id?: number
  study_program_id?: number | null
  code?: string
  name?: string | null
  section?: string
  capacity?: number
  status?: ClassStatus
  notes?: string | null
}

export interface AssignClassLecturerPayload {
  lecturer_id: number
  role?: ClassLecturerRole
}

export interface ClassFilters {
  search?: string
  semester_id?: number | string | ''
  academic_year_id?: number | string | ''
  course_id?: number | string | ''
  study_program_id?: number | string | ''
  lecturer_id?: number | string | ''
  status?: ClassStatus | ''
  sort?: string
  direction?: 'asc' | 'desc'
  page?: number
  per_page?: number
}
