import { useAuthStore } from '@/stores/auth'
import type { Permission, Role } from '@/types/auth'

export function usePermissions() {
  const authStore = useAuthStore()

  function can(permission: string): boolean {
    if (authStore.isSuperAdmin) return true
    return authStore.permissions.some((p: Permission) => p.name === permission)
  }

  function hasRole(role: string): boolean {
    return authStore.roles.some((r: Role) => r.name === role)
  }

  function hasAnyPermission(permissions: string[]): boolean {
    if (authStore.isSuperAdmin) return true
    return permissions.some((p: string) => can(p))
  }

  function hasAllPermissions(permissions: string[]): boolean {
    if (authStore.isSuperAdmin) return true
    return permissions.every((p: string) => can(p))
  }

  function hasAnyRole(roles: string[]): boolean {
    return roles.some((r: string) => hasRole(r))
  }

  return {
    can,
    hasRole,
    hasAnyPermission,
    hasAllPermissions,
    hasAnyRole,
  }
}
