/**
 * Suchprofile müssen dieselben Filter speichern, die sie beim Laden setzen.
 *
 * Genau hier klaffte die Lücke: Produkttyp-, Hersteller- und Tag-Filter waren in
 * der Oberfläche vorhanden, wurden aber nicht ins Profil geschrieben. Ein
 * gespeichertes Profil lieferte danach mehr Treffer als die Suche beim Speichern
 * — ohne Hinweis. Der Test liest die Quelle, weil die Lücke im Zusammenspiel von
 * drei Funktionen entsteht, nicht in einer einzelnen.
 */
import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'

const here = dirname(fileURLToPath(import.meta.url))
const source = readFileSync(resolve(here, '../SearchWizardView.vue'), 'utf8')

/** Nutzlast-Schlüssel eines `searchProfilesApi.<method>(…{ … })`-Aufrufs. */
function payloadKeys(method) {
  const start = source.indexOf(`searchProfilesApi.${method}(`)
  expect(start, `Aufruf searchProfilesApi.${method}( nicht gefunden`).toBeGreaterThan(-1)
  const body = source.slice(start, source.indexOf('\n    })', start))
  return new Set([...body.matchAll(/^\s{6}(\w+):/gm)].map(m => m[1]))
}

/** Profilfelder, die loadProfile() in den Zustand der Suche zurückschreibt. */
function restoredKeys() {
  const start = source.indexOf('async function loadProfile(')
  expect(start, 'loadProfile() nicht gefunden').toBeGreaterThan(-1)
  const body = source.slice(start, source.indexOf('\n}', start))
  return new Set([...body.matchAll(/profile\.(\w+)/g)].map(m => m[1]))
}

describe('Suchprofile — gespeicherte und geladene Felder', () => {
  it('schreibt beim Anlegen und Aktualisieren dieselben Felder', () => {
    expect([...payloadKeys('update')].sort()).toEqual([...payloadKeys('create')].sort())
  })

  it('speichert jedes Feld, das beim Laden wiederhergestellt wird', () => {
    const saved = payloadKeys('create')
    // id dient nur dem Nachschlagen des Profils, ist also kein Filter
    const fehlend = [...restoredKeys()].filter(k => k !== 'id' && !saved.has(k)).sort()

    expect(fehlend).toEqual([])
  })

  it('deckt die Filter der Profisuche ab', () => {
    const saved = payloadKeys('create')

    for (const feld of ['category_ids', 'product_type_ids', 'manufacturer_ids', 'tag_ids', 'tag_match', 'status_filter']) {
      expect(saved, `${feld} fehlt in der Profil-Nutzlast`).toContain(feld)
    }
  })
})
