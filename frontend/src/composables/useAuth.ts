import { useAuthStore } from '@/stores/auth'
import { storeToRefs } from 'pinia'

export function useAuth() {
  const authStore = useAuthStore()
  const { user, token, loading, isAuthenticated, roles, permissions, isSuperAdmin, isLecturer, isStudent } = storeToRefs(authStore)

  return {
    user,
    token,
    loading,
    isAuthenticated,
    roles,
    permissions,
    isSuperAdmin,
    isLecturer,
    isStudent,
    login: authStore.login,
    logout: authStore.logout,
    fetchMe: authStore.fetchMe,
    changePassword: authStore.changePassword,
  }
}
