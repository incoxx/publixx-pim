/**
 * Nachschlagetabelle fuer Sprachnamen — KEINE Quelle der verfuegbaren Sprachen.
 *
 * Welche Sprachen es gibt, steht in der Tabelle `languages` und kommt ueber
 * den Locale-Store (`useLocaleStore().availableLocales`). Diese Datei liefert
 * nur noch Anzeigenamen fuer Codes, die noch keinen gepflegten Namen haben.
 */
export const languageNames = {
  de: 'Deutsch', en: 'Englisch', fr: 'Franzoesisch', it: 'Italienisch',
  es: 'Spanisch', nl: 'Niederlaendisch', pl: 'Polnisch', pt: 'Portugiesisch',
  cs: 'Tschechisch', da: 'Daenisch', sv: 'Schwedisch', fi: 'Finnisch',
  nb: 'Norwegisch', hu: 'Ungarisch', ro: 'Rumaenisch', sk: 'Slowakisch',
  sl: 'Slowenisch', bg: 'Bulgarisch', el: 'Griechisch', tr: 'Tuerkisch',
  ru: 'Russisch', ja: 'Japanisch', ko: 'Koreanisch', zh: 'Chinesisch',
  et: 'Estnisch', lv: 'Lettisch', lt: 'Litauisch', uk: 'Ukrainisch',
}

export function languageLabel(code) {
  return languageNames[code] || String(code || '').toUpperCase()
}
