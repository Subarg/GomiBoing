<script setup>
import { computed, onMounted, onBeforeUnmount, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useInventoryStore } from '../stores/inventory'
import { num } from '../api'

const auth = useAuthStore()
const inv = useInventoryStore()
const router = useRouter()
const abierta = ref(false)
let timer

const links = computed(() =>
  auth.esAdmin
    ? [['/admin', 'Panel'], ['/admin/insumos', 'Insumos y compras'], ['/admin/recetas', 'Recetas'], ['/admin/asignar', 'Asignar turno'], ['/admin/vendedores', 'Vendedores']]
    : [['/vendedor', 'Vender'], ['/vendedor/inventario', 'Mi inventario'], ['/vendedor/corte', 'Corte de caja']]
)

async function salir() {
  await auth.logout()
  router.push('/login')
}

onMounted(() => {
  if (auth.esAdmin) {
    inv.fetchAlertas()
    timer = setInterval(inv.fetchAlertas, 30000)
  }
})
onBeforeUnmount(() => clearInterval(timer))
</script>

<template>
  <header class="bg-uva text-white">
    <div class="mx-auto max-w-6xl px-3 sm:px-5 py-3 flex items-center gap-3 flex-wrap">
      <span class="font-display text-2xl font-extrabold tracking-tight">Gomiboing</span>

      <nav class="flex gap-1 overflow-x-auto flex-1 min-w-0" aria-label="Principal">
        <router-link
          v-for="[to, texto] in links" :key="to" :to="to"
          class="px-3 py-1.5 rounded-lg whitespace-nowrap hover:bg-white/10"
          active-class="bg-white/20 font-bold" exact-active-class="bg-white/20 font-bold"
        >{{ texto }}</router-link>
      </nav>

      <div class="relative" v-if="auth.esAdmin">
        <button class="relative p-2 rounded-lg hover:bg-white/10" @click="abierta = !abierta" :aria-expanded="abierta" aria-label="Alertas de stock">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
          <span v-if="inv.alertas.length" class="absolute -top-0.5 -right-0.5 bg-chamoy text-white text-xs font-bold rounded-full min-w-5 h-5 grid place-items-center px-1">{{ inv.alertas.length }}</span>
        </button>
        <div v-if="abierta" class="absolute right-0 mt-2 w-72 max-w-[90vw] bg-white text-tinta rounded-xl shadow-xl border border-uva/15 z-20 p-3">
          <p class="font-display font-bold mb-2">Por resurtir</p>
          <p v-if="!inv.alertas.length" class="text-sm text-tinta/70">Todos los insumos están por encima de su mínimo.</p>
          <ul v-else class="space-y-1.5 max-h-72 overflow-auto">
            <li v-for="a in inv.alertas" :key="a.id" class="flex justify-between gap-2 text-sm bg-chamoy-claro rounded-lg px-2.5 py-1.5">
              <span>{{ a.nombre }}</span>
              <span class="font-bold text-chamoy">{{ num(a.stock_almacen) }} / {{ num(a.stock_minimo) }} {{ a.unidad_medida }}</span>
            </li>
          </ul>
          <router-link to="/admin/insumos" class="btn-ghost w-full mt-3 text-sm" @click="abierta = false">Registrar compra</router-link>
        </div>
      </div>

      <span class="hidden sm:inline text-sm text-white/80">{{ auth.user.name }}</span>
      <button class="px-3 py-1.5 rounded-lg bg-white/15 hover:bg-white/25 text-sm font-bold" @click="salir">Salir</button>
    </div>
  </header>
</template>
