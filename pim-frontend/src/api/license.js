import client from './client'

export default {
  get() {
    return client.get('/license')
  },

  activate(licenseKey) {
    return client.put('/license', { license_key: licenseKey })
  },

  // License Generator (hidden admin tool)
  validatePrivateKey(privateKey) {
    return client.post('/license-generator/validate-key', { private_key: privateKey })
  },

  generateLicense(data) {
    return client.post('/license-generator/generate', data)
  },

  generateKeypair() {
    return client.post('/license-generator/generate-keypair')
  },
}
