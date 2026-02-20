import client from './client'

export default {
  query(pql, options = {}) {
    return client.post('/pql/query', { pql: pql, ...options })
  },

  count(pql) {
    return client.post('/pql/query/count', { pql: pql })
  },

  validate(pql) {
    return client.post('/pql/query/validate', { pql: pql })
  },

  explain(pql) {
    return client.post('/pql/query/explain', { pql: pql })
  },
}
