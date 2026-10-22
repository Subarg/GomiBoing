<script setup>
import { num } from '../api'
defineProps({ c: { type: Object, required: true } })
</script>

<template>
  <div class="space-y-3">
    <div class="rounded-lg bg-uva-claro px-3 py-2">
      <p class="text-sm text-uva">Con este lote alcanzan</p>
      <p class="font-display text-3xl font-bold text-uva">{{ c.vasos_total }} vasos</p>
      <p v-if="c.limitan.length" class="text-xs text-uva/80">Lo que limita: {{ c.limitan.join(', ') }}</p>
    </div>

    <div>
      <p class="text-sm font-bold mb-1">Insumos que comparten todos los sabores</p>
      <p v-if="!c.compartidos.length" class="text-sm text-tinta/60">Sin datos.</p>
      <ul class="text-sm divide-y divide-uva/10">
        <li v-for="i in c.compartidos" :key="i.insumo_id" class="py-1 flex justify-between gap-2">
          <span>{{ i.nombre }}</span>
          <span :class="i.vasos <= 5 ? 'text-chamoy font-bold' : ''">{{ num(i.stock) }} {{ i.unidad_medida }} · {{ i.vasos }} vasos</span>
        </li>
        <li class="py-1 flex justify-between gap-2"><span>Boing (todos los sabores)</span><span>{{ num(c.boings) }} pzas</span></li>
      </ul>
    </div>

    <div>
      <p class="text-sm font-bold mb-1">Por producto</p>
      <ul class="text-sm divide-y divide-uva/10">
        <li v-for="p in c.productos" :key="p.id" class="py-1">
          <div class="flex justify-between gap-2"><span>{{ p.nombre }}</span><b :class="p.disponible ? '' : 'text-chamoy'">{{ p.disponible }}</b></div>
          <p v-if="p.faltan.length" class="text-xs text-chamoy">Falta: {{ p.faltan.join(', ') }}</p>
        </li>
      </ul>
    </div>
  </div>
</template>
