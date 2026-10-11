<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useInventoryStore } from '../../stores/inventory'
import api, { mensajeError, money, num } from '../../api'

const inv = useInventoryStore()
const vendedores = ref([])
const userId = ref('')
const lote = ref([])        // inventario actual del vendedor
const lineas = ref([{ insumo_id: '', cantidad: 0 }])
const fondo = ref(0)
const error = ref('')
const aviso = ref('')

// Solo insumos con existencia, sin repetir los ya elegidos en otro renglón
const disponibles = computed(() => inv.conExistencia)
const opciones = (idx) => {
  const usados = lineas.value.filter((_, i) => i !== idx).map((l) => l.insumo_id)
  return disponibles.value.filter((i) => !usados.includes(i.id))
}
const unidad = (id) => inv.insumos.find((i) => i.id === id)?.unidad_medida ?? ''
const existencia = (id) => inv.insumos.find((i) => i.id === id)?.stock_almacen ?? 0

async function cargarVendedor() {
  if (!userId.value) { lote.value = []; fondo.value = 0; return }
  const [l, f] = await Promise.allSettled([
    api.get(`/admin/vendedores/${userId.value}/inventario`),
    api.get(`/admin/vendedores/${userId.value}/fondo`),
  ])
  if (l.status === 'fulfilled') lote.value = l.value.data
  else { lote.value = []; error.value = mensajeError(l.reason) }
  if (f.status === 'fulfilled') fondo.value = f.value.data.monto
  else { fondo.value = 0; error.value = mensajeError(f.reason) }
}
watch(userId, cargarVendedor)

const refrescar = () => Promise.all([cargarVendedor(), inv.fetchInsumos(), inv.fetchAlertas()])

async function asignar() {
  error.value = ''; aviso.value = ''
  try {
    await api.post('/admin/asignaciones', {
      user_id: userId.value, items: lineas.value.filter((l) => l.insumo_id && l.cantidad > 0),
    })
    aviso.value = 'Insumos asignados al vendedor.'
    lineas.value = [{ insumo_id: '', cantidad: 0 }]
    await refrescar()
  } catch (e) { error.value = mensajeError(e) }
}

async function guardarFondo() {
  error.value = ''; aviso.value = ''
  try {
    await api.post('/admin/fondos', { user_id: userId.value, monto: fondo.value })
    aviso.value = `Fondo de caja guardado: ${money(fondo.value)}.`
  } catch (e) { error.value = mensajeError(e) }
}

async function devolver(fila) {
  error.value = ''; aviso.value = ''
  try {
    await api.post('/admin/asignaciones/devolver', {
      user_id: userId.value, items: [{ insumo_id: fila.insumo_id, cantidad: fila.cantidad_asignada }],
    })
    await refrescar()
  } catch (e) { error.value = mensajeError(e) }
}

onMounted(async () => {
  await inv.fetchInsumos()
  vendedores.value = (await api.get('/admin/vendedores')).data.filter((v) => v.activo)
})
</script>

<template>
  <h1 class="text-3xl font-bold mb-4">Asignar turno</h1>
  <div class="grid lg:grid-cols-2 gap-5">
    <div class="space-y-5">
      <section class="card space-y-3">
        <div>
          <label class="label" for="a-vend">Vendedor</label>
          <select id="a-vend" v-model="userId" class="input">
            <option value="" disabled>Elige un vendedor</option>
            <option v-for="v in vendedores" :key="v.id" :value="v.id">{{ v.name }}</option>
          </select>
        </div>

        <form class="flex items-end gap-3" @submit.prevent="guardarFondo">
          <div class="flex-1">
            <label class="label" for="a-fondo">Fondo de caja (cambio del día)</label>
            <input id="a-fondo" v-model.number="fondo" type="number" min="0" step="0.01" class="input" :disabled="!userId" />
          </div>
          <button class="btn-ghost" :disabled="!userId">Guardar fondo</button>
        </form>
      </section>

      <form class="card space-y-3" @submit.prevent="asignar">
        <h2 class="text-xl font-bold">Insumos del turno</h2>
        <p v-if="!disponibles.length" class="text-sm text-chamoy font-bold">No hay insumos con existencia en el almacén. Registra una compra primero.</p>

        <div v-for="(l, idx) in lineas" :key="idx" class="grid grid-cols-[1fr_7rem_auto] gap-2 items-end">
          <div>
            <label class="label" :for="`a-ins-${idx}`">Insumo</label>
            <select :id="`a-ins-${idx}`" v-model="l.insumo_id" class="input">
              <option value="" disabled>Elige</option>
              <option v-for="i in opciones(idx)" :key="i.id" :value="i.id">{{ i.nombre }} (hay {{ num(i.stock_almacen) }} {{ i.unidad_medida }})</option>
            </select>
          </div>
          <div>
            <label class="label" :for="`a-cant-${idx}`">Cantidad {{ unidad(l.insumo_id) }}</label>
            <input :id="`a-cant-${idx}`" v-model.number="l.cantidad" type="number" min="0" :max="existencia(l.insumo_id)" step="any" class="input" />
          </div>
          <button type="button" class="btn-ghost" @click="lineas.splice(idx, 1)" :disabled="lineas.length === 1" aria-label="Quitar renglón">✕</button>
        </div>

        <button type="button" class="btn-ghost" :disabled="lineas.length >= disponibles.length" @click="lineas.push({ insumo_id: '', cantidad: 0 })">Agregar insumo</button>
        <p v-if="error" class="text-chamoy font-bold text-sm" role="alert">{{ error }}</p>
        <p v-if="aviso" class="text-green-700 font-bold text-sm" role="status">{{ aviso }}</p>
        <button class="btn-primary w-full" :disabled="!userId || !disponibles.length">Entregar insumos</button>
      </form>
    </div>

    <section class="card h-fit">
      <h2 class="text-xl font-bold mb-2">Inventario actual del vendedor</h2>
      <p v-if="!userId" class="text-sm text-tinta/60">Elige un vendedor para ver su lote.</p>
      <p v-else-if="!lote.length" class="text-sm text-tinta/60">No tiene insumos asignados.</p>
      <table v-else class="w-full">
        <thead><tr><th class="th">Insumo</th><th class="th text-right">Cantidad</th><th class="th"></th></tr></thead>
        <tbody>
          <tr v-for="f in lote" :key="f.id">
            <td class="td">{{ f.insumo.nombre }}</td>
            <td class="td text-right font-bold">{{ num(f.cantidad_asignada) }} {{ f.insumo.unidad_medida }}</td>
            <td class="td text-right"><button class="text-uva text-sm font-bold disabled:opacity-40" :disabled="f.cantidad_asignada <= 0" @click="devolver(f)">Devolver todo</button></td>
          </tr>
        </tbody>
      </table>
    </section>
  </div>
</template>
