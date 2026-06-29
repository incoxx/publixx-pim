import client from './client'

/**
 * Öffentliche Website-Vorschau (gerenderte Sitemap + Seiten).
 * navigation = technical_name (z. B. 'main') oder UUID.
 */
export default {
  getSitemap(navigation, lang = 'de') {
    return client.get(`/site/${navigation}/sitemap`, { params: { lang } })
  },

  getPage(navigation, slug, lang = 'de') {
    return client.get(`/site/${navigation}/page/${slug}`, { params: { lang } })
  },

  getProductPage(navigation, productId, lang = 'de') {
    return client.get(`/site/${navigation}/product/${productId}`, { params: { lang } })
  },
}
