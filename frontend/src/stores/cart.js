import { defineStore } from 'pinia'
import api from '../api'

export const useCartStore = defineStore('cart', {
  state: () => ({ items: [], metodoPago: null }),
  getters: {
    unidades: (s) => s.items.reduce((a, i) => a + i.cantidad, 0),
    total: (s) => s.items.reduce((a, i) => a + i.producto.precio_venta * i.cantidad, 0),
    comision: (s) => s.items.reduce((a, i) => a + i.producto.comision * i.cantidad, 0),
    listo: (s) => s.items.length > 0 && !!s.metodoPago,
    enCarrito: (s) => (id) => s.items.find((i) => i.producto.id === id)?.cantidad ?? 0,
  },
  actions: {
    /** Agrega una pieza sin pasar de lo que el vendedor tiene disponible. Devuelve false si ya no hay. */
    agregar(producto) {
      const linea = this.items.find((i) => i.producto.id === producto.id)
      if (linea) {
        if (linea.cantidad + 1 > producto.disponible) return false
        linea.cantidad++
      } else {
        this.items.push({ producto, cantidad: 1, cucharas_extra: 0 })
      }
      return true
    },
    cambiar(id, delta) {
      const linea = this.items.find((i) => i.producto.id === id)
      if (!linea) return
      if (delta > 0 && linea.cantidad + delta > linea.producto.disponible) return
      linea.cantidad += delta
      if (linea.cantidad <= 0) this.quitar(id)
    },
    cucharas(id, delta) {
      const linea = this.items.find((i) => i.producto.id === id)
      if (linea) linea.cucharas_extra = Math.max(0, linea.cucharas_extra + delta)
    },
    quitar(id) { this.items = this.items.filter((i) => i.producto.id !== id) },
    vaciar() { this.items = []; this.metodoPago = null },
    async cobrar() {
      const payload = {
        metodo_pago: this.metodoPago,
        items: this.items.map((i) => ({
          producto_id: i.producto.id, cantidad: i.cantidad, cucharas_extra: i.cucharas_extra,
        })),
      }
      const { data } = await api.post('/vendedor/ventas', payload)
      this.vaciar()
      return data
    },
  },
})
