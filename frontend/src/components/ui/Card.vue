<script setup lang="ts">
interface Props {
  noPadding?: boolean
  dense?: boolean
  overflowHidden?: boolean
}

withDefaults(defineProps<Props>(), {
  noPadding: false,
  dense: false,
  overflowHidden: false,
})
</script>

<template>
  <div
    class="bg-white border border-slate-200 rounded-lg shadow-subtle"
    :class="overflowHidden ? 'overflow-hidden' : 'overflow-visible'"
  >
    <div v-if="$slots.header" :class="['border-b border-slate-100 bg-slate-50/50 flex items-center justify-between rounded-t-lg', dense ? 'px-3 py-2' : 'px-4 py-3']">
      <slot name="header" />
    </div>

    <div :class="[noPadding ? '' : dense ? 'p-3' : 'p-4 sm:p-5']">
      <slot />
    </div>

    <div v-if="$slots.footer" :class="['border-t border-slate-100 bg-slate-50/50 flex items-center justify-end rounded-b-lg', dense ? 'px-3 py-2' : 'px-4 py-3']">
      <slot name="footer" />
    </div>
  </div>
</template>
