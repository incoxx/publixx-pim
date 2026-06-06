import MarkdownIt from 'markdown-it'

/**
 * Markdown-Renderer für Copilot-Antworten.
 *
 * Sicherheit: html: false → Roh-HTML aus Modell-Output wird escaped (XSS-Schutz).
 * Tabellen, Listen, Fett/Kursiv, Code und Links werden unterstützt.
 */
const md = new MarkdownIt({
  html: false,
  linkify: true,
  breaks: true,
})

// Links in neuem Tab öffnen + rel-Attribute setzen
const defaultLinkOpen = md.renderer.rules.link_open
  || ((tokens, idx, options, env, self) => self.renderToken(tokens, idx, options))

md.renderer.rules.link_open = (tokens, idx, options, env, self) => {
  tokens[idx].attrSet('target', '_blank')
  tokens[idx].attrSet('rel', 'noopener noreferrer')
  return defaultLinkOpen(tokens, idx, options, env, self)
}

export function renderMarkdown(text) {
  if (!text) return ''
  return md.render(String(text))
}
