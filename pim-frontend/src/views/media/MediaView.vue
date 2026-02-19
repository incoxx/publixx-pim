<script setup>
import { ref, onMounted } from 'vue'
import { Upload, Image, Grid, List } from 'lucide-vue-next'
import mediaApi from '@/api/media'

const items = ref([])
const loading = ref(false)
const viewMode = ref('grid')

async function fetchMedia() {
  loading.value = true
  try { const { data } = await mediaApi.list({ perPage: 50 }); items.value = data.data || data }
  finally { loading.value = false }
}
async function handleUpload(e) {
  for (const file of e.target.files) { await mediaApi.upload(file) }
  await fetchMedia()
}
onMounted(() => fetchMedia())
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-[var(--color-text-primary)]">Medien</h2>
      <div class="flex items-center gap-2">
        <button :class="['pim-btn pim-btn-ghost p-1.5', viewMode==='grid'?'bg-[var(--color-bg)]':'']" @click="viewMode='grid'"><Grid class="w-4 h-4" :stroke-width="1.75" /></button>
        <button :class="['pim-btn pim-btn-ghost p-1.5', viewMode==='list'?'bg-[var(--color-bg)]':'']" @click="viewMode='list'"><List class="w-4 h-4" :stroke-width="1.75" /></button>
        <input type="file" accept="image/*" multiple class="hidden" id="media-upload" @change="handleUpload" />
        <label for="media-upload" class="pim-btn pim-btn-primary text-sm cursor-pointer"><Upload class="w-4 h-4" :stroke-width="2" /> Hochladen</label>
      </div>
    </div>
    <div v-if="loading" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
      <div v-for="i in 10" :key="i" class="pim-skeleton aspect-square rounded-lg" />
    </div>
    <div v-else-if="items.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
      <div v-for="item in items" :key="item.id" class="pim-card overflow-hidden group cursor-pointer hover:shadow-md transition-shadow">
        <div class="aspect-square bg-[var(--color-bg)] flex items-center justify-center overflow-hidden">
          <img v-if="item.url || item.filename" :src="item.url || `/api/v1/media/file/${item.filename}`" class="w-full h-full object-cover" loading="lazy" alt="" />
          <Image v-else class="w-8 h-8 text-[var(--color-text-tertiary)]" :stroke-width="1.5" />
        </div>
        <div class="p-2">
          <p class="text-[11px] text-[var(--color-text-primary)] truncate">{{ item.original_filename || item.filename }}</p>
          <p class="text-[10px] text-[var(--color-text-tertiary)]">{{ item.media_type }}</p>
        </div>
      </div>
    </div>
    <div v-else class="pim-card p-12 text-center">
      <Image class="w-10 h-10 mx-auto mb-3 text-[var(--color-text-tertiary)]" :stroke-width="1.5" />
      <p class="text-sm text-[var(--color-text-tertiary)]">Keine Medien vorhanden</p>
    </div>
  </div>
</template>
