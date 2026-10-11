<script setup>
import { onMounted, ref } from 'vue'
import { useInventoryStore } from '../../stores/inventory'
import api, { mensajeError, num } from '../../api'

const inv = useInventoryStore()
const mermas = ref([])
const form = ref({ insumo_id: '', cantidad: 1, motivo: '' })
const error = ref('')
const aviso = ref('')

async function cargar() {
  await inv.fetchMiInventario()
  mermas.value = (await api.get('/vendedor/mermas')).data
}

async function registrar() {
  error.value = ''; aviso.value = ''
  try {
    await api.post('/vendedor/mermas', form.value)
    aviso.value = 'Merma registrada. No genera comisión ni cobro.'
    form.value = { insumo_id: '', cantidad: 1, motivo: '' }
    await cargar()
  } catch (e) { error.value = mensajeError(e) }
}

onMounted(cargar)
</script>

<template>
  <h1 class="text-3xl font-bold mb-4">Mi inventario</h1>
  <div class="grid md:grid-cols-2 gap-5">
    <section class="card">
      <h2 class="text-xl font-bold mb-2">Lo que tengo asignado</h2>
      <p v-if="!inv.miInventario.length" class="text-tinta/60 text-sm">Aún no tienes insumos asignados. Pídele al administrador tu lote del turno.</p>
      <table v-else class="w-full">
        <thead><tr><th class="th">Insumo</th><th class="th text-right">Cantidad</th></tr></thead>
        <tbody>
          <tr v-for="i in inv.miInventario" :key="i.id">
            <td class="td">{{ i.insumo.nombre }}</td>
            <td class="td text-right font-bold">{{ num(i.cantidad_asignada) }} {{ i.insumo.unidad_medida }}</td>
          </tr>
        </tbody>
      </table>
    </section>

    <section class="card">
      <h2 class="text-xl font-bold mb-2">Registrar merma</h2>
      <form class="space-y-3" @submit.prevent="registrar">
        <div>
          <label class="label" for="m-insumo">Producto dañado</label>
          <select id="m-insumo" v-model="form.insumo_id" class="input" required>
            <option value="" disabled>Elige un insumo</option>
            <option v-for="i in inv.miInventario" :key="i.insumo_id" :value="i.insumo_id">{{ i.insumo.nombre }} ({{ num(i.cantidad_asignada) }} {{ i.insumo.unidad_medida }})</option>
          </select>
        </div>
        <div class="grid grid-cols-3 gap-3">
          <div><label class="label" for="m-cant">Cantidad</label><input id="m-cant" v-model.number="form.cantidad" type="number" min="0.01" step="any" class="input" required /></div>
          <div class="col-span-2"><label class="label" for="m-motivo">Motivo</label><input id="m-motivo" v-model="form.motivo" class="input" placeholder="Boing roto" required /></div>
        </div>
        <p v-if="error" class="text-chamoy text-sm font-bold" role="alert">{{ error }}</p>
        <p v-if="aviso" class="text-green-700 text-sm font-bold" role="status">{{ aviso }}</p>
        <button class="btn-danger">Dar de baja</button>
      </form>

      <h3 class="font-bold mt-5 mb-1">Mis mermas recientes</h3>
      <p v-if="!mermas.length" class="text-sm text-tinta/60">Sin mermas registradas.</p>
      <ul class="text-sm divide-y divide-uva/10">
        <li v-for="m in mermas" :key="m.id" class="py-1.5 flex justify-between gap-2">
          <span>{{ m.insumo.nombre }} — {{ m.motivo }}</span><b>{{ num(m.cantidad) }} {{ m.insumo.unidad_medida }}</b>
        </li>
      </ul>
    </section>
  </div>
</template>
