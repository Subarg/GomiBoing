import { defineStore } from 'pinia'
import api from '../api'

export const useInventoryStore = defineStore('inventory', {
  state: () => ({
    insumos: [],      // almacén central (admin)
    alertas: [],      // insumos en o bajo el mínimo (admin)
    miInventario: [], // lote asignado al vendedor
    productos: [],    // vendedor: todos los productos con `disponible` y `faltan`
  }),
  getters: {
    cantidadDe: (s) => (insumoId) =>
      s.miInventario.find((i) => i.insumo_id === insumoId)?.cantidad_asignada ?? 0,
    disponibles: (s) => s.productos.filter((p) => p.disponible >= 1),
    conExistencia: (s) => s.insumos.filter((i) => i.stock_almacen > 0),
  },
  actions: {
    async fetchInsumos() { this.insumos = (await api.get('/admin/insumos')).data },
    async fetchAlertas() { this.alertas = (await api.get('/admin/alertas')).data },
    async fetchMiInventario() { this.miInventario = (await api.get('/vendedor/inventario')).data },
    /** Productos con cuántas piezas puede preparar el vendedor y qué insumos le faltan. */
    async fetchProductos() { this.productos = (await api.get('/vendedor/productos')).data },
  },
})
