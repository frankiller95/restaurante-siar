import axios from 'axios'

/**
 * URL de la API de Laravel.
 * - Desarrollo: QCLI_API_URL del .env (por defecto http://localhost:8000/api).
 * - Producción: la API está en el mismo dominio, dentro de la carpeta "backend"
 *   (https://siar.frajarele.dev/backend/api), así no hace falta CORS. Se puede
 *   cambiar con QCLI_API_URL_PRODUCCION al compilar.
 */
const urlApi = import.meta.env.PROD
  ? import.meta.env.QCLI_API_URL_PRODUCCION || `${import.meta.env.BASE_URL}backend/api`
  : import.meta.env.QCLI_API_URL || 'http://localhost:8000/api'

/**
 * Cliente HTTP de la API. Los interceptores (token y sesión vencida) se
 * configuran en src/boot/axios.js.
 */
export const api = axios.create({
  baseURL: urlApi,
  headers: { Accept: 'application/json' },
  timeout: 20000,
})
