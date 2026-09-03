<script setup lang="ts">
import ToastContainer from '@/components/feedback/ToastContainer.vue'
</script>

<template>
  <div class="min-h-screen bg-slate-50 flex items-center justify-center p-4">
    <router-view />
    <ToastContainer />
  </div>
</template>
