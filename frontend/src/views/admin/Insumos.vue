<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useInventoryStore } from '../../stores/inventory'
import api, { mensajeError, money, num } from '../../api'
import ProveedorSelect from '../../components/ProveedorSelect.vue'

const inv = useInventoryStore()
const entradas = ref([])
const productosActivos = ref([])
// Sabores activos del tamaño elegido (un sabor que ya no manejas en 500 ml no aparece)
const sabores = computed(() => [...new Set(productosActivos.value.filter((p) => p.tamano === surtido.value.tamano).map((p) => p.sabor))])
const error = ref('')
const aviso = ref('')

// ---- Compra normal (la presentación puede variar: chamoy de 1.5 L, de 5.1 L, etc.)
const compra = ref({ insumo_id: '', presentaciones: 1, tam_presentacion: 1, costo_total: 0, proveedor: '' })
const seleccionado = computed(() => inv.insumos.find((i) => i.id === compra.value.insumo_id))
const cantidadBase = computed(() => compra.value.presentaciones * compra.value.tam_presentacion)
const costoUnit = computed(() => (cantidadBase.value ? compra.value.costo_total / cantidadBase.value : 0))
watch(() => compra.value.insumo_id, () => {
  if (seleccionado.value) compra.value.tam_presentacion = seleccionado.value.presentacion_cantidad
})

// ---- Boing surtido
const surtido = ref({ tamano: 250, proveedor: '', costo_total: 0, cantidades: {} })
const piezasSurtido = computed(() => sabores.value.reduce((a, s) => a + (Number(surtido.value.cantidades[s]) || 0), 0))
const cajaSurtido = computed(() => (surtido.value.tamano === 500 ? 24 : 27))

// ---- Insumo nuevo / edición
const vacio = () => ({ nombre: '', unidad_medida: 'pza', stock_minimo: 0, presentacion: '', presentacion_cantidad: 1 })
const nuevo = ref(vacio())
const edicion = ref(null)

async function cargar() {
  await Promise.all([inv.fetchInsumos(), inv.fetchAlertas()])
  entradas.value = (await api.get('/admin/entradas')).data
  productosActivos.value = (await api.get('/productos')).data
}

const ok = (m) => { error.value = ''; aviso.value = m }
const fallo = (e) => { aviso.value = ''; error.value = mensajeError(e) }

async function registrarCompra() {
  try {
    await api.post('/admin/entradas', {
      insumo_id: compra.value.insumo_id, cantidad_ingresada: cantidadBase.value,
      costo_total: compra.value.costo_total, proveedor: compra.value.proveedor,
    })
    ok('Compra registrada y sumada al almacén central.')
    compra.value = { insumo_id: '', presentaciones: 1, tam_presentacion: 1, costo_total: 0, proveedor: '' }
    await cargar()
  } catch (e) { fallo(e) }
}

async function registrarSurtido() {
  try {
    await api.post('/admin/entradas/surtido', {
      tamano: surtido.value.tamano, proveedor: surtido.value.proveedor, costo_total: surtido.value.costo_total,
      sabores: sabores.value.map((s) => ({ sabor: s, cantidad: Number(surtido.value.cantidades[s]) || 0 })),
    })
    ok(`Boing surtido de ${surtido.value.tamano} ml registrado: ${piezasSurtido.value} piezas sumadas por sabor.`)
    surtido.value = { tamano: surtido.value.tamano, proveedor: '', costo_total: 0, cantidades: {} }
    await cargar()
  } catch (e) { fallo(e) }
}

async function crearInsumo() {
  try { await api.post('/admin/insumos', nuevo.value); nuevo.value = vacio(); ok('Insumo creado.'); await cargar() } catch (e) { fallo(e) }
}

const editar = (i) => { edicion.value = { ...i }; error.value = ''; aviso.value = '' }

async function guardarEdicion() {
  try {
    const e = edicion.value
    await api.put(`/admin/insumos/${e.id}`, {
      nombre: e.nombre, unidad_medida: e.unidad_medida, stock_minimo: e.stock_minimo, stock_almacen: e.stock_almacen,
      presentacion: e.presentacion, presentacion_cantidad: e.presentacion_cantidad,
    })
    edicion.value = null
    ok('Insumo actualizado.')
    await cargar()
  } catch (e) { fallo(e) }
}

async function eliminar(i) {
  if (!confirm(`¿Eliminar "${i.nombre}" por completo? Se quitará de todas las recetas y se borrará su historial de compras y mermas.`)) return
  try { await api.delete(`/admin/insumos/${i.id}`); ok(`"${i.nombre}" eliminado.`); await cargar() } catch (e) { fallo(e) }
}

// ---- Filtros y orden del almacén
const orden = ref('nombre')
const filtroUnidad = ref('')
const busqueda = ref('')
const soloBajos = ref(false)
const ordenes = {
  nombre: ['Nombre (A-Z)', (a, b) => a.nombre.localeCompare(b.nombre)],
  mayor: ['Mayor cantidad', (a, b) => b.stock_almacen - a.stock_almacen],
  menor: ['Menor cantidad', (a, b) => a.stock_almacen - b.stock_almacen],
  caro: ['Más caro (costo unitario)', (a, b) => b.costo_unitario - a.costo_unitario],
  barato: ['Más barato (costo unitario)', (a, b) => a.costo_unitario - b.costo_unitario],
  valor: ['Mayor valor en almacén', (a, b) => b.stock_almacen * b.costo_unitario - a.stock_almacen * a.costo_unitario],
  tipo: ['Tipo (gr, ml, pza)', (a, b) => a.unidad_medida.localeCompare(b.unidad_medida) || a.nombre.localeCompare(b.nombre)],
}
const lista = computed(() => {
  const q = busqueda.value.trim().toLowerCase()
  return inv.insumos
    .filter((i) => (!q || i.nombre.toLowerCase().includes(q)) && (!filtroUnidad.value || i.unidad_medida === filtroUnidad.value) && (!soloBajos.value || bajo(i)))
    .sort(ordenes[orden.value][1])
})

const bajo = (i) => i.stock_almacen <= i.stock_minimo
onMounted(cargar)
</script>

<template>
  <h1 class="text-3xl font-bold mb-4">Insumos y compras</h1>
  <p v-if="error" class="text-chamoy font-bold mb-3" role="alert">{{ error }}</p>
  <p v-if="aviso" class="text-green-700 font-bold mb-3" role="status">{{ aviso }}</p>

  <div class="grid lg:grid-cols-2 gap-5 mb-5">
    <!-- Compra normal -->
    <form class="card space-y-3" @submit.prevent="registrarCompra">
      <h2 class="text-xl font-bold">Registrar compra</h2>
      <div>
        <label class="label" for="c-insumo">Insumo</label>
        <select id="c-insumo" v-model="compra.insumo_id" class="input" required>
          <option value="" disabled>Elige un insumo</option>
          <option v-for="i in inv.insumos" :key="i.id" :value="i.id">{{ i.nombre }}</option>
        </select>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="label" for="c-pres">Presentaciones compradas</label>
          <input id="c-pres" v-model.number="compra.presentaciones" type="number" min="0.01" step="any" class="input" required />
        </div>
        <div>
          <label class="label" for="c-tam">{{ seleccionado?.unidad_medida || 'Unidades' }} por presentación</label>
          <input id="c-tam" v-model.number="compra.tam_presentacion" type="number" min="0.01" step="any" class="input" required />
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="label" for="c-costo">Costo total ($)</label><input id="c-costo" v-model.number="compra.costo_total" type="number" min="0" step="0.01" class="input" required /></div>
        <ProveedorSelect id="c-prov" v-model="compra.proveedor" />
      </div>
      <p v-if="seleccionado" class="text-sm text-uva bg-uva-claro rounded-lg px-3 py-2">
        Entran {{ num(cantidadBase) }} {{ seleccionado.unidad_medida }} · esta compra cuesta {{ money(costoUnit) }} por {{ seleccionado.unidad_medida }}
      </p>
      <p class="text-xs text-tinta/60">Cambia las unidades por presentación si compraste otra medida, por ejemplo chamoy de 5100 ml en lugar de 1500 ml.</p>
      <button class="btn-primary">Guardar compra</button>
    </form>

    <!-- Boing surtido -->
    <form class="card space-y-3" @submit.prevent="registrarSurtido">
      <h2 class="text-xl font-bold">Compra de Boing surtido</h2>
      <div class="inline-flex rounded-lg bg-uva-claro p-1" role="group" aria-label="Tamaño de la caja">
        <button type="button" v-for="t in [250, 500]" :key="t" class="px-4 py-1.5 rounded-md font-bold"
          :class="surtido.tamano === t ? 'bg-uva text-white' : 'text-uva'" @click="surtido.tamano = t; surtido.cantidades = {}">{{ t }} ml</button>
      </div>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div v-for="s in sabores" :key="s">
          <label class="label" :for="`s-${s}`">{{ s }}</label>
          <input :id="`s-${s}`" v-model.number="surtido.cantidades[s]" type="number" min="0" step="1" class="input" placeholder="0" />
        </div>
      </div>
      <p class="text-sm rounded-lg px-3 py-2" :class="piezasSurtido === cajaSurtido ? 'bg-green-50 text-green-800' : 'bg-mango-claro text-amber-900'">
        {{ piezasSurtido }} piezas capturadas · la caja de {{ surtido.tamano }} ml trae {{ cajaSurtido }}
        <template v-if="piezasSurtido !== cajaSurtido"> (revisa si faltan o sobran)</template>
      </p>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="label" for="s-costo">Costo total ($)</label><input id="s-costo" v-model.number="surtido.costo_total" type="number" min="0" step="0.01" class="input" required /></div>
        <ProveedorSelect id="s-prov" v-model="surtido.proveedor" />
      </div>
      <p class="text-xs text-tinta/60">El costo se reparte entre los sabores según las piezas de cada uno.</p>
      <button class="btn-primary" :disabled="!piezasSurtido">Guardar surtido</button>
    </form>
  </div>

  <!-- Almacén -->
  <section class="card overflow-x-auto mb-5">
    <h2 class="text-xl font-bold mb-2">Almacén central</h2>
    <div class="grid sm:grid-cols-4 gap-3 mb-3">
      <div><label class="label" for="f-q">Buscar</label><input id="f-q" v-model="busqueda" class="input" placeholder="Chamoy…" /></div>
      <div><label class="label" for="f-o">Ordenar por</label>
        <select id="f-o" v-model="orden" class="input"><option v-for="(o, k) in ordenes" :key="k" :value="k">{{ o[0] }}</option></select></div>
      <div><label class="label" for="f-u">Tipo</label>
        <select id="f-u" v-model="filtroUnidad" class="input"><option value="">Todos</option><option value="gr">Gramos (gr)</option><option value="ml">Mililitros (ml)</option><option value="pza">Piezas (pza)</option></select></div>
      <label class="flex items-end gap-2 pb-2.5 cursor-pointer"><input type="checkbox" v-model="soloBajos" class="size-4 accent-uva" /><span>Solo por resurtir</span></label>
    </div>
    <p v-if="!lista.length" class="text-sm text-tinta/60">Ningún insumo coincide con el filtro.</p>
    <table class="w-full min-w-[44rem]">
      <thead><tr><th class="th">Insumo</th><th class="th text-right">Existencia</th><th class="th text-right">Mínimo</th><th class="th text-right">Presentación</th><th class="th text-right">Costo unitario</th><th class="th"></th></tr></thead>
      <tbody>
        <tr v-for="i in lista" :key="i.id" :class="bajo(i) ? 'bg-chamoy-claro' : ''">
          <td class="td font-bold">{{ i.nombre }}<span v-if="bajo(i)" class="ml-2 text-xs text-chamoy">Resurtir</span></td>
          <td class="td text-right">{{ num(i.stock_almacen) }} {{ i.unidad_medida }}</td>
          <td class="td text-right">{{ num(i.stock_minimo) }}</td>
          <td class="td text-right text-sm">{{ i.presentacion || '—' }} ({{ num(i.presentacion_cantidad) }})</td>
          <td class="td text-right">{{ money(i.costo_unitario) }}/{{ i.unidad_medida }}</td>
          <td class="td text-right whitespace-nowrap">
            <button class="text-uva font-bold text-sm mr-3" @click="editar(i)">Editar</button>
            <button class="text-chamoy font-bold text-sm" @click="eliminar(i)">Eliminar</button>
          </td>
        </tr>
      </tbody>
    </table>
  </section>

  <form class="card space-y-3 mb-5" @submit.prevent="crearInsumo">
    <h2 class="text-xl font-bold">Nuevo insumo</h2>
    <div class="grid sm:grid-cols-5 gap-3">
      <div class="sm:col-span-2"><label class="label" for="n-nombre">Nombre</label><input id="n-nombre" v-model="nuevo.nombre" class="input" placeholder="Gomitas tiburón" required /></div>
      <div><label class="label" for="n-u">Unidad</label>
        <select id="n-u" v-model="nuevo.unidad_medida" class="input"><option>gr</option><option>ml</option><option>pza</option></select></div>
      <div><label class="label" for="n-pres">Presentación</label><input id="n-pres" v-model="nuevo.presentacion" class="input" placeholder="Kilo" /></div>
      <div><label class="label" for="n-pc">Unidades por presentación</label><input id="n-pc" v-model.number="nuevo.presentacion_cantidad" type="number" min="0.01" step="any" class="input" required /></div>
    </div>
    <div class="flex items-end gap-3">
      <div class="w-40"><label class="label" for="n-min">Stock mínimo</label><input id="n-min" v-model.number="nuevo.stock_minimo" type="number" min="0" step="any" class="input" required /></div>
      <button class="btn-ghost">Crear insumo</button>
    </div>
  </form>

  <section class="card overflow-x-auto">
    <h2 class="text-xl font-bold mb-2">Compras recientes</h2>
    <p v-if="!entradas.length" class="text-sm text-tinta/60">Aún no registras compras.</p>
    <table v-else class="w-full min-w-[34rem]">
      <thead><tr><th class="th">Fecha</th><th class="th">Insumo</th><th class="th">Proveedor</th><th class="th text-right">Cantidad</th><th class="th text-right">Costo</th></tr></thead>
      <tbody>
        <tr v-for="e in entradas" :key="e.id">
          <td class="td">{{ e.fecha?.slice(0, 10) }}</td><td class="td">{{ e.insumo.nombre }}</td><td class="td">{{ e.proveedor }}</td>
          <td class="td text-right">{{ num(e.cantidad_ingresada) }} {{ e.insumo.unidad_medida }}</td><td class="td text-right">{{ money(e.costo_total) }}</td>
        </tr>
      </tbody>
    </table>
  </section>

  <!-- Edición de insumo -->
  <div v-if="edicion" class="fixed inset-0 bg-tinta/50 grid place-items-center p-3 z-30" role="dialog" aria-modal="true" aria-label="Editar insumo">
    <form class="card w-full max-w-lg space-y-3" @submit.prevent="guardarEdicion">
      <h2 class="text-xl font-bold">Editar {{ edicion.nombre }}</h2>
      <div class="grid grid-cols-3 gap-3">
        <div class="col-span-2"><label class="label" for="e-n">Nombre</label><input id="e-n" v-model="edicion.nombre" class="input" required /></div>
        <div><label class="label" for="e-u">Unidad</label><select id="e-u" v-model="edicion.unidad_medida" class="input"><option>gr</option><option>ml</option><option>pza</option></select></div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="label" for="e-p">Presentación</label><input id="e-p" v-model="edicion.presentacion" class="input" /></div>
        <div><label class="label" for="e-pc">Unidades por presentación</label><input id="e-pc" v-model.number="edicion.presentacion_cantidad" type="number" min="0.01" step="any" class="input" required /></div>
        <div><label class="label" for="e-s">Existencia en almacén ({{ edicion.unidad_medida }})</label><input id="e-s" v-model.number="edicion.stock_almacen" type="number" min="0" step="any" class="input" required /></div>
        <div><label class="label" for="e-m">Stock mínimo</label><input id="e-m" v-model.number="edicion.stock_minimo" type="number" min="0" step="any" class="input" required /></div>
      </div>
      <p class="text-xs text-tinta/60">Cambiar la existencia es un ajuste manual (conteo físico). No afecta lo que ya tienen los vendedores.</p>
      <div class="flex justify-end gap-2">
        <button type="button" class="btn-ghost" @click="edicion = null">Cancelar</button>
        <button class="btn-primary">Guardar cambios</button>
      </div>
    </form>
  </div>
</template>
