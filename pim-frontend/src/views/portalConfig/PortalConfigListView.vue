<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Plus, Globe, Trash2, Copy, ExternalLink, Pencil } from 'lucide-vue-next'
import portalConfigApi from '@/api/portalConfig'

const router = useRouter()
const portals = ref([])
const presets = ref([])
const loading = ref(true)
const showPresets = ref(false)

onMounted(async () => {
  await loadPortals()
  loading.value = false
})

async function loadPortals() {
  const { data } = await portalConfigApi.list()
  portals.value = data.data || []
}

async function loadPresets() {
  if (presets.value.length) {
    showPresets.value = !showPresets.value
    return
  }
  const { data } = await portalConfigApi.presets()
  presets.value = data.data || []
  showPresets.value = true
}

async function createNew() {
  const defaultHtml = `<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Portal</title>
</head>
<body>
  <div data-portal="branding"></div>
  <div data-portal="country-select"></div>
  <div data-portal="submit-button"></div>

  <script src="../dist/portal-embed.umd.js"><\/script>
  <script>
    PortalEmbed.init({
      api: '__API_URL__',
      slug: '__SLUG__',
    })
  <\/script>
</body>
</html>`

  const { data } = await portalConfigApi.create({
    name: 'Neues Portal',
    html_template: defaultHtml,
    filter_steps: [],
  })
  router.push(`/portal-config/${data.data.id}`)
}

async function createFromPreset(preset) {
  const { data } = await portalConfigApi.create({
    name: preset.name,
    slug: preset.slug,
    html_template: preset.html,
    filter_steps: [],
  })
  router.push(`/portal-config/${data.data.id}`)
}

async function duplicatePortal(portal) {
  await portalConfigApi.duplicate(portal.id)
  await loadPortals()
}

async function deletePortal(portal) {
  if (!confirm(`Portal "${portal.name}" wirklich loeschen?`)) return
  await portalConfigApi.remove(portal.id)
  await loadPortals()
}

function portalUrl(portal) {
  return `${window.location.origin}/portal/${portal.slug}`
}
</script>

<template>
  <div class="p-6 max-w-5xl mx-auto">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold flex items-center gap-2">
        <Globe class="w-6 h-6" />
        Portale
      </h1>
      <div class="flex gap-2">
        <button class="btn btn-sm btn-outline" @click="loadPresets">
          Aus Vorlage
        </button>
        <button class="btn btn-sm btn-primary" @click="createNew">
          <Plus class="w-4 h-4" />
          Neues Portal
        </button>
      </div>
    </div>

    <!-- Preset-Auswahl -->
    <div v-if="showPresets && presets.length" class="mb-6 p-4 bg-base-200 rounded-lg">
      <h3 class="text-sm font-semibold mb-3">Vorlagen</h3>
      <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
        <button
          v-for="preset in presets"
          :key="preset.slug"
          class="p-3 bg-base-100 rounded-lg border border-base-300 hover:border-primary text-left transition-colors"
          @click="createFromPreset(preset)"
        >
          <span class="font-medium text-sm">{{ preset.name }}</span>
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-12 text-base-content/50">
      Lade...
    </div>

    <!-- Leere Liste -->
    <div v-else-if="portals.length === 0" class="text-center py-12">
      <Globe class="w-12 h-12 mx-auto mb-3 opacity-20" />
      <p class="text-base-content/50">Noch keine Portale erstellt</p>
      <button class="btn btn-primary btn-sm mt-4" @click="createNew">
        <Plus class="w-4 h-4" />
        Erstes Portal erstellen
      </button>
    </div>

    <!-- Portal-Liste -->
    <div v-else class="flex flex-col gap-3">
      <div
        v-for="portal in portals"
        :key="portal.id"
        class="p-4 bg-base-100 border border-base-300 rounded-lg flex items-center gap-4 hover:border-primary transition-colors cursor-pointer"
        @click="router.push(`/portal-config/${portal.id}`)"
      >
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 mb-1">
            <span class="font-semibold">{{ portal.name }}</span>
            <span
              class="badge badge-xs"
              :class="portal.is_active ? 'badge-success' : 'badge-ghost'"
            >
              {{ portal.is_active ? 'Aktiv' : 'Inaktiv' }}
            </span>
          </div>
          <div class="text-xs text-base-content/50 flex items-center gap-3">
            <span>/portal/{{ portal.slug }}</span>
            <span v-if="portal.catalog_template">→ {{ portal.catalog_template.name }}</span>
          </div>
        </div>
        <div class="flex items-center gap-1" @click.stop>
          <a :href="portalUrl(portal)" target="_blank" class="btn btn-ghost btn-xs" title="Oeffnen">
            <ExternalLink class="w-3.5 h-3.5" />
          </a>
          <button class="btn btn-ghost btn-xs" title="Bearbeiten" @click="router.push(`/portal-config/${portal.id}`)">
            <Pencil class="w-3.5 h-3.5" />
          </button>
          <button class="btn btn-ghost btn-xs" title="Duplizieren" @click="duplicatePortal(portal)">
            <Copy class="w-3.5 h-3.5" />
          </button>
          <button class="btn btn-ghost btn-xs text-error" title="Loeschen" @click="deletePortal(portal)">
            <Trash2 class="w-3.5 h-3.5" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
