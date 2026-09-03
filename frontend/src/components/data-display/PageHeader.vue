<script setup lang="ts">
import Breadcrumb, { type BreadcrumbItem } from '../ui/Breadcrumb.vue'

interface Props {
  title: string
  subtitle?: string
  breadcrumbs?: BreadcrumbItem[]
}

defineProps<Props>()
</script>

<template>
  <div class="mb-5">
    <Breadcrumb v-if="breadcrumbs && breadcrumbs.length > 0" :items="breadcrumbs" />

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <h1 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight">
          {{ title }}
        </h1>
        <p v-if="subtitle" class="text-xs text-slate-500 mt-0.5 leading-normal">
          {{ subtitle }}
        </p>
      </div>

      <div v-if="$slots.actions" class="flex items-center gap-2 shrink-0">
        <slot name="actions" />
      </div>
    </div>
  </div>
</template>
