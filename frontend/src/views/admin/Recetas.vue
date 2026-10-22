<script setup>
import { computed, onMounted, ref } from 'vue'
import { useInventoryStore } from '../../stores/inventory'
import api, { mensajeError, num } from '../../api'

const inv = useInventoryStore()
const productos = ref([])
const guardadas = ref([]) // recetas tal como están guardadas en el servidor
const error = ref('')
const aviso = ref('')

const masivo = ref({ accion: 'agregar', insumo_id: '', nuevo_insumo_id: '', cantidad: 1, tamano: '' })

// El Boing no va en la receta: se asigna por sabor y tamaño en "Sabores y precios"
const insumosOrdenados = computed(() => [...inv.insumosReceta].sort((a, b) => a.nombre.localeCompare(b.nombre)))
const unidad = (id) => inv.insumos.find((i) => i.id === id)?.unidad_medida ?? ''

async function cargar() {
  await inv.fetchInsumos()
  const data = (await api.get('/admin/recetas')).data.map((p) => ({ ...p, activo: p.activo ?? true }))
  guardadas.value = JSON.parse(JSON.stringify(data))
  productos.value = data.map((p) => ({ ...p, sucio: false }))
}

const ok = (m) => { error.value = ''; aviso.value = m }
const fallo = (e) => { aviso.value = ''; error.value = mensajeError(e) }

// ---- Edición por producto
const opciones = (p, idx) => {
  const usados = p.receta.filter((_, i) => i !== idx).map((l) => l.insumo_id)
  return insumosOrdenados.value.filter((i) => !usados.includes(i.id))
}
function agregarLinea(p) { p.receta.push({ insumo_id: '', cantidad: 1 }); p.sucio = true }
function quitarLinea(p, idx) { p.receta.splice(idx, 1); p.sucio = true }

async function guardar(p) {
  const items = p.receta.filter((l) => l.insumo_id && l.cantidad > 0).map((l) => ({ insumo_id: l.insumo_id, cantidad: l.cantidad }))
  try {
    await api.put(`/admin/recetas/${p.id}`, { items })
    ok(`Receta de ${p.nombre} guardada.`)
    await cargar()
  } catch (e) { fallo(e) }
}

// ---- Cambio en bloque
const textoAccion = { agregar: 'agregar o actualizar', quitar: 'quitar', reemplazar: 'reemplazar' }
async function aplicarMasivo() {
  const m = masivo.value
  const alcance = m.tamano ? `las recetas de ${m.tamano} ml` : 'todas las recetas'
  if (!confirm(`¿Seguro que quieres ${textoAccion[m.accion]} este insumo en ${alcance}?`)) return
  try {
    const { data } = await api.post('/admin/recetas/masivo', {
      accion: m.accion, insumo_id: m.insumo_id,
      nuevo_insumo_id: m.accion === 'reemplazar' ? m.nuevo_insumo_id : null,
      cantidad: m.accion === 'quitar' ? null : m.cantidad,
      tamano: m.tamano || null,
    })
    ok(`Listo: ${data.afectados} recetas modificadas.`)
    await cargar()
  } catch (e) { fallo(e) }
}

// ---- Receta vigente: insumos (filas) x productos activos (columnas)
const activas = computed(() => guardadas.value.filter((p) => p.activo))
const filas = computed(() => {
  const mapa = new Map()
  for (const p of activas.value) {
    for (const l of p.receta) {
      if (!mapa.has(l.insumo_id)) mapa.set(l.insumo_id, { key: l.insumo_id, nombre: l.nombre, unidad: l.unidad_medida, valores: {} })
      mapa.get(l.insumo_id).valores[p.id] = l.cantidad
    }
  }
  return [...mapa.values()].sort((a, b) => a.nombre.localeCompare(b.nombre))
})
const resumen = (id) => (guardadas.value.find((p) => p.id === id)?.receta ?? []).map((l) => `${l.nombre} ${num(l.cantidad)} ${l.unidad_medida}`).join(' · ')

onMounted(cargar)
</script>

<template>
  <h1 class="text-3xl font-bold mb-4">Recetas</h1>
  <p v-if="error" class="text-chamoy font-bold mb-3" role="alert">{{ error }}</p>
  <p v-if="aviso" class="text-green-700 font-bold mb-3" role="status">{{ aviso }}</p>

  <p class="text-sm text-tinta/70 mb-4">
    La receta lista lo que se descuenta por vaso y es compartido entre sabores (gomitas, chamoy, tajín…).
    Cada producto lleva además <b>su propio Boing</b> (1 pza), que se asigna en «Sabores y precios».
  </p>

  <section class="card overflow-x-auto mb-5">
    <h2 class="text-xl font-bold mb-1">Receta actual</h2>
    <p class="text-sm text-tinta/70 mb-3">Un guion significa que ese insumo no se usa en ese producto.</p>
    <table class="w-full min-w-[40rem] text-sm">
      <thead>
        <tr>
          <th class="th">Insumo</th>
          <th v-for="p in activas" :key="p.id" class="th text-right whitespace-nowrap">{{ p.sabor }} {{ p.tamano }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="f in filas" :key="f.key">
          <td class="td font-bold">{{ f.nombre }} <span class="font-normal text-tinta/60">({{ f.unidad }})</span></td>
          <td v-for="p in activas" :key="p.id" class="td text-right" :class="f.valores[p.id] ? '' : 'text-tinta/30'">
            {{ f.valores[p.id] ? num(f.valores[p.id]) : '—' }}
          </td>
        </tr>
        <tr class="bg-uva-claro/50">
          <td class="td font-bold">Boing propio (pza)</td>
          <td v-for="p in activas" :key="p.id" class="td text-right" :class="p.boing ? '' : 'text-chamoy font-bold'">
            {{ p.boing ? '1' : 'Sin Boing' }}
          </td>
        </tr>
      </tbody>
    </table>
  </section>

  <form class="card space-y-3 mb-5" @submit.prevent="aplicarMasivo">
    <h2 class="text-xl font-bold">Cambio para ocasiones especiales</h2>
    <p class="text-sm text-tinta/70">Agrega unas gomitas especiales (tiburón, araña), retira los palitos de tamarindo o cambia un insumo por otro en varias recetas a la vez. No toca los Boing.</p>
    <div class="grid sm:grid-cols-4 gap-3">
      <div>
        <label class="label" for="m-a">Acción</label>
        <select id="m-a" v-model="masivo.accion" class="input">
          <option value="agregar">Agregar o actualizar</option>
          <option value="quitar">Quitar</option>
          <option value="reemplazar">Reemplazar por otro</option>
        </select>
      </div>
      <div>
        <label class="label" for="m-i">{{ masivo.accion === 'reemplazar' ? 'Insumo a cambiar' : 'Insumo' }}</label>
        <select id="m-i" v-model="masivo.insumo_id" class="input" required>
          <option value="" disabled>Elige</option>
          <option v-for="i in insumosOrdenados" :key="i.id" :value="i.id">{{ i.nombre }}</option>
        </select>
      </div>
      <div v-if="masivo.accion === 'reemplazar'">
        <label class="label" for="m-n">Nuevo insumo</label>
        <select id="m-n" v-model="masivo.nuevo_insumo_id" class="input" required>
          <option value="" disabled>Elige</option>
          <option v-for="i in insumosOrdenados.filter((x) => x.id !== masivo.insumo_id)" :key="i.id" :value="i.id">{{ i.nombre }}</option>
        </select>
      </div>
      <div v-if="masivo.accion !== 'quitar'">
        <label class="label" for="m-c">Cantidad por vaso {{ masivo.accion === 'reemplazar' ? '(opcional)' : '' }}</label>
        <input id="m-c" v-model.number="masivo.cantidad" type="number" min="0.0001" step="any" class="input" :required="masivo.accion === 'agregar'" />
      </div>
      <div>
        <label class="label" for="m-t">Aplicar a</label>
        <select id="m-t" v-model="masivo.tamano" class="input">
          <option value="">Todos los productos</option>
          <option :value="250">Solo 250 ml</option>
          <option :value="500">Solo 500 ml</option>
        </select>
      </div>
    </div>
    <button class="btn-primary">Aplicar cambio</button>
  </form>

  <h2 class="text-xl font-bold mb-2">Editar por producto</h2>
  <section class="space-y-3">
    <details v-for="p in productos" :key="p.id" class="card">
      <summary class="cursor-pointer font-display text-lg font-bold flex justify-between gap-2">
        <span>{{ p.nombre }}<span v-if="!p.activo" class="ml-2 text-xs text-chamoy font-sans font-normal">Inactivo</span></span>
        <span class="text-sm font-sans font-normal text-tinta/60">{{ p.sucio ? 'Sin guardar' : `${p.receta.length} insumos` }}</span>
      </summary>

      <p class="text-xs text-tinta/60 mt-2">Guardada: {{ resumen(p.id) || 'sin insumos' }}</p>
      <p class="text-sm mt-2 rounded-lg px-3 py-1.5" :class="p.boing ? 'bg-uva-claro text-uva' : 'bg-chamoy-claro text-chamoy font-bold'">
        {{ p.boing ? `Boing propio: ${p.boing.nombre} · 1 pza por vaso` : 'Este producto no tiene Boing asignado. Asígnalo en «Sabores y precios».' }}
      </p>

      <div class="mt-3 space-y-2">
        <div v-for="(l, idx) in p.receta" :key="idx" class="grid grid-cols-[1fr_7rem_auto] gap-2 items-end">
          <div>
            <label class="label" :for="`r-${p.id}-${idx}`">Insumo</label>
            <select :id="`r-${p.id}-${idx}`" v-model="l.insumo_id" class="input" @change="p.sucio = true">
              <option value="" disabled>Elige</option>
              <option v-for="i in opciones(p, idx)" :key="i.id" :value="i.id">{{ i.nombre }}</option>
            </select>
          </div>
          <div>
            <label class="label" :for="`rc-${p.id}-${idx}`">Cantidad {{ unidad(l.insumo_id) }}</label>
            <input :id="`rc-${p.id}-${idx}`" v-model.number="l.cantidad" type="number" min="0.0001" step="any" class="input" @input="p.sucio = true" />
          </div>
          <button type="button" class="btn-ghost" @click="quitarLinea(p, idx)" aria-label="Quitar insumo de la receta">✕</button>
        </div>
        <div class="flex gap-2 pt-1">
          <button type="button" class="btn-ghost" @click="agregarLinea(p)">Agregar insumo</button>
          <button type="button" class="btn-primary" :disabled="!p.sucio || !p.receta.length" @click="guardar(p)">Guardar receta</button>
        </div>
      </div>
    </details>
  </section>
</template>
