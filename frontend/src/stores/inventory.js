import { defineStore } from 'pinia'
import api from '../api'

export const useInventoryStore = defineStore('inventory', {
  state: () => ({
    insumos: [],      // almacén central (admin)
    alertas: [],      // insumos en o bajo el mínimo (admin)
    miInventario: [], // lote asignado al vendedor
    // Datos del POS del vendedor: productos con las líneas de su receta, stock y nombres de insumos
    pos: { productos: [], stock: {}, insumos: {}, cuchara: null },
  }),
  getters: {
    cantidadDe: (s) => (insumoId) =>
      s.miInventario.find((i) => i.insumo_id === insumoId)?.cantidad_asignada ?? 0,
    conExistencia: (s) => s.insumos.filter((i) => i.stock_almacen > 0),
    // Insumos que no son Boing (los Boing se asignan por sabor, no van en la receta)
    insumosReceta: (s) => s.insumos.filter((i) => !i.nombre.startsWith('Boing ')),
  },
  actions: {
    async fetchInsumos() { this.insumos = (await api.get('/admin/insumos')).data },
    async fetchAlertas() { this.alertas = (await api.get('/admin/alertas')).data },
    async fetchMiInventario() { this.miInventario = (await api.get('/vendedor/inventario')).data },
    async fetchPos() {
      const d = (await api.get('/vendedor/productos')).data
      this.pos = {
        productos: d.productos,
        stock: Object.fromEntries(d.stock.map((s) => [s.insumo_id, s.cantidad])),
        insumos: Object.fromEntries(d.insumos.map((i) => [i.id, i])),
        cuchara: d.cuchara_insumo_id,
      }
    },
  },
})
