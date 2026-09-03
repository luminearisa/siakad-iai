<script setup lang="ts">
interface Props {
  label?: string
  vertical?: boolean
}

withDefaults(defineProps<Props>(), {
  vertical: false,
})
</script>

<template>
  <div v-if="vertical" class="inline-block w-px h-full min-h-[1em] bg-slate-200 self-stretch mx-2" />
  <div v-else-if="label" class="relative flex py-2 items-center">
    <div class="flex-grow border-t border-slate-200" />
    <span class="flex-shrink mx-3 text-slate-400 text-2xs uppercase tracking-wider font-semibold">{{ label }}</span>
    <div class="flex-grow border-t border-slate-200" />
  </div>
  <hr v-else class="border-t border-slate-200 my-3" />
</template>
