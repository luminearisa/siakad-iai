<script setup lang="ts">
import { watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAppStore } from '@/stores/app'
import Sidebar from '@/components/navigation/Sidebar.vue'
import Header from '@/components/navigation/Header.vue'
import ToastContainer from '@/components/feedback/ToastContainer.vue'
import Drawer from '@/components/ui/Drawer.vue'

const route = useRoute()
const appStore = useAppStore()

// Auto close mobile drawer on route change
watch(() => route.path, () => {
  appStore.closeMobileSidebar()
})
</script>

<template>
  <div class="min-h-screen bg-slate-50 flex">
    <!-- Desktop & Tablet Sidebar -->
    <div class="hidden lg:block shrink-0">
      <Sidebar />
    </div>

    <!-- Mobile Drawer for Sidebar -->
    <Drawer
      :open="appStore.mobileSidebarOpen"
      position="left"
      width="w-64"
      @update:open="appStore.mobileSidebarOpen = $event"
    >
      <template #header>
        <div class="flex items-center gap-2">
          <div class="w-7 h-7 rounded-lg bg-white p-0.5 border border-emerald-100 flex items-center justify-center shadow-xs">
            <img
              src="/images/logo.webp"
              alt="Logo IAI Al-Irsyad"
              class="w-full h-full object-contain"
            />
          </div>
          <span class="font-bold text-xs text-slate-900 tracking-tight">IAI AL IRSYAD</span>
        </div>
      </template>

      <Sidebar class="border-r-0 !h-auto !static !w-full bg-[#062317] rounded-lg text-slate-300" />
    </Drawer>

    <!-- Main Shell -->
    <div class="flex-1 flex flex-col min-w-0">
      <Header />

      <main class="flex-1">
        <router-view />
      </main>

      <footer class="py-3 px-6 border-t border-slate-200 bg-white text-2xs text-slate-500 text-center sm:text-left flex flex-col sm:flex-row items-center justify-between gap-2">
        <span>&copy; 2026 Institut Agama Islam Al-Irsyad Jakarta — Sistem Informasi Akademik Terpadu (SIAKAD)</span>
        <span class="text-slate-400">Versi 1.0.0</span>
      </footer>
    </div>

    <!-- Centralized Toast Notifications -->
    <ToastContainer />
  </div>
</template>
