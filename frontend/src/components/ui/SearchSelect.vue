<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Search, ChevronDown, Check, X, Menu } from 'lucide-vue-next'

export interface SearchSelectOption {
  value: any
  label: string
  description?: string
  badge?: string
}

interface Props {
  modelValue: any
  options: SearchSelectOption[]
  placeholder?: string
  searchPlaceholder?: string
  disabled?: boolean
  required?: boolean
  showMenuIcon?: boolean
  clearable?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  placeholder: 'Pilih data...',
  searchPlaceholder: 'Cari opsi...',
  disabled: false,
  required: false,
  showMenuIcon: true,
  clearable: true,
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: any): void
  (e: 'change', option: SearchSelectOption | null): void
}>()

const isOpen = ref<boolean>(false)
const searchQuery = ref<string>('')
const containerRef = ref<HTMLDivElement | null>(null)
const searchInputRef = ref<HTMLInputElement | null>(null)

const selectedOption = computed(() => {
  return props.options.find((opt) => opt.value === props.modelValue) || null
})

const filteredOptions = computed(() => {
  if (!searchQuery.value.trim()) return props.options
  const q = searchQuery.value.toLowerCase()
  return props.options.filter(
    (opt) =>
      opt.label.toLowerCase().includes(q) ||
      (opt.description && opt.description.toLowerCase().includes(q))
  )
})

function toggleDropdown() {
  if (props.disabled) return
  isOpen.value = !isOpen.value
  if (isOpen.value) {
    searchQuery.value = ''
    setTimeout(() => {
      searchInputRef.value?.focus()
    }, 50)
  }
}

function selectOption(option: SearchSelectOption) {
  emit('update:modelValue', option.value)
  emit('change', option)
  isOpen.value = false
  searchQuery.value = ''
}

function handleClear(event: Event) {
  event.stopPropagation()
  emit('update:modelValue', null)
  emit('change', null)
}

function handleClickOutside(event: MouseEvent) {
  if (containerRef.value && !containerRef.value.contains(event.target as Node)) {
    isOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
  <div ref="containerRef" class="relative w-full text-xs">
    <!-- Trigger Button -->
    <div
      class="w-full min-h-[38px] px-3 py-2 bg-white border border-slate-300 rounded-lg flex items-center justify-between gap-2 cursor-pointer transition-all hover:border-slate-400"
      :class="{
        'ring-2 ring-brand-500/20 border-brand-600': isOpen,
        'opacity-60 bg-slate-50 cursor-not-allowed': disabled,
      }"
      @click="toggleDropdown"
    >
      <div class="flex-1 truncate">
        <span v-if="selectedOption" class="font-medium text-slate-900">
          {{ selectedOption.label }}
          <span v-if="selectedOption.description" class="text-3xs text-slate-400 font-normal ml-1">
            ({{ selectedOption.description }})
          </span>
        </span>
        <span v-else class="text-slate-400">
          {{ placeholder }}
        </span>
      </div>

      <div class="flex items-center gap-1.5 shrink-0 text-slate-400">
        <button
          v-if="clearable && selectedOption && !disabled"
          type="button"
          class="p-0.5 hover:text-slate-600 hover:bg-slate-100 rounded"
          @click="handleClear"
        >
          <X class="w-3.5 h-3.5" />
        </button>

        <Menu v-if="showMenuIcon" class="w-3.5 h-3.5 text-slate-500" />
        <ChevronDown v-else class="w-3.5 h-3.5 transition-transform" :class="{ 'rotate-180': isOpen }" />
      </div>
    </div>

    <!-- Dropdown Popover with Live Search -->
    <div
      v-if="isOpen"
      class="absolute z-[100] left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-2xl overflow-hidden animate-fade-in min-w-[280px]"
    >
      <!-- Search Input -->
      <div class="p-2 border-b border-slate-100 bg-slate-50/50">
        <div class="relative">
          <Search class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" />
          <input
            ref="searchInputRef"
            v-model="searchQuery"
            type="text"
            :placeholder="searchPlaceholder"
            class="w-full pl-8 pr-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs outline-none focus:border-brand-600 focus:ring-1 focus:ring-brand-500/20"
            @click.stop
          />
        </div>
      </div>

      <!-- Options List -->
      <div class="max-h-56 overflow-y-auto py-1 divide-y divide-slate-50">
        <div
          v-if="filteredOptions.length === 0"
          class="py-4 text-center text-3xs text-slate-400"
        >
          Tidak ada data ditemukan
        </div>

        <div
          v-for="opt in filteredOptions"
          :key="opt.value"
          class="px-3 py-2 hover:bg-rose-50/50 cursor-pointer flex items-center justify-between gap-2 transition-colors"
          :class="{ 'bg-rose-50 text-rose-800 font-semibold': opt.value === modelValue }"
          @click.stop="selectOption(opt)"
        >
          <div class="flex flex-col">
            <span class="text-xs text-slate-900 leading-snug">
              {{ opt.label }}
            </span>
            <span v-if="opt.description" class="text-3xs text-slate-500 mt-0.5 font-normal">
              {{ opt.description }}
            </span>
          </div>

          <Check
            v-if="opt.value === modelValue"
            class="w-3.5 h-3.5 text-rose-600 shrink-0"
          />
        </div>
      </div>
    </div>
  </div>
</template>
