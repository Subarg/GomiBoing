<script setup>
import { computed } from 'vue'
import { money } from '../api'
const props = defineProps({ r: { type: Object, required: true }, admin: Boolean })
const dinero = computed(() => props.r.dinero_total ?? (props.r.saldo_inicial || 0) + (props.r.total_efectivo || 0) + (props.r.total_transferencia || 0))
</script>

<template>
  <div class="space-y-4">
    <!-- 1. Dinero total -->
    <section>
      <p class="text-sm text-tinta/70">Dinero total (caja + efectivo + transferencias)</p>
      <p class="font-display text-4xl font-bold">{{ money(dinero) }}</p>
      <dl class="mt-2 text-sm space-y-1">
        <div class="flex justify-between"><dt>Saldo inicial en caja</dt><dd class="font-bold">{{ money(r.saldo_inicial) }}</dd></div>
        <div class="flex justify-between"><dt>Efectivo de ventas</dt><dd class="font-bold">{{ money(r.total_efectivo) }}</dd></div>
        <div class="flex justify-between"><dt>Transferencias</dt><dd class="font-bold">{{ money(r.total_transferencia) }}</dd></div>
      </dl>
    </section>

    <!-- 2. Boing grande y chico -->
    <section class="border-t border-uva/15 pt-3 space-y-3">
      <div>
        <div class="flex justify-between"><span class="font-bold">Boing grande (500 ml)</span><b>{{ money(r.total_500) }}</b></div>
        <p class="text-sm text-tinta/70">{{ r.unidades_500 }} pzas · ganancia {{ money(r.comision_500) }}</p>
      </div>
      <div>
        <div class="flex justify-between"><span class="font-bold">Boing chico (250 ml)</span><b>{{ money(r.total_250) }}</b></div>
        <p class="text-sm text-tinta/70">{{ r.unidades_250 }} pzas · ganancia {{ money(r.comision_250) }}</p>
      </div>
      <div class="flex justify-between border-t border-uva/10 pt-2"><span>Total vendido</span><b>{{ money(r.total_vendido) }}</b></div>
    </section>

    <!-- 3. Ganancia del vendedor -->
    <section class="border-t border-uva/15 pt-3">
      <div class="flex justify-between items-baseline text-uva">
        <span class="font-bold">Ganancia {{ admin ? 'del vendedor' : 'tuya' }}</span>
        <b class="font-display text-2xl">{{ money(r.comision) }}</b>
      </div>
      <p class="text-xs text-tinta/60 text-right">{{ money(r.comision_500) }} grandes + {{ money(r.comision_250) }} chicos</p>
    </section>

    <section class="rounded-lg bg-mango-claro p-3">
      <p class="text-sm">Efectivo en caja (saldo inicial + efectivo): <b>{{ money(r.efectivo_en_caja) }}</b></p>
      <template v-if="r.monto_entregar > 0">
        <p class="text-sm mt-2">{{ admin ? 'El vendedor entrega (en caja menos su ganancia)' : 'Entrega al administrador (en caja menos tu ganancia)' }}</p>
        <p class="font-display text-3xl font-bold">{{ money(r.monto_entregar) }}</p>
      </template>
      <template v-else-if="r.monto_recibir > 0">
        <p class="text-sm mt-2">{{ admin ? 'La caja no cubre su ganancia: hay que completarle' : 'Tu caja no cubre tu ganancia. No entregas dinero y el administrador te completa' }}</p>
        <p class="font-display text-3xl font-bold">{{ money(r.monto_recibir) }}</p>
      </template>
      <template v-else>
        <p class="text-sm mt-2">No hay nada que entregar</p>
        <p class="font-display text-3xl font-bold">{{ money(0) }}</p>
      </template>
      <p class="text-xs text-tinta/60 mt-1">{{ money(r.saldo_inicial) }} + {{ money(r.total_efectivo) }} − {{ money(r.comision) }}</p>
    </section>
  </div>
</template>
