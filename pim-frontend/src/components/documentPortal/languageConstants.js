/**
 * Gemeinsame Sprach-Konstanten fuer das Dokumentenportal.
 */

/** Sprachcode → Anzeigename (in Landessprache). */
export const LANGUAGE_NAMES = {
  de: 'Deutsch', en: 'English', fr: 'Fran\u00e7ais', es: 'Espa\u00f1ol',
  it: 'Italiano', nl: 'Nederlands', pl: 'Polski', pt: 'Portugu\u00eas',
  ru: '\u0420\u0443\u0441\u0441\u043a\u0438\u0439', zh: '\u4e2d\u6587', ja: '\u65e5\u672c\u8a9e', ko: '\ud55c\uad6d\uc5b4',
  cs: '\u010ce\u0161tina', hu: 'Magyar', hr: 'Hrvatski', da: 'Dansk',
  sv: 'Svenska', no: 'Norsk', fi: 'Suomi', el: '\u0395\u03bb\u03bb\u03b7\u03bd\u03b9\u03ba\u03ac',
  tr: 'T\u00fcrk\u00e7e', ro: 'Rom\u00e2n\u0103', ar: '\u0627\u0644\u0639\u0631\u0628\u064a\u0629',
  mul: 'Mehrsprachig', lv: 'Latvie\u0161u', lt: 'Lietuvi\u0173',
}

/** Sprachcode → Laendercode (fuer Badge-Anzeige). */
export const LANGUAGE_COUNTRY_CODES = {
  de: 'DE', en: 'GB', fr: 'FR', es: 'ES', it: 'IT', nl: 'NL', pl: 'PL',
  pt: 'PT', ru: 'RU', zh: 'CN', ja: 'JP', ko: 'KR', cs: 'CZ', hu: 'HU',
  hr: 'HR', da: 'DK', sv: 'SE', no: 'NO', fi: 'FI', el: 'GR', tr: 'TR',
  ro: 'RO', ar: 'SA', lv: 'LV', lt: 'LT',
}

export function languageName(code) {
  return LANGUAGE_NAMES[code] || (code ? code.toUpperCase() : '')
}

export function languageCountryCode(code) {
  return LANGUAGE_COUNTRY_CODES[code] || (code ? code.toUpperCase() : '')
}
