import api from './api'

export default {
  list: () => api.get('/portal-configs'),
  show: (id) => api.get(`/portal-configs/${id}`),
  create: (data) => api.post('/portal-configs', data),
  update: (id, data) => api.put(`/portal-configs/${id}`, data),
  remove: (id) => api.delete(`/portal-configs/${id}`),
  duplicate: (id) => api.post(`/portal-configs/${id}/duplicate`),
  presets: () => api.get('/portal-configs/presets'),
}
