export interface StudyProgramRef {
  id: number
  code: string
  name: string
  degree?: string
}

export interface GraduateProfile {
  id: number
  study_program_id?: number | null
  study_program?: StudyProgramRef | null
  code: string
  name: string
  profession?: string | null
  description?: string | null
  status?: string
  created_at?: string
  updated_at?: string
}

export interface LearningOutcome {
  id: number
  study_program_id?: number | null
  study_program?: StudyProgramRef | null
  code: string
  name: string
  category: 'sikap' | 'pengetahuan' | 'keterampilan_umum' | 'keterampilan_khusus'
  description?: string | null
  status?: string
  created_at?: string
  updated_at?: string
}

export interface CourseLearningOutcome {
  id: number
  study_program_id?: number | null
  study_program?: StudyProgramRef | null
  course_id?: number | null
  course?: {
    id: number
    code: string
    name: string
  } | null
  learning_outcome_id?: number | null
  learning_outcome?: LearningOutcome | null
  code: string
  name: string
  description?: string | null
  status?: string
  sub_outcomes?: SubCourseLearningOutcome[]
  created_at?: string
  updated_at?: string
}

export interface SubCourseLearningOutcome {
  id: number
  course_learning_outcome_id?: number | null
  course_outcome?: CourseLearningOutcome | null
  code: string
  name: string
  description?: string | null
  status?: string
  created_at?: string
  updated_at?: string
}

export interface AiSuggestionItem {
  code: string
  name: string
  profession?: string
  category?: 'sikap' | 'pengetahuan' | 'keterampilan_umum' | 'keterampilan_khusus'
  description?: string
}
