<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'

const props = defineProps<{
  modelValue: string
  disabled?: boolean
  placeholder?: string
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: string): void
  (e: 'submit', html: string): void   // ✅ nuevo
}>()

const el = ref<HTMLDivElement | null>(null)

const normalizeEmpty = () => {
  if (!el.value) return
  const html = el.value.innerHTML.trim()
  if (html === '<br>' || html === '<div><br></div>' || html === '') {
    el.value.innerHTML = ''
  }
}

const syncToModel = () => {
  if (!el.value) return
  normalizeEmpty()
  emit('update:modelValue', el.value.innerHTML)
}

const focus = () => el.value?.focus()

const exec = (cmd: 'bold' | 'italic' | 'underline') => {
  if (props.disabled) return
  focus()
  document.execCommand(cmd)
  syncToModel()
}

const insertText = (text: string) => {
  if (props.disabled) return
  focus()
  const ok = document.execCommand('insertText', false, text)
  if (!ok) {
    const sel = window.getSelection()
    if (!sel || sel.rangeCount === 0) return
    const range = sel.getRangeAt(0)
    range.deleteContents()
    range.insertNode(document.createTextNode(text))
    range.collapse(false)
    sel.removeAllRanges()
    sel.addRange(range)
  }
  syncToModel()
}

const onKeydown = (e: KeyboardEvent) => {
  if (props.disabled) return

  if (e.key === 'Enter' && !e.shiftKey) {
    // ✅ Enter = enviar
    e.preventDefault()
    e.stopPropagation()
    if (el.value) emit('submit', el.value.innerHTML)
  }
  // ✅ Shift+Enter: NO hacemos nada -> el navegador inserta salto de línea
}

const onPaste = (e: ClipboardEvent) => {
  if (props.disabled) return
  e.preventDefault()
  const text = e.clipboardData?.getData('text/plain') ?? ''
  insertText(text)
}

onMounted(() => {
  if (!el.value) return
  el.value.innerHTML = props.modelValue || ''
})

watch(
  () => props.modelValue,
  (v) => {
    if (!el.value) return
    // si el usuario está escribiendo, no lo pises
    if (document.activeElement === el.value) return
    if (el.value.innerHTML !== (v || '')) {
      el.value.innerHTML = v || ''
      normalizeEmpty()
    }
  }
)

defineExpose({
  focus,
  toggleBold: () => exec('bold'),
  toggleItalic: () => exec('italic'),
  toggleUnderline: () => exec('underline'),
  insertText,
})
</script>

<template>
  <div
    ref="el"
    v-bind="$attrs"
    :contenteditable="!disabled"
    :data-placeholder="placeholder || ''"
    class="min-h-11 max-h-36 w-full overflow-y-auto rounded-md border border-input bg-background px-3 py-2 text-sm
       whitespace-pre-wrap break-words
       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
    :class="disabled ? 'opacity-60 cursor-not-allowed' : ''"
    @input="syncToModel"
    @keydown="onKeydown"
    @paste="onPaste"
  />
</template>

<style scoped>
/* Placeholder para contenteditable */
div[contenteditable="true"]:empty::before {
  content: attr(data-placeholder);
  color: hsl(var(--muted-foreground));
  pointer-events: none;
}
</style>