import client from './client'

export default {
  query(pql, options = {}) {
    return client.post('/pql/query', { query: pql, ...options })
  },

  count(pql) {
    return client.post('/pql/query/count', { query: pql })
  },

  validate(pql) {
    return client.post('/pql/query/validate', { query: pql })
  },

  explain(pql) {
    return client.post('/pql/query/explain', { query: pql })
  },
}
