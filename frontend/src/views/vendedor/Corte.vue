<script setup>
import { onMounted, ref } from 'vue'
import api, { mensajeError, num } from '../../api'
import CorteDesglose from '../../components/CorteDesglose.vue'

const r = ref(null)
const devolver = ref(false)
const error = ref('')
const hecho = ref(null)
const enviando = ref(false)

const cargar = async () => {
  try { r.value = (await api.get('/vendedor/corte')).data; error.value = '' } catch (e) { error.value = mensajeError(e) }
}

async function cerrar() {
  if (!confirm('¿Cerrar el turno? Las ventas actuales quedarán incluidas en este corte.')) return
  error.value = ''; enviando.value = true
  try {
    hecho.value = (await api.post('/vendedor/corte', { devolver_sobrante: devolver.value })).data
    await cargar()
  } catch (e) { error.value = mensajeError(e) } finally { enviando.value = false }
}

onMounted(cargar)
</script>

<template>
  <h1 class="text-3xl font-bold mb-4">Corte de caja</h1>
  <p v-if="error && !r" class="text-chamoy font-bold" role="alert">{{ error }}</p>
  <div v-if="r" class="grid md:grid-cols-2 gap-5">
    <section class="card"><CorteDesglose :r="r" /></section>

    <section class="card h-fit">
      <h2 class="text-xl font-bold mb-2">Sobrante en tu inventario</h2>
      <p v-if="!(r.sobrante ?? []).length" class="text-sm text-tinta/60">No tienes sobrante.</p>
      <ul v-else class="text-sm divide-y divide-uva/10 mb-3">
        <li v-for="s in (r.sobrante ?? [])" :key="s.id" class="py-1.5 flex justify-between"><span>{{ s.insumo.nombre }}</span><b>{{ num(s.cantidad_asignada) }} {{ s.insumo.unidad_medida }}</b></li>
      </ul>

      <label class="flex items-start gap-2 mb-4 cursor-pointer">
        <input type="checkbox" v-model="devolver" class="mt-1 size-4 accent-uva" />
        <span class="text-sm">Devolver el sobrante al administrador. Si no lo marcas, queda como inventario inicial de tu siguiente turno.</span>
      </label>

      <p v-if="error" class="text-chamoy text-sm font-bold mb-2" role="alert">{{ error }}</p>
      <p v-if="hecho" class="text-green-700 text-sm font-bold mb-2" role="status">Corte #{{ hecho.id }} registrado.</p>
      <button class="btn-primary w-full" :disabled="(!r.unidades && !r.saldo_inicial) || enviando" @click="cerrar">Cerrar turno</button>
    </section>
  </div>
</template>
