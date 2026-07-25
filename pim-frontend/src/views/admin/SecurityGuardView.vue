<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import securityGuardApi from '@/api/securityGuard'
import {
  ShieldAlert, RefreshCw, Save, ShieldCheck, ShieldOff, Ban, Globe, Bot, AlertTriangle,
} from 'lucide-vue-next'

const loading = ref(false)
const saving = ref(false)
const error = ref('')
const success = ref('')

const readonly = ref({})
const detected = ref({ country: null, ip: null, geo_available: false })
const stats = ref(null)

// Geo ist konfiguriert, aber es kommt kein Länder-Header an → Regel läuft ins Leere.
const geoConfiguredButUnavailable = computed(() => {
  const hasGeoRules = (form.allowed_countries || '').trim() !== '' || (form.blocked_countries || '').trim() !== ''
  return hasGeoRules && !detected.value.geo_available
})

// Bearbeitbare Konfiguration
const form = reactive({
  enabled: true,
  block: true,
  mail: true,
  mail_min_severity: 'high',
  client_error_burst: 25,
  allowed_countries: '',
  blocked_countries: '',
  alert_emails: '',
})

// Events
const events = ref([])
const eventsMeta = ref({ current_page: 1, last_page: 1, total: 0 })
const eventFilters = reactive({ type: '', severity: '' })

const severityBadge = (s) => ({
  high: 'badge-error',
  medium: 'badge-warning',
  low: 'badge-ghost',
}[s] || 'badge-ghost')

const typeLabels = {
  login_failed: 'Login fehlgeschlagen',
  login_bruteforce: 'Login Brute-Force',
  geo_blocked: 'Geo-Sperre',
  bot_sensitive_path: 'Bot auf sensiblem Pfad',
  enumeration_burst: '4xx-Enumeration',
}
const typeLabel = (t) => typeLabels[t] || t

const eventTypes = computed(() => Object.keys(typeLabels))

onMounted(async () => {
  await Promise.all([loadConfig(), loadStats(), loadEvents()])
})

async function loadConfig() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await securityGuardApi.show()
    const cfg = (data.data || data).config
    readonly.value = (data.data || data).readonly || {}
    detected.value = (data.data || data).detected || { country: null, ip: null, geo_available: false }
    form.enabled = cfg.enabled
    form.block = cfg.block
    form.mail = cfg.mail
    form.mail_min_severity = cfg.mail_min_severity
    form.client_error_burst = cfg.client_error_burst
    form.allowed_countries = (cfg.allowed_countries || []).join(', ')
    form.blocked_countries = (cfg.blocked_countries || []).join(', ')
    form.alert_emails = (cfg.alert_emails || []).join(', ')
  } catch (e) {
    error.value = e.response?.data?.detail || 'Konfiguration konnte nicht geladen werden.'
  } finally {
    loading.value = false
  }
}

async function loadStats() {
  try {
    const { data } = await securityGuardApi.stats()
    stats.value = data.data || data
  } catch {
    // Statistik ist optional — Fehler still ignorieren.
  }
}

async function loadEvents() {
  try {
    const { data } = await securityGuardApi.events({
      type: eventFilters.type || undefined,
      severity: eventFilters.severity || undefined,
      per_page: 50,
    })
    events.value = data.data || []
    eventsMeta.value = data.meta || eventsMeta.value
  } catch (e) {
    error.value = e.response?.data?.detail || 'Events konnten nicht geladen werden.'
  }
}

function parseCountries(value) {
  return String(value || '')
    .split(',')
    .map((c) => c.trim().toUpperCase())
    .filter((c) => c.length === 2)
}

function parseEmails(value) {
  return String(value || '')
    .split(',')
    .map((e) => e.trim())
    .filter((e) => /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(e))
}

const emailsInvalid = computed(() => {
  if (!form.mail) return false
  const raw = String(form.alert_emails || '').split(',').map((e) => e.trim()).filter(Boolean)
  return raw.length !== parseEmails(form.alert_emails).length
})

async function save() {
  saving.value = true
  error.value = ''
  success.value = ''
  try {
    await securityGuardApi.update({
      enabled: form.enabled,
      block: form.block,
      mail: form.mail,
      mail_min_severity: form.mail_min_severity,
      client_error_burst: Number(form.client_error_burst),
      allowed_countries: parseCountries(form.allowed_countries),
      blocked_countries: parseCountries(form.blocked_countries),
      alert_emails: form.mail ? parseEmails(form.alert_emails) : [],
    })
    success.value = 'Guard-Konfiguration gespeichert.'
    await loadConfig()
  } catch (e) {
    error.value = e.response?.data?.detail
      || Object.values(e.response?.data?.errors || {})[0]?.[0]
      || 'Speichern fehlgeschlagen.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="space-y-6 max-w-5xl">
    <!-- Header -->
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-rose-500/10 via-red-500/5 to-orange-500/10 border border-rose-500/20 p-6">
      <div class="absolute top-0 right-0 w-64 h-64 bg-rose-500/5 rounded-full -translate-y-1/2 translate-x-1/2"></div>
      <div class="relative flex items-center justify-between">
        <div>
          <div class="flex items-center gap-3 mb-2">
            <ShieldAlert class="w-7 h-7 text-rose-500" />
            <h1 class="text-2xl font-bold">Security Guard</h1>
          </div>
          <p class="text-sm text-base-content/60 max-w-2xl">
            Erkennt verdächtige Muster (Login-Angriffe, Bot-Zugriffe, 4xx-Enumeration, Geo-Anomalien),
            protokolliert sie und blockiert harte Signale. Legitime authentifizierte Integrationen bleiben unberührt.
          </p>
        </div>
        <button class="btn btn-sm btn-ghost gap-2" :disabled="loading" @click="loadConfig(); loadStats(); loadEvents()">
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': loading }" /> Aktualisieren
        </button>
      </div>
    </div>

    <div v-if="error" class="alert alert-error text-sm">
      <AlertTriangle class="w-4 h-4" /> {{ error }}
    </div>
    <div v-if="success" class="alert alert-success text-sm">
      <ShieldCheck class="w-4 h-4" /> {{ success }}
    </div>

    <!-- Stats -->
    <div v-if="stats" class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="stat bg-base-200 rounded-xl p-4">
        <div class="stat-title text-xs">Events (24h)</div>
        <div class="stat-value text-2xl">{{ stats.total_24h }}</div>
      </div>
      <div class="stat bg-base-200 rounded-xl p-4">
        <div class="stat-title text-xs">Blockiert (24h)</div>
        <div class="stat-value text-2xl text-rose-500">{{ stats.blocked_24h }}</div>
      </div>
      <div class="stat bg-base-200 rounded-xl p-4">
        <div class="stat-title text-xs">Hoch</div>
        <div class="stat-value text-2xl">{{ stats.by_severity?.high || 0 }}</div>
      </div>
      <div class="stat bg-base-200 rounded-xl p-4">
        <div class="stat-title text-xs">Login/Brute-Force</div>
        <div class="stat-value text-2xl">{{ (stats.by_type?.login_bruteforce || 0) + (stats.by_type?.login_failed || 0) }}</div>
      </div>
    </div>

    <!-- Konfiguration -->
    <div class="card bg-base-100 border border-base-300">
      <div class="card-body gap-5">
        <h2 class="card-title text-base">Konfiguration</h2>

        <div class="grid md:grid-cols-2 gap-5">
          <label class="flex items-center justify-between gap-3 cursor-pointer">
            <span class="flex items-center gap-2 text-sm"><ShieldCheck class="w-4 h-4" /> Guard aktiv</span>
            <input v-model="form.enabled" type="checkbox" class="toggle toggle-success" />
          </label>

          <label class="flex items-center justify-between gap-3 cursor-pointer">
            <span class="flex items-center gap-2 text-sm"><Ban class="w-4 h-4" /> Harte Signale blockieren</span>
            <input v-model="form.block" type="checkbox" class="toggle toggle-error" />
          </label>

          <label class="flex items-center justify-between gap-3 cursor-pointer">
            <span class="flex items-center gap-2 text-sm">E-Mail-Alarm</span>
            <input v-model="form.mail" type="checkbox" class="toggle" />
          </label>

          <label class="flex items-center justify-between gap-3">
            <span class="text-sm">Alarm ab Severity</span>
            <select v-model="form.mail_min_severity" class="select select-bordered select-sm w-40">
              <option value="low">Niedrig</option>
              <option value="medium">Mittel</option>
              <option value="high">Hoch</option>
            </select>
          </label>

          <label class="flex items-center justify-between gap-3">
            <span class="text-sm">4xx-Schwelle (Enumeration)</span>
            <input v-model="form.client_error_burst" type="number" min="1" class="input input-bordered input-sm w-40" />
          </label>
        </div>

        <!-- E-Mail-Empfänger nur anzeigen, wenn E-Mail-Alarm aktiv ist -->
        <div v-if="form.mail" class="rounded-lg bg-base-200/50 p-4">
          <label class="text-sm flex items-center gap-2 mb-1">Alarm-E-Mail-Adressen</label>
          <input
            v-model="form.alert_emails"
            type="text"
            placeholder="z. B. security@firma.de, admin@firma.de"
            class="input input-bordered input-sm w-full"
            :class="{ 'input-error': emailsInvalid }"
          />
          <p class="text-xs mt-1" :class="emailsInvalid ? 'text-error' : 'text-base-content/50'">
            <template v-if="emailsInvalid">Bitte gültige, kommagetrennte E-Mail-Adressen eingeben.</template>
            <template v-else>Kommagetrennt. Zusätzlich werden aktive Admins automatisch benachrichtigt.</template>
          </p>
        </div>

        <div class="divider my-0"></div>

        <div class="grid md:grid-cols-2 gap-5">
          <div>
            <label class="text-sm flex items-center gap-2 mb-1"><Globe class="w-4 h-4" /> Erlaubte Länder (Whitelist)</label>
            <input v-model="form.allowed_countries" type="text" placeholder="z. B. DE, AT, CH" class="input input-bordered input-sm w-full" />
            <p class="text-xs text-base-content/50 mt-1">Leer = keine Whitelist. Alle anderen Länder gelten als verdächtig.</p>
          </div>
          <div>
            <label class="text-sm flex items-center gap-2 mb-1"><Ban class="w-4 h-4" /> Gesperrte Länder (Blacklist)</label>
            <input v-model="form.blocked_countries" type="text" placeholder="z. B. RU, KP" class="input input-bordered input-sm w-full" />
            <p class="text-xs text-base-content/50 mt-1">ISO-2-Codes (z. B. GB, nicht EN). Erfordert Cloudflare/Proxy-Header.</p>
          </div>
        </div>

        <!-- Aktuelle Geo-Erkennung / Warnung -->
        <div class="text-xs flex items-center gap-2 text-base-content/60">
          <Globe class="w-4 h-4" />
          <span>Aktuell erkannt: Land <b>{{ detected.country || '—' }}</b> · IP <span class="font-mono">{{ detected.ip || '—' }}</span></span>
        </div>
        <div v-if="geoConfiguredButUnavailable" class="alert alert-warning text-sm">
          <AlertTriangle class="w-4 h-4" />
          <span>
            Es wird aktuell kein Land erkannt — die Geo-Regeln greifen daher <b>nicht</b>.
            Zwei Wege: (1) hinter <b>Cloudflare/Proxy</b> laufen, der einen Header wie
            <span class="font-mono">CF-IPCountry</span> setzt, oder (2) die lokale
            <b>MaxMind-GeoLite2</b>-Datenbank installieren
            (<span class="font-mono">php artisan pim:geoip-update</span>, benötigt einen kostenlosen
            <span class="font-mono">MAXMIND_LICENSE_KEY</span>).
            <template v-if="readonly.geoip && readonly.geoip.enabled === false"> — GeoIP ist derzeit deaktiviert.</template>
          </span>
        </div>

        <div class="flex justify-end">
          <button class="btn pim-btn-primary btn-sm gap-2" :disabled="saving || emailsInvalid" @click="save">
            <Save class="w-4 h-4" /> Speichern
          </button>
        </div>

        <!-- Readonly Referenz -->
        <div class="collapse collapse-arrow bg-base-200/50 rounded-lg">
          <input type="checkbox" />
          <div class="collapse-title text-sm font-medium">Nur-Lese-Referenz (via .env / config)</div>
          <div class="collapse-content text-xs space-y-2">
            <div><span class="opacity-60">Länder-Header:</span> {{ readonly.country_header || '—' }}</div>
            <div><span class="opacity-60">IP-Header:</span> {{ readonly.ip_header || '—' }}</div>
            <div>
              <span class="opacity-60">GeoIP (MaxMind):</span>
              <span v-if="readonly.geoip && readonly.geoip.available" class="text-success">aktiv (DB geladen)</span>
              <span v-else-if="readonly.geoip && readonly.geoip.enabled === false">deaktiviert</span>
              <span v-else class="text-warning">keine DB — via „php artisan pim:geoip-update" installieren</span>
            </div>
            <div><span class="opacity-60">Fenster / Cooldown (s):</span> {{ readonly.window_seconds }} / {{ readonly.block_cooldown_seconds }}</div>
            <div class="flex items-start gap-2"><Bot class="w-4 h-4 mt-0.5" /> <span class="opacity-60">Bot-Muster:</span> {{ (readonly.bot_user_agent_patterns || []).join(', ') }}</div>
            <div><span class="opacity-60">Sensible Pfade:</span> {{ (readonly.sensitive_path_patterns || []).join(', ') }}</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Events -->
    <div class="card bg-base-100 border border-base-300">
      <div class="card-body">
        <div class="flex items-center justify-between flex-wrap gap-3">
          <h2 class="card-title text-base">Erkannte Ereignisse ({{ eventsMeta.total }})</h2>
          <div class="flex items-center gap-2">
            <select v-model="eventFilters.type" class="select select-bordered select-xs" @change="loadEvents">
              <option value="">Alle Typen</option>
              <option v-for="t in eventTypes" :key="t" :value="t">{{ typeLabel(t) }}</option>
            </select>
            <select v-model="eventFilters.severity" class="select select-bordered select-xs" @change="loadEvents">
              <option value="">Alle Schweregrade</option>
              <option value="high">Hoch</option>
              <option value="medium">Mittel</option>
              <option value="low">Niedrig</option>
            </select>
          </div>
        </div>

        <div class="overflow-x-auto mt-2">
          <table class="table table-sm table-zebra">
            <thead>
              <tr>
                <th>Zeit</th>
                <th>Typ</th>
                <th>Severity</th>
                <th>IP</th>
                <th>Land</th>
                <th>Pfad</th>
                <th>Aktion</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="ev in events" :key="ev.id">
                <td class="whitespace-nowrap text-xs">{{ new Date(ev.created_at).toLocaleString('de-DE') }}</td>
                <td class="text-xs">{{ typeLabel(ev.type) }}</td>
                <td><span class="badge badge-sm" :class="severityBadge(ev.severity)">{{ ev.severity }}</span></td>
                <td class="font-mono text-xs">{{ ev.ip_address || '—' }}</td>
                <td class="text-xs">{{ ev.country || '—' }}</td>
                <td class="font-mono text-xs max-w-[16rem] truncate" :title="ev.path">{{ ev.method }} {{ ev.path }}</td>
                <td>
                  <span v-if="ev.blocked" class="badge badge-sm badge-error gap-1"><ShieldOff class="w-3 h-3" /> blockiert</span>
                  <span v-else class="badge badge-sm badge-ghost">erkannt</span>
                </td>
              </tr>
              <tr v-if="!events.length">
                <td colspan="7" class="text-center text-sm text-base-content/50 py-6">Keine Ereignisse.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>
