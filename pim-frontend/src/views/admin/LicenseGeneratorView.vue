<script setup>
import { ref, computed } from 'vue'
import { Key, Shield, Copy, Check, AlertTriangle, RefreshCw, Loader2 } from 'lucide-vue-next'
import licenseApi from '@/api/license'

// ── State ──
const privateKey = ref('')
const publicKey = ref('')
const keyValid = ref(false)
const validating = ref(false)
const validationError = ref(null)

const customer = ref('')
const maxUsers = ref(0)
const expiresMode = ref('perpetual')
const customDate = ref('')
const selectedModules = ref([])

const generating = ref(false)
const generatedKey = ref('')
const generatedPayload = ref(null)
const generateError = ref(null)
const copied = ref(false)

const generatingKeypair = ref(false)
const keypairResult = ref(null)

// ── Modules from backend config (hardcoded to match config/license.php) ──
const availableModules = [
  { key: 'bmecat', name: 'BMEcat Import/Export', description: 'BMEcat XML 1.2 / 2005 Import und Export' },
  { key: 'advanced_export', name: 'Erweiterte Exports', description: 'Export-Jobs, Zeitpläne, SFTP/HTTP-Delivery, Export-Mappings' },
  { key: 'publixx', name: 'Publixx-Integration', description: 'Publixx-Mappings' },
  { key: 'reports', name: 'Berichte', description: 'Bericht-Designer und Auswertungen' },
  { key: 'pdf_templates', name: 'PDF-Vorlagen', description: 'PDF-Template-Designer und -Generierung' },
  { key: 'sso', name: 'Single Sign-On', description: 'Azure AD / OAuth SSO-Integration' },
  { key: 'workflow', name: 'Workflow-Management', description: 'Konfigurierbare Workflows, Teams und Projekte' },
  { key: 'api_designer', name: 'API-Designer', description: 'Visueller API-Designer mit JSON-Streaming-Endpoints' },
]

const allSelected = computed(() => selectedModules.value.length === availableModules.length)

function toggleAll() {
  if (allSelected.value) {
    selectedModules.value = []
  } else {
    selectedModules.value = availableModules.map(m => m.key)
  }
}

const expiresAt = computed(() => {
  if (expiresMode.value === 'perpetual') return null
  if (expiresMode.value === '1y') {
    const d = new Date()
    d.setFullYear(d.getFullYear() + 1)
    return d.toISOString().split('T')[0]
  }
  if (expiresMode.value === '2y') {
    const d = new Date()
    d.setFullYear(d.getFullYear() + 2)
    return d.toISOString().split('T')[0]
  }
  return customDate.value || null
})

const canGenerate = computed(() =>
  keyValid.value && customer.value.trim() && selectedModules.value.length > 0
)

// ── Actions ──
async function validateKey() {
  validating.value = true
  validationError.value = null
  keyValid.value = false
  publicKey.value = ''
  generatedKey.value = ''
  generatedPayload.value = null

  try {
    const { data } = await licenseApi.validatePrivateKey(privateKey.value.trim())
    if (data.valid) {
      keyValid.value = true
      publicKey.value = data.public_key
    } else {
      validationError.value = data.error
    }
  } catch (e) {
    validationError.value = e.response?.data?.error || 'Validierung fehlgeschlagen'
  } finally {
    validating.value = false
  }
}

async function generate() {
  generating.value = true
  generateError.value = null
  generatedKey.value = ''
  generatedPayload.value = null

  try {
    const { data } = await licenseApi.generateLicense({
      private_key: privateKey.value.trim(),
      customer: customer.value.trim(),
      modules: selectedModules.value,
      max_users: maxUsers.value,
      expires_at: expiresAt.value,
    })
    generatedKey.value = data.license_key
    generatedPayload.value = data.payload
  } catch (e) {
    generateError.value = e.response?.data?.error || 'Generierung fehlgeschlagen'
  } finally {
    generating.value = false
  }
}

async function copyKey() {
  await navigator.clipboard.writeText(generatedKey.value)
  copied.value = true
  setTimeout(() => { copied.value = false }, 2000)
}

async function generateKeypair() {
  generatingKeypair.value = true
  keypairResult.value = null
  try {
    const { data } = await licenseApi.generateKeypair()
    keypairResult.value = data
  } catch {
    keypairResult.value = { error: 'Keypair-Generierung fehlgeschlagen' }
  } finally {
    generatingKeypair.value = false
  }
}
</script>

<template>
  <div class="max-w-3xl mx-auto p-6 space-y-6">
    <!-- Header -->
    <div class="flex items-center gap-3">
      <div class="p-2 rounded-lg bg-amber-100 text-amber-700">
        <Key class="w-6 h-6" />
      </div>
      <div>
        <h1 class="text-xl font-semibold text-gray-900">License Generator</h1>
        <p class="text-sm text-gray-500">Signierte Lizenzschlüssel erstellen</p>
      </div>
    </div>

    <!-- Keypair Generator -->
    <div class="pim-card p-4 space-y-3">
      <h2 class="text-sm font-medium text-gray-700 flex items-center gap-2">
        <RefreshCw class="w-4 h-4" />
        Neues Schlüsselpaar erzeugen
      </h2>
      <p class="text-xs text-gray-500">
        Generiert ein neues Ed25519-Keypair. Den Public Key in <code class="bg-gray-100 px-1 rounded">.env</code> als
        <code class="bg-gray-100 px-1 rounded">ANYPIM_LICENSE_PUBLIC_KEY</code> eintragen.
      </p>
      <button
        class="pim-btn pim-btn-secondary text-sm"
        :disabled="generatingKeypair"
        @click="generateKeypair"
      >
        <Loader2 v-if="generatingKeypair" class="w-4 h-4 animate-spin" />
        <RefreshCw v-else class="w-4 h-4" />
        Keypair generieren
      </button>

      <div v-if="keypairResult && !keypairResult.error" class="space-y-2 mt-2">
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Private Key (geheim halten!)</label>
          <code class="block bg-red-50 border border-red-200 rounded p-2 text-xs break-all font-mono text-red-800">
            {{ keypairResult.private_key }}
          </code>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Public Key (in .env eintragen)</label>
          <code class="block bg-green-50 border border-green-200 rounded p-2 text-xs break-all font-mono text-green-800">
            {{ keypairResult.public_key }}
          </code>
        </div>
      </div>
      <div v-if="keypairResult?.error" class="text-sm text-red-600">{{ keypairResult.error }}</div>
    </div>

    <!-- Step 1: Private Key -->
    <div class="pim-card p-4 space-y-3">
      <h2 class="text-sm font-medium text-gray-700 flex items-center gap-2">
        <Shield class="w-4 h-4" />
        Schritt 1 — Private Key
      </h2>
      <textarea
        v-model="privateKey"
        rows="3"
        class="pim-input w-full font-mono text-xs"
        placeholder="Base64-kodierten Ed25519 Private Key hier einfügen …"
        :disabled="keyValid"
      />
      <div class="flex items-center gap-3">
        <button
          v-if="!keyValid"
          class="pim-btn pim-btn-primary text-sm"
          :disabled="!privateKey.trim() || validating"
          @click="validateKey"
        >
          <Loader2 v-if="validating" class="w-4 h-4 animate-spin" />
          <Shield v-else class="w-4 h-4" />
          Validieren
        </button>
        <button
          v-if="keyValid"
          class="pim-btn pim-btn-ghost text-sm"
          @click="keyValid = false; publicKey = ''; generatedKey = ''; generatedPayload = null"
        >
          Anderen Key verwenden
        </button>
        <span v-if="keyValid" class="text-sm text-green-600 flex items-center gap-1">
          <Check class="w-4 h-4" /> Key gültig
        </span>
      </div>
      <div v-if="validationError" class="flex items-center gap-2 text-sm text-red-600">
        <AlertTriangle class="w-4 h-4 shrink-0" />
        {{ validationError }}
      </div>
      <div v-if="publicKey" class="text-xs text-gray-500">
        Public Key: <code class="bg-gray-100 px-1 rounded font-mono">{{ publicKey }}</code>
      </div>
    </div>

    <!-- Step 2: Configuration (only visible after valid key) -->
    <div v-if="keyValid" class="pim-card p-4 space-y-4">
      <h2 class="text-sm font-medium text-gray-700">Schritt 2 — Lizenz konfigurieren</h2>

      <!-- Customer -->
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Firma *</label>
        <input v-model="customer" type="text" class="pim-input w-full" placeholder="Firma GmbH" />
      </div>

      <!-- Expiry -->
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Laufzeit</label>
        <select v-model="expiresMode" class="pim-input">
          <option value="perpetual">Unbegrenzt</option>
          <option value="1y">1 Jahr</option>
          <option value="2y">2 Jahre</option>
          <option value="custom">Benutzerdefiniert</option>
        </select>
        <input
          v-if="expiresMode === 'custom'"
          v-model="customDate"
          type="date"
          class="pim-input mt-2"
        />
      </div>

      <!-- Max Users -->
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Max. Benutzer (0 = unbegrenzt)</label>
        <input v-model.number="maxUsers" type="number" min="0" class="pim-input w-32" />
      </div>

      <!-- Modules -->
      <div>
        <div class="flex items-center justify-between mb-2">
          <label class="block text-xs font-medium text-gray-600">Module *</label>
          <button class="text-xs text-blue-600 hover:underline" @click="toggleAll">
            {{ allSelected ? 'Keine auswählen' : 'Alle auswählen' }}
          </button>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
          <label
            v-for="mod in availableModules"
            :key="mod.key"
            class="flex items-start gap-2 p-2 rounded border border-gray-200 hover:bg-gray-50 cursor-pointer transition"
            :class="{ 'border-blue-300 bg-blue-50': selectedModules.includes(mod.key) }"
          >
            <input
              type="checkbox"
              :value="mod.key"
              v-model="selectedModules"
              class="mt-0.5 accent-blue-600"
            />
            <div class="min-w-0">
              <div class="text-sm font-medium text-gray-800">{{ mod.name }}</div>
              <div class="text-xs text-gray-500 truncate">{{ mod.description }}</div>
            </div>
          </label>
        </div>
      </div>

      <!-- Generate Button -->
      <div class="pt-2">
        <button
          class="pim-btn pim-btn-primary"
          :disabled="!canGenerate || generating"
          @click="generate"
        >
          <Loader2 v-if="generating" class="w-4 h-4 animate-spin" />
          <Key v-else class="w-4 h-4" />
          Lizenz generieren
        </button>
      </div>

      <div v-if="generateError" class="flex items-center gap-2 text-sm text-red-600">
        <AlertTriangle class="w-4 h-4 shrink-0" />
        {{ generateError }}
      </div>
    </div>

    <!-- Result -->
    <div v-if="generatedKey" class="pim-card p-4 space-y-3">
      <h2 class="text-sm font-medium text-green-700 flex items-center gap-2">
        <Check class="w-4 h-4" />
        Lizenzschlüssel erstellt
      </h2>

      <!-- Summary -->
      <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
        <span class="text-gray-500">Firma:</span>
        <span class="font-medium">{{ generatedPayload.customer }}</span>
        <span class="text-gray-500">Module:</span>
        <span class="font-medium">{{ generatedPayload.modules.join(', ') }}</span>
        <span class="text-gray-500">Laufzeit:</span>
        <span class="font-medium">{{ generatedPayload.expires_at || 'Unbegrenzt' }}</span>
        <span class="text-gray-500">Max. Benutzer:</span>
        <span class="font-medium">{{ generatedPayload.max_users || 'Unbegrenzt' }}</span>
        <span class="text-gray-500">Erstellt:</span>
        <span class="font-medium">{{ new Date(generatedPayload.issued_at).toLocaleString('de-DE') }}</span>
      </div>

      <!-- License Key -->
      <div class="relative">
        <pre class="bg-gray-900 text-green-400 rounded-lg p-4 text-xs font-mono break-all whitespace-pre-wrap select-all">{{ generatedKey }}</pre>
        <button
          class="absolute top-2 right-2 p-1.5 rounded bg-gray-700 hover:bg-gray-600 text-gray-300 transition"
          @click="copyKey"
          :title="copied ? 'Kopiert!' : 'In Zwischenablage kopieren'"
        >
          <Check v-if="copied" class="w-4 h-4 text-green-400" />
          <Copy v-else class="w-4 h-4" />
        </button>
      </div>

      <p class="text-xs text-gray-500">
        Diesen Schlüssel unter <strong>Einstellungen → Lizenzschlüssel</strong> aktivieren.
      </p>
    </div>
  </div>
</template>
