export type QuestionType = 'yes_no' | 'scale'

export interface SurveyQuestion {
  id: number
  topic_id: number
  question: string
  question_type: QuestionType
  scale_min: number
  scale_max: number
  scale_min_label: string | null
  scale_max_label: string | null
  is_required: boolean
  order_number: number
  created_at?: string
  updated_at?: string
}

export interface SurveyTopic {
  id: number
  survey_template_id: number
  title: string
  description: string | null
  order_number: number
  questions?: SurveyQuestion[]
  questions_count?: number
  created_at?: string
  updated_at?: string
}

export interface AssignedCourse {
  id: number
  code: string
  name: string
  credits: number
  type?: string
  study_program?: {
    id: number
    name: string
    code: string
  } | null
}

export interface SurveyTemplate {
  id: number
  name: string
  description: string | null
  is_active: boolean
  topics_count?: number
  questions_count?: number
  courses_count?: number
  topics?: SurveyTopic[]
  courses?: AssignedCourse[]
  created_at?: string
  updated_at?: string
}

export interface CreateSurveyTemplatePayload {
  name: string
  description?: string | null
  is_active?: boolean
}

export interface CreateSurveyTopicPayload {
  title: string
  description?: string | null
  order_number?: number
}

export interface CreateSurveyQuestionPayload {
  question: string
  question_type: QuestionType
  scale_min?: number
  scale_max?: number
  scale_min_label?: string | null
  scale_max_label?: string | null
  is_required?: boolean
  order_number?: number
}
