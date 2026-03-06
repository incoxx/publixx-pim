<script setup>
import { watch } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Underline from '@tiptap/extension-underline'
import Link from '@tiptap/extension-link'
import {
  Bold, Italic, Underline as UnderlineIcon, Link as LinkIcon,
  Heading2, Heading3, List, ListOrdered, Undo, Redo, Unlink,
} from 'lucide-vue-next'

const props = defineProps({
  modelValue: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  placeholder: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue'])

const editor = useEditor({
  content: props.modelValue || '',
  editable: !props.disabled,
  extensions: [
    StarterKit.configure({
      heading: { levels: [2, 3] },
    }),
    Underline,
    Link.configure({
      openOnClick: false,
      HTMLAttributes: { rel: 'noopener noreferrer', target: '_blank' },
    }),
  ],
  onUpdate({ editor }) {
    emit('update:modelValue', editor.getHTML())
  },
})

watch(() => props.modelValue, (val) => {
  if (editor.value && val !== editor.value.getHTML()) {
    editor.value.commands.setContent(val || '', false)
  }
})

watch(() => props.disabled, (val) => {
  editor.value?.setEditable(!val)
})

function setLink() {
  const prev = editor.value?.getAttributes('link').href
  const url = window.prompt('URL eingeben:', prev || 'https://')
  if (url === null) return
  if (url === '') {
    editor.value?.chain().focus().extendMarkRange('link').unsetLink().run()
  } else {
    editor.value?.chain().focus().extendMarkRange('link').setLink({ href: url }).run()
  }
}

const btnClass = 'p-1.5 rounded hover:bg-[var(--color-bg)] text-[var(--color-text-secondary)] transition-colors disabled:opacity-40'
const activeClass = 'bg-[color-mix(in_srgb,var(--color-accent)_15%,transparent)] text-[var(--color-accent)]'
</script>

<template>
  <div class="border border-[var(--color-border)] rounded-[var(--radius-md)] overflow-hidden" :class="disabled ? 'opacity-60' : ''">
    <!-- Toolbar -->
    <div v-if="editor && !disabled" class="flex items-center gap-0.5 px-2 py-1 border-b border-[var(--color-border)] bg-[var(--color-bg)]">
      <button type="button" :class="[btnClass, editor.isActive('bold') ? activeClass : '']" @click="editor.chain().focus().toggleBold().run()" title="Fett">
        <Bold class="w-3.5 h-3.5" :stroke-width="2" />
      </button>
      <button type="button" :class="[btnClass, editor.isActive('italic') ? activeClass : '']" @click="editor.chain().focus().toggleItalic().run()" title="Kursiv">
        <Italic class="w-3.5 h-3.5" :stroke-width="2" />
      </button>
      <button type="button" :class="[btnClass, editor.isActive('underline') ? activeClass : '']" @click="editor.chain().focus().toggleUnderline().run()" title="Unterstrichen">
        <UnderlineIcon class="w-3.5 h-3.5" :stroke-width="2" />
      </button>

      <div class="w-px h-4 bg-[var(--color-border)] mx-1" />

      <button type="button" :class="[btnClass, editor.isActive('heading', { level: 2 }) ? activeClass : '']" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()" title="Überschrift 2">
        <Heading2 class="w-3.5 h-3.5" :stroke-width="2" />
      </button>
      <button type="button" :class="[btnClass, editor.isActive('heading', { level: 3 }) ? activeClass : '']" @click="editor.chain().focus().toggleHeading({ level: 3 }).run()" title="Überschrift 3">
        <Heading3 class="w-3.5 h-3.5" :stroke-width="2" />
      </button>

      <div class="w-px h-4 bg-[var(--color-border)] mx-1" />

      <button type="button" :class="[btnClass, editor.isActive('bulletList') ? activeClass : '']" @click="editor.chain().focus().toggleBulletList().run()" title="Aufzählung">
        <List class="w-3.5 h-3.5" :stroke-width="2" />
      </button>
      <button type="button" :class="[btnClass, editor.isActive('orderedList') ? activeClass : '']" @click="editor.chain().focus().toggleOrderedList().run()" title="Nummerierte Liste">
        <ListOrdered class="w-3.5 h-3.5" :stroke-width="2" />
      </button>

      <div class="w-px h-4 bg-[var(--color-border)] mx-1" />

      <button type="button" :class="[btnClass, editor.isActive('link') ? activeClass : '']" @click="setLink" title="Link">
        <LinkIcon class="w-3.5 h-3.5" :stroke-width="2" />
      </button>
      <button v-if="editor.isActive('link')" type="button" :class="btnClass" @click="editor.chain().focus().unsetLink().run()" title="Link entfernen">
        <Unlink class="w-3.5 h-3.5" :stroke-width="2" />
      </button>

      <div class="flex-1" />

      <button type="button" :class="btnClass" :disabled="!editor.can().undo()" @click="editor.chain().focus().undo().run()" title="Rückgängig">
        <Undo class="w-3.5 h-3.5" :stroke-width="2" />
      </button>
      <button type="button" :class="btnClass" :disabled="!editor.can().redo()" @click="editor.chain().focus().redo().run()" title="Wiederholen">
        <Redo class="w-3.5 h-3.5" :stroke-width="2" />
      </button>
    </div>

    <!-- Editor Content -->
    <EditorContent
      v-if="editor"
      :editor="editor"
      class="pim-richtext-content"
    />
  </div>
</template>

<style>
.pim-richtext-content .tiptap {
  padding: 8px 12px;
  min-height: 120px;
  max-height: 400px;
  overflow-y: auto;
  font-size: 13px;
  line-height: 1.6;
  color: var(--color-text-primary);
  outline: none;
}

.pim-richtext-content .tiptap:focus {
  outline: none;
}

.pim-richtext-content .tiptap p {
  margin: 0 0 0.5em;
}

.pim-richtext-content .tiptap h2 {
  font-size: 1.25em;
  font-weight: 600;
  margin: 0.75em 0 0.25em;
}

.pim-richtext-content .tiptap h3 {
  font-size: 1.1em;
  font-weight: 600;
  margin: 0.5em 0 0.25em;
}

.pim-richtext-content .tiptap ul,
.pim-richtext-content .tiptap ol {
  padding-left: 1.5em;
  margin: 0.25em 0;
}

.pim-richtext-content .tiptap li {
  margin: 0.1em 0;
}

.pim-richtext-content .tiptap a {
  color: var(--color-accent);
  text-decoration: underline;
  cursor: pointer;
}

.pim-richtext-content .tiptap p.is-editor-empty:first-child::before {
  content: attr(data-placeholder);
  color: var(--color-text-tertiary);
  float: left;
  height: 0;
  pointer-events: none;
}
</style>
