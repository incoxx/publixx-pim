/**
 * Der Menüpunkt-Finder (Command-Palette) muss jeden Menüpunkt der Sidebar
 * finden können — sonst ist ein Bereich zwar erreichbar, aber nicht auffindbar.
 *
 * Der Test liest beide Quelldateien und vergleicht die Ziel-Routen. Er schlägt
 * fehl, sobald ein neuer Menüpunkt ergänzt wird, ohne ihn in die Palette
 * aufzunehmen (genau das war der Grund für die Nachpflege von 29 Einträgen).
 */
import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'

const here = dirname(fileURLToPath(import.meta.url))
const src = resolve(here, '../../..')

const sidebar = readFileSync(resolve(src, 'components/layout/AppSidebar.vue'), 'utf8')
const palette = readFileSync(resolve(src, 'components/shared/PimCommandPalette.vue'), 'utf8')

function matchAll(source, pattern) {
  return [...source.matchAll(pattern)].map(m => m[1])
}

// Bewusste Ausnahmen: Ziele, die die Palette anders öffnet als per router.push.
const EXCEPTIONS = new Set([
  '/catalog-embed', // Palette-Eintrag 'Katalog-Demo' öffnet die Demo in einem neuen Tab
])

describe('Command-Palette deckt das Menü ab', () => {
  it('kennt jeden Menüpunkt der Sidebar', () => {
    const menuPaths = new Set(matchAll(sidebar, /to: '(\/[^']*)'/g))
    const paletteTargets = new Set([
      ...matchAll(palette, /router\.push\('(\/[^']*)'\)/g),
      ...matchAll(palette, /window\.open\('(\/[^']*)'/g),
    ])

    const missing = [...menuPaths]
      .filter(path => !paletteTargets.has(path) && !EXCEPTIONS.has(path))
      .sort()

    expect(missing).toEqual([])
  })

  it('verweist nicht auf Ziele ohne Route', () => {
    const router = readFileSync(resolve(src, 'router/index.js'), 'utf8')
    const routes = new Set(matchAll(router, /path: '(\/[^']*)'/g))

    const unknown = matchAll(palette, /router\.push\('(\/[^']*)'\)/g)
      // Query-Parameter (z.B. /products?new=1) gehören zur Route davor
      .map(path => path.split('?')[0])
      .filter(path => !routes.has(path))
      .sort()

    expect([...new Set(unknown)]).toEqual([])
  })
})
