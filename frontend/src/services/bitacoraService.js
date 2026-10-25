import { api } from './api'

export default {
  async listar(filtros = {}) {
    const { data } = await api.get('/bitacora', { params: filtros })
    return data
  },
}
