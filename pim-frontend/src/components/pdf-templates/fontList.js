/**
 * Shared font list for PDF Template Designer.
 * All fonts are open-source (SIL OFL / Apache 2.0) and bundled as TTF for DomPDF.
 */
import client from '@/api/client'

export const builtInFontFamilies = [
  { value: 'DejaVu Sans', label: 'DejaVu Sans' },
  { value: 'DejaVu Serif', label: 'DejaVu Serif' },
  { value: 'DejaVu Sans Mono', label: 'DejaVu Sans Mono' },
  { value: 'Noto Sans', label: 'Noto Sans' },
  { value: 'Noto Serif', label: 'Noto Serif' },
  { value: 'Roboto', label: 'Roboto' },
  { value: 'Open Sans', label: 'Open Sans' },
  { value: 'Lato', label: 'Lato' },
  { value: 'Source Sans 3', label: 'Source Sans 3' },
]

// Abwärtskompatibel: fontFamilies enthält zunächst nur Built-in-Fonts
export const fontFamilies = [...builtInFontFamilies]

export const defaultFontFamily = 'DejaVu Sans'

/**
 * Custom-Fonts von der API laden und mit Built-in-Fonts zusammenführen.
 * Gibt das kombinierte Array zurück und aktualisiert fontFamilies.
 */
export async function fetchAllFonts() {
  try {
    const res = await client.get('/pdf-fonts')
    const customFonts = (res.data.data || []).map(f => ({
      value: f.family_name,
      label: f.family_name,
      custom: true,
    }))

    // fontFamilies-Array aktualisieren (in-place für reaktive Referenzen)
    fontFamilies.length = 0
    fontFamilies.push(...builtInFontFamilies, ...customFonts)

    return fontFamilies
  } catch (e) {
    console.warn('Custom-Fonts konnten nicht geladen werden:', e.message)
    return builtInFontFamilies
  }
}
