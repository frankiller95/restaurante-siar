import axios from 'axios'

/**
 * Cliente HTTP de la API de Laravel. Los interceptores (token y sesión
 * vencida) se configuran en src/boot/axios.js.
 */
export const api = axios.create({
  baseURL: import.meta.env.QCLI_API_URL || 'http://localhost:8000/api',
  headers: { Accept: 'application/json' },
  timeout: 20000,
})
