import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authService } from '@/services/api/auth'
import { tokenStorage } from '@/services/storage/token'
import type { ChangePasswordPayload, LoginPayload, Permission, Role, User } from '@/types/auth'
import type { ApiError } from '@/services/api/client'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const token = ref<string | null>(tokenStorage.get())
  const loading = ref<boolean>(false)
  const isInitialized = ref<boolean>(false)
  const error = ref<string | null>(null)

  const isAuthenticated = computed<boolean>(() => !!token.value && !!user.value)
  const roles = computed<Role[]>(() => user.value?.roles || [])

  const permissions = computed<Permission[]>(() => {
    if (!user.value?.permissions) return []
    return user.value.permissions.map((p, idx) => {
      if (typeof p === 'string') {
        return {
          id: idx + 1,
          name: p,
          display_name: p,
          group: 'general',
        }
      }
      return p
    })
  })

  const isSuperAdmin = computed<boolean>(() => roles.value.some((r: Role) => r.name === 'super_admin'))
  const isLecturer = computed<boolean>(() => roles.value.some((r: Role) => r.name === 'dosen'))
  const isStudent = computed<boolean>(() => roles.value.some((r: Role) => r.name === 'mahasiswa'))

  async function login(payload: LoginPayload): Promise<User> {
    loading.value = true
    error.value = null
    try {
      const response = await authService.login(payload)
      token.value = response.data.token
      user.value = response.data.user
      tokenStorage.set(response.data.token)
      return response.data.user
    } catch (err: unknown) {
      const apiErr = err as ApiError
      error.value = apiErr.message || 'Login gagal.'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchMe(): Promise<User | null> {
    if (!tokenStorage.has()) {
      user.value = null
      token.value = null
      isInitialized.value = true
      return null
    }

    loading.value = true
    try {
      const response = await authService.getMe()
      user.value = response.data
      return response.data
    } catch {
      logoutLocal()
      return null
    } finally {
      loading.value = false
      isInitialized.value = true
    }
  }

  async function logout(): Promise<void> {
    try {
      if (token.value) {
        await authService.logout()
      }
    } catch {
      // Ignore API logout error, clear local state regardless
    } finally {
      logoutLocal()
    }
  }

  function logoutLocal(): void {
    user.value = null
    token.value = null
    tokenStorage.remove()
    isInitialized.value = true
  }

  async function changePassword(payload: ChangePasswordPayload): Promise<void> {
    loading.value = true
    error.value = null
    try {
      await authService.changePassword(payload)
    } catch (err: unknown) {
      const apiErr = err as ApiError
      error.value = apiErr.message || 'Gagal mengubah kata sandi.'
      throw err
    } finally {
      loading.value = false
    }
  }

  return {
    user,
    token,
    loading,
    isInitialized,
    error,
    isAuthenticated,
    roles,
    permissions,
    isSuperAdmin,
    isLecturer,
    isStudent,
    login,
    fetchMe,
    logout,
    logoutLocal,
    changePassword,
  }
})
