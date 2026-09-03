import type { Institution } from './academic'

export type RoomStatus = 'active' | 'maintenance' | 'inactive'

export interface Campus {
  id: number
  code: string
  name: string
  address?: string | null
  phone?: string | null
  status: 'active' | 'inactive'
  buildings_count?: number
  created_at?: string
  updated_at?: string
}

export interface Building {
  id: number
  campus_id?: number | null
  campus?: Campus | null
  code: string
  name: string
  address?: string | null
  total_floors?: number | null
  total_rooms?: number | null
  status: 'active' | 'inactive'
  rooms_count?: number
  created_at?: string
  updated_at?: string
}

export interface Room {
  id: number
  institution_id?: number | null
  building_id?: number | null
  building_model?: Building | null
  code: string
  name: string
  building?: string | null
  floor?: number | null
  capacity: number
  location?: string | null
  room_type?: string | null
  status: RoomStatus
  institution?: Institution | null
  created_at?: string
  updated_at?: string
}

export interface RoomCreatePayload {
  institution_id?: number | null
  building_id?: number | null
  code: string
  name: string
  building?: string | null
  floor?: number | null
  capacity?: number
  location?: string | null
  room_type?: string | null
  status?: RoomStatus
}

export interface RoomUpdatePayload {
  institution_id?: number | null
  building_id?: number | null
  code?: string
  name?: string
  building?: string | null
  floor?: number | null
  capacity?: number
  location?: string | null
  room_type?: string | null
  status?: RoomStatus
}

export interface RoomFilters {
  search?: string
  building_id?: number | string | ''
  building?: string
  floor?: number | string | ''
  room_type?: string | ''
  status?: RoomStatus | ''
  sort?: string
  direction?: 'asc' | 'desc'
  page?: number
  per_page?: number
}
