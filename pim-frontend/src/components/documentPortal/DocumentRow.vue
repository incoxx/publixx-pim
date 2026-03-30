<script setup>
import { languageName } from './languageConstants'
import { formatFileSize } from '@/utils/formatting'

defineProps({
  document: { type: Object, required: true },
})

const langLabel = languageName
const formatSize = formatFileSize
</script>

<template>
  <a :href="document.download_url" target="_blank" class="dr-row">
    <div class="dr-row__icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z" /><polyline points="14 2 14 8 20 8" /></svg>
    </div>
    <div class="dr-row__info">
      <span class="dr-row__name">{{ document.file_name }}</span>
      <span class="dr-row__meta">
        <span v-if="document.language" class="dr-row__lang">{{ langLabel(document.language) }}</span>
        <span v-if="document.file_size">{{ formatSize(document.file_size) }}</span>
      </span>
    </div>
    <svg class="dr-row__download" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" y1="15" x2="12" y2="3" /></svg>
  </a>
</template>

<style scoped>
.dr-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  border-radius: 6px;
  text-decoration: none;
  color: inherit;
  transition: background 0.1s;
}
.dr-row:hover {
  background: var(--dp-surface, #f0f9ff);
}
.dr-row__icon svg {
  width: 20px;
  height: 20px;
  color: #ef4444;
}
.dr-row__info {
  flex: 1;
  min-width: 0;
}
.dr-row__name {
  display: block;
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--dp-dark, #0c4a6e);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.dr-row__meta {
  display: flex;
  gap: 8px;
  font-size: 0.75rem;
  color: #94a3b8;
}
.dr-row__lang {
  font-weight: 500;
}
.dr-row__download {
  width: 16px;
  height: 16px;
  color: var(--dp-primary, #0891b2);
  opacity: 0;
  flex-shrink: 0;
}
.dr-row:hover .dr-row__download {
  opacity: 1;
}
</style>
