<script setup>
import { ref, onMounted } from 'vue'
import { Settings, Save, Eye, EyeOff, CheckCircle, AlertTriangle, Plug } from 'lucide-vue-next'
import connectorsApi from '@/api/connectors'

const loading = ref(false)
const saving = ref(false)
const error = ref('')
const success = ref('')
const schema = ref({})
const hasValues = ref({})

// Editable form data per connector
const form = ref({})
const showSecrets = ref({})

const connectorLabels = {
  canva: 'Canva',
  deepl: 'DeepL',
  shopware: 'Shopware 6',
  cloudinary: 'Cloudinary',
  claude_ai: 'Claude AI',
  google_translate: 'Google Translate',
  anthropic_tms: 'Anthropic (Claude)',
}

// Group connectors into sections for the UI
const sections = {
  connectors: {
    label: 'Connectoren',
    description: 'API-Zugangsdaten für externe Dienste und Integrationen.',
    keys: ['canva', 'deepl', 'shopware', 'cloudinary', 'claude_ai'],
  },
  tms: {
    label: 'Übersetzungsdienste (TMS)',
    description: 'API-Keys für maschinelle Übersetzung im Translation Management System. Der DeepL-Key oben wird automatisch auch für das TMS verwendet.',
    keys: ['google_translate', 'anthropic_tms'],
  },
}

const fieldLabels = {
  client_id: 'Client ID',
  client_secret: 'Client Secret',
  redirect_uri: 'Redirect URI',
  api_key: 'API Key',
  api_secret: 'API Secret',
  shop_url: 'Shop URL',
  cloud_name: 'Cloud Name',
  model: 'Modell',
  max_tokens: 'Max Tokens',
}

const fieldTypes = {
  shop_url: 'url',
  redirect_uri: 'url',
  max_tokens: 'number',
  model: 'text',
  cloud_name: 'text',
}

onMounted(load)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await connectorsApi.getCredentials()
    const d = data.data || data
    schema.value = d.schema || {}
    hasValues.value = d.has_values || {}

    // Initialize form with masked values
    const values = d.values || {}
    const f = {}
    for (const [connector, fields] of Object.entries(d.schema || {})) {
      f[connector] = {}
      for (const field of fields) {
        f[connector][field] = values[connector]?.[field] || ''
      }
    }
    form.value = f
  } catch (e) {
    error.value = 'Fehler beim Laden der Einstellungen'
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  error.value = ''
  success.value = ''
  try {
    await connectorsApi.updateCredentials(form.value)
    success.value = 'Einstellungen gespeichert'
    setTimeout(() => { success.value = '' }, 3000)
    await load()
  } catch (e) {
    error.value = e.response?.data?.message || 'Fehler beim Speichern'
  } finally {
    saving.value = false
  }
}

function isSecret(field) {
  return ['client_secret', 'api_key', 'api_secret'].includes(field)
}

function toggleSecret(connector, field) {
  const key = `${connector}.${field}`
  showSecrets.value[key] = !showSecrets.value[key]
}

function isSecretVisible(connector, field) {
  return showSecrets.value[`${connector}.${field}`]
}
</script>

<template>
  <div class="space-y-6 max-w-4xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <Settings class="w-6 h-6 text-primary" />
        <h1 class="text-xl font-bold">Plugin-Einstellungen</h1>
      </div>
      <button
        class="btn btn-sm btn-primary"
        :disabled="saving"
        @click="save"
      >
        <Save class="w-4 h-4" />
        {{ saving ? 'Speichern...' : 'Speichern' }}
      </button>
    </div>

    <p class="text-sm text-base-content/60">
      Konfigurieren Sie hier zentral alle API-Zugangsdaten — für Connectoren und Übersetzungsdienste.
      Die Daten werden verschlüsselt in der Datenbank gespeichert und überschreiben ggf. die .env-Werte.
    </p>

    <!-- Messages -->
    <div v-if="error" class="alert alert-error text-sm">
      <AlertTriangle class="w-4 h-4" />
      {{ error }}
    </div>
    <div v-if="success" class="alert alert-success text-sm">
      <CheckCircle class="w-4 h-4" />
      {{ success }}
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-8">
      <span class="loading loading-spinner loading-lg"></span>
    </div>

    <!-- Connector & TMS Cards grouped by section -->
    <template v-else>
      <template v-for="(section, sectionKey) in sections" :key="sectionKey">
        <div class="pt-2">
          <h2 class="text-lg font-semibold mb-1">{{ section.label }}</h2>
          <p class="text-sm text-base-content/60 mb-4">{{ section.description }}</p>
        </div>

        <template v-for="connector in section.keys" :key="connector">
          <div
            v-if="schema[connector]"
            class="card bg-base-100 shadow-sm border border-base-200"
          >
            <div class="card-body p-5 space-y-4">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <Plug class="w-5 h-5 text-primary" />
                  <h3 class="font-semibold text-lg">{{ connectorLabels[connector] || connector }}</h3>
                </div>
                <span
                  :class="hasValues[connector] ? 'badge-success' : 'badge-warning'"
                  class="badge badge-sm"
                >
                  {{ hasValues[connector] ? 'Konfiguriert' : 'Nicht konfiguriert' }}
                </span>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div v-for="field in schema[connector]" :key="field" class="form-control">
                  <label class="label">
                    <span class="label-text text-sm font-medium">{{ fieldLabels[field] || field }}</span>
                  </label>
                  <div class="relative">
                    <input
                      v-model="form[connector][field]"
                      :type="isSecret(field) && !isSecretVisible(connector, field) ? 'password' : (fieldTypes[field] || 'text')"
                      :placeholder="fieldLabels[field] || field"
                      class="input input-bordered input-sm w-full pr-10"
                      autocomplete="off"
                    />
                    <button
                      v-if="isSecret(field)"
                      type="button"
                      class="absolute right-2 top-1/2 -translate-y-1/2 text-base-content/40 hover:text-base-content/70"
                      @click="toggleSecret(connector, field)"
                    >
                      <EyeOff v-if="isSecretVisible(connector, field)" class="w-4 h-4" />
                      <Eye v-else class="w-4 h-4" />
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </template>
      </template>
    </template>
  </div>
</template>
