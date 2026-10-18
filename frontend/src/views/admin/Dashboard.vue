<script setup>
import { computed, onMounted, onBeforeUnmount, ref } from 'vue'
import api, { mensajeError, money } from '../../api'
import PieChart from '../../components/PieChart.vue'

const d = ref(null)
const monitor = ref([])
const vista = ref('hoy')
const error = ref('')
const base = () => ({
  hoy: { unidades: 0, ingresos: 0, efectivo: 0, transferencia: 0, comisiones: 0, costo_insumos: 0, utilidad: 0 },
  por_sabor_hoy: [], por_tamano_hoy: [], por_hora_hoy: [], por_vendedor_hoy: [], por_sabor: [], por_tamano: [], eliminadas_hoy: [],
})
let timer

async function cargar() {
  // Cada petición va por separado: si una falla, la otra se muestra igual
  const [a, b] = await Promise.allSettled([api.get('/admin/dashboard'), api.get('/admin/monitor')])
  const b0 = base()
  if (a.status === 'fulfilled') {
    d.value = { ...b0, ...a.value.data, hoy: { ...b0.hoy, ...a.value.data.hoy } }
    error.value = ''
  } else {
    d.value = d.value ?? b0
    error.value = mensajeError(a.reason)
  }
  if (b.status === 'fulfilled') monitor.value = b.value.data
  else if (a.status === 'fulfilled') error.value = mensajeError(b.reason)
}

const conMl = (lista) => lista.map((x) => ({ ...x, etiqueta: `${x.etiqueta} ml` }))
const pieSabor = computed(() => (vista.value === 'hoy' ? d.value.por_sabor_hoy : d.value.por_sabor))
const pieTamano = computed(() => conMl(vista.value === 'hoy' ? d.value.por_tamano_hoy : d.value.por_tamano))
const maxVendedor = computed(() => Math.max(1, ...(d.value?.por_vendedor_hoy.map((v) => v.unidades) ?? [1])))
const maxHora = computed(() => Math.max(1, ...(d.value?.por_hora_hoy.map((h) => h.u250 + h.u500) ?? [1])))
const pct = (n, max) => `${(n / max) * 100}%`

onMounted(() => { cargar(); timer = setInterval(cargar, 15000) }) // casi en tiempo real
onBeforeUnmount(() => clearInterval(timer))
</script>

<template>
  <div class="flex items-end justify-between gap-2 mb-4">
    <h1 class="text-3xl font-bold">Panel</h1>
    <p class="text-xs text-tinta/60">Se actualiza cada 15 s</p>
  </div>

  <p v-if="error" class="text-chamoy font-bold mb-3" role="alert">{{ error }}</p>

  <template v-if="d">
    <section class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
      <div class="card"><p class="text-sm text-tinta/70">Vendidos hoy</p><p class="font-display text-3xl font-bold">{{ d.hoy.unidades }}</p></div>
      <div class="card"><p class="text-sm text-tinta/70">Ingresos hoy</p><p class="font-display text-3xl font-bold">{{ money(d.hoy.ingresos) }}</p><p class="text-xs text-tinta/60">Efectivo {{ money(d.hoy.efectivo) }} · Transf. {{ money(d.hoy.transferencia) }}</p></div>
      <div class="card"><p class="text-sm text-tinta/70">Costo de insumos</p><p class="font-display text-3xl font-bold">{{ money(d.hoy.costo_insumos) }}</p><p class="text-xs text-tinta/60">Ganancia vendedores {{ money(d.hoy.comisiones) }}</p></div>
      <div class="card bg-uva-claro"><p class="text-sm text-uva">Utilidad hoy</p><p class="font-display text-3xl font-bold text-uva">{{ money(d.hoy.utilidad) }}</p></div>
    </section>

    <!-- Vendedores en turno -->
    <section class="card mb-5">
      <div class="flex items-center justify-between gap-2 mb-2">
        <h2 class="text-xl font-bold">Vendedores en turno</h2>
        <router-link to="/admin/cortes" class="text-uva font-bold text-sm">Ver desglose de caja</router-link>
      </div>
      <p v-if="!monitor.length" class="text-sm text-tinta/60">No hay vendedores activos.</p>
      <ul class="divide-y divide-uva/10">
        <li v-for="v in monitor" :key="v.id" class="py-2.5">
          <div class="flex justify-between gap-2">
            <span class="font-bold">{{ v.name }}</span>
            <span class="font-display text-xl font-bold">{{ money(v.dinero_total ?? 0) }}</span>
          </div>
          <div class="flex flex-wrap gap-x-4 text-sm text-tinta/70">
            <span>{{ v.unidades_500 }} grandes · {{ v.unidades_250 }} chicos</span>
            <span>Ganancia {{ money(v.comision) }}</span>
            <span v-if="v.monto_recibir > 0" class="text-chamoy font-bold">Completarle {{ money(v.monto_recibir) }}</span>
            <span v-else class="font-bold">Entrega {{ money(v.monto_entregar) }}</span>
          </div>
        </li>
      </ul>
    </section>

    <!-- Cómo va cada vendedor hoy -->
    <section class="card mb-5">
      <h2 class="text-xl font-bold mb-1">Ventas de hoy por vendedor</h2>
      <p class="text-xs text-tinta/60 mb-3 flex gap-3">
        <span class="inline-flex items-center gap-1"><span class="size-3 rounded-sm bg-uva"></span>Grande 500 ml</span>
        <span class="inline-flex items-center gap-1"><span class="size-3 rounded-sm bg-mango"></span>Chico 250 ml</span>
      </p>
      <p v-if="!d.por_vendedor_hoy.length" class="text-sm text-tinta/60">Aún no hay ventas hoy.</p>
      <div v-for="v in d.por_vendedor_hoy" :key="v.name" class="mb-3">
        <div class="flex justify-between text-sm"><span class="font-bold">{{ v.name }}</span><span>{{ v.unidades }} pzas · {{ money(v.ingresos) }}</span></div>
        <div class="flex h-5 rounded bg-uva-claro overflow-hidden" role="img" :aria-label="`${v.name}: ${v.u500} grandes y ${v.u250} chicos`">
          <div class="bg-uva text-white text-xs grid place-items-center" :style="{ width: pct(v.u500, maxVendedor) }">{{ v.u500 || '' }}</div>
          <div class="bg-mango text-tinta text-xs grid place-items-center" :style="{ width: pct(v.u250, maxVendedor) }">{{ v.u250 || '' }}</div>
        </div>
      </div>
    </section>

    <!-- Pasteles -->
    <section class="card mb-5">
      <div class="flex items-center justify-between gap-2 mb-3 flex-wrap">
        <h2 class="text-xl font-bold">Más vendidos</h2>
        <div class="inline-flex rounded-lg bg-uva-claro p-1" role="group" aria-label="Periodo">
          <button v-for="[v, t] in [['hoy', 'Hoy'], ['historico', 'Histórico']]" :key="v" class="px-4 py-1.5 rounded-md font-bold text-sm"
            :class="vista === v ? 'bg-uva text-white' : 'text-uva'" @click="vista = v">{{ t }}</button>
        </div>
      </div>
      <div class="grid md:grid-cols-2 gap-6">
        <div><h3 class="font-bold mb-2">Por sabor</h3><PieChart :items="pieSabor" titulo="Por sabor" /></div>
        <div><h3 class="font-bold mb-2">Por tamaño</h3><PieChart :items="pieTamano" titulo="Por tamaño" /></div>
      </div>
    </section>

    <!-- Por hora -->
    <section class="card mb-5">
      <h2 class="text-xl font-bold mb-3">Ventas de hoy por hora</h2>
      <p v-if="!d.por_hora_hoy.length" class="text-sm text-tinta/60">Aún no hay ventas hoy.</p>
      <div v-else class="flex items-end gap-2 h-40 overflow-x-auto pb-1">
        <div v-for="h in d.por_hora_hoy" :key="h.hora" class="flex flex-col items-center justify-end h-full min-w-10 flex-1">
          <span class="text-xs font-bold">{{ h.u250 + h.u500 }}</span>
          <div class="w-full flex flex-col justify-end rounded-t overflow-hidden" :style="{ height: pct(h.u250 + h.u500, maxHora) }">
            <div class="bg-mango" :style="{ height: pct(h.u250, h.u250 + h.u500) }"></div>
            <div class="bg-uva" :style="{ height: pct(h.u500, h.u250 + h.u500) }"></div>
          </div>
          <span class="text-xs text-tinta/60 mt-1">{{ h.hora }}</span>
        </div>
      </div>
    </section>

    <section v-if="d.eliminadas_hoy.length" class="card border-chamoy/30">
      <h2 class="text-xl font-bold mb-2">Ventas eliminadas hoy</h2>
      <ul class="text-sm divide-y divide-uva/10">
        <li v-for="(e, i) in d.eliminadas_hoy" :key="i" class="py-1.5 flex justify-between gap-2">
          <span>{{ e.vendedor }} — {{ e.cantidad }} × {{ e.producto }}</span><b>{{ money(e.total_venta) }}</b>
        </li>
      </ul>
    </section>
  </template>
</template>
