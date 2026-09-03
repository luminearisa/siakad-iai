export type QuestionType = 'likert' | 'multiple_choice' | 'essay'

export interface CourseQuestionnaireQuestion {
  id: number
  topic_id: number
  question: string
  question_type: QuestionType
  scale_min: number
  scale_max: number
  scale_min_label: string | null
  scale_max_label: string | null
  options: string[] | null
  is_required: boolean
  order_number: number
  created_at?: string
  updated_at?: string
}

export interface CourseQuestionnaireTopic {
  id: number
  course_id: number
  title: string
  description: string | null
  order_number: number
  is_active: boolean
  questions: CourseQuestionnaireQuestion[]
  created_at?: string
  updated_at?: string
}
