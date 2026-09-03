import { apiClient } from './client'
import type { ApiResponse } from '@/types/api'
import type { ChangePasswordPayload, LoginPayload, LoginResponseData, User } from '@/types/auth'

export const authService = {
  login(payload: LoginPayload): Promise<ApiResponse<LoginResponseData>> {
    return apiClient.post<LoginResponseData>('/auth/login', payload)
  },

  logout(): Promise<ApiResponse<null>> {
    return apiClient.post<null>('/auth/logout')
  },

  getMe(): Promise<ApiResponse<User>> {
    return apiClient.get<User>('/auth/me')
  },

  changePassword(payload: ChangePasswordPayload): Promise<ApiResponse<null>> {
    return apiClient.post<null>('/auth/change-password', payload)
  }
}
