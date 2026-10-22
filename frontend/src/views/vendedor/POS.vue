<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useCartStore } from '../../stores/cart'
import { useInventoryStore } from '../../stores/inventory'
import api, { mensajeError, money } from '../../api'

const cart = useCartStore()
const inv = useInventoryStore()
const tamano = ref(250)
const error = ref('')
const aviso = ref('')
const enviando = ref(false)
const ventas = ref([])
const caja = ref(null)

const EPS = 0.00001
const stock = (id) => inv.pos.stock[id] ?? 0

// Lo que ya está en la cuenta (receta de cada vaso + cucharas extra) por insumo
const consumo = computed(() => {
  const c = {}
  for (const l of cart.items) {
    for (const ln of l.producto.lineas) c[ln.insumo_id] = (c[ln.insumo_id] || 0) + ln.cantidad * l.cantidad
    if (l.cucharas_extra && inv.pos.cuchara) c[inv.pos.cuchara] = (c[inv.pos.cuchara] || 0) + l.cucharas_extra
  }
  return c
})
const restante = (id) => stock(id) - (consumo.value[id] || 0)

// Cuántos vasos más de ese producto alcanzan, descontando lo que ya está en la cuenta
function capacidad(p) {
  if (!p.lineas.length) return 0
  return Math.min(...p.lineas.map((ln) => Math.floor((restante(ln.insumo_id) + EPS) / ln.cantidad)))
}
// Insumos que impiden preparar un vaso más
const bloqueo = (p) => p.lineas.filter((ln) => restante(ln.insumo_id) + EPS < ln.cantidad).map((ln) => inv.pos.insumos[ln.insumo_id]?.nombre ?? 'Insumo')

// Un sabor aparece solo si el vendedor tiene SU Boing; si falta otro insumo, aparece bloqueado con el motivo
const visibles = computed(() => inv.pos.productos.filter((p) => p.boing_insumo_id && stock(p.boing_insumo_id) >= 1))
const tamanos = computed(() => [...new Set(visibles.value.map((p) => p.tamano))].sort((a, b) => a - b))
const sabores = computed(() => visibles.value.filter((p) => p.tamano === tamano.value))
const faltantesGlobal = computed(() => [...new Set(visibles.value.filter((p) => capacidad(p) < 1).flatMap(bloqueo))])

const colorSabor = { Manzana: 'bg-lime-100 text-lime-900', Mango: 'bg-mango-claro text-amber-900', Uva: 'bg-uva-claro text-uva', Guayaba: 'bg-chamoy-claro text-chamoy' }
const vendidasHoy = computed(() => ventas.value.reduce((a, v) => a + v.cantidad, 0))

watch(tamanos, (t) => { if (t.length && !t.includes(tamano.value)) tamano.value = t[0] })

async function cargarVentas() { ventas.value = (await api.get('/vendedor/ventas')).data }
async function cargarCaja() { caja.value = (await api.get('/vendedor/corte')).data }

function elegir(p) {
  if (capacidad(p) < 1) {
    aviso.value = ''
    error.value = `No puedes preparar ${p.nombre}: te falta ${bloqueo(p).join(', ')}.`
    return
  }
  error.value = ''
  cart.agregar(p)
}
function mas(l) { if (capacidad(l.producto) >= 1) cart.cambiar(l.producto.id, 1) }
function masCucharas(l) { if (inv.pos.cuchara && restante(inv.pos.cuchara) >= 1) cart.cucharas(l.producto.id, 1) }

async function cobrar() {
  error.value = ''; aviso.value = ''; enviando.value = true
  try {
    const total = cart.total
    await cart.cobrar()
    aviso.value = `Venta registrada: ${money(total)}`
  } catch (e) {
    error.value = mensajeError(e)
  } finally {
    enviando.value = false
    await Promise.all([cargarVentas(), inv.fetchPos(), cargarCaja()])
  }
}

onMounted(async () => {
  try {
    await Promise.all([inv.fetchPos(), cargarVentas(), cargarCaja()])
  } catch (e) { error.value = mensajeError(e) }
  if (tamanos.value.length && !tamanos.value.includes(tamano.value)) tamano.value = tamanos.value[0]
})
</script>

<template>
  <div class="grid lg:grid-cols-[1fr_22rem] gap-5">
    <section>
      <div class="flex items-end justify-between gap-2 mb-3">
        <h1 class="text-3xl font-bold">Vender</h1>
        <div class="text-sm text-tinta/70 text-right">
          <p>Turno actual: <b>{{ vendidasHoy }}</b> vendidos</p>
          <p v-if="caja">Saldo inicial: <b>{{ money(caja.saldo_inicial) }}</b> · Efectivo en caja: <b>{{ money(caja.efectivo_en_caja) }}</b></p>
        </div>
      </div>

      <div v-if="!visibles.length" class="card text-center py-8">
        <p class="font-bold text-lg">No tienes Boing asignados.</p>
        <p class="text-tinta/70 text-sm mt-1">Pídele al administrador que te asigne tu lote del turno.</p>
      </div>

      <template v-else>
        <p v-if="faltantesGlobal.length" class="rounded-lg bg-chamoy-claro text-chamoy px-3 py-2 text-sm font-bold mb-3" role="status">
          Te quedan Boing, pero te falta: {{ faltantesGlobal.join(', ') }}. Estos insumos los comparten todos los sabores; pídeselos al administrador.
        </p>

        <div class="inline-flex rounded-lg bg-uva-claro p-1 mb-4" role="group" aria-label="Tamaño">
          <button v-for="t in tamanos" :key="t" class="px-5 py-2 rounded-md font-bold"
            :class="tamano === t ? 'bg-uva text-white' : 'text-uva'" @click="tamano = t">{{ t }} ml</button>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-2 xl:grid-cols-4 gap-3">
          <button v-for="p in sabores" :key="p.id" @click="elegir(p)" :aria-disabled="capacidad(p) < 1"
            class="rounded-xl p-4 text-left min-h-28 flex flex-col justify-between active:scale-[.98] transition-transform"
            :class="[colorSabor[p.sabor] || 'bg-white', capacidad(p) < 1 ? 'opacity-50' : '']">
            <span class="font-display text-2xl font-bold">{{ p.sabor }}</span>
            <span>
              <span class="font-bold block">{{ money(p.precio_venta) }}</span>
              <span v-if="capacidad(p) >= 1" class="text-xs">{{ capacidad(p) }} disponibles</span>
              <span v-else class="text-xs font-bold">Falta: {{ bloqueo(p).join(', ') }}</span>
            </span>
          </button>
        </div>
      </template>
    </section>

    <aside id="cuenta" class="card h-fit lg:sticky lg:top-4 scroll-mt-16">
      <h2 class="text-2xl font-bold mb-3">Cuenta</h2>
      <p v-if="!cart.items.length" class="text-tinta/60 text-sm">Toca un sabor para agregarlo.</p>

      <ul class="space-y-3">
        <li v-for="l in cart.items" :key="l.producto.id" class="border-b border-uva/10 pb-3">
          <div class="flex justify-between gap-2">
            <span class="font-bold">{{ l.producto.nombre }}</span>
            <button class="text-chamoy text-sm font-bold" @click="cart.quitar(l.producto.id)">Quitar</button>
          </div>
          <div class="flex items-center justify-between mt-2 text-sm">
            <div class="flex items-center gap-2">
              <button class="btn-ghost !px-3 !py-1" @click="cart.cambiar(l.producto.id, -1)" aria-label="Menos">−</button>
              <b class="w-6 text-center">{{ l.cantidad }}</b>
              <button class="btn-ghost !px-3 !py-1" :disabled="capacidad(l.producto) < 1" @click="mas(l)" aria-label="Más">+</button>
            </div>
            <span class="font-bold">{{ money(l.producto.precio_venta * l.cantidad) }}</span>
          </div>
          <div class="flex items-center gap-2 mt-2 text-sm">
            <span>Cucharas extra</span>
            <button class="btn-ghost !px-2.5 !py-0.5" @click="cart.cucharas(l.producto.id, -1)" aria-label="Menos cucharas">−</button>
            <b>{{ l.cucharas_extra }}</b>
            <button class="btn-ghost !px-2.5 !py-0.5" :disabled="!inv.pos.cuchara || restante(inv.pos.cuchara) < 1" @click="masCucharas(l)" aria-label="Más cucharas">+</button>
          </div>
        </li>
      </ul>

      <fieldset class="mt-4">
        <legend class="label">Método de pago</legend>
        <div class="grid grid-cols-2 gap-2">
          <label v-for="m in ['efectivo', 'transferencia']" :key="m"
            class="rounded-lg border-2 py-2.5 text-center font-bold cursor-pointer capitalize"
            :class="cart.metodoPago === m ? 'border-uva bg-uva-claro text-uva' : 'border-uva/15'">
            <input type="radio" class="sr-only" :value="m" v-model="cart.metodoPago" />{{ m }}
          </label>
        </div>
      </fieldset>

      <div class="flex justify-between items-baseline mt-4">
        <span>Total</span><span class="font-display text-3xl font-bold">{{ money(cart.total) }}</span>
      </div>
      <p class="text-sm text-uva text-right">Tu ganancia: {{ money(cart.comision) }}</p>

      <p v-if="error" class="text-chamoy text-sm font-bold mt-3" role="alert">{{ error }}</p>
      <p v-if="aviso" class="text-green-700 text-sm font-bold mt-3" role="status">{{ aviso }}</p>

      <button class="btn-primary w-full mt-4 text-lg" :disabled="!cart.listo || enviando" @click="cobrar">
        {{ enviando ? 'Cobrando…' : cart.items.length && !cart.metodoPago ? 'Elige método de pago' : 'Cobrar' }}
      </button>
    </aside>
  </div>

  <a v-if="cart.items.length" href="#cuenta"
    class="lg:hidden fixed bottom-3 inset-x-3 z-20 bg-uva text-white rounded-xl px-4 py-3 flex justify-between items-center shadow-lg font-bold">
    <span>Cuenta · {{ cart.unidades }} pzas</span><span class="font-display text-xl">{{ money(cart.total) }} ›</span>
  </a>
  <div v-if="cart.items.length" class="h-16 lg:hidden"></div>
</template>
