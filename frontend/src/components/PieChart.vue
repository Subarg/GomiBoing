<script setup>
import { computed } from 'vue'

const props = defineProps({
  items: { type: Array, default: () => [] },   // [{ etiqueta, total }]
  titulo: { type: String, default: '' },
})
const colores = ['#4B2A7B', '#FFB627', '#D7263D', '#FF7A8A', '#2E9E6B', '#2D7DD2', '#8D5A97']

const total = computed(() => props.items.reduce((a, i) => a + i.total, 0))
const segmentos = computed(() => {
  let acumulado = 0
  return props.items.map((it, idx) => {
    const pct = total.value ? (it.total / total.value) * 100 : 0
    const seg = { ...it, pct, color: colores[idx % colores.length], offset: 25 - acumulado }
    acumulado += pct
    return seg
  })
})
</script>

<template>
  <div class="flex items-center gap-4 flex-wrap">
    <svg viewBox="0 0 42 42" class="w-36 h-36 shrink-0" role="img" :aria-label="`${titulo}: ${items.map((i) => `${i.etiqueta} ${i.total}`).join(', ') || 'sin datos'}`">
      <circle cx="21" cy="21" r="15.9155" fill="none" stroke="#EFE8F7" stroke-width="7" />
      <circle v-for="s in segmentos" :key="s.etiqueta" cx="21" cy="21" r="15.9155" fill="none"
        :stroke="s.color" stroke-width="7" :stroke-dasharray="`${s.pct} ${100 - s.pct}`" :stroke-dashoffset="s.offset" />
      <text x="21" y="22.5" text-anchor="middle" font-size="7" font-weight="700" fill="#1E1626">{{ total }}</text>
    </svg>
    <ul class="text-sm space-y-1 min-w-32 flex-1">
      <li v-if="!items.length" class="text-tinta/60">Sin ventas todavía.</li>
      <li v-for="s in segmentos" :key="s.etiqueta" class="flex items-center gap-2">
        <span class="size-3 rounded-sm shrink-0" :style="{ background: s.color }"></span>
        <span class="flex-1">{{ s.etiqueta }}</span>
        <b>{{ s.total }}</b><span class="text-tinta/60 w-10 text-right">{{ Math.round(s.pct) }}%</span>
      </li>
    </ul>
  </div>
</template>
