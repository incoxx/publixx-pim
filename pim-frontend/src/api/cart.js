import catalogClient from './catalogClient'

export const cart = {
  // Warenkorb abrufen (oder anlegen wenn nicht vorhanden)
  show(cartTypeName) {
    return catalogClient.get(`/cart/${cartTypeName}`)
  },

  // Produkt hinzufügen
  addItem(cartTypeName, productId, quantity = 1, note = null) {
    return catalogClient.post(`/cart/${cartTypeName}/items`, {
      product_id: productId,
      quantity,
      note,
    })
  },

  // Menge aktualisieren
  updateItem(cartTypeName, productId, quantity, note) {
    return catalogClient.put(`/cart/${cartTypeName}/items/${productId}`, {
      quantity,
      note,
    })
  },

  // Produkt entfernen
  removeItem(cartTypeName, productId) {
    return catalogClient.delete(`/cart/${cartTypeName}/items/${productId}`)
  },

  // Warenkorb leeren
  clear(cartTypeName) {
    return catalogClient.delete(`/cart/${cartTypeName}`)
  },

  // Bestellung / Anfrage abschicken
  submit(cartTypeName, formData) {
    return catalogClient.post(`/cart/${cartTypeName}/submit`, formData)
  },

  // Gast-Cart nach Login mergen
  merge() {
    return catalogClient.post('/cart/merge')
  },
}
