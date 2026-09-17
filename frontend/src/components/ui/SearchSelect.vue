<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'
import { Search, ChevronDown, Check, X, Loader2 } from 'lucide-vue-next'

export interface SearchSelectOption {
  value: any
  label: string
  description?: string
  badge?: string
  disabled?: boolean
}

interface Props {
  modelValue?: any
  options?: SearchSelectOption[]
  placeholder?: string
  searchPlaceholder?: string
  disabled?: boolean
  required?: boolean
  error?: boolean | string
  size?: 'sm' | 'md' | 'lg'
  id?: string
  name?: string
  clearable?: boolean
  /** true = selalu tampilkan kotak cari, false = tidak pernah, undefined = otomatis dari jumlah opsi */
  searchable?: boolean
  /** Ambang jumlah opsi untuk memunculkan kotak cari saat `searchable` tidak diisi */
  searchThreshold?: number
  emptyText?: string
  loading?: boolean
  /** Target Teleport untuk panel dropdown. `false` = render inline (tanpa teleport). */
  teleport?: boolean | string
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: null,
  options: () => [],
  placeholder: 'Pilih data...',
  searchPlaceholder: 'Cari...',
  disabled: false,
  required: false,
  error: false,
  size: 'sm',
  clearable: true,
  searchThreshold: 8,
  emptyText: 'Data tidak ditemukan',
  loading: false,
  teleport: 'body',
})

const teleportDisabled = computed(() => props.teleport === false)
const teleportTarget = computed(() => (typeof props.teleport === 'string' ? props.teleport : 'body'))

const emit = defineEmits<{
  (e: 'update:modelValue', value: any): void
  (e: 'change', option: SearchSelectOption | null): void
  (e: 'open'): void
  (e: 'close'): void
}>()

const isOpen = ref(false)
const searchQuery = ref('')
const activeIndex = ref(-1)

const triggerRef = ref<HTMLDivElement | null>(null)
const dropdownRef = ref<HTMLDivElement | null>(null)
const searchInputRef = ref<HTMLInputElement | null>(null)
const listRef = ref<HTMLDivElement | null>(null)

const dropdownStyle = ref<Record<string, string>>({})

/**
 * Bandingkan value secara toleran: id numerik dari API sering bertemu string
 * dari form/URL, jadi "3" dan 3 harus dianggap sama. null dan '' tetap berbeda.
 */
function sameValue(a: any, b: any): boolean {
  if (a === b) return true
  if (a === null || a === undefined || b === null || b === undefined) return false
  if (a === '' || b === '') return false
  return String(a) === String(b)
}

const sizeClasses = computed(() => {
  switch (props.size) {
    case 'md':
      return 'min-h-9 px-3 py-2 text-sm rounded-lg'
    case 'lg':
      return 'min-h-11 px-3.5 py-2.5 text-base rounded-lg'
    default:
      return 'min-h-8 px-2.5 py-1.5 text-xs rounded-md'
  }
})

const hasError = computed(() => !!props.error)

const showSearch = computed(() => {
  if (props.searchable !== undefined) return props.searchable
  return props.options.length > props.searchThreshold
})

const selectedOption = computed<SearchSelectOption | null>(() => {
  return props.options.find((opt) => sameValue(opt.value, props.modelValue)) ?? null
})

const filteredOptions = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return props.options
  return props.options.filter(
    (opt) =>
      opt.label.toLowerCase().includes(q) ||
      (opt.description ? opt.description.toLowerCase().includes(q) : false) ||
      String(opt.value).toLowerCase().includes(q),
  )
})

/** Indeks opsi pertama yang bisa dipilih (untuk navigasi keyboard). */
function firstSelectableIndex(from = 0): number {
  const list = filteredOptions.value
  for (let i = from; i < list.length; i++) {
    if (!list[i].disabled) return i
  }
  return -1
}

function updatePosition() {
  const el = triggerRef.value
  if (!el) return
  const rect = el.getBoundingClientRect()
  const width = Math.max(rect.width, 240)
  const maxLeft = Math.max(8, window.innerWidth - width - 8)
  const spaceBelow = window.innerHeight - rect.bottom
  const openUp = spaceBelow < 300 && rect.top > spaceBelow

  dropdownStyle.value = {
    position: 'fixed',
    left: `${Math.min(Math.max(rect.left, 8), maxLeft)}px`,
    width: `${width}px`,
    zIndex: '120',
    ...(openUp
      ? { bottom: `${window.innerHeight - rect.top + 4}px` }
      : { top: `${rect.bottom + 4}px` }),
  }
}

function scrollActiveIntoView() {
  nextTick(() => {
    const container = listRef.value
    if (!container) return
    const node = container.querySelector<HTMLElement>(`[data-option-index="${activeIndex.value}"]`)
    // scrollIntoView tidak tersedia di semua environment (mis. jsdom saat test).
    node?.scrollIntoView?.({ block: 'nearest' })
  })
}

function openDropdown() {
  if (props.disabled) return
  updatePosition()
  isOpen.value = true
  searchQuery.value = ''
  const current = filteredOptions.value.findIndex((opt) => sameValue(opt.value, props.modelValue))
  activeIndex.value = current >= 0 && !filteredOptions.value[current].disabled ? current : firstSelectableIndex()
  emit('open')
  nextTick(() => {
    if (showSearch.value) searchInputRef.value?.focus()
    scrollActiveIntoView()
  })
}

function closeDropdown() {
  if (!isOpen.value) return
  isOpen.value = false
  searchQuery.value = ''
  activeIndex.value = -1
  emit('close')
}

function toggleDropdown() {
  if (isOpen.value) closeDropdown()
  else openDropdown()
}

function selectOption(option: SearchSelectOption) {
  if (option.disabled) return
  emit('update:modelValue', option.value)
  emit('change', option)
  closeDropdown()
  nextTick(() => triggerRef.value?.focus())
}

function handleClear(event: Event) {
  event.stopPropagation()
  emit('update:modelValue', null)
  emit('change', null)
  searchQuery.value = ''
  nextTick(() => triggerRef.value?.focus())
}

function moveActive(step: number) {
  const list = filteredOptions.value
  if (list.length === 0) return
  let i = activeIndex.value
  for (let guard = 0; guard < list.length; guard++) {
    i += step
    if (i < 0) i = list.length - 1
    if (i >= list.length) i = 0
    if (!list[i].disabled) break
  }
  activeIndex.value = i
  scrollActiveIntoView()
}

function handleKeydown(event: KeyboardEvent) {
  if (props.disabled) return
  switch (event.key) {
    case 'ArrowDown':
      event.preventDefault()
      if (!isOpen.value) openDropdown()
      else moveActive(1)
      break
    case 'ArrowUp':
      event.preventDefault()
      if (!isOpen.value) openDropdown()
      else moveActive(-1)
      break
    case 'Home':
      if (isOpen.value) {
        event.preventDefault()
        activeIndex.value = firstSelectableIndex()
        scrollActiveIntoView()
      }
      break
    case 'End':
      if (isOpen.value) {
        event.preventDefault()
        const list = filteredOptions.value
        for (let i = list.length - 1; i >= 0; i--) {
          if (!list[i].disabled) {
            activeIndex.value = i
            break
          }
        }
        scrollActiveIntoView()
      }
      break
    case 'Enter':
      if (isOpen.value) {
        event.preventDefault()
        const option = filteredOptions.value[activeIndex.value]
        if (option) selectOption(option)
      } else {
        event.preventDefault()
        openDropdown()
      }
      break
    case 'Escape':
      if (isOpen.value) {
        event.preventDefault()
        closeDropdown()
      }
      break
    case 'Tab':
      if (isOpen.value) closeDropdown()
      break
    default:
      break
  }
}

function handleClickOutside(event: MouseEvent) {
  const target = event.target as Node
  if (triggerRef.value?.contains(target)) return
  if (dropdownRef.value?.contains(target)) return
  closeDropdown()
}

function handleReposition() {
  if (isOpen.value) updatePosition()
}

// Bila daftar opsi berubah saat terbuka (mis. data selesai dimuat), jaga posisi & indeks aktif.
watch(
  () => props.options.length,
  () => {
    if (!isOpen.value) return
    updatePosition()
    if (activeIndex.value >= filteredOptions.value.length) {
      activeIndex.value = firstSelectableIndex()
    }
  },
)

watch(searchQuery, () => {
  activeIndex.value = firstSelectableIndex()
})

onMounted(() => {
  document.addEventListener('mousedown', handleClickOutside)
  window.addEventListener('resize', handleReposition)
  window.addEventListener('scroll', handleReposition, true)
})

onUnmounted(() => {
  document.removeEventListener('mousedown', handleClickOutside)
  window.removeEventListener('resize', handleReposition)
  window.removeEventListener('scroll', handleReposition, true)
})
</script>

<template>
  <div class="relative w-full">
    <!-- Trigger -->
    <div
      ref="triggerRef"
      role="combobox"
      :aria-expanded="isOpen"
      :aria-controls="id ? `${id}-listbox` : undefined"
      :aria-required="required"
      :tabindex="disabled ? -1 : 0"
      :class="[
        'w-full bg-white text-slate-900 border flex items-center justify-between gap-2 transition-colors duration-150 outline-none cursor-pointer',
        sizeClasses,
        disabled ? 'opacity-60 bg-slate-50 cursor-not-allowed' : 'hover:border-slate-400',
        hasError ? 'border-rose-500' : 'border-slate-300',
        isOpen ? 'ring-2 ring-brand-500/20 border-brand-600' : '',
      ]"
      @click="toggleDropdown"
      @keydown="handleKeydown"
    >
      <div class="flex-1 min-w-0 truncate">
        <span v-if="selectedOption" class="font-medium text-slate-900">
          {{ selectedOption.label }}
          <span v-if="selectedOption.description" class="text-3xs text-slate-400 font-normal ml-1">
            ({{ selectedOption.description }})
          </span>
        </span>
        <span v-else class="text-slate-400">{{ placeholder }}</span>
      </div>

      <div class="flex items-center gap-1 shrink-0 text-slate-400">
        <Loader2 v-if="loading" class="w-3.5 h-3.5 animate-spin" />
        <button
          v-else-if="clearable && selectedOption && !disabled"
          type="button"
          tabindex="-1"
          class="p-0.5 hover:text-slate-600 hover:bg-slate-100 rounded cursor-pointer"
          title="Kosongkan"
          @click="handleClear"
        >
          <X class="w-3.5 h-3.5" />
        </button>
        <ChevronDown
          class="w-3.5 h-3.5 transition-transform duration-150"
          :class="{ 'rotate-180': isOpen }"
        />
      </div>
    </div>

    <!-- Dropdown -->
    <Teleport :to="teleportTarget" :disabled="teleportDisabled">
      <div
        v-if="isOpen"
        ref="dropdownRef"
        class="bg-white border border-slate-200 rounded-xl shadow-2xl overflow-hidden animate-fade-in"
        :style="dropdownStyle"
      >
        <div v-if="showSearch" class="p-2 border-b border-slate-100 bg-slate-50/60">
          <div class="relative">
            <Search class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2" />
            <input
              ref="searchInputRef"
              v-model="searchQuery"
              type="text"
              autocomplete="off"
              :placeholder="searchPlaceholder"
              class="w-full pl-8 pr-7 py-1.5 bg-white border border-slate-200 rounded-lg text-xs outline-none focus:border-brand-600 focus:ring-1 focus:ring-brand-500/20"
              @click.stop
              @keydown="handleKeydown"
            />
            <button
              v-if="searchQuery"
              type="button"
              class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer"
              @click.stop="searchQuery = ''"
            >
              <X class="w-3 h-3" />
            </button>
          </div>
        </div>

        <div
          :id="id ? `${id}-listbox` : undefined"
          ref="listRef"
          role="listbox"
          class="max-h-64 overflow-y-auto py-1"
        >
          <div v-if="filteredOptions.length === 0" class="py-5 text-center text-3xs text-slate-400">
            {{ emptyText }}
          </div>

          <div
            v-for="(opt, idx) in filteredOptions"
            :key="String(opt.value)"
            :data-option-index="idx"
            role="option"
            :aria-selected="sameValue(opt.value, modelValue)"
            :class="[
              'px-3 py-2 flex items-center justify-between gap-2 transition-colors',
              opt.disabled
                ? 'opacity-40 cursor-not-allowed'
                : idx === activeIndex
                  ? 'bg-brand-50 cursor-pointer'
                  : 'hover:bg-slate-50 cursor-pointer',
            ]"
            @click.stop="selectOption(opt)"
            @mousemove="!opt.disabled && (activeIndex = idx)"
          >
            <div class="flex flex-col min-w-0">
              <span class="text-xs text-slate-900 leading-snug truncate">{{ opt.label }}</span>
              <span v-if="opt.description" class="text-3xs text-slate-500 mt-0.5">
                {{ opt.description }}
              </span>
            </div>

            <span
              v-if="opt.badge"
              class="px-1.5 py-0.5 text-3xs font-semibold rounded-full bg-slate-100 text-slate-600 border border-slate-200 shrink-0"
            >
              {{ opt.badge }}
            </span>
            <Check
              v-if="sameValue(opt.value, modelValue)"
              class="w-3.5 h-3.5 text-brand-600 shrink-0"
            />
          </div>
        </div>

        <div
          v-if="options.length > 0"
          class="px-3 py-1.5 border-t border-slate-100 bg-slate-50/60 text-3xs text-slate-400 flex items-center justify-between"
        >
          <span>{{ filteredOptions.length }} dari {{ options.length }} data</span>
          <span v-if="showSearch">↑↓ navigasi · Enter pilih</span>
        </div>
      </div>
    </Teleport>
  </div>
</template>
