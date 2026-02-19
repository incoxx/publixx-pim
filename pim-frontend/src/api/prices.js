import client, { buildParams } from './client'

export const priceTypes = {
  list() {
    return client.get('/price-types')
  },
}

export const relationTypes = {
  list() {
    return client.get('/relation-types')
  },
}

export default { priceTypes, relationTypes }
