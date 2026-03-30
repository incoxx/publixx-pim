import client from './client'

export default {
  list: () => client.get('/portal-configs'),
  show: (id) => client.get(`/portal-configs/${id}`),
  create: (data) => client.post('/portal-configs', data),
  update: (id, data) => client.put(`/portal-configs/${id}`, data),
  remove: (id) => client.delete(`/portal-configs/${id}`),
  duplicate: (id) => client.post(`/portal-configs/${id}/duplicate`),
  presets: () => client.get('/portal-configs/presets'),
}
