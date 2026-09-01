/**
 * Bausteine des geführten Modus der Profisuche.
 *
 * Jeder Baustein muss an drei Stellen verdrahtet sein: er meldet seinen Zustand
 * (intentHasValue), er räumt beim Entfernen auf (closeIntent) und er wird vom
 * Zurücksetzen erfasst. Fehlt eine davon, lässt sich die Kachel z.B. anklicken,
 * aber nicht mehr schließen — ein Fehler, den ein Blick auf eine einzelne
 * Funktion nicht zeigt. Deshalb prüft der Test die Quelle im Zusammenspiel.
 */
import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'

const here = dirname(fileURLToPath(import.meta.url))
const source = readFileSync(resolve(here, '../SearchWizardView.vue'), 'utf8')

function block(startMarker, endMarker = '\n}') {
  const start = source.indexOf(startMarker)
  expect(start, `${startMarker} nicht gefunden`).toBeGreaterThan(-1)
  return source.slice(start, source.indexOf(endMarker, start))
}

const intentKeys = [...block('const guidedIntents = computed(', '\n})')
  .matchAll(/\{ key: '(\w+)'/g)].map(m => m[1])

describe('Geführter Modus — Bausteine', () => {
  it('kennt den Tag-Baustein', () => {
    expect(intentKeys).toContain('tags')
  })

  it('meldet für jeden Baustein seinen Zustand', () => {
    const body = block('function intentHasValue(')
    const fehlend = intentKeys.filter(k => !body.includes(`case '${k}':`))

    expect(fehlend).toEqual([])
  })

  it('räumt jeden Baustein beim Entfernen auf', () => {
    const body = block('function closeIntent(')
    const fehlend = intentKeys.filter(k => !body.includes(`case '${k}':`))

    expect(fehlend).toEqual([])
  })

  it('setzt den Tag-Baustein beim Zurücksetzen mit zurück', () => {
    const body = block('function resetGuided(')

    expect(body).toContain('selectedTags.value = []')
    expect(body).toContain("tagMatch.value = 'any'")
  })

  it('formuliert den Tag-Filter im Klartext-Suchsatz', () => {
    const body = block('const guidedClauses = computed(', '\n})')

    // Ein einzelner Tag darf nicht als "mit einem der Tags" formuliert werden
    expect(body).toContain("t('mit Tag {list}'")
    expect(body).toContain("t('mit allen Tags {list}'")
    expect(body).toContain("t('mit einem der Tags {list}'")
  })

  it('übersetzt alle neuen Tag-Texte ins Englische', () => {
    const locale = readFileSync(resolve(here, '../../../locales/searchRawText.js'), 'utf8')

    for (const key of [
      'mit bestimmten Tags', 'Stichworte', 'Keine Tags angelegt',
      'eines davon', 'alle',
      'mit Tag {list}', 'mit einem der Tags {list}', 'mit allen Tags {list}',
    ]) {
      expect(locale, `Übersetzung fehlt: ${key}`).toContain(`'${key}':`)
    }
  })
})
