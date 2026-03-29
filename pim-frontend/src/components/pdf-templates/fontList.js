/**
 * Shared font list for PDF Template Designer.
 * All fonts are open-source (SIL OFL / Apache 2.0) and bundled as TTF for DomPDF.
 * Custom-Fonts werden via API geladen und per @font-face im Browser registriert.
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

// Style-Element für dynamische @font-face-Regeln
let fontStyleEl = null

const VARIANT_MAP = {
  regular:     { weight: 'normal', style: 'normal' },
  bold:        { weight: 'bold',   style: 'normal' },
  italic:      { weight: 'normal', style: 'italic' },
  bold_italic: { weight: 'bold',   style: 'italic' },
}

/**
 * @font-face-Regeln für Custom-Fonts in den Browser injizieren.
 */
function injectFontFaces(fonts) {
  if (!fontStyleEl) {
    fontStyleEl = document.createElement('style')
    fontStyleEl.id = 'pim-custom-fonts'
    document.head.appendChild(fontStyleEl)
  }

  const rules = []
  for (const font of fonts) {
    if (!font.files) continue
    for (const [variant, url] of Object.entries(font.files)) {
      const map = VARIANT_MAP[variant]
      if (!map) continue
      rules.push(`@font-face {
  font-family: '${font.family_name}';
  src: url('${url}') format('truetype');
  font-weight: ${map.weight};
  font-style: ${map.style};
  font-display: swap;
}`)
    }
  }
  fontStyleEl.textContent = rules.join('\n')
}

/**
 * Custom-Fonts von der API laden, im Browser registrieren und fontFamilies aktualisieren.
 */
export async function fetchAllFonts() {
  try {
    const res = await client.get('/pdf-fonts')
    const data = res.data.data || []

    // @font-face im Browser injizieren
    injectFontFaces(data)

    const customFonts = data.map(f => ({
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
