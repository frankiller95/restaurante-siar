import { api } from './api'

/** Arma el FormData del producto (multipart, porque puede llevar imagen). */
function formularioProducto(producto, { imagen = null, quitarImagen = false, confirmarDuplicado = false } = {}) {
  const datos = new FormData()
  datos.append('nombre', producto.nombre ?? '')
  datos.append('descripcion', producto.descripcion ?? '')
  datos.append('precio', producto.precio ?? '')
  datos.append('id_categoria', producto.id_categoria ?? '')
  if (imagen) datos.append('imagen', imagen)
  if (quitarImagen) datos.append('quitar_imagen', '1')
  if (confirmarDuplicado) datos.append('confirmar_duplicado', '1')
  return datos
}

export default {
  // --- Categorías (CU04) ---
  async categorias({ soloActivas = false } = {}) {
    const { data } = await api.get('/categorias', { params: soloActivas ? { solo_activas: 1 } : {} })
    return data
  },

  async crearCategoria(categoria) {
    const { data } = await api.post('/categorias', categoria)
    return data
  },

  async actualizarCategoria(idCategoria, categoria) {
    const { data } = await api.put(`/categorias/${idCategoria}`, categoria)
    return data
  },

  async eliminarCategoria(idCategoria) {
    const { data } = await api.delete(`/categorias/${idCategoria}`)
    return data
  },

  // --- Productos (CU01, CU02, CU03) ---
  async productos(filtros = {}) {
    const { data } = await api.get('/productos', { params: filtros })
    return data
  },

  async crearProducto(producto, opciones) {
    const { data } = await api.post('/productos', formularioProducto(producto, opciones))
    return data
  },

  /** PHP no lee multipart en PUT: se envía POST con _method=PUT. */
  async actualizarProducto(idProducto, producto, opciones) {
    const datos = formularioProducto(producto, opciones)
    datos.append('_method', 'PUT')
    const { data } = await api.post(`/productos/${idProducto}`, datos)
    return data
  },

  async eliminarProducto(idProducto) {
    const { data } = await api.delete(`/productos/${idProducto}`)
    return data
  },

  // --- CU04 y CU05 ---
  async cambiarCategoria(idProducto, idCategoria) {
    const { data } = await api.patch(`/productos/${idProducto}/categoria`, { id_categoria: idCategoria })
    return data
  },

  async cambiarDisponibilidad(idProducto, disponible) {
    const { data } = await api.patch(`/productos/${idProducto}/disponibilidad`, { disponible })
    return data
  },

  /** Carta: categorías activas con sus productos. */
  async carta() {
    const { data } = await api.get('/menu')
    return data
  },
}
