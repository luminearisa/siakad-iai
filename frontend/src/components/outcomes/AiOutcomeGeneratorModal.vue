<script setup lang="ts">
import { ref } from 'vue'
import { Sparkles, X, Check } from 'lucide-vue-next'
import { outcomeService } from '@/services/api/outcomes'
import type { AiSuggestionItem } from '@/types/outcomes'
import type { StudyProgram } from '@/types/academic'
import Button from '@/components/ui/Button.vue'

interface Props {
  open: boolean
  type: 'pl' | 'cpl' | 'cpmk' | 'sub_cpmk'
  studyPrograms?: StudyProgram[]
  initialProdiId?: number | null
}

const props = withDefaults(defineProps<Props>(), {
  open: false,
  type: 'pl',
  studyPrograms: () => [],
  initialProdiId: null,
})

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void
  (e: 'select', item: AiSuggestionItem): void
}>()

const selectedProdiId = ref<number | ''>(props.initialProdiId || '')
const topic = ref<string>('')
const loading = ref<boolean>(false)
const suggestions = ref<AiSuggestionItem[]>([])

async function handleGenerate() {
  loading.value = true
  try {
    const res = await outcomeService.generateAi({
      type: props.type,
      study_program_id: selectedProdiId.value ? Number(selectedProdiId.value) : undefined,
      topic: topic.value || undefined,
    })
    suggestions.value = res.data || []
  } catch (err) {
    console.error('Failed to generate with AI:', err)
  } finally {
    loading.value = false
  }
}

function handleSelect(item: AiSuggestionItem) {
  emit('select', item)
  emit('update:open', false)
}
</script>

<template>
  <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs" @click="emit('update:open', false)" />

    <!-- Modal Content -->
    <div class="relative w-full max-w-2xl bg-white rounded-2xl shadow-2xl border border-emerald-100 overflow-hidden flex flex-col max-h-[90vh] z-10 animate-scale-in">
      <!-- Modal Header -->
      <div class="px-6 py-4.5 bg-gradient-to-r from-[#041a11] via-[#062317] to-brand-800 text-white flex items-center justify-between">
        <div class="flex items-center gap-2.5">
          <div class="p-2 rounded-lg bg-gold-400/20 border border-gold-400/40 text-gold-300">
            <Sparkles class="w-4 h-4" />
          </div>
          <div>
            <h3 class="text-sm font-bold tracking-tight">AI Assistant OBE Generator</h3>
            <p class="text-3xs text-emerald-200/80">Rekomendasi rumusan taksonomi berstandar SN-Dikti & PTKI</p>
          </div>
        </div>
        <button
          type="button"
          class="p-1 rounded-md text-emerald-300 hover:text-white hover:bg-white/10 transition-colors"
          @click="emit('update:open', false)"
        >
          <X class="w-4 h-4" />
        </button>
      </div>

      <!-- Modal Body -->
      <div class="p-6 space-y-4 overflow-y-auto flex-1 custom-scrollbar">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 bg-emerald-50/50 p-3.5 rounded-xl border border-emerald-100">
          <div class="space-y-1">
            <label class="block text-3xs font-semibold uppercase text-emerald-900">Program Studi Target</label>
            <select
              v-model="selectedProdiId"
              class="w-full text-xs py-1.5 px-2.5 bg-white border border-emerald-200 rounded-lg text-slate-800 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none"
            >
              <option value="">-- Semua / Umum --</option>
              <option v-for="p in studyPrograms" :key="p.id" :value="p.id">
                {{ p.name }} ({{ p.degree || 'S1' }})
              </option>
            </select>
          </div>

          <div class="space-y-1">
            <label class="block text-3xs font-semibold uppercase text-emerald-900">Fokus Topik / Kata Kunci</label>
            <input
              v-model="topic"
              type="text"
              placeholder="Contoh: Fiqih, Multimedia, Metodologi"
              class="w-full text-xs py-1.5 px-2.5 bg-white border border-emerald-200 rounded-lg text-slate-800 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none"
            />
          </div>
        </div>

        <div class="flex justify-end">
          <Button
            type="button"
            variant="primary"
            size="sm"
            class="bg-brand-700 hover:bg-brand-800 text-white gap-1.5"
            :loading="loading"
            @click="handleGenerate"
          >
            <Sparkles class="w-3.5 h-3.5 text-gold-300" />
            Generate Rekomendasi Taksonomi
          </Button>
        </div>

        <!-- Generated Suggestions List -->
        <div v-if="suggestions.length > 0" class="space-y-3 pt-2">
          <div class="text-xs font-bold text-slate-900 flex items-center gap-1.5">
            <span>Pilihan Rumusan Cerdas (Klik untuk Menggunakan):</span>
          </div>

          <div class="grid grid-cols-1 gap-2.5">
            <div
              v-for="(item, idx) in suggestions"
              :key="idx"
              class="p-3.5 rounded-xl border border-slate-200 bg-white hover:border-brand-500 hover:shadow-xs transition-all cursor-pointer group space-y-1.5"
              @click="handleSelect(item)"
            >
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <span class="font-mono text-xs font-bold text-brand-700 bg-brand-50 px-2 py-0.5 rounded-md border border-brand-200/60">
                    {{ item.code }}
                  </span>
                  <span v-if="item.category" class="text-3xs uppercase font-semibold text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded">
                    {{ item.category }}
                  </span>
                </div>
                <span class="text-3xs text-brand-600 font-semibold group-hover:underline flex items-center gap-1">
                  Gunakan Rumusan Ini <Check class="w-3 h-3" />
                </span>
              </div>

              <div class="font-bold text-xs text-slate-900 group-hover:text-brand-800 transition-colors">
                {{ item.name }}
              </div>

              <p v-if="item.description" class="text-2xs text-slate-500 leading-relaxed">
                {{ item.description }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="px-6 py-3 bg-slate-50 border-t border-slate-100 flex items-center justify-end">
        <Button variant="secondary" size="sm" @click="emit('update:open', false)">
          Tutup
        </Button>
      </div>
    </div>
  </div>
</template>
