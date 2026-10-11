<script setup>
import { computed, onMounted, onBeforeUnmount, ref } from 'vue'
import api, { money } from '../../api'

const d = ref(null)
const monitor = ref([])
let timer

async function cargar() {
  const [a, b] = await Promise.all([api.get('/admin/dashboard'), api.get('/admin/monitor')])
  d.value = a.data
  monitor.value = b.data
}

const maxSabor = computed(() => Math.max(1, ...(d.value?.por_sabor.map((x) => x.total) ?? [1])))
const maxTam = computed(() => Math.max(1, ...(d.value?.por_tamano.map((x) => x.total) ?? [1])))

onMounted(() => { cargar(); timer = setInterval(cargar, 15000) }) // monitor casi en tiempo real
onBeforeUnmount(() => clearInterval(timer))
</script>

<template>
  <h1 class="text-3xl font-bold mb-4">Panel</h1>

  <template v-if="d">
    <section class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
      <div class="card"><p class="text-sm text-tinta/70">Vendidos hoy</p><p class="font-display text-3xl font-bold">{{ d.hoy.unidades }}</p></div>
      <div class="card"><p class="text-sm text-tinta/70">Ingresos hoy</p><p class="font-display text-3xl font-bold">{{ money(d.hoy.ingresos) }}</p><p class="text-xs text-tinta/60">Efectivo {{ money(d.hoy.efectivo) }} · Transf. {{ money(d.hoy.transferencia) }}</p></div>
      <div class="card"><p class="text-sm text-tinta/70">Costo de insumos</p><p class="font-display text-3xl font-bold">{{ money(d.hoy.costo_insumos) }}</p><p class="text-xs text-tinta/60">Comisiones {{ money(d.hoy.comisiones) }}</p></div>
      <div class="card bg-uva-claro"><p class="text-sm text-uva">Utilidad hoy</p><p class="font-display text-3xl font-bold text-uva">{{ money(d.hoy.utilidad) }}</p></div>
    </section>

    <section class="card mb-5 overflow-x-auto">
      <h2 class="text-xl font-bold mb-2">Vendedores en turno</h2>
      <p v-if="!monitor.length" class="text-sm text-tinta/60">No hay vendedores activos. Crea uno en la sección Vendedores.</p>
      <table v-else class="w-full min-w-[44rem]">
        <thead><tr><th class="th">Vendedor</th><th class="th text-right">Boings</th><th class="th text-right">Fondo</th><th class="th text-right">Efectivo</th><th class="th text-right">Transferencia</th><th class="th text-right">Su ganancia</th><th class="th text-right">Debe entregar</th></tr></thead>
        <tbody>
          <tr v-for="v in monitor" :key="v.id">
            <td class="td font-bold">{{ v.name }}</td><td class="td text-right">{{ v.unidades }}</td>
            <td class="td text-right">{{ money(v.fondo) }}</td>
            <td class="td text-right">{{ money(v.efectivo) }}</td><td class="td text-right">{{ money(v.transferencia) }}</td>
            <td class="td text-right font-bold text-uva">{{ money(v.comision) }}</td>
            <td class="td text-right font-bold">
              <template v-if="v.entregar >= 0">{{ money(v.entregar) }}</template>
              <template v-else><span class="text-chamoy">Pagarle {{ money(-v.entregar) }}</span></template>
            </td>
          </tr>
        </tbody>
      </table>
      <p class="text-xs text-tinta/60 mt-2">Se actualiza cada 15 segundos. Solo cuenta ventas aún sin corte. Debe entregar = fondo + efectivo − su ganancia.</p>
    </section>

    <section class="grid md:grid-cols-2 gap-5">
      <div class="card">
        <h2 class="text-xl font-bold mb-3">Sabores más vendidos</h2>
        <p v-if="!d.por_sabor.length" class="text-sm text-tinta/60">Aún no hay ventas.</p>
        <div v-for="s in d.por_sabor" :key="s.etiqueta" class="mb-2">
          <div class="flex justify-between text-sm"><span>{{ s.etiqueta }}</span><b>{{ s.total }}</b></div>
          <div class="h-3 rounded bg-uva-claro"><div class="h-3 rounded bg-uva" :style="{ width: (s.total / maxSabor) * 100 + '%' }"></div></div>
        </div>
      </div>
      <div class="card">
        <h2 class="text-xl font-bold mb-3">Tamaños más vendidos</h2>
        <p v-if="!d.por_tamano.length" class="text-sm text-tinta/60">Aún no hay ventas.</p>
        <div v-for="s in d.por_tamano" :key="s.etiqueta" class="mb-2">
          <div class="flex justify-between text-sm"><span>{{ s.etiqueta }} ml</span><b>{{ s.total }}</b></div>
          <div class="h-3 rounded bg-mango-claro"><div class="h-3 rounded bg-mango" :style="{ width: (s.total / maxTam) * 100 + '%' }"></div></div>
        </div>
      </div>
    </section>
  </template>
</template>
