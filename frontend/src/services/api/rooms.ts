import { apiClient } from './client'
import type { ApiResponse } from '@/types/api'
import type {
  Campus,
  Building,
  Room,
  RoomCreatePayload,
  RoomUpdatePayload,
  RoomFilters,
} from '@/types/room'

export const roomService = {
  // 1. Campuses
  getCampuses(params?: { search?: string }): Promise<ApiResponse<Campus[]>> {
    return apiClient.get<Campus[]>('/campuses', params as Record<string, unknown>)
  },

  getCampus(id: number | string): Promise<ApiResponse<Campus>> {
    return apiClient.get<Campus>(`/campuses/${id}`)
  },

  createCampus(data: Partial<Campus>): Promise<ApiResponse<Campus>> {
    return apiClient.post<Campus>('/campuses', data)
  },

  updateCampus(id: number | string, data: Partial<Campus>): Promise<ApiResponse<Campus>> {
    return apiClient.put<Campus>(`/campuses/${id}`, data)
  },

  deleteCampus(id: number | string): Promise<ApiResponse<null>> {
    return apiClient.delete<null>(`/campuses/${id}`)
  },

  // 2. Buildings
  getBuildings(params?: { search?: string; campus_id?: number | string }): Promise<ApiResponse<Building[]>> {
    return apiClient.get<Building[]>('/buildings', params as Record<string, unknown>)
  },

  getBuilding(id: number | string): Promise<ApiResponse<Building>> {
    return apiClient.get<Building>(`/buildings/${id}`)
  },

  createBuilding(data: Partial<Building>): Promise<ApiResponse<Building>> {
    return apiClient.post<Building>('/buildings', data)
  },

  updateBuilding(id: number | string, data: Partial<Building>): Promise<ApiResponse<Building>> {
    return apiClient.put<Building>(`/buildings/${id}`, data)
  },

  deleteBuilding(id: number | string): Promise<ApiResponse<null>> {
    return apiClient.delete<null>(`/buildings/${id}`)
  },

  // 3. Rooms
  list(params?: RoomFilters): Promise<ApiResponse<Room[]>> {
    return apiClient.get<Room[]>('/rooms', params as Record<string, unknown>)
  },

  get(id: number | string): Promise<ApiResponse<Room>> {
    return apiClient.get<Room>(`/rooms/${id}`)
  },

  create(data: RoomCreatePayload): Promise<ApiResponse<Room>> {
    return apiClient.post<Room>('/rooms', data)
  },

  update(id: number | string, data: RoomUpdatePayload): Promise<ApiResponse<Room>> {
    return apiClient.put<Room>(`/rooms/${id}`, data)
  },

  delete(id: number | string): Promise<ApiResponse<null>> {
    return apiClient.delete<null>(`/rooms/${id}`)
  },

  changeStatus(id: number | string, status: string): Promise<ApiResponse<Room>> {
    return apiClient.patch<Room>(`/rooms/${id}/status`, { status })
  },
}
