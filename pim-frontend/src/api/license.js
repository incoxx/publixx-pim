import client from './client'

export default {
  get() {
    return client.get('/license')
  },

  activate(licenseKey) {
    return client.put('/license', { license_key: licenseKey })
  },
}
