<script setup lang="ts">
import { computed, useSlots } from 'vue'
import SearchSelect, { type SearchSelectOption } from './SearchSelect.vue'
import { readSlotOptions, type SelectOption } from './slotOptions'

export type { SelectOption }

interface Props {
  modelValue?: string | number | null
  options?: SelectOption[]
  placeholder?: string
  disabled?: boolean
  required?: boolean
  error?: boolean | string
  size?: 'sm' | 'md' | 'lg'
  id?: string
  name?: string
  /**
   * undefined (default) = otomatis: jadi searchable bila jumlah opsi melebihi `searchThreshold`.
   * true = selalu searchable. false = selalu pakai <select> bawaan.
   */
  searchable?: boolean
  /** Ambang jumlah opsi untuk otomatis beralih ke search select. */
  searchThreshold?: number
  searchPlaceholder?: string
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: '',
  options: () => [],
  placeholder: '',
  disabled: false,
  required: false,
  error: false,
  size: 'sm',
  searchThreshold: 8,
  searchPlaceholder: 'Cari data...',
})

const slots = useSlots()

const emit = defineEmits<{
  (e: 'update:modelValue', value: string | number | null): void
  (e: 'change', payload: unknown): void
}>()

const sizeClasses = computed(() => {
  switch (props.size) {
    case 'md':
      return 'text-sm px-3 py-2 rounded-md h-9'
    case 'lg':
      return 'text-base px-3.5 py-2.5 rounded-lg h-11'
    default:
      return 'text-xs px-2.5 py-1.5 rounded-md h-8'
  }
})

function handleChange(event: Event) {
  const target = event.target as HTMLSelectElement
  emit('update:modelValue', target.value)
  emit('change', event)
}

/* ------------------------------------------------------------------ *
 * Deteksi opsi dari slot
 *
 * Banyak halaman menulis opsi sebagai anak <option> (bukan lewat prop
 * `:options`). Slot dirender di scope induk, sehingga v-for pada <option>
 * sudah diekspansi menjadi satu vnode per baris. Vnode itulah yang dibaca
 * di sini agar daftar panjang ikut menjadi searchable tanpa perlu mengubah
 * setiap halaman satu per satu. Logika pembacaannya ada di `slotOptions.ts`.
 * ------------------------------------------------------------------ */

function readOptionsFromSlot(): SelectOption[] {
  try {
    // Dipanggil saat render (bukan di dalam computed) supaya isi slot tetap
    // segar ketika daftar data selesai dimuat dari API.
    return readSlotOptions(slots.default?.() as unknown[] | undefined)
  } catch {
    return []
  }
}

/** Opsi efektif: prop `:options` bila ada, jika tidak dibaca dari slot. */
function resolveOptions(): SelectOption[] {
  if (props.options.length > 0) return props.options
  return readOptionsFromSlot()
}

/**
 * Tentukan mode render. Dipanggil dari template (saat render) supaya isi slot
 * selalu segar ketika daftar data selesai dimuat.
 */
function useSearchMode(): boolean {
  if (props.searchable === false) return false
  const count = resolveOptions().length
  if (props.searchable === true) return count > 0
  return count > props.searchThreshold
}

function searchOptions(): SearchSelectOption[] {
  return resolveOptions().map((opt) => ({
    value: opt.value,
    label: opt.label,
    disabled: opt.disabled,
  }))
}
</script>

<template>
  <SearchSelect
    v-if="useSearchMode()"
    :model-value="modelValue"
    :options="searchOptions()"
    :placeholder="placeholder || 'Pilih data...'"
    :search-placeholder="searchPlaceholder"
    :disabled="disabled"
    :required="required"
    :error="error"
    :size="size"
    :id="id"
    :name="name"
    @update:model-value="emit('update:modelValue', $event)"
    @change="emit('change', $event)"
  />

  <div v-else class="relative w-full">
    <select
      :id="id"
      :name="name"
      :value="modelValue"
      :disabled="disabled"
      :required="required"
      :class="[
        'w-full bg-white text-slate-900 border transition-colors duration-150 outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 disabled:bg-slate-50 disabled:text-slate-500 disabled:cursor-not-allowed appearance-none pr-8 cursor-pointer',
        sizeClasses,
        error ? 'border-rose-500 focus:ring-rose-500 focus:border-rose-500 text-rose-900' : 'border-slate-300',
      ]"
      @change="handleChange"
    >
      <option v-if="placeholder && !slots.default" value="">
        {{ placeholder }}
      </option>
      <option
        v-for="opt in options"
        :key="String(opt.value)"
        :value="opt.value"
        :disabled="opt.disabled"
      >
        {{ opt.label }}
      </option>
      <slot />
    </select>
    <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none text-slate-400">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
      </svg>
    </div>
  </div>
</template>
