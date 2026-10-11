<script setup>
import { onMounted, ref } from 'vue'
import api, { mensajeError, money, num } from '../../api'

const r = ref(null)
const devolver = ref(false)
const error = ref('')
const hecho = ref(null)
const enviando = ref(false)

const cargar = async () => { r.value = (await api.get('/vendedor/corte')).data }

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
  <div v-if="r" class="grid md:grid-cols-2 gap-5">
    <section class="card">
      <dl class="space-y-2.5">
        <div class="flex justify-between"><dt>Saldo inicial (cambio)</dt><dd class="font-bold">{{ money(r.saldo_inicial) }}</dd></div>

        <div class="border-t border-uva/15 pt-2.5 flex justify-between">
          <dt>Boing de 500 ml vendidos<span class="block text-sm text-tinta/60">{{ r.unidades_500 }} pzas</span></dt>
          <dd class="font-bold">{{ money(r.total_500) }}</dd>
        </div>
        <div class="flex justify-between">
          <dt>Boing de 250 ml vendidos<span class="block text-sm text-tinta/60">{{ r.unidades_250 }} pzas</span></dt>
          <dd class="font-bold">{{ money(r.total_250) }}</dd>
        </div>
        <div class="flex justify-between border-t border-uva/15 pt-2.5">
          <dt>Total vendido<span class="block text-sm text-tinta/60">Efectivo {{ money(r.total_efectivo) }} · Transferencia {{ money(r.total_transferencia) }}</span></dt>
          <dd class="font-display text-2xl font-bold">{{ money(r.total_vendido) }}</dd>
        </div>

        <div class="flex justify-between text-uva border-t border-uva/15 pt-2.5">
          <dt>Ganancia tuya</dt><dd class="font-bold">{{ money(r.comision) }}</dd>
        </div>
      </dl>

      <div class="rounded-lg bg-mango-claro p-3 mt-4">
        <p class="text-sm">En tu caja debes tener (saldo inicial + efectivo): <b>{{ money(r.efectivo_en_caja) }}</b></p>
        <template v-if="r.monto_entregar > 0">
          <p class="text-sm mt-2">Entrega al administrador (en caja menos tu ganancia)</p>
          <p class="font-display text-3xl font-bold">{{ money(r.monto_entregar) }}</p>
        </template>
        <template v-else-if="r.monto_recibir > 0">
          <p class="text-sm mt-2">Tu caja no alcanza para pagar tu ganancia. No entregas dinero y el administrador te completa</p>
          <p class="font-display text-3xl font-bold">{{ money(r.monto_recibir) }}</p>
        </template>
        <template v-else>
          <p class="text-sm mt-2">No hay nada que entregar</p>
          <p class="font-display text-3xl font-bold">{{ money(0) }}</p>
        </template>
        <p class="text-xs text-tinta/60 mt-1">{{ money(r.saldo_inicial) }} + {{ money(r.total_efectivo) }} − {{ money(r.comision) }}</p>
      </div>
    </section>

    <section class="card">
      <h2 class="text-xl font-bold mb-2">Sobrante en tu inventario</h2>
      <p v-if="!r.sobrante.length" class="text-sm text-tinta/60">No tienes sobrante.</p>
      <ul v-else class="text-sm divide-y divide-uva/10 mb-3">
        <li v-for="s in r.sobrante" :key="s.id" class="py-1.5 flex justify-between"><span>{{ s.insumo.nombre }}</span><b>{{ num(s.cantidad_asignada) }} {{ s.insumo.unidad_medida }}</b></li>
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
