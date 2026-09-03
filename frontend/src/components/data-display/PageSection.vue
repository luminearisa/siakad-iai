<script setup lang="ts">
interface Props {
  title?: string
  description?: string
}

defineProps<Props>()
</script>

<template>
  <div class="space-y-3">
    <div v-if="title || $slots.header || $slots.actions" class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
      <div>
        <h3 v-if="title" class="text-sm font-semibold text-slate-800 tracking-tight">
          {{ title }}
        </h3>
        <p v-if="description" class="text-2xs text-slate-500">
          {{ description }}
        </p>
      </div>

      <div v-if="$slots.actions" class="flex items-center gap-2">
        <slot name="actions" />
      </div>
    </div>

    <div>
      <slot />
    </div>
  </div>
</template>
