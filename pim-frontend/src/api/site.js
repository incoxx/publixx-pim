import client from './client'

/**
 * Öffentliche Website-Vorschau (gerenderte Sitemap + Seiten).
 * navigation = technical_name (z. B. 'main') oder UUID.
 */
export default {
  // opts.anonymous = true → ohne Login-Token anfragen (echter öffentlicher
  // Besucher → trifft den anonymen Cache). Admin-Vorschau lässt es weg → live.
  getSitemap(navigation, lang = 'de', { anonymous = false } = {}) {
    return client.get(`/site/${navigation}/sitemap`, { params: { lang }, anonymous })
  },

  getPage(navigation, slug, lang = 'de', { anonymous = false } = {}) {
    return client.get(`/site/${navigation}/page/${slug}`, { params: { lang }, anonymous })
  },

  getProductPage(navigation, productId, lang = 'de', { anonymous = false } = {}) {
    return client.get(`/site/${navigation}/product/${productId}`, { params: { lang }, anonymous })
  },
}
