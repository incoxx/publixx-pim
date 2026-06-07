import { defineStore } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { resolveApiUrl } from '@/api/client'

/**
 * Copilot-Store — In-App Chat-Assistent.
 *
 * Hält den (clientseitigen, pro Session) Gesprächsverlauf im Anthropic-
 * Message-Format, streamt Antworten via SSE und steuert den Bestätigungs-Flow
 * für schreibende Aktionen (graphql_mutate).
 */
export const useCopilotStore = defineStore('copilot', {
  state: () => ({
    open: false,
    busy: false,
    // Anthropic-Message-Format: { role, content: string | block[] }
    messages: [],
    // Live-Puffer des gerade streamenden Assistenten-Turns
    streamingText: '',
    toolStatus: null,          // z.B. "Durchsucht Produkte…"
    error: null,
    // Offene Mutations-Bestätigung: { toolUseId, input, assistantContent, context }
    pendingTool: null,
    // PIM-Such-Aktionen je Nachrichten-Index: { [index]: { query } }
    pimSearchByIndex: {},
    // Gekürzte Antworten (max_tokens) je Nachrichten-Index: { [index]: true }
    truncatedByIndex: {},
    // Letzter Kontext — für "Erneut versuchen"
    lastContext: {},
  }),

  getters: {
    // Anzeige-Transkript: nur Text aus User-/Assistent-Turns
    transcript(state) {
      return state.messages
        .map((m, idx) => ({
          idx,
          role: m.role,
          text: extractText(m.content),
          pimSearch: state.pimSearchByIndex[idx] || null,
          truncated: state.truncatedByIndex[idx] || false,
        }))
        .filter((m) => m.text !== '')
    },
  },

  actions: {
    toggle() { this.open = !this.open },
    openPanel() { this.open = true },
    closePanel() { this.open = false },

    reset() {
      this.messages = []
      this.streamingText = ''
      this.toolStatus = null
      this.error = null
      this.pendingTool = null
      this.pimSearchByIndex = {}
      this.truncatedByIndex = {}
    },

    /** Nutzer-Nachricht senden und Antwort streamen. */
    async send(text, context = {}) {
      const trimmed = (text || '').trim()
      if (trimmed === '' || this.busy) return

      this.messages.push({ role: 'user', content: trimmed })
      await this.runTurn(context)
    },

    /**
     * Baut die an die API gesendete History: vom MCP-Connector erzeugte Blöcke
     * (mcp_tool_use / mcp_tool_result) werden entfernt — sie wieder einzureichen
     * ist fehleranfällig (HTTP 400 "input: Input should be an object"). Der
     * Text-Verlauf genügt dem Modell; Client-Tool-Blöcke (graphql_mutate +
     * tool_result) bleiben erhalten, damit der Bestätigungs-Flow funktioniert.
     */
    buildApiMessages() {
      return this.messages.map((m) => {
        if (!Array.isArray(m.content)) return m
        const filtered = m.content.filter(
          (b) => b.type !== 'mcp_tool_use' && b.type !== 'mcp_tool_result',
        )
        if (filtered.length === 0) {
          // Leeren Turn vermeiden → auf Text zurückfallen
          return { role: m.role, content: extractText(m.content) || ' ' }
        }
        return { role: m.role, content: filtered }
      })
    },

    /**
     * Einen Assistenten-Turn ausführen: Request an /copilot/chat, SSE parsen,
     * Tool-Status / Text / Mutations-Bestätigung verarbeiten.
     */
    async runTurn(context = {}) {
      this.busy = true
      this.error = null
      this.streamingText = ''
      this.toolStatus = null
      this.lastContext = context

      const blocks = {}          // index → content-block (laufend befüllt)
      const jsonBuf = {}         // index → akkumuliertes input_json
      let stopReason = null

      try {
        const authStore = useAuthStore()
        const resp = await fetch(resolveApiUrl('copilot/chat'), {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'text/event-stream',
            'Authorization': `Bearer ${authStore.token}`,
            ...xsrfHeader(),
          },
          body: JSON.stringify({ messages: this.buildApiMessages(), context }),
        })

        if (!resp.ok || !resp.body) {
          throw new Error(httpErrorMessage(resp.status))
        }

        const reader = resp.body.getReader()
        const decoder = new TextDecoder()
        let buffer = ''

        // eslint-disable-next-line no-constant-condition
        while (true) {
          const { done, value } = await reader.read()
          if (done) break
          buffer += decoder.decode(value, { stream: true })

          let sep
          while ((sep = buffer.indexOf('\n\n')) !== -1) {
            const rawEvent = buffer.slice(0, sep)
            buffer = buffer.slice(sep + 2)
            const evt = parseSseEvent(rawEvent)
            if (!evt) continue
            stopReason = this.handleEvent(evt, blocks, jsonBuf) ?? stopReason
          }
        }
      } catch (e) {
        this.error = e?.message
          || 'Die Verbindung zum Copilot wurde unterbrochen. Bitte versuche es erneut.'
        this.busy = false
        this.toolStatus = null
        return
      }

      // Assistenten-Turn aus den gesammelten Blöcken zusammensetzen
      const assistantContent = Object.keys(blocks)
        .sort((a, b) => Number(a) - Number(b))
        .map((i) => finalizeBlock(blocks[i], jsonBuf[i]))
        .filter(Boolean)

      this.streamingText = ''
      this.toolStatus = null

      if (assistantContent.length > 0) {
        this.messages.push({ role: 'assistant', content: assistantContent })
        const idx = this.messages.length - 1

        // search_products-Aufruf erkennen → "Im PIM anzeigen"-Aktion anbieten
        const search = extractPimSearch(assistantContent)
        if (search) {
          this.pimSearchByIndex[idx] = search
        }

        // Antwort wegen Token-Limit abgeschnitten? → Hinweis am Turn vermerken
        if (stopReason === 'max_tokens') {
          this.truncatedByIndex[idx] = true
        }
      }

      // Schreibende Aktion (Client-Tool)? → zur Bestätigung anhalten.
      const writeBlock = assistantContent.find(
        (b) => b.type === 'tool_use'
          && (b.name === 'graphql_mutate' || b.name === 'update_product_attribute'),
      )

      if (stopReason === 'tool_use' && writeBlock) {
        this.pendingTool = {
          name: writeBlock.name,
          toolUseId: writeBlock.id,
          input: writeBlock.input || {},
          context,
        }
        this.busy = false
        return
      }

      // Sicherheitsnetz: Turn ohne Text, ohne Fehler, ohne Bestätigung →
      // verständlicher Hinweis statt leerer Antwort.
      const hasText = assistantContent.some((b) => b.type === 'text' && (b.text || '').trim() !== '')
      if (!hasText && !this.error) {
        this.error = 'Der Copilot konnte keine Antwort erzeugen. Bitte versuche es erneut oder formuliere die Frage anders.'
      }

      this.busy = false
    },

    /** Letzten fehlgeschlagenen Turn erneut versuchen. */
    async retry() {
      if (this.busy) return
      this.error = null

      // Einen unvollständigen Assistenten-Turn (ohne Text) vorher entfernen
      const last = this.messages[this.messages.length - 1]
      if (last && last.role === 'assistant' && extractText(last.content) === '') {
        const idx = this.messages.length - 1
        this.messages.pop()
        delete this.pimSearchByIndex[idx]
        delete this.truncatedByIndex[idx]
      }

      await this.runTurn(this.lastContext || {})
    },

    /** Verarbeitet ein einzelnes SSE-Event. Gibt ggf. stop_reason zurück. */
    handleEvent(evt, blocks, jsonBuf) {
      const { event, data } = evt

      if (event === 'copilot_error') {
        this.error = data?.message || 'Copilot-Fehler.'
        return null
      }
      if (event === 'error') {
        this.error = data?.error?.message || 'Stream-Fehler.'
        return null
      }

      const type = data?.type

      if (type === 'content_block_start') {
        const cb = data.content_block || {}
        blocks[data.index] = { ...cb }
        if (cb.type === 'text') blocks[data.index].text = ''
        if (cb.type === 'mcp_tool_use' || cb.type === 'server_tool_use') {
          this.toolStatus = toolLabel(cb.name)
        }
        return null
      }

      if (type === 'content_block_delta') {
        const d = data.delta || {}
        if (d.type === 'text_delta') {
          if (blocks[data.index]) blocks[data.index].text = (blocks[data.index].text || '') + d.text
          this.streamingText += d.text
        } else if (d.type === 'input_json_delta') {
          jsonBuf[data.index] = (jsonBuf[data.index] || '') + (d.partial_json || '')
        }
        return null
      }

      if (type === 'content_block_stop') {
        // Tool-Status zurücksetzen, sobald ein Tool-Block fertig ist
        const b = blocks[data.index]
        if (b && (b.type === 'mcp_tool_use' || b.type === 'server_tool_use')) {
          this.toolStatus = null
        }
        return null
      }

      if (type === 'message_delta') {
        return data.delta?.stop_reason ?? null
      }

      return null
    },

    /** Bestätigte Schreib-Aktion ausführen und Gespräch fortsetzen. */
    async confirmTool() {
      const pending = this.pendingTool
      if (!pending || this.busy) return
      this.pendingTool = null
      this.busy = true

      let result
      try {
        const authStore = useAuthStore()
        const resp = await fetch(resolveApiUrl('copilot/execute-tool'), {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Authorization': `Bearer ${authStore.token}`,
            ...xsrfHeader(),
          },
          body: JSON.stringify({ name: pending.name, input: pending.input }),
        })
        result = await resp.json()
      } catch (e) {
        result = { is_error: true, content: e?.message || 'Ausführung fehlgeschlagen.' }
      }

      this.messages.push({
        role: 'user',
        content: [{
          type: 'tool_result',
          tool_use_id: pending.toolUseId,
          content: result?.content ?? '',
          is_error: Boolean(result?.is_error),
        }],
      })

      await this.runTurn(pending.context || {})
    },

    /** Schreib-Aktion ablehnen — Claude erhält eine Ablehnung und kann reagieren. */
    async denyTool() {
      const pending = this.pendingTool
      if (!pending || this.busy) return
      this.pendingTool = null
      this.busy = true

      this.messages.push({
        role: 'user',
        content: [{
          type: 'tool_result',
          tool_use_id: pending.toolUseId,
          content: 'Der Nutzer hat diese Aktion abgelehnt. Führe sie nicht aus und frage nach, wie fortgefahren werden soll.',
          is_error: true,
        }],
      })

      await this.runTurn(pending.context || {})
    },
  },
})

// ── Fehler-/SSE-/Block-Helfer ───────────────────────────────────────────────

/** Verständliche Meldung für HTTP-Transportfehler der Chat-Route. */
function httpErrorMessage(status) {
  if (status === 401) return 'Deine Sitzung ist abgelaufen. Bitte melde dich erneut an.'
  if (status === 419) return 'Deine Sitzung ist abgelaufen. Bitte lade die Seite neu.'
  if (status === 429) return 'Zu viele Anfragen in kurzer Zeit. Bitte einen Moment warten und erneut versuchen.'
  if (status >= 500) return 'Der Copilot ist momentan nicht erreichbar. Bitte versuche es später erneut.'
  return `Der Copilot konnte nicht erreicht werden (Fehler ${status}).`
}


/**
 * Liest das XSRF-TOKEN-Cookie und liefert den CSRF-Header (wie axios es bei
 * same-origin-Requests automatisch tut). Nötig, weil natives fetch das nicht
 * selbst übernimmt und Sanctum im stateful-Modus CSRF erzwingt → sonst HTTP 419.
 */
function xsrfHeader() {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)
  return match ? { 'X-XSRF-TOKEN': decodeURIComponent(match[1]) } : {}
}

function parseSseEvent(raw) {
  let event = 'message'
  const dataLines = []
  for (const line of raw.split('\n')) {
    if (line.startsWith('event:')) event = line.slice(6).trim()
    else if (line.startsWith('data:')) dataLines.push(line.slice(5).trim())
  }
  if (dataLines.length === 0) return null
  const dataStr = dataLines.join('\n')
  try {
    return { event, data: JSON.parse(dataStr) }
  } catch {
    return { event, data: null }
  }
}

function finalizeBlock(block, jsonStr) {
  if (!block) return null
  const out = { ...block }
  if (out.type === 'tool_use' || out.type === 'mcp_tool_use') {
    // Tool-Input wird via input_json_delta gestreamt
    if (jsonStr !== undefined && jsonStr !== '') {
      try {
        out.input = JSON.parse(jsonStr)
      } catch {
        // bestehenden input (aus content_block_start) beibehalten
      }
    }
    // input MUSS ein Objekt sein — Tools ohne Argumente (z.B. list_templates)
    // liefern keine Deltas; sonst lehnt die API die History ab:
    // "mcp_tool_use.input: Input should be an object"
    if (typeof out.input !== 'object' || out.input === null) {
      out.input = {}
    }
  }
  return out
}

/**
 * Sucht im Assistenten-Turn den letzten search_products-Aufruf mit Suchbegriff
 * und liefert die "Im PIM anzeigen"-Such-Aktion (oder null).
 */
function extractPimSearch(content) {
  if (!Array.isArray(content)) return null
  let found = null
  for (const b of content) {
    if (b.type === 'mcp_tool_use' && b.name === 'search_products') {
      const query = (b.input?.query || '').trim()
      if (query !== '') found = { query }
    }
  }
  return found
}

function extractText(content) {
  if (typeof content === 'string') return content
  if (!Array.isArray(content)) return ''
  return content
    .filter((b) => b.type === 'text')
    .map((b) => b.text || '')
    .join('')
    .trim()
}

function toolLabel(name) {
  switch (name) {
    case 'search_products': return 'Durchsucht Produkte…'
    case 'stream_products': return 'Lädt Produktdaten…'
    case 'list_templates': return 'Liest verfügbare Templates…'
    case 'graphql_query': return 'Führt GraphQL-Abfrage aus…'
    case 'get_schema': return 'Liest Schema…'
    default: return 'Nutzt Werkzeug…'
  }
}
