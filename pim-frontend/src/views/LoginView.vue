<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useLicenseStore } from '@/stores/license'
import { useConnectorsStore } from '@/stores/connectors'
import authApi from '@/api/auth'
import AnyPimLogo from '@/components/shared/AnyPimLogo.vue'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const licenseStore = useLicenseStore()
const connectorsStore = useConnectorsStore()

const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)
const ssoEnabled = ref(false)
const ssoLabel = ref('Mit Microsoft anmelden')
const ssoLoading = ref(false)

// Demo-/Tutorial-Videos (Portal-Charakter)
const videos = ref([])
const activeVideo = ref(null)

// Externe Links
// VITE_DOCS_BASE_PATH wird von setup.sh/update.sh gesetzt (Root-Modus -> "/web/help/",
// Unterverzeichnis-Modus -> "${WEB_PATH}/help/" -- siehe Apache-Alias). Kann NICHT aus
// VITE_BASE_PATH abgeleitet werden: im Root-Modus liegt die Doku per fixer Konvention
// unter "/web/help/", nicht unter der (dort leeren) App-Basis -- das hatte zuvor einen
// kaputten Doku-Link im Root-Modus verursacht.
const DOCS_BASE = (import.meta.env.VITE_DOCS_BASE_PATH || '/web/help/').replace(/\/+$/, '')
const DOCS_URL = `${DOCS_BASE}/de/`
const VENDOR_URL = 'https://www.incoxx.com'

async function handleLogin() {
  error.value = ''
  loading.value = true
  try {
    await authStore.login({ email: email.value, password: password.value })
    licenseStore.fetchLicense()
    connectorsStore.loadConfiguredPlugins()
    router.push(route.query.redirect || '/products')
  } catch (e) {
    error.value = e.response?.data?.detail || e.response?.data?.title || 'Anmeldung fehlgeschlagen'
  } finally {
    loading.value = false
  }
}

function handleSsoLogin() {
  const apiBase = import.meta.env.VITE_API_BASE_URL || '/api/v1'
  const redirectPath = route.query.redirect || '/products'
  window.location.href = `${apiBase}/auth/sso/redirect?redirect=${encodeURIComponent(redirectPath)}`
}

async function handleSsoCallback() {
  const params = new URLSearchParams(window.location.search)
  const ssoToken = params.get('sso_token')
  const ssoError = params.get('sso_error')

  if (ssoError) {
    error.value = ssoError
    window.history.replaceState({}, '', window.location.pathname)
    return
  }

  if (!ssoToken) return

  ssoLoading.value = true

  try {
    authStore.token = ssoToken
    localStorage.setItem('pim_token', ssoToken)

    await authStore.checkAuth()

    if (!authStore.user) {
      throw new Error('User info could not be loaded')
    }

    licenseStore.fetchLicense()
    connectorsStore.loadConfiguredPlugins()

    const redirectPath = params.get('redirect') || '/products'
    window.history.replaceState({}, '', window.location.pathname)
    router.push(redirectPath)
  } catch (e) {
    error.value = 'SSO-Anmeldung fehlgeschlagen. Bitte versuchen Sie es erneut.'
    authStore.token = null
    localStorage.removeItem('pim_token')
    window.history.replaceState({}, '', window.location.pathname)
  } finally {
    ssoLoading.value = false
  }
}

async function loadSsoConfig() {
  try {
    const { data } = await authApi.ssoConfig()
    const cfg = data.data || data
    ssoEnabled.value = cfg.enabled
    if (cfg.label) ssoLabel.value = cfg.label
  } catch {
    // SSO not available — keep button hidden
  }
}

async function loadVideos() {
  try {
    const { data } = await authApi.demoVideos()
    videos.value = Array.isArray(data?.data) ? data.data : []
  } catch {
    // Keine Videos verfügbar — Abschnitt bleibt ausgeblendet
    videos.value = []
  }
}

function openVideo(video) {
  activeVideo.value = video
}

function closeVideo() {
  activeVideo.value = null
}

function onKeydown(e) {
  if (e.key === 'Escape') closeVideo()
}

onMounted(async () => {
  // Handle SSO callback first (has sso_token in URL)
  await handleSsoCallback()
  // Then load SSO config + demo videos in parallel
  loadSsoConfig()
  loadVideos()
  window.addEventListener('keydown', onKeydown)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown)
})
</script>

<template>
  <div class="login-root">
    <!-- Hintergrund-Verläufe -->
    <div class="login-bg" aria-hidden="true">
      <span class="blob blob-1"></span>
      <span class="blob blob-2"></span>
      <span class="blob blob-3"></span>
      <span class="grid-overlay"></span>
    </div>

    <div class="login-content">
      <main class="login-main">
        <!-- Marke -->
        <div class="brand">
          <div class="brand-mark">
            <AnyPimLogo size="lg" :show-text="false" />
          </div>
          <h1 class="brand-word"><span class="any">any</span><span class="pim">PIM</span></h1>
          <p class="brand-tagline">Product Information Management</p>
        </div>

        <!-- SSO Loading -->
        <div v-if="ssoLoading" class="login-card login-card--center">
          <div class="spinner"></div>
          <p class="muted text-sm">Anmeldung wird verarbeitet…</p>
        </div>

        <!-- Login Form -->
        <template v-else>
          <form @submit.prevent="handleLogin" class="login-card" data-testid="login-form">
            <div v-if="error" class="login-error">{{ error }}</div>

            <div class="field">
              <label class="field-label">E-Mail</label>
              <input
                v-model="email"
                type="email"
                required
                class="login-input"
                placeholder="admin@example.com"
                autofocus
                data-testid="input-email"
              />
            </div>

            <div class="field">
              <label class="field-label">Passwort</label>
              <input
                v-model="password"
                type="password"
                required
                class="login-input"
                placeholder="••••••••"
                data-testid="input-password"
              />
            </div>

            <button type="submit" class="btn-gradient" :disabled="loading" data-testid="btn-login">
              {{ loading ? 'Anmelden…' : 'Anmelden' }}
            </button>

            <!-- SSO Button -->
            <template v-if="ssoEnabled">
              <div class="divider"><span>oder</span></div>
              <button type="button" @click="handleSsoLogin" class="btn-sso">
                <svg class="w-5 h-5" viewBox="0 0 21 21" xmlns="http://www.w3.org/2000/svg">
                  <rect x="1" y="1" width="9" height="9" fill="#f25022" />
                  <rect x="11" y="1" width="9" height="9" fill="#7fba00" />
                  <rect x="1" y="11" width="9" height="9" fill="#00a4ef" />
                  <rect x="11" y="11" width="9" height="9" fill="#ffb900" />
                </svg>
                {{ ssoLabel }}
              </button>
            </template>
          </form>
        </template>

        <!-- Tutorials / Erste Einblicke -->
        <section v-if="videos.length" class="discover">
          <div class="discover-head">
            <h2>Erste Einblicke</h2>
            <p>Kurze Touren durch anyPIM — direkt hier ansehen.</p>
          </div>
          <div class="video-grid">
            <button
              v-for="video in videos"
              :key="video.slug"
              type="button"
              class="video-card"
              @click="openVideo(video)"
            >
              <span class="video-play" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                  <path d="M8 5v14l11-7z" />
                </svg>
              </span>
              <span class="video-meta">
                <span class="video-title">{{ video.title }}</span>
                <span v-if="video.description" class="video-desc">{{ video.description }}</span>
              </span>
            </button>
          </div>
        </section>
      </main>

      <!-- Footer-Links -->
      <footer class="login-footer">
        <a :href="DOCS_URL" target="_blank" rel="noopener" class="footer-link">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-4 h-4">
            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" /><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" />
          </svg>
          Dokumentation
        </a>
        <span class="footer-sep">·</span>
        <a :href="VENDOR_URL" target="_blank" rel="noopener" class="footer-link">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-4 h-4">
            <path d="M3 21h18" /><path d="M5 21V7l8-4v18" /><path d="M19 21V11l-6-4" />
          </svg>
          Hersteller&nbsp;incoxx
        </a>
      </footer>
    </div>

    <!-- Video-Modal -->
    <transition name="fade">
      <div v-if="activeVideo" class="video-modal" @click.self="closeVideo">
        <div class="video-modal-inner">
          <div class="video-modal-bar">
            <span class="video-modal-title">{{ activeVideo.title }}</span>
            <button type="button" class="video-modal-close" @click="closeVideo" aria-label="Schließen">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5">
                <path d="M18 6 6 18M6 6l12 12" />
              </svg>
            </button>
          </div>
          <video :src="activeVideo.url" controls autoplay playsinline class="video-player"></video>
          <p v-if="activeVideo.description" class="video-modal-desc">{{ activeVideo.description }}</p>
        </div>
      </div>
    </transition>
  </div>
</template>

<style scoped>
.login-root {
  position: relative;
  min-height: 100vh;
  overflow: hidden;
  background: #0a0e1a;
  color: #e7eaf3;
  font-family: var(--font-ui);
}

/* ── Hintergrund ───────────────────────────────── */
.login-bg {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(1200px 800px at 15% -10%, rgba(99, 102, 241, 0.18), transparent 60%),
    radial-gradient(1000px 700px at 110% 120%, rgba(6, 182, 212, 0.16), transparent 60%),
    linear-gradient(160deg, #0b1020 0%, #0a0e1a 55%, #090c16 100%);
}
.blob {
  position: absolute;
  border-radius: 9999px;
  filter: blur(80px);
  opacity: 0.5;
}
.blob-1 { width: 460px; height: 460px; top: -120px; left: -80px; background: #6366f1; }
.blob-2 { width: 380px; height: 380px; top: 30%; left: 50%; transform: translateX(-50%); background: #2563eb; opacity: 0.32; }
.blob-3 { width: 520px; height: 520px; bottom: -160px; right: -100px; background: #06b6d4; opacity: 0.4; }
.grid-overlay {
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(255, 255, 255, 0.025) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255, 255, 255, 0.025) 1px, transparent 1px);
  background-size: 44px 44px;
  mask-image: radial-gradient(circle at 50% 40%, black, transparent 80%);
}

/* ── Layout ────────────────────────────────────── */
.login-content {
  position: relative;
  z-index: 1;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}
.login-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 24px 24px;
}

/* ── Marke ─────────────────────────────────────── */
.brand {
  text-align: center;
  margin-bottom: 28px;
}
.brand-mark {
  display: inline-flex;
  transform: scale(1.7);
  margin-bottom: 22px;
  filter: drop-shadow(0 6px 24px rgba(99, 102, 241, 0.45));
}
.brand-word {
  font-size: 44px;
  letter-spacing: -0.02em;
  line-height: 1.25;
  padding-bottom: 0.06em;
}
.brand-word .any { font-weight: 500; color: #60a5fa; }
.brand-word .pim { font-weight: 800; color: #ffffff; }
.brand-tagline {
  margin-top: 12px;
  font-size: 12px;
  font-weight: 500;
  letter-spacing: 0.32em;
  text-transform: uppercase;
  color: #8a93ad;
}

/* ── Karte / Formular ──────────────────────────── */
.login-card {
  width: 100%;
  max-width: 380px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 26px;
  border-radius: 16px;
  background: rgba(255, 255, 255, 0.045);
  border: 1px solid rgba(255, 255, 255, 0.09);
  box-shadow: 0 24px 60px -20px rgba(0, 0, 0, 0.6), inset 0 1px 0 rgba(255, 255, 255, 0.06);
  backdrop-filter: blur(14px);
}
.login-card--center {
  align-items: center;
  text-align: center;
  gap: 12px;
}
.field { display: flex; flex-direction: column; gap: 6px; }
.field-label {
  font-size: 12px;
  font-weight: 500;
  color: #aab2c8;
}
.login-input {
  width: 100%;
  padding: 11px 13px;
  font-size: 14px;
  color: #f2f4fa;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 10px;
  transition: border-color 120ms ease, box-shadow 120ms ease, background 120ms ease;
}
.login-input::placeholder { color: #6b7390; }
.login-input:focus {
  outline: none;
  background: rgba(255, 255, 255, 0.07);
  border-color: #818cf8;
  box-shadow: 0 0 0 3px rgba(129, 140, 248, 0.25);
}
.login-error {
  padding: 10px 12px;
  border-radius: 10px;
  font-size: 13px;
  color: #fecaca;
  background: rgba(220, 38, 38, 0.14);
  border: 1px solid rgba(248, 113, 113, 0.3);
}

.btn-gradient {
  width: 100%;
  padding: 12px 16px;
  font-size: 14px;
  font-weight: 600;
  color: #fff;
  border: none;
  border-radius: 10px;
  cursor: pointer;
  background: linear-gradient(135deg, #6366f1 0%, #2563eb 50%, #06b6d4 100%);
  background-size: 160% 160%;
  background-position: 0% 50%;
  box-shadow: 0 10px 26px -8px rgba(99, 102, 241, 0.65);
  transition: background-position 300ms ease, transform 80ms ease, box-shadow 200ms ease;
}
.btn-gradient:hover:not(:disabled) {
  background-position: 100% 50%;
  box-shadow: 0 12px 30px -8px rgba(37, 99, 235, 0.7);
}
.btn-gradient:active:not(:disabled) { transform: translateY(1px); }
.btn-gradient:disabled { opacity: 0.6; cursor: not-allowed; }

.divider {
  display: flex;
  align-items: center;
  text-align: center;
  color: #6b7390;
  font-size: 12px;
}
.divider::before,
.divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: rgba(255, 255, 255, 0.1);
}
.divider span { padding: 0 12px; }

.btn-sso {
  width: 100%;
  padding: 11px 16px;
  font-size: 14px;
  font-weight: 500;
  color: #e7eaf3;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.14);
  border-radius: 10px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  transition: background 120ms ease, border-color 120ms ease;
}
.btn-sso:hover {
  background: rgba(255, 255, 255, 0.09);
  border-color: rgba(255, 255, 255, 0.22);
}

.spinner {
  width: 32px;
  height: 32px;
  border-radius: 9999px;
  border: 2px solid rgba(255, 255, 255, 0.15);
  border-bottom-color: #818cf8;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.muted { color: #8a93ad; }

/* ── Tutorials ─────────────────────────────────── */
.discover {
  width: 100%;
  max-width: 880px;
  margin-top: 56px;
}
.discover-head { text-align: center; margin-bottom: 20px; }
.discover-head h2 {
  font-size: 18px;
  font-weight: 600;
  color: #eef0f7;
}
.discover-head p {
  margin-top: 4px;
  font-size: 13px;
  color: #8a93ad;
}
.video-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px;
}
@media (min-width: 640px) { .video-grid { grid-template-columns: 1fr 1fr; } }
@media (min-width: 1024px) { .video-grid { grid-template-columns: 1fr 1fr 1fr; } }

.video-card {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 14px;
  text-align: left;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.08);
  cursor: pointer;
  transition: transform 140ms ease, border-color 140ms ease, background 140ms ease;
}
.video-card:hover {
  transform: translateY(-2px);
  background: rgba(255, 255, 255, 0.07);
  border-color: rgba(129, 140, 248, 0.5);
}
.video-play {
  flex: none;
  width: 38px;
  height: 38px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  color: #fff;
  background: linear-gradient(135deg, #6366f1, #2563eb 55%, #06b6d4);
  box-shadow: 0 6px 16px -6px rgba(99, 102, 241, 0.8);
}
.video-meta { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
.video-title { font-size: 14px; font-weight: 600; color: #eef0f7; }
.video-desc {
  font-size: 12px;
  line-height: 1.45;
  color: #8a93ad;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* ── Footer ────────────────────────────────────── */
.login-footer {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-wrap: wrap;
  gap: 8px;
  padding: 22px 24px 28px;
  font-size: 13px;
}
.footer-link {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  color: #aab2c8;
  text-decoration: none;
  transition: color 120ms ease;
}
.footer-link:hover { color: #fff; }
.footer-sep { color: #4b5570; }

/* ── Video-Modal ───────────────────────────────── */
.video-modal {
  position: fixed;
  inset: 0;
  z-index: 50;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  background: rgba(5, 7, 14, 0.78);
  backdrop-filter: blur(6px);
}
.video-modal-inner {
  width: 100%;
  max-width: 880px;
  border-radius: 16px;
  overflow: hidden;
  background: #0d1222;
  border: 1px solid rgba(255, 255, 255, 0.1);
  box-shadow: 0 30px 80px -20px rgba(0, 0, 0, 0.8);
}
.video-modal-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}
.video-modal-title { font-size: 14px; font-weight: 600; color: #eef0f7; }
.video-modal-close {
  display: inline-flex;
  padding: 6px;
  border-radius: 8px;
  color: #aab2c8;
  cursor: pointer;
  transition: background 120ms ease, color 120ms ease;
}
.video-modal-close:hover { background: rgba(255, 255, 255, 0.08); color: #fff; }
.video-player { width: 100%; max-height: 70vh; display: block; background: #000; }
.video-modal-desc {
  padding: 12px 16px 16px;
  font-size: 13px;
  line-height: 1.5;
  color: #aab2c8;
}

/* ── Transitions ───────────────────────────────── */
.fade-enter-active,
.fade-leave-active { transition: opacity 160ms ease; }
.fade-enter-from,
.fade-leave-to { opacity: 0; }
</style>
