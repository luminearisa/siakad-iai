import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useAppStore = defineStore('app', () => {
  // Sidebar state
  const sidebarCollapsed = ref<boolean>(false)
  const mobileSidebarOpen = ref<boolean>(false)

  // Current page context
  const pageTitle = ref<string>('SIAKAD')
  const pageSubtitle = ref<string>('')

  function toggleSidebar(): void {
    sidebarCollapsed.value = !sidebarCollapsed.value
  }

  function setSidebarCollapsed(val: boolean): void {
    sidebarCollapsed.value = val
  }

  function toggleMobileSidebar(): void {
    mobileSidebarOpen.value = !mobileSidebarOpen.value
  }

  function closeMobileSidebar(): void {
    mobileSidebarOpen.value = false
  }

  function setPageTitle(title: string, subtitle: string = ''): void {
    pageTitle.value = title
    pageSubtitle.value = subtitle
    document.title = `${title} — SIAKAD`
  }

  return {
    sidebarCollapsed,
    mobileSidebarOpen,
    pageTitle,
    pageSubtitle,
    toggleSidebar,
    setSidebarCollapsed,
    toggleMobileSidebar,
    closeMobileSidebar,
    setPageTitle,
  }
})
