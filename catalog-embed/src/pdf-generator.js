/**
 * Client-side PDF generation using jsPDF.
 * Used for offline catalogs where server-side PDF generation is not available.
 */
import { jsPDF } from 'jspdf'

const PAGE_WIDTH = 210
const PAGE_HEIGHT = 297
const MARGIN = 20
const CONTENT_WIDTH = PAGE_WIDTH - 2 * MARGIN
const LINE_HEIGHT = 6
const SECTION_GAP = 8

/**
 * Generate a product data sheet PDF.
 * @param {object} product - Full product detail object
 * @param {object} [options] - Options
 * @param {string} [options.locale] - Locale ('de' or 'en')
 * @param {string} [options.primaryColor] - Brand color hex
 * @returns {Blob} PDF blob
 */
export function generateProductPdf(product, options = {}) {
  const locale = options.locale || 'de'
  const brandColor = hexToRgb(options.primaryColor || '#1B3A5C')

  const doc = new jsPDF({ unit: 'mm', format: 'a4' })
  let y = MARGIN

  // ── Header bar ──
  doc.setFillColor(brandColor.r, brandColor.g, brandColor.b)
  doc.rect(0, 0, PAGE_WIDTH, 16, 'F')
  doc.setTextColor(255, 255, 255)
  doc.setFontSize(9)
  doc.setFont('helvetica', 'normal')
  doc.text(locale === 'de' ? 'Produktdatenblatt' : 'Product Data Sheet', MARGIN, 10)
  doc.text(new Date().toLocaleDateString(locale === 'de' ? 'de-DE' : 'en-US'), PAGE_WIDTH - MARGIN, 10, { align: 'right' })

  y = 26

  // ── Product title ──
  doc.setTextColor(brandColor.r, brandColor.g, brandColor.b)
  doc.setFontSize(16)
  doc.setFont('helvetica', 'bold')
  const titleLines = doc.splitTextToSize(product.name || '', CONTENT_WIDTH)
  doc.text(titleLines, MARGIN, y)
  y += titleLines.length * 7 + 2

  // ── SKU / EAN ──
  doc.setTextColor(120, 120, 120)
  doc.setFontSize(9)
  doc.setFont('helvetica', 'normal')
  const meta = []
  if (product.sku) meta.push(`SKU: ${product.sku}`)
  if (product.ean) meta.push(`EAN: ${product.ean}`)
  if (meta.length) {
    doc.text(meta.join('  |  '), MARGIN, y)
    y += LINE_HEIGHT
  }

  // ── Category breadcrumb ──
  if (product.category_breadcrumb?.length) {
    const path = product.category_breadcrumb.map(c => c.name).join(' > ')
    doc.setTextColor(150, 150, 150)
    doc.setFontSize(8)
    doc.text(path, MARGIN, y)
    y += LINE_HEIGHT
  }

  y += 4

  // ── Separator ──
  doc.setDrawColor(brandColor.r, brandColor.g, brandColor.b)
  doc.setLineWidth(0.5)
  doc.line(MARGIN, y, PAGE_WIDTH - MARGIN, y)
  y += SECTION_GAP

  // ── Description attributes ──
  if (product.description_attributes?.length) {
    doc.setTextColor(50, 50, 50)
    doc.setFontSize(9)
    doc.setFont('helvetica', 'normal')
    for (const da of product.description_attributes) {
      if (!da.value) continue
      const lines = doc.splitTextToSize(String(da.value), CONTENT_WIDTH)
      y = checkPageBreak(doc, y, lines.length * LINE_HEIGHT)
      doc.text(lines, MARGIN, y)
      y += lines.length * LINE_HEIGHT + 2
    }
    y += 4
  }

  // ── Prices ──
  if (product.prices?.length) {
    y = checkPageBreak(doc, y, 20)
    doc.setTextColor(brandColor.r, brandColor.g, brandColor.b)
    doc.setFontSize(11)
    doc.setFont('helvetica', 'bold')
    doc.text(locale === 'de' ? 'Preise' : 'Prices', MARGIN, y)
    y += LINE_HEIGHT + 2

    doc.setFontSize(9)
    for (const price of product.prices) {
      y = checkPageBreak(doc, y, LINE_HEIGHT * 2)
      const label = price.type_name || (locale === 'de' ? 'Preis' : 'Price')
      const formatted = formatPrice(price.amount, price.currency, locale)
      doc.setFont('helvetica', 'normal')
      doc.setTextColor(100, 100, 100)
      doc.text(label, MARGIN, y)
      doc.setFont('helvetica', 'bold')
      doc.setTextColor(50, 50, 50)
      doc.text(formatted, MARGIN + 50, y)
      y += LINE_HEIGHT
    }
    y += SECTION_GAP
  }

  // ── Attributes table ──
  if (product.attributes?.length) {
    y = checkPageBreak(doc, y, 20)
    doc.setTextColor(brandColor.r, brandColor.g, brandColor.b)
    doc.setFontSize(11)
    doc.setFont('helvetica', 'bold')
    doc.text(locale === 'de' ? 'Technische Daten' : 'Technical Data', MARGIN, y)
    y += LINE_HEIGHT + 2

    doc.setFontSize(8)
    const labelWidth = 65
    const valueWidth = CONTENT_WIDTH - labelWidth
    let rowIndex = 0

    for (const attr of product.attributes) {
      if (attr.parent_attribute_id) continue
      const value = attr.value ? String(attr.value) + (attr.unit ? ` ${attr.unit}` : '') : '—'
      const valueLines = doc.splitTextToSize(value, valueWidth - 4)
      const rowHeight = Math.max(LINE_HEIGHT, valueLines.length * LINE_HEIGHT) + 2

      y = checkPageBreak(doc, y, rowHeight)

      // Alternating row background
      if (rowIndex % 2 === 0) {
        doc.setFillColor(245, 247, 250)
        doc.rect(MARGIN, y - 4, CONTENT_WIDTH, rowHeight, 'F')
      }

      // Label
      doc.setFont('helvetica', 'normal')
      doc.setTextColor(100, 100, 100)
      doc.text(attr.label || '', MARGIN + 2, y)

      // Value
      doc.setFont('helvetica', 'normal')
      doc.setTextColor(50, 50, 50)
      doc.text(valueLines, MARGIN + labelWidth, y)

      y += rowHeight
      rowIndex++
    }
    y += SECTION_GAP
  }

  // ── Documents list ──
  const documents = product.media?.filter(m => m.media_type !== 'image') || []
  if (documents.length) {
    y = checkPageBreak(doc, y, 20)
    doc.setTextColor(brandColor.r, brandColor.g, brandColor.b)
    doc.setFontSize(11)
    doc.setFont('helvetica', 'bold')
    doc.text(locale === 'de' ? 'Dokumente' : 'Documents', MARGIN, y)
    y += LINE_HEIGHT + 2

    doc.setFontSize(8)
    for (const docItem of documents) {
      y = checkPageBreak(doc, y, LINE_HEIGHT)
      const name = docItem.description || docItem.file_name || 'Dokument'
      doc.setFont('helvetica', 'normal')
      doc.setTextColor(brandColor.r, brandColor.g, brandColor.b)
      doc.text(`• ${name}`, MARGIN + 2, y)
      if (docItem.url) {
        doc.link(MARGIN + 2, y - 4, CONTENT_WIDTH, LINE_HEIGHT, { url: docItem.url })
      }
      y += LINE_HEIGHT
    }
    y += SECTION_GAP
  }

  // ── Footer ──
  const pageCount = doc.getNumberOfPages()
  for (let i = 1; i <= pageCount; i++) {
    doc.setPage(i)
    doc.setFontSize(7)
    doc.setTextColor(180, 180, 180)
    doc.text(
      `${locale === 'de' ? 'Seite' : 'Page'} ${i} / ${pageCount}`,
      PAGE_WIDTH - MARGIN,
      PAGE_HEIGHT - 10,
      { align: 'right' }
    )
    if (product.name) {
      doc.text(product.name, MARGIN, PAGE_HEIGHT - 10)
    }
  }

  return doc.output('blob')
}

/**
 * Generate a wishlist PDF with multiple products.
 * @param {object[]} products - Array of product objects (list format)
 * @param {object} [options] - Options
 * @returns {Blob} PDF blob
 */
export function generateWishlistPdf(products, options = {}) {
  const locale = options.locale || 'de'
  const brandColor = hexToRgb(options.primaryColor || '#1B3A5C')

  const doc = new jsPDF({ unit: 'mm', format: 'a4', orientation: 'landscape' })
  let y = MARGIN

  const pageW = 297
  const pageH = 210
  const marginX = 15
  const contentW = pageW - 2 * marginX

  // ── Header ──
  doc.setFillColor(brandColor.r, brandColor.g, brandColor.b)
  doc.rect(0, 0, pageW, 14, 'F')
  doc.setTextColor(255, 255, 255)
  doc.setFontSize(9)
  doc.setFont('helvetica', 'bold')
  doc.text(locale === 'de' ? 'Merkliste' : 'Wishlist', marginX, 9)
  doc.setFont('helvetica', 'normal')
  doc.text(
    `${products.length} ${locale === 'de' ? 'Produkte' : 'Products'} — ${new Date().toLocaleDateString(locale === 'de' ? 'de-DE' : 'en-US')}`,
    pageW - marginX, 9, { align: 'right' }
  )

  y = 22

  // ── Table header ──
  const cols = [
    { label: '#', width: 10 },
    { label: 'SKU', width: 35 },
    { label: locale === 'de' ? 'Produktname' : 'Product Name', width: contentW - 10 - 35 - 30 - 45, flex: true },
    { label: locale === 'de' ? 'Kategorie' : 'Category', width: 45 },
    { label: locale === 'de' ? 'Preis' : 'Price', width: 30 },
  ]

  doc.setFillColor(240, 242, 245)
  doc.rect(marginX, y - 4, contentW, 8, 'F')
  doc.setFontSize(7)
  doc.setFont('helvetica', 'bold')
  doc.setTextColor(80, 80, 80)

  let colX = marginX
  for (const col of cols) {
    doc.text(col.label, colX + 2, y)
    colX += col.width
  }
  y += 8

  // ── Table rows ──
  doc.setFontSize(8)
  doc.setFont('helvetica', 'normal')

  products.forEach((product, idx) => {
    if (y > pageH - 20) {
      doc.addPage('landscape')
      y = MARGIN
    }

    const rowHeight = 7
    if (idx % 2 === 0) {
      doc.setFillColor(250, 250, 252)
      doc.rect(marginX, y - 4, contentW, rowHeight, 'F')
    }

    colX = marginX
    doc.setTextColor(150, 150, 150)
    doc.text(String(idx + 1), colX + 2, y)
    colX += cols[0].width

    doc.setTextColor(50, 50, 50)
    doc.setFont('helvetica', 'normal')
    doc.text(truncate(product.sku || '', 20), colX + 2, y)
    colX += cols[1].width

    doc.setFont('helvetica', 'bold')
    doc.text(truncate(product.name || '', 60), colX + 2, y)
    colX += cols[2].width

    doc.setFont('helvetica', 'normal')
    doc.setTextColor(100, 100, 100)
    doc.text(truncate(product.category_path || '', 25), colX + 2, y)
    colX += cols[3].width

    doc.setTextColor(brandColor.r, brandColor.g, brandColor.b)
    doc.setFont('helvetica', 'bold')
    doc.text(product.price ? formatPrice(product.price, product.currency, locale) : '', colX + 2, y)

    y += rowHeight
  })

  // ── Footer ──
  const pageCount = doc.getNumberOfPages()
  for (let i = 1; i <= pageCount; i++) {
    doc.setPage(i)
    doc.setFontSize(7)
    doc.setTextColor(180, 180, 180)
    doc.text(
      `${locale === 'de' ? 'Seite' : 'Page'} ${i} / ${pageCount}`,
      pageW - marginX, pageH - 8, { align: 'right' }
    )
  }

  return doc.output('blob')
}

// ── Helpers ──

function checkPageBreak(doc, y, neededHeight) {
  if (y + neededHeight > PAGE_HEIGHT - MARGIN) {
    doc.addPage()
    return MARGIN
  }
  return y
}

function hexToRgb(hex) {
  const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex)
  return result
    ? { r: parseInt(result[1], 16), g: parseInt(result[2], 16), b: parseInt(result[3], 16) }
    : { r: 27, g: 58, b: 92 }
}

function formatPrice(amount, currency, locale) {
  if (amount == null) return ''
  try {
    return new Intl.NumberFormat(locale === 'de' ? 'de-DE' : 'en-US', {
      style: 'currency', currency: currency || 'EUR',
    }).format(amount)
  } catch {
    return `${amount} ${currency || 'EUR'}`
  }
}

function truncate(str, maxLen) {
  return str.length > maxLen ? str.slice(0, maxLen - 1) + '…' : str
}
