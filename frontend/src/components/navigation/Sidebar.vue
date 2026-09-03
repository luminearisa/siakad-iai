<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAppStore } from '@/stores/app'
import { useNavigationStore } from '@/stores/navigation'
import type { NavigationItem } from '@/constants/navigation'
import {
  LayoutDashboard,
  Building2,
  GraduationCap,
  Users,
  BookOpen,
  Layers,
  School,
  Calendar,
  CalendarDays,
  CalendarCheck,
  FileSpreadsheet,
  FileText,
  DoorOpen,
  UserCheck,
  User,
  UserX,
  ClipboardCheck,
  ChevronLeft,
  ChevronRight,
  ChevronDown,
  ShieldCheck,
  QrCode,
  Award,
  Database,
  BookMarked,
  Circle,
} from 'lucide-vue-next'

const route = useRoute()
const appStore = useAppStore()
const navigationStore = useNavigationStore()

const isCollapsed = computed(() => appStore.sidebarCollapsed)

const iconMap: Record<string, any> = {
  LayoutDashboard,
  Building2,
  GraduationCap,
  Users,
  BookOpen,
  Layers,
  School,
  Calendar,
  CalendarDays,
  CalendarCheck,
  FileSpreadsheet,
  FileText,
  DoorOpen,
  UserCheck,
  User,
  UserX,
  ClipboardCheck,
  ShieldCheck,
  QrCode,
  Award,
  Database,
  BookMarked,
  Circle,
}

function getIcon(name: string) {
  return iconMap[name] || LayoutDashboard
}

// Track expanded items by id
const expandedItems = ref<Record<string, boolean>>({
  'master-data-group': true,
  'periode-group': true,
})

function toggleExpand(id: string) {
  expandedItems.value[id] = !expandedItems.value[id]
}

function isItemActive(item: NavigationItem): boolean {
  if (item.to) {
    if (item.to === '/dashboard' && route.path === '/dashboard') return true
    if (item.to !== '/dashboard' && route.path.startsWith(item.to)) return true
  }
  if (item.children) {
    return item.children.some((child) => isItemActive(child))
  }
  return false
}

// Auto-expand parents if active route matches child
function checkAutoExpand() {
  for (const section of navigationStore.authorizedNavigation) {
    for (const item of section.items) {
      if (item.children && isItemActive(item)) {
        expandedItems.value[item.id] = true
        for (const sub of item.children) {
          if (sub.children && isItemActive(sub)) {
            expandedItems.value[sub.id] = true
          }
        }
      }
    }
  }
}

watch(() => route.path, () => {
  checkAutoExpand()
}, { immediate: true })
</script>

<template>
  <aside
    :class="[
      'bg-[#062317] text-emerald-100/80 border-r border-emerald-900/60 h-screen sticky top-0 flex flex-col transition-all duration-200 z-30 select-none shrink-0',
      isCollapsed ? 'w-16' : 'w-60',
    ]"
  >
    <!-- Brand / Header -->
    <div class="h-14 flex items-center justify-between px-3 border-b border-emerald-900/60 bg-[#041a11]">
      <router-link to="/dashboard" class="flex items-center gap-2.5 overflow-hidden">
        <div class="w-8 h-8 rounded-lg bg-white/10 p-0.5 border border-emerald-500/20 flex items-center justify-center shrink-0 shadow-xs">
          <img
            src="/images/logo.webp"
            alt="Logo IAI Al-Irsyad"
            class="w-full h-full object-contain"
          />
        </div>
        <div v-if="!isCollapsed" class="flex flex-col min-w-0">
          <span class="font-bold text-xs tracking-tight text-white uppercase truncate">IAI AL IRSYAD</span>
          <span class="text-3xs text-emerald-400 font-medium tracking-wide truncate">SIAKAD TERPADU</span>
        </div>
      </router-link>

      <button
        v-if="!isCollapsed"
        type="button"
        class="hidden lg:flex p-1 rounded-xs text-emerald-400/80 hover:text-white hover:bg-emerald-900/50 transition-colors cursor-pointer"
        title="Kecilkan Sidebar"
        @click="appStore.toggleSidebar"
      >
        <ChevronLeft class="w-4 h-4" />
      </button>
    </div>

    <!-- Navigation Items -->
    <div class="flex-1 overflow-y-auto py-3 px-2 space-y-4 custom-scrollbar">
      <div v-for="(section, idx) in navigationStore.authorizedNavigation" :key="idx" class="space-y-1">
        <div
          v-if="section.title && !isCollapsed"
          class="px-2.5 py-1 text-3xs font-semibold uppercase tracking-wider text-emerald-400/60"
        >
          {{ section.title }}
        </div>
        <div v-else-if="section.title && isCollapsed" class="h-2" />

        <ul class="space-y-0.5">
          <li v-for="item in section.items" :key="item.id">
            <!-- If Collapsed: Simple link or icon -->
            <template v-if="isCollapsed">
              <router-link
                v-if="item.to"
                :to="item.to"
                :title="item.label"
                :class="[
                  'flex items-center justify-center w-11 h-9 rounded-md transition-colors text-xs font-medium',
                  isItemActive(item)
                    ? 'bg-brand-600 text-white shadow-xs'
                    : 'text-emerald-200/70 hover:text-white hover:bg-emerald-900/50',
                ]"
              >
                <component :is="getIcon(item.icon)" class="w-4 h-4 shrink-0" />
              </router-link>
              <div
                v-else
                :title="item.label"
                class="flex items-center justify-center w-11 h-9 rounded-md text-emerald-200/70 hover:text-white hover:bg-emerald-900/50 cursor-pointer"
                @click="appStore.toggleSidebar"
              >
                <component :is="getIcon(item.icon)" class="w-4 h-4 shrink-0" />
              </div>
            </template>

            <!-- If Expanded: Full tree support -->
            <template v-else>
              <!-- 1. Direct Item (No Children) -->
              <router-link
                v-if="item.to && !item.children"
                :to="item.to"
                :class="[
                  'flex items-center justify-between px-2.5 py-2 rounded-md transition-all text-xs font-medium group',
                  isItemActive(item)
                    ? 'bg-brand-600 text-white font-semibold shadow-xs border-l-2 border-gold-400 pl-2'
                    : 'text-emerald-100/70 hover:text-white hover:bg-emerald-900/50',
                ]"
              >
                <div class="flex items-center gap-2.5 truncate">
                  <component
                    :is="getIcon(item.icon)"
                    :class="[
                      'w-4 h-4 shrink-0 transition-colors',
                      isItemActive(item) ? 'text-white' : 'text-emerald-300/70 group-hover:text-emerald-200'
                    ]"
                  />
                  <span class="truncate">{{ item.label }}</span>
                </div>
                <span
                  v-if="item.badge"
                  class="px-1.5 py-0.2 text-3xs font-semibold rounded-full bg-emerald-800 text-emerald-100 border border-emerald-600/40"
                >
                  {{ item.badge }}
                </span>
              </router-link>

              <!-- 2. Group Item (Has Children) -->
              <div v-else-if="item.children" class="space-y-0.5">
                <button
                  type="button"
                  :class="[
                    'w-full flex items-center justify-between px-2.5 py-2 rounded-md transition-all text-xs font-medium group cursor-pointer text-left',
                    isItemActive(item)
                      ? 'text-white font-semibold bg-emerald-900/40'
                      : 'text-emerald-100/70 hover:text-white hover:bg-emerald-900/50',
                  ]"
                  @click="toggleExpand(item.id)"
                >
                  <div class="flex items-center gap-2.5 truncate">
                    <component
                      :is="getIcon(item.icon)"
                      :class="[
                        'w-4 h-4 shrink-0 transition-colors',
                        isItemActive(item) ? 'text-gold-400' : 'text-emerald-300/70 group-hover:text-emerald-200'
                      ]"
                    />
                    <span class="truncate">{{ item.label }}</span>
                  </div>
                  <ChevronDown
                    :class="[
                      'w-3.5 h-3.5 transition-transform duration-200 text-emerald-400/80',
                      expandedItems[item.id] ? 'rotate-180' : 'rotate-0',
                    ]"
                  />
                </button>

                <!-- Submenu Level 1 -->
                <ul v-show="expandedItems[item.id]" class="pl-3.5 space-y-0.5 border-l border-emerald-800/40 ml-4 py-0.5">
                  <li v-for="sub in item.children" :key="sub.id">
                    <!-- Sub-Group (Level 2 Nested e.g. Periode) -->
                    <div v-if="sub.children" class="space-y-0.5">
                      <button
                        type="button"
                        :class="[
                          'w-full flex items-center justify-between px-2 py-1.5 rounded-md transition-all text-xs font-medium group cursor-pointer text-left',
                          isItemActive(sub)
                            ? 'text-white font-semibold'
                            : 'text-emerald-200/70 hover:text-white hover:bg-emerald-900/40',
                        ]"
                        @click="toggleExpand(sub.id)"
                      >
                        <div class="flex items-center gap-2 truncate">
                          <span class="w-1.5 h-1.5 rounded-full bg-emerald-400/60" />
                          <span class="truncate">{{ sub.label }}</span>
                        </div>
                        <ChevronDown
                          :class="[
                            'w-3 h-3 transition-transform duration-200 text-emerald-400/70',
                            expandedItems[sub.id] ? 'rotate-180' : 'rotate-0',
                          ]"
                        />
                      </button>

                      <!-- Submenu Level 2 (e.g. Tahun Ajaran, Periode Akademik) -->
                      <ul v-show="expandedItems[sub.id]" class="pl-3 space-y-0.5 border-l border-emerald-700/30 ml-3 py-0.5">
                        <li v-for="leaf in sub.children" :key="leaf.id">
                          <router-link
                            v-if="leaf.to"
                            :to="leaf.to"
                            :class="[
                              'flex items-center justify-between px-2 py-1.5 rounded-md transition-all text-xs font-medium group',
                              isItemActive(leaf)
                                ? 'bg-brand-600 text-white font-semibold shadow-2xs border-l-2 border-gold-400 pl-2'
                                : 'text-emerald-200/60 hover:text-white hover:bg-emerald-900/40',
                            ]"
                          >
                            <span class="truncate">{{ leaf.label }}</span>
                          </router-link>
                        </li>
                      </ul>
                    </div>

                    <!-- Direct Sub-Item -->
                    <router-link
                      v-else-if="sub.to"
                      :to="sub.to"
                      :class="[
                        'flex items-center justify-between px-2 py-1.5 rounded-md transition-all text-xs font-medium group',
                        isItemActive(sub)
                          ? 'bg-brand-600 text-white font-semibold shadow-2xs border-l-2 border-gold-400 pl-2'
                          : 'text-emerald-200/70 hover:text-white hover:bg-emerald-900/40',
                      ]"
                    >
                      <span class="truncate">{{ sub.label }}</span>
                    </router-link>
                  </li>
                </ul>
              </div>
            </template>
          </li>
        </ul>
      </div>
    </div>

    <!-- Bottom Expand button when collapsed -->
    <div v-if="isCollapsed" class="p-2 border-t border-emerald-900/60 hidden lg:flex justify-center bg-[#041a11]">
      <button
        type="button"
        class="p-1.5 rounded-md text-emerald-400 hover:text-white hover:bg-emerald-900/50 transition-colors cursor-pointer"
        title="Perbesar Sidebar"
        @click="appStore.toggleSidebar"
      >
        <ChevronRight class="w-4 h-4" />
      </button>
    </div>
  </aside>
</template>
