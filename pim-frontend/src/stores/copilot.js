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
    pendingMutation: null,
  }),

  getters: {
    // Anzeige-Transkript: nur Text aus User-/Assistent-Turns
    transcript(state) {
      return state.messages
        .map((m) => ({ role: m.role, text: extractText(m.content) }))
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
      this.pendingMutation = null
    },

    /** Nutzer-Nachricht senden und Antwort streamen. */
    async send(text, context = {}) {
      const trimmed = (text || '').trim()
      if (trimmed === '' || this.busy) return

      this.messages.push({ role: 'user', content: trimmed })
      await this.runTurn(context)
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

      const blocks = {}          // index → content-block (laufend befüllt)
      const jsonBuf = {}         // index → akkumuliertes input_json
      let stopReason = null

      try {
        const authStore = useAuthStore()
        const resp = await fetch(resolveApiUrl('copilot/chat'), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'text/event-stream',
            'Authorization': `Bearer ${authStore.token}`,
          },
          body: JSON.stringify({ messages: this.messages, context }),
        })

        if (!resp.ok || !resp.body) {
          throw new Error(`Copilot-Anfrage fehlgeschlagen (HTTP ${resp.status}).`)
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
        this.error = e?.message || 'Unbekannter Fehler beim Copilot.'
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
      }

      // Schreibende Aktion? → zur Bestätigung anhalten.
      const mutateBlock = assistantContent.find(
        (b) => b.type === 'tool_use' && b.name === 'graphql_mutate',
      )

      if (stopReason === 'tool_use' && mutateBlock) {
        this.pendingMutation = {
          toolUseId: mutateBlock.id,
          input: mutateBlock.input || {},
          context,
        }
        this.busy = false
        return
      }

      this.busy = false
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

    /** Bestätigte Mutation ausführen und Gespräch fortsetzen. */
    async confirmMutation() {
      const pending = this.pendingMutation
      if (!pending || this.busy) return
      this.pendingMutation = null
      this.busy = true

      let result
      try {
        const authStore = useAuthStore()
        const resp = await fetch(resolveApiUrl('copilot/execute-tool'), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Authorization': `Bearer ${authStore.token}`,
          },
          body: JSON.stringify({ name: 'graphql_mutate', input: pending.input }),
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

    /** Mutation ablehnen — Claude erhält eine Ablehnung und kann reagieren. */
    async denyMutation() {
      const pending = this.pendingMutation
      if (!pending || this.busy) return
      this.pendingMutation = null
      this.busy = true

      this.messages.push({
        role: 'user',
        content: [{
          type: 'tool_result',
          tool_use_id: pending.toolUseId,
          content: 'Der Nutzer hat diese Änderung abgelehnt. Führe sie nicht aus und frage nach, wie fortgefahren werden soll.',
          is_error: true,
        }],
      })

      await this.runTurn(pending.context || {})
    },
  },
})

// ── SSE-/Block-Helfer ────────────────────────────────────────────────────────

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
  if (jsonStr !== undefined && (out.type === 'tool_use')) {
    try {
      out.input = JSON.parse(jsonStr || '{}')
    } catch {
      out.input = {}
    }
  }
  return out
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
