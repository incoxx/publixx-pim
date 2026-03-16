import { watchEffect, onUnmounted } from 'vue'

// Convert hex color (#RRGGBB) to oklch() string for DaisyUI v5
export function hexToOklch(hex) {
  if (!hex || !/^#[0-9A-Fa-f]{6}$/.test(hex)) return null
  let r = parseInt(hex.slice(1, 3), 16) / 255
  let g = parseInt(hex.slice(3, 5), 16) / 255
  let b = parseInt(hex.slice(5, 7), 16) / 255
  r = r <= 0.04045 ? r / 12.92 : Math.pow((r + 0.055) / 1.055, 2.4)
  g = g <= 0.04045 ? g / 12.92 : Math.pow((g + 0.055) / 1.055, 2.4)
  b = b <= 0.04045 ? b / 12.92 : Math.pow((b + 0.055) / 1.055, 2.4)
  const x = 0.4124564 * r + 0.3575761 * g + 0.1804375 * b
  const y = 0.2126729 * r + 0.7151522 * g + 0.0721750 * b
  const z = 0.0193339 * r + 0.1191920 * g + 0.9503041 * b
  const l_ = 0.8189330101 * x + 0.3618667424 * y - 0.1288597137 * z
  const m_ = 0.0329845436 * x + 0.9293118715 * y + 0.0361456387 * z
  const s_ = 0.0482003018 * x + 0.2643662691 * y + 0.6338517070 * z
  const lc = Math.cbrt(l_), mc = Math.cbrt(m_), sc = Math.cbrt(s_)
  const L = 0.2104542553 * lc + 0.7936177850 * mc - 0.0040720468 * sc
  const A = 1.9779984951 * lc - 2.4285922050 * mc + 0.4505937099 * sc
  const B = 0.0259040371 * lc + 0.7827717662 * mc - 0.8086757660 * sc
  const C = Math.sqrt(A * A + B * B)
  let H = Math.atan2(B, A) * (180 / Math.PI)
  if (H < 0) H += 360
  return `oklch(${(L * 100).toFixed(2)}% ${C.toFixed(4)} ${H.toFixed(2)})`
}

function hexToRgb(hex) {
  return {
    r: parseInt(hex.slice(1, 3), 16),
    g: parseInt(hex.slice(3, 5), 16),
    b: parseInt(hex.slice(5, 7), 16),
  }
}

export function adjustBrightness(hex, factor) {
  if (!hex || !/^#[0-9A-Fa-f]{6}$/.test(hex)) return hex
  const { r, g, b } = hexToRgb(hex)
  const adjust = (c) => {
    if (factor > 0) return Math.round(c + (255 - c) * factor)
    return Math.round(c * (1 + factor))
  }
  const clamp = (v) => Math.max(0, Math.min(255, v))
  const rr = clamp(adjust(r)).toString(16).padStart(2, '0')
  const gg = clamp(adjust(g)).toString(16).padStart(2, '0')
  const bb = clamp(adjust(b)).toString(16).padStart(2, '0')
  return `#${rr}${gg}${bb}`
}

/**
 * Apply theme settings to a root DOM element via CSS custom properties.
 * @param {import('vue').Ref<HTMLElement|null>} themeRootRef
 * @param {import('vue').Ref<object>} themeSettingsRef - reactive theme settings object
 */
export function useThemeApplicator(themeRootRef, themeSettingsRef) {
  let fontLinkEl = null

  watchEffect(() => {
    const el = themeRootRef.value
    const t = themeSettingsRef.value
    if (!el) return

    // Apply font family
    el.style.fontFamily = `"${t.font_family}", sans-serif`

    // Inject Google Font link
    const isSystemFont = t.font_family?.startsWith('System') || !t.font_family
    if (!isSystemFont) {
      const href = `https://fonts.googleapis.com/css2?family=${encodeURIComponent(t.font_family)}:wght@300;400;500;600;700&display=swap`
      if (!fontLinkEl) {
        fontLinkEl = document.createElement('link')
        fontLinkEl.rel = 'stylesheet'
        document.head.appendChild(fontLinkEl)
      }
      fontLinkEl.href = href
    } else if (fontLinkEl) {
      fontLinkEl.remove()
      fontLinkEl = null
    }

    // Apply DaisyUI v5 color overrides (oklch CSS custom properties)
    const colorMap = {
      color_primary: '--color-primary',
      color_accent: '--color-accent',
      color_body_text: '--color-base-content',
      color_table_bg: '--color-base-200',
      color_button: '--color-secondary',
    }
    for (const [key, cssVar] of Object.entries(colorMap)) {
      const oklch = hexToOklch(t[key])
      if (oklch) el.style.setProperty(cssVar, oklch)
    }

    // Derive base-100 (cards, modals) and base-300 (borders) from table_bg
    if (t.color_table_bg) {
      const base100 = adjustBrightness(t.color_table_bg, 0.5)
      const base300 = adjustBrightness(t.color_table_bg, -0.15)
      const b100 = hexToOklch(base100)
      const b300 = hexToOklch(base300)
      if (b100) el.style.setProperty('--color-base-100', b100)
      if (b300) el.style.setProperty('--color-base-300', b300)
    }

    // Custom CSS vars for sidebar and table stripes
    if (t.color_sidebar) el.style.setProperty('--catalog-sidebar-color', t.color_sidebar)
    if (t.color_table_stripe) el.style.setProperty('--catalog-stripe-color', t.color_table_stripe)

    // Font sizes as CSS vars
    el.style.setProperty('--catalog-heading-size', t.font_heading_size || '1.75rem')
    el.style.setProperty('--catalog-body-size', t.font_body_size || '0.875rem')

    // Header & mobile menu colors
    if (t.color_header_bg) el.style.setProperty('--catalog-header-bg', t.color_header_bg)
    if (t.color_header_text) el.style.setProperty('--catalog-header-text', t.color_header_text)
    if (t.color_mobile_menu_bg) el.style.setProperty('--catalog-mobile-menu-bg', t.color_mobile_menu_bg)
    if (t.color_mobile_menu_text) el.style.setProperty('--catalog-mobile-menu-text', t.color_mobile_menu_text)

    // SEO: update document title and meta description
    const seoTitle = t.seo_title || t.catalog_title || 'Produktkatalog'
    document.title = seoTitle
    let metaDesc = document.querySelector('meta[name="description"]')
    if (t.seo_description) {
      if (!metaDesc) {
        metaDesc = document.createElement('meta')
        metaDesc.setAttribute('name', 'description')
        document.head.appendChild(metaDesc)
      }
      metaDesc.setAttribute('content', t.seo_description)
    }
  })

  onUnmounted(() => {
    if (fontLinkEl) {
      fontLinkEl.remove()
      fontLinkEl = null
    }
  })
}
