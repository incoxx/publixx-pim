import catalogClient from './catalogClient'

export const cart = {
  // Warenkorb abrufen (oder anlegen wenn nicht vorhanden)
  show(cartTypeName) {
    return catalogClient.get(`/catalog/cart/${cartTypeName}`)
  },

  // Produkt hinzufügen
  addItem(cartTypeName, productId, quantity = 1, note = null) {
    return catalogClient.post(`/catalog/cart/${cartTypeName}/items`, {
      product_id: productId,
      quantity,
      note,
    })
  },

  // Menge aktualisieren
  updateItem(cartTypeName, productId, quantity, note) {
    return catalogClient.put(`/catalog/cart/${cartTypeName}/items/${productId}`, {
      quantity,
      note,
    })
  },

  // Produkt entfernen
  removeItem(cartTypeName, productId) {
    return catalogClient.delete(`/catalog/cart/${cartTypeName}/items/${productId}`)
  },

  // Warenkorb leeren
  clear(cartTypeName) {
    return catalogClient.delete(`/catalog/cart/${cartTypeName}`)
  },

  // Bestellung / Anfrage abschicken
  submit(cartTypeName, formData) {
    return catalogClient.post(`/catalog/cart/${cartTypeName}/submit`, formData)
  },

  // Gast-Cart nach Login mergen
  merge() {
    return catalogClient.post('/catalog/cart/merge')
  },
}
