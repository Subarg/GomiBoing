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

// Solo tamaños y sabores que el vendedor todavía puede preparar
const tamanos = computed(() => [...new Set(inv.disponibles.map((p) => p.tamano))].sort((a, b) => a - b))
const sabores = computed(() => inv.disponibles.filter((p) => p.tamano === tamano.value))
const colorSabor = { Manzana: 'bg-lime-100 text-lime-900', Mango: 'bg-mango-claro text-amber-900', Uva: 'bg-uva-claro text-uva', Guayaba: 'bg-chamoy-claro text-chamoy' }
const vendidasHoy = computed(() => ventas.value.reduce((a, v) => a + v.cantidad, 0))

// Si el tamaño elegido se agota, cambia al que sí tenga existencia
watch(tamanos, (t) => { if (t.length && !t.includes(tamano.value)) tamano.value = t[0] })

// Insumos que le faltan para preparar cualquier producto
const faltantes = computed(() => [...new Set(inv.productos.flatMap((p) => p.faltan))])

async function cargarCaja() { caja.value = (await api.get('/vendedor/corte')).data }
async function cargarVentas() { ventas.value = (await api.get('/vendedor/ventas')).data }

function elegir(p) {
  if (!cart.agregar(p)) {
    aviso.value = ''
    error.value = `Solo te quedan ${p.disponible} de ${p.nombre}.`
  } else error.value = ''
}

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
    await Promise.all([cargarVentas(), inv.fetchProductos(), cargarCaja()]) // refresca sabores y caja
  }
}

onMounted(async () => {
  await Promise.all([inv.fetchProductos(), cargarVentas(), cargarCaja()])
  if (tamanos.value.length && !tamanos.value.includes(tamano.value)) tamano.value = tamanos.value[0]
})
</script>

<template>
  <div class="grid lg:grid-cols-[1fr_22rem] gap-5">
    <section>
      <div class="flex items-end justify-between mb-3">
        <h1 class="text-3xl font-bold">Vender</h1>
        <div class="text-sm text-tinta/70 text-right">
          <p>Turno actual: <b>{{ vendidasHoy }}</b> vendidos</p>
          <p v-if="caja">Saldo inicial: <b>{{ money(caja.saldo_inicial) }}</b> · Efectivo en caja: <b>{{ money(caja.efectivo_en_caja) }}</b></p>
        </div>
      </div>

      <div v-if="!inv.disponibles.length" class="card text-center py-8">
        <p class="font-bold text-lg">No puedes preparar ningún Gomiboing con tu lote actual.</p>
        <p v-if="faltantes.length" class="text-tinta/70 text-sm mt-2">Te hace falta:</p>
        <ul v-if="faltantes.length" class="text-sm mt-1 flex flex-wrap justify-center gap-1.5">
          <li v-for="f in faltantes" :key="f" class="bg-chamoy-claro text-chamoy rounded-full px-2.5 py-0.5">{{ f }}</li>
        </ul>
        <p class="text-tinta/70 text-sm mt-3">Pídele al administrador que complete tu lote del turno.</p>
      </div>

      <template v-else>
        <div class="inline-flex rounded-lg bg-uva-claro p-1 mb-4" role="group" aria-label="Tamaño">
          <button v-for="t in tamanos" :key="t" class="px-5 py-2 rounded-md font-bold"
            :class="tamano === t ? 'bg-uva text-white' : 'text-uva'" @click="tamano = t">{{ t }} ml</button>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-2 xl:grid-cols-4 gap-3">
          <button v-for="p in sabores" :key="p.id" @click="elegir(p)"
            class="rounded-xl p-4 text-left min-h-28 flex flex-col justify-between active:scale-[.98] transition-transform"
            :class="colorSabor[p.sabor] || 'bg-white'">
            <span class="font-display text-2xl font-bold">{{ p.sabor }}</span>
            <span>
              <span class="font-bold block">{{ money(p.precio_venta) }}</span>
              <span class="text-xs">{{ p.disponible - cart.enCarrito(p.id) }} disponibles</span>
            </span>
          </button>
        </div>
      </template>
    </section>

    <aside class="card h-fit lg:sticky lg:top-4">
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
              <button class="btn-ghost !px-3 !py-1" :disabled="l.cantidad >= l.producto.disponible" @click="cart.cambiar(l.producto.id, 1)" aria-label="Más">+</button>
            </div>
            <span class="font-bold">{{ money(l.producto.precio_venta * l.cantidad) }}</span>
          </div>
          <div class="flex items-center gap-2 mt-2 text-sm">
            <span>Cucharas extra</span>
            <button class="btn-ghost !px-2.5 !py-0.5" @click="cart.cucharas(l.producto.id, -1)" aria-label="Menos cucharas">−</button>
            <b>{{ l.cucharas_extra }}</b>
            <button class="btn-ghost !px-2.5 !py-0.5" @click="cart.cucharas(l.producto.id, 1)" aria-label="Más cucharas">+</button>
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
</template>
