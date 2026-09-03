<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { Plus, Trash2 } from 'lucide-vue-next'
import { courseService } from '@/services/api/courses'
import { useToast } from '@/composables/useToast'
import type { Course, CoursePrerequisiteItem } from '@/types/course'
import Modal from '@/components/ui/Modal.vue'
import Button from '@/components/ui/Button.vue'
import Select from '@/components/ui/Select.vue'
import Alert from '@/components/ui/Alert.vue'

interface Props {
  open: boolean
  course: Course
}

const props = defineProps<Props>()

const emit = defineEmits<{
  (e: 'update:open', val: boolean): void
  (e: 'success', updatedCourse: Course): void
}>()

const toast = useToast()
const allCourses = ref<Course[]>([])
const loading = ref<boolean>(false)
const errorMessage = ref<string | null>(null)
const prerequisites = ref<CoursePrerequisiteItem[]>([])

const gradeOptions = [
  { label: 'Minimal D (Lulus)', value: 'D' },
  { label: 'Minimal C (Standar)', value: 'C' },
  { label: 'Minimal B (Baik)', value: 'B' },
  { label: 'Minimal A (Sangat Baik)', value: 'A' },
]

async function loadAllCourses() {
  try {
    const res = await courseService.list({ per_page: 100 })
    allCourses.value = (res.data || []).filter(c => c.id !== props.course.id)
  } catch {
    allCourses.value = []
  }
}

watch(
  () => props.course,
  (c) => {
    if (c) {
      prerequisites.value = (c.prerequisites || []).map(p => ({
        course_id: p.id,
        minimum_grade: p.pivot?.minimum_grade || 'C',
      }))
      errorMessage.value = null
    }
  },
  { immediate: true }
)

function addRow() {
  const available = allCourses.value.find(c => !prerequisites.value.some(p => p.course_id === c.id))
  if (available) {
    prerequisites.value.push({
      course_id: available.id,
      minimum_grade: 'C',
    })
  } else if (allCourses.value.length > 0) {
    prerequisites.value.push({
      course_id: allCourses.value[0].id,
      minimum_grade: 'C',
    })
  }
}

function removeRow(idx: number) {
  prerequisites.value.splice(idx, 1)
}

async function handleSubmit() {
  errorMessage.value = null
  loading.value = true
  try {
    const res = await courseService.setPrerequisites(props.course.id, prerequisites.value)
    toast.success('Daftar prasyarat mata kuliah berhasil disimpan.')
    emit('success', res.data)
    emit('update:open', false)
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal menyimpan prasyarat mata kuliah.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadAllCourses()
})
</script>

<template>
  <Modal
    :open="open"
    title="Atur Prasyarat Mata Kuliah"
    size="lg"
    @update:open="emit('update:open', $event)"
  >
    <div class="space-y-4">
      <Alert v-if="errorMessage" variant="danger">
        {{ errorMessage }}
      </Alert>

      <div class="p-3 bg-slate-50 rounded-lg border border-slate-200 text-xs">
        <span class="text-slate-500 text-2xs block">Mata Kuliah Target:</span>
        <strong class="text-slate-900 font-mono">{{ course.code }}</strong>
        <span class="text-slate-700 ml-1.5 font-medium">— {{ course.name }}</span>
      </div>

      <!-- Prerequisite List Rows -->
      <div class="space-y-2.5">
        <div class="flex items-center justify-between">
          <label class="block text-xs font-semibold text-slate-700">
            Daftar Mata Kuliah Prasyarat
          </label>

          <Button
            type="button"
            variant="outline"
            size="xs"
            @click="addRow"
          >
            <Plus class="w-3 h-3" />
            <span>Tambah Prasyarat</span>
          </Button>
        </div>

        <div v-if="prerequisites.length === 0" class="p-4 border border-dashed border-slate-200 rounded-lg text-center text-xs text-slate-400">
          Belum ada prasyarat yang dipilih. Klik tombol "+ Tambah Prasyarat" untuk menambahkan.
        </div>

        <div v-else class="space-y-2 max-h-60 overflow-y-auto pr-1">
          <div
            v-for="(item, idx) in prerequisites"
            :key="idx"
            class="flex items-center gap-2 p-2.5 bg-slate-50 rounded-md border border-slate-200"
          >
            <!-- Course Select -->
            <div class="flex-1">
              <Select v-model="item.course_id" size="sm" required>
                <option
                  v-for="c in allCourses"
                  :key="c.id"
                  :value="c.id"
                >
                  {{ c.code }} — {{ c.name }} ({{ c.credits }} SKS)
                </option>
              </Select>
            </div>

            <!-- Min Grade Select -->
            <div class="w-40">
              <Select
                v-model="item.minimum_grade"
                :options="gradeOptions"
                size="sm"
              />
            </div>

            <!-- Delete Button -->
            <Button
              type="button"
              variant="ghost"
              size="xs"
              class="text-rose-600 hover:bg-rose-50 shrink-0"
              @click="removeRow(idx)"
            >
              <Trash2 class="w-3.5 h-3.5" />
            </Button>
          </div>
        </div>
      </div>
    </div>

    <template #footer>
      <Button variant="outline" size="sm" :disabled="loading" @click="emit('update:open', false)">
        Batal
      </Button>
      <Button variant="primary" size="sm" :loading="loading" @click="handleSubmit">
        Simpan Prasyarat
      </Button>
    </template>
  </Modal>
</template>
