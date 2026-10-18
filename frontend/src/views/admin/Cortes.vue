<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import api, { mensajeError, money } from '../../api'
import CorteDesglose from '../../components/CorteDesglose.vue'

const abiertos = ref([])
const cerrados = ref([])
const error = ref('')
let timer

async function cargar() {
  error.value = ''
  const [a, b] = await Promise.allSettled([api.get('/admin/monitor'), api.get('/admin/cortes')])
  if (a.status === 'fulfilled') abiertos.value = a.value.data; else error.value = mensajeError(a.reason)
  if (b.status === 'fulfilled') cerrados.value = b.value.data; else error.value = mensajeError(b.reason)
}
const fecha = (f) => new Date(f).toLocaleString('es-MX', { dateStyle: 'medium', timeStyle: 'short' })

onMounted(() => { cargar(); timer = setInterval(cargar, 20000) })
onBeforeUnmount(() => clearInterval(timer))
</script>

<template>
  <h1 class="text-3xl font-bold mb-4">Cortes de caja</h1>

  <p v-if="error" class="text-chamoy font-bold mb-3" role="alert">{{ error }}</p>
  <h2 class="text-xl font-bold mb-2">Turnos abiertos</h2>
  <p v-if="!abiertos.length" class="text-sm text-tinta/60 mb-5">No hay vendedores activos.</p>
  <div class="grid md:grid-cols-2 gap-4 mb-6">
    <section v-for="v in abiertos" :key="v.id" class="card">
      <h3 class="text-lg font-bold mb-2">{{ v.name }}</h3>
      <CorteDesglose :r="v" admin />
    </section>
  </div>

  <h2 class="text-xl font-bold mb-2">Cortes cerrados</h2>
  <p v-if="!cerrados.length" class="text-sm text-tinta/60">Todavía no hay cortes cerrados.</p>
  <div class="space-y-2">
    <details v-for="c in cerrados" :key="c.id" class="card">
      <summary class="cursor-pointer flex justify-between gap-2 flex-wrap">
        <span><b>{{ c.vendedor }}</b> <span class="text-sm text-tinta/60">· {{ fecha(c.fecha) }}</span></span>
        <b>{{ money(c.dinero_total) }}</b>
      </summary>
      <div class="mt-3"><CorteDesglose :r="c" admin /></div>
    </details>
  </div>
</template>
