export interface Institution {
  id: number
  code: string
  name: string
  short_name?: string
  legal_name?: string
  address?: string
  phone?: string
  email?: string
  website?: string
  status?: string
  accreditation?: string
  created_at?: string
}

export interface Faculty {
  id: number
  institution_id?: number | null
  code: string
  name: string
  name_en?: string
  status?: string
  dean_name?: string
  institution?: Institution
  created_at?: string
}

export interface StudyProgramSetting {
  id?: number
  study_program_id?: number
  min_gpa_graduation: number | string
  min_final_exam_guidance: number
  final_exam_advisors_count: number
  final_exam_examiners_count: number
  is_thesis_required: boolean
}

export interface StudyProgram {
  id: number
  faculty_id?: number | null
  code: string
  short_name?: string
  name: string
  degree: string
  status?: string
  accreditation?: string
  head_name?: string
  faculty?: Faculty
  setting?: StudyProgramSetting | null
  created_at?: string
}

export interface StudyProgramPayload {
  faculty_id?: number | null
  code: string
  short_name?: string
  name: string
  degree: string
  status?: string
}

export interface StudyProgramFilters {
  search?: string
  faculty_id?: number | string | ''
  degree?: string | ''
  status?: string | ''
  page?: number
  per_page?: number
  [key: string]: string | number | boolean | undefined
}

export interface AcademicYear {
  id: number
  name: string
  start_date: string
  end_date: string
  status?: string
  semesters?: Semester[]
  created_at?: string
  updated_at?: string
}

export interface AcademicYearPayload {
  name: string
  start_date: string
  end_date: string
  status?: string
}

export interface Semester {
  id: number
  academic_year_id: number
  name: string
  type: 'ganjil' | 'genap' | 'pendek'
  start_date: string
  end_date: string
  krs_start_date?: string | null
  krs_end_date?: string | null
  kprs_start_date?: string | null
  kprs_end_date?: string | null
  lecture_start_date?: string
  lecture_end_date?: string
  uts_start_date?: string
  uts_end_date?: string
  uas_start_date?: string
  uas_end_date?: string
  min_attendance_uts_percentage?: number
  min_attendance_uas_percentage?: number
  total_teaching_weeks?: number
  status: string
  is_active?: boolean
  academic_year?: AcademicYear
  created_at?: string
  updated_at?: string
}

export interface SemesterPayload {
  academic_year_id: number
  name: string
  type: 'ganjil' | 'genap' | 'pendek'
  start_date: string
  end_date: string
  krs_start_date?: string | null
  krs_end_date?: string | null
  kprs_start_date?: string | null
  kprs_end_date?: string | null
  lecture_start_date?: string
  lecture_end_date?: string
  uts_start_date?: string
  uts_end_date?: string
  uas_start_date?: string
  uas_end_date?: string
  min_attendance_uts_percentage?: number
  min_attendance_uas_percentage?: number
  total_teaching_weeks?: number
  status?: string
}
