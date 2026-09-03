import { defineStore } from 'pinia'
import { computed } from 'vue'
import { NAVIGATION_CONFIG, type NavigationItem, type NavigationSection } from '@/constants/navigation'
import { useAuthStore } from './auth'
import type { Permission, Role } from '@/types/auth'

export const useNavigationStore = defineStore('navigation', () => {
  const authStore = useAuthStore()

  function hasAccessToSection(section: NavigationSection): boolean {
    // If section specifies roles, user MUST have at least one of those roles
    if (section.roles && section.roles.length > 0) {
      const hasMatchingRole = section.roles.some((roleName: string) =>
        authStore.roles.some((r: Role) => r.name === roleName)
      )
      if (!hasMatchingRole) return false
    }

    return true
  }

  function hasAccessToItem(item: NavigationItem): boolean {
    // Check item roles if specified
    if (item.roles && item.roles.length > 0) {
      const hasMatchingRole = item.roles.some((roleName: string) =>
        authStore.roles.some((r: Role) => r.name === roleName)
      )
      if (!hasMatchingRole) return false
    }

    // Super admin bypasses permission check if they have role access to the section/item
    if (authStore.isSuperAdmin) return true

    // Check permission
    if (item.permission) {
      const hasPerm = authStore.permissions.some((p: Permission) => p.name === item.permission)
      if (!hasPerm) return false
    }

    return true
  }

  const authorizedNavigation = computed<NavigationSection[]>(() => {
    return NAVIGATION_CONFIG
      .filter(hasAccessToSection)
      .map(section => {
        const filteredItems = section.items.filter(hasAccessToItem)
        return {
          ...section,
          items: filteredItems,
        }
      })
      .filter(section => section.items.length > 0)
  })

  return {
    authorizedNavigation,
  }
})
