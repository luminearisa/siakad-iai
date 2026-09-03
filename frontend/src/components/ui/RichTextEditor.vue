<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import {
  Bold,
  Italic,
  Underline,
  Strikethrough,
  Subscript,
  Superscript,
  Heading1,
  Heading2,
  Quote,
  Code,
  List,
  ListOrdered,
  Indent,
  Outdent,
  ChevronDown,
} from 'lucide-vue-next'

interface Props {
  modelValue?: string
  placeholder?: string
  minHeight?: string
  required?: boolean
  disabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: '',
  placeholder: 'Tuliskan teks di sini...',
  minHeight: '120px',
  required: false,
  disabled: false,
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
}>()

const editorRef = ref<HTMLDivElement | null>(null)
const selectedFont = ref<string>('Sans Serif')
const selectedFormat = ref<string>('Normal')

function format(command: string, value: string | undefined = undefined) {
  if (props.disabled) return
  document.execCommand(command, false, value)
  handleInput()
  if (editorRef.value) {
    editorRef.value.focus()
  }
}

function handleFontChange(event: Event) {
  const target = event.target as HTMLSelectElement
  selectedFont.value = target.value
  format('fontName', target.value)
}

function handleFormatChange(event: Event) {
  const target = event.target as HTMLSelectElement
  selectedFormat.value = target.value
  if (target.value === 'Heading 1') {
    format('formatBlock', '<h1>')
  } else if (target.value === 'Heading 2') {
    format('formatBlock', '<h2>')
  } else if (target.value === 'Heading 3') {
    format('formatBlock', '<h3>')
  } else {
    format('formatBlock', '<p>')
  }
}

function handleInput() {
  if (editorRef.value) {
    const html = editorRef.value.innerHTML
    emit('update:modelValue', html === '<p><br></p>' ? '' : html)
  }
}

watch(
  () => props.modelValue,
  (newVal) => {
    if (editorRef.value && editorRef.value.innerHTML !== newVal) {
      editorRef.value.innerHTML = newVal || ''
    }
  }
)

onMounted(() => {
  if (editorRef.value) {
    editorRef.value.innerHTML = props.modelValue || ''
  }
})
</script>

<template>
  <div
    class="border border-slate-300 rounded-lg overflow-hidden transition-all bg-white"
    :class="{
      'focus-within:ring-2 focus-within:ring-brand-500/20 focus-within:border-brand-600': !disabled,
      'opacity-60 bg-slate-50 cursor-not-allowed': disabled,
    }"
  >
    <!-- Rich Text Toolbar (Matching Reference Screenshot) -->
    <div
      class="px-2 py-1.5 bg-slate-50/90 border-b border-slate-200 flex flex-wrap items-center gap-1 text-xs text-slate-700 select-none"
    >
      <!-- Font Family Dropdown -->
      <div class="relative flex items-center">
        <select
          v-model="selectedFont"
          :disabled="disabled"
          class="text-3xs py-1 pl-2 pr-5 bg-transparent border-0 font-medium text-slate-700 cursor-pointer outline-none appearance-none hover:bg-slate-200/50 rounded"
          @change="handleFontChange"
        >
          <option value="Sans Serif">Sans Serif</option>
          <option value="Serif">Serif</option>
          <option value="Monospace">Monospace</option>
        </select>
        <ChevronDown class="w-3 h-3 text-slate-400 absolute right-1 pointer-events-none" />
      </div>

      <div class="w-px h-4 bg-slate-300 mx-0.5" />

      <!-- Format Block Dropdown -->
      <div class="relative flex items-center">
        <select
          v-model="selectedFormat"
          :disabled="disabled"
          class="text-3xs py-1 pl-2 pr-5 bg-transparent border-0 font-medium text-slate-700 cursor-pointer outline-none appearance-none hover:bg-slate-200/50 rounded"
          @change="handleFormatChange"
        >
          <option value="Normal">Normal</option>
          <option value="Heading 1">Heading 1</option>
          <option value="Heading 2">Heading 2</option>
          <option value="Heading 3">Heading 3</option>
        </select>
        <ChevronDown class="w-3 h-3 text-slate-400 absolute right-1 pointer-events-none" />
      </div>

      <div class="w-px h-4 bg-slate-300 mx-0.5" />

      <!-- Bold -->
      <button
        type="button"
        title="Bold (Ctrl+B)"
        :disabled="disabled"
        class="p-1 rounded hover:bg-slate-200/60 text-slate-700 transition-colors font-bold text-xs w-6 h-6 flex items-center justify-center"
        @click.prevent="format('bold')"
      >
        <Bold class="w-3.5 h-3.5" />
      </button>

      <!-- Italic -->
      <button
        type="button"
        title="Italic (Ctrl+I)"
        :disabled="disabled"
        class="p-1 rounded hover:bg-slate-200/60 text-slate-700 transition-colors text-xs w-6 h-6 flex items-center justify-center"
        @click.prevent="format('italic')"
      >
        <Italic class="w-3.5 h-3.5" />
      </button>

      <!-- Underline -->
      <button
        type="button"
        title="Underline (Ctrl+U)"
        :disabled="disabled"
        class="p-1 rounded hover:bg-slate-200/60 text-slate-700 transition-colors text-xs w-6 h-6 flex items-center justify-center"
        @click.prevent="format('underline')"
      >
        <Underline class="w-3.5 h-3.5" />
      </button>

      <!-- Strikethrough -->
      <button
        type="button"
        title="Strikethrough"
        :disabled="disabled"
        class="p-1 rounded hover:bg-slate-200/60 text-slate-700 transition-colors text-xs w-6 h-6 flex items-center justify-center"
        @click.prevent="format('strikeThrough')"
      >
        <Strikethrough class="w-3.5 h-3.5" />
      </button>

      <div class="w-px h-4 bg-slate-300 mx-0.5" />

      <!-- Subscript & Superscript -->
      <button
        type="button"
        title="Subscript"
        :disabled="disabled"
        class="p-1 rounded hover:bg-slate-200/60 text-slate-700 transition-colors w-6 h-6 flex items-center justify-center"
        @click.prevent="format('subscript')"
      >
        <Subscript class="w-3.5 h-3.5" />
      </button>
      <button
        type="button"
        title="Superscript"
        :disabled="disabled"
        class="p-1 rounded hover:bg-slate-200/60 text-slate-700 transition-colors w-6 h-6 flex items-center justify-center"
        @click.prevent="format('superscript')"
      >
        <Superscript class="w-3.5 h-3.5" />
      </button>

      <div class="w-px h-4 bg-slate-300 mx-0.5" />

      <!-- Headings H1 & H2 -->
      <button
        type="button"
        title="Heading 1"
        :disabled="disabled"
        class="p-1 rounded hover:bg-slate-200/60 text-slate-700 transition-colors w-6 h-6 flex items-center justify-center"
        @click.prevent="format('formatBlock', '<h1>')"
      >
        <Heading1 class="w-3.5 h-3.5" />
      </button>
      <button
        type="button"
        title="Heading 2"
        :disabled="disabled"
        class="p-1 rounded hover:bg-slate-200/60 text-slate-700 transition-colors w-6 h-6 flex items-center justify-center"
        @click.prevent="format('formatBlock', '<h2>')"
      >
        <Heading2 class="w-3.5 h-3.5" />
      </button>

      <!-- Quote -->
      <button
        type="button"
        title="Blockquote"
        :disabled="disabled"
        class="p-1 rounded hover:bg-slate-200/60 text-slate-700 transition-colors w-6 h-6 flex items-center justify-center"
        @click.prevent="format('formatBlock', '<blockquote>')"
      >
        <Quote class="w-3.5 h-3.5" />
      </button>

      <!-- Code -->
      <button
        type="button"
        title="Code Block"
        :disabled="disabled"
        class="p-1 rounded hover:bg-slate-200/60 text-slate-700 transition-colors w-6 h-6 flex items-center justify-center"
        @click.prevent="format('formatBlock', '<pre>')"
      >
        <Code class="w-3.5 h-3.5" />
      </button>

      <div class="w-px h-4 bg-slate-300 mx-0.5" />

      <!-- Lists -->
      <button
        type="button"
        title="Bullet List"
        :disabled="disabled"
        class="p-1 rounded hover:bg-slate-200/60 text-slate-700 transition-colors w-6 h-6 flex items-center justify-center"
        @click.prevent="format('insertUnorderedList')"
      >
        <List class="w-3.5 h-3.5" />
      </button>
      <button
        type="button"
        title="Numbered List"
        :disabled="disabled"
        class="p-1 rounded hover:bg-slate-200/60 text-slate-700 transition-colors w-6 h-6 flex items-center justify-center"
        @click.prevent="format('insertOrderedList')"
      >
        <ListOrdered class="w-3.5 h-3.5" />
      </button>

      <!-- Indent / Outdent -->
      <button
        type="button"
        title="Decrease Indent"
        :disabled="disabled"
        class="p-1 rounded hover:bg-slate-200/60 text-slate-700 transition-colors w-6 h-6 flex items-center justify-center"
        @click.prevent="format('outdent')"
      >
        <Outdent class="w-3.5 h-3.5" />
      </button>
      <button
        type="button"
        title="Increase Indent"
        :disabled="disabled"
        class="p-1 rounded hover:bg-slate-200/60 text-slate-700 transition-colors w-6 h-6 flex items-center justify-center"
        @click.prevent="format('indent')"
      >
        <Indent class="w-3.5 h-3.5" />
      </button>
    </div>

    <!-- Content Editable Body -->
    <div
      ref="editorRef"
      contenteditable="true"
      :style="{ minHeight }"
      class="p-3 text-xs text-slate-800 outline-none overflow-y-auto leading-relaxed prose prose-sm max-w-none focus:outline-none"
      :class="{ 'pointer-events-none': disabled }"
      @input="handleInput"
      @blur="handleInput"
    />
  </div>
</template>

<style scoped>
[contenteditable]:empty:before {
  content: attr(placeholder);
  color: #94a3b8;
  pointer-events: none;
  display: block;
}
</style>
