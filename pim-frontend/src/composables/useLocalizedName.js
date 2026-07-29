import { useI18n } from 'vue-i18n'

/**
 * Generischer Helper fuer zweisprachige Konfigurations-Entitaeten
 * (Attribute, Attributgruppen, Attributsichten, Einheiten, Produkttypen,
 * Beziehungstypen, Wertelisten, ...) -- alle mit dem gleichen Schema
 * {field}_de / {field}_en (siehe docs/architecture/05-i18n.md, Level 1:
 * System Labels).
 *
 * Regel: UI-Sprache Englisch -> {field}_en, falls vorhanden. Ist {field}_en
 * leer, wird {field}_de als Fallback mit Sternchen markiert (*Text*), damit
 * erkennbar bleibt, dass es keine echte Uebersetzung ist. UI-Sprache
 * Deutsch -> immer {field}_de (Pflichtfeld, kein Fallback noetig).
 *
 * Richtet sich nach der UI-Sprache (vue-i18n locale), nicht nach der
 * separaten Datensprachen-Auswahl -- Konfigurations-Namen sind Systemlabels,
 * keine mehrsprachigen Produktdaten.
 */
export function useLocalizedName() {
  const { locale } = useI18n()

  function localizedName(entity, field = 'name') {
    if (!entity) return ''
    const de = entity[`${field}_de`] || ''
    if (locale.value !== 'en') return de
    const en = entity[`${field}_en`]
    if (en) return en
    return de ? `*${de}*` : ''
  }

  return { localizedName }
}
