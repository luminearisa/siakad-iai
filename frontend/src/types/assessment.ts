export interface AssessmentComponent {
  id: number
  academic_class_id?: number
  name: string
  code: string
  type: 'attendance' | 'assignment' | 'quiz' | 'midterm' | 'final' | 'practical' | 'other'
  max_score: number
  is_required: boolean
  sequence: number
  created_at?: string
  updated_at?: string
}

export interface AssessmentSchemeItem {
  id: number
  assessment_scheme_id: number
  assessment_component_id: number
  weight: number
  component?: AssessmentComponent
}

export interface AssessmentScheme {
  id: number
  academic_class_id: number
  name: string
  description?: string
  is_active: boolean
  items: AssessmentSchemeItem[]
  total_weight?: number
  created_at?: string
  updated_at?: string
}

export interface StudentGradeBreakdown {
  component_id: number
  component_name: string
  component_code: string
  weight: number
  raw_score: number
  weighted_score: number
  status: 'draft' | 'submitted' | 'finalized' | 'final' | 'revision_needed' | 'not_graded'
  graded_at?: string
  notes?: string
}

export interface StudentGradeItem {
  student: {
    id: number
    student_number: string
    full_name: string
    study_program?: string
  }
  final_score: number
  letter_grade: string
  grade_point: number
  is_complete: boolean
  components: StudentGradeBreakdown[]
}

export interface ClassGradeRecap {
  class: {
    id: number
    code: string
    name: string
    section: string
    course?: {
      id: number
      code: string
      name: string
      credits: number
    }
  }
  scheme?: AssessmentScheme
  components?: AssessmentComponent[]
  grades?: StudentGradeItem[]
  students?: StudentGradeItem[]
  summary?: {
    total_students: number
    average_score: number
    grade_distribution: Record<string, number>
  }
  statistics?: {
    total_students: number
    average_score: number
    grade_distribution: Record<string, number>
    passed_count: number
    failed_count: number
    incomplete_count: number
  }
}

export interface BatchGradeEntry {
  student_id: number
  assessment_component_id: number
  score: number
  notes?: string
}

export interface BatchGradePayload {
  grades: BatchGradeEntry[]
}
