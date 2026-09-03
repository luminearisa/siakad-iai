import type { AcademicClass } from './class'
import type { Room } from './room'

export type DayOfWeek =
  | 'monday'
  | 'tuesday'
  | 'wednesday'
  | 'thursday'
  | 'friday'
  | 'saturday'
  | 'sunday'

export type ScheduleStatus = 'active' | 'cancelled'

export interface ClassSchedule {
  id: number
  class_id: number
  room_id?: number | null
  day_of_week: DayOfWeek
  start_time: string
  end_time: string
  effective_from?: string | null
  effective_until?: string | null
  status: ScheduleStatus
  notes?: string | null
  room?: Room | null
  academic_class?: AcademicClass | null
  created_at: string
  updated_at?: string
}

export interface CreateSchedulePayload {
  class_id: number
  room_id?: number | null
  day_of_week: DayOfWeek
  start_time: string
  end_time: string
  effective_from?: string | null
  effective_until?: string | null
  status?: ScheduleStatus
  notes?: string | null
}

export interface UpdateSchedulePayload {
  class_id?: number
  room_id?: number | null
  day_of_week?: DayOfWeek
  start_time?: string
  end_time?: string
  effective_from?: string | null
  effective_until?: string | null
  status?: ScheduleStatus
  notes?: string | null
}

export interface ScheduleFilters {
  search?: string
  semester_id?: number | string | ''
  lecturer_id?: number | string | ''
  class_id?: number | string | ''
  room_id?: number | string | ''
  day_of_week?: DayOfWeek | ''
  status?: ScheduleStatus | ''
  sort?: string
  direction?: 'asc' | 'desc'
  page?: number
  per_page?: number
}
