<script setup>
import { onMounted, ref } from 'vue'
import api, { mensajeError, money } from '../../api'
import CorteDesglose from '../../components/CorteDesglose.vue'

const ventas = ref([])
const cortes = ref([])
const error = ref('')
const aviso = ref('')

async function cargar() {
  const [v, c] = await Promise.all([api.get('/vendedor/ventas'), api.get('/vendedor/cortes')])
  ventas.value = v.data
  cortes.value = c.data
}

async function eliminar(v) {
  if (!confirm(`¿Eliminar la venta de ${v.cantidad} × ${v.producto.nombre} (${money(v.total_venta)})? Los insumos regresan a tu inventario.`)) return
  error.value = ''; aviso.value = ''
  try {
    await api.delete(`/vendedor/ventas/${v.id}`)
    aviso.value = 'Venta eliminada. Tus insumos regresaron a tu inventario.'
    await cargar()
  } catch (e) { error.value = mensajeError(e) }
}

const hora = (f) => new Date(f).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })
const fecha = (f) => new Date(f).toLocaleDateString('es-MX', { dateStyle: 'medium' })
onMounted(cargar)
</script>

<template>
  <h1 class="text-3xl font-bold mb-4">Mis ventas</h1>
  <p v-if="error" class="text-chamoy font-bold mb-3" role="alert">{{ error }}</p>
  <p v-if="aviso" class="text-green-700 font-bold mb-3" role="status">{{ aviso }}</p>

  <section class="card mb-5">
    <h2 class="text-xl font-bold mb-2">Turno actual</h2>
    <p v-if="!ventas.length" class="text-sm text-tinta/60">Todavía no has vendido en este turno.</p>
    <ul class="divide-y divide-uva/10">
      <li v-for="v in ventas" :key="v.id" class="py-2.5 flex items-center justify-between gap-3">
        <div class="min-w-0">
          <p class="font-bold truncate">{{ v.cantidad }} × {{ v.producto.nombre }}</p>
          <p class="text-sm text-tinta/60 capitalize">{{ hora(v.fecha) }} · {{ v.metodo_pago }}<span v-if="v.cucharas_extra"> · +{{ v.cucharas_extra }} cucharas</span></p>
        </div>
        <div class="text-right shrink-0">
          <p class="font-bold">{{ money(v.total_venta) }}</p>
          <button class="text-chamoy text-sm font-bold" @click="eliminar(v)">Eliminar</button>
        </div>
      </li>
    </ul>
    <p class="text-xs text-tinta/60 mt-3">Si vendiste algo por error, elimínalo aquí antes del corte. El administrador puede ver las ventas eliminadas.</p>
  </section>

  <section>
    <h2 class="text-xl font-bold mb-2">Cortes anteriores</h2>
    <p v-if="!cortes.length" class="text-sm text-tinta/60">Aún no tienes cortes cerrados.</p>
    <div class="space-y-2">
      <details v-for="c in cortes" :key="c.id" class="card">
        <summary class="cursor-pointer flex justify-between gap-2"><span>{{ fecha(c.fecha) }}</span><b>{{ money(c.dinero_total) }}</b></summary>
        <div class="mt-3"><CorteDesglose :r="c" /></div>
      </details>
    </div>
  </section>
</template>
