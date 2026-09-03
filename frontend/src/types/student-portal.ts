export interface StudentAcademicSummary {
  total_credits_passed: number
  cumulative_gpa: number
  current_semester: number
  max_credits_next: number
}

export interface StudentAdvisor {
  id: number
  lecturer_id: number
  name: string
  nidn: string
  email: string
  phone: string
  start_date: string
}

export interface StudentStudyProgram {
  id: number
  code: string
  name: string
  level?: string
  faculty?: {
    id: number
    name: string
  }
}

export interface StudentFamily {
  id: number
  student_id: number
  relationship: string
  full_name: string
  phone?: string
  occupation?: string
  address?: string
  notes?: string
}

export interface StudentEducation {
  id: number
  student_id: number
  institution_name: string
  level: string
  major?: string
  graduation_year: number
  certificate_number?: string
  notes?: string
}

export interface StudentFullProfile {
  id: number
  user_id: number
  student_number: string
  national_student_number: string
  full_name: string
  gender: string
  birth_place: string
  birth_date: string
  religion: string
  citizenship: string
  nik: string
  phone: string
  phone_number: string
  email: string
  address: string
  postal_code?: string
  status: string
  admission_year: number
  entry_date: string
  study_program: StudentStudyProgram
  advisor: StudentAdvisor | null
  academic_summary: StudentAcademicSummary
}

export interface StudentProfileData {
  student: StudentFullProfile
  families: StudentFamily[]
  educations: StudentEducation[]
}

export interface StudentKhsCourse {
  enrollment_item_id: number
  class_id: number
  class_code: string
  class_name: string
  section: string
  course_id: number
  course_code: string
  course_name: string
  credits: number
  final_score: number
  letter_grade: string
  grade_point: number
  quality_points: number
  is_passed: boolean
  status: string
  lecturers: string[]
  components: Array<{
    component_id: number
    component_name: string
    component_code: string
    component_type: string
    max_score: number
    weight: number
    raw_score: number
    weighted_score: number
    status: string
  }>
}

export interface StudentKhsSummary {
  semester_credits: number
  semester_quality_points: number
  semester_gpa: number
  cumulative_credits: number
  cumulative_gpa: number
  max_credits_next: number
}

export interface StudentKhsSemester {
  id: number
  name: string
  academic_year: string
  status?: string
}

export interface StudentKhsData {
  semesters: StudentKhsSemester[]
  selected_semester: StudentKhsSemester | null
  courses: StudentKhsCourse[]
  summary: StudentKhsSummary
}

export interface StudentScheduleItem {
  id: number
  class_id: number
  class_code: string
  class_name: string
  section: string
  course: {
    code: string
    name: string
    credits: number
  }
  lecturers: string[]
  room: {
    code: string
    name: string
    building?: string
  } | null
  day_of_week: number
  day_name: string
  start_time: string
  end_time: string
}
