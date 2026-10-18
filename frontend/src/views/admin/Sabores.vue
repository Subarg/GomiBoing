<script setup>
import { computed, onMounted, ref } from 'vue'
import api, { mensajeError } from '../../api'

const productos = ref([])
const error = ref('')
const aviso = ref('')
const nuevo = ref({ sabor: '', base_sabor: '', tamanos: [250, 500], precio_250: null, comision_250: null, precio_500: null, comision_500: null })

const saboresBase = computed(() => [...new Set([...productos.value].sort((a, b) => Number(b.activo) - Number(a.activo)).map((p) => p.sabor))])
const ok = (m) => { error.value = ''; aviso.value = m }
const fallo = (e) => { aviso.value = ''; error.value = mensajeError(e) }

async function cargar() {
  productos.value = (await api.get('/admin/recetas')).data.map((p) => ({ ...p, activo: p.activo ?? true }))
  if (!nuevo.value.base_sabor && saboresBase.value.length) nuevo.value.base_sabor = saboresBase.value[0]
}

async function crear() {
  try {
    await api.post('/admin/sabores', nuevo.value)
    ok(`Sabor ${nuevo.value.sabor} creado. Revisa su receta en Recetas y compra su Boing.`)
    nuevo.value = { ...nuevo.value, sabor: '', precio_250: null, comision_250: null, precio_500: null, comision_500: null }
    await cargar()
  } catch (e) { fallo(e) }
}

async function reparar() {
  if (!confirm('Esto quita de cada receta los Boing que no sean de su sabor y tamaño, y agrega el propio si falta. ¿Continuar?')) return
  try {
    const { data } = await api.post('/admin/recetas/reparar')
    ok(`Recetas revisadas: ${data.quitados} Boing ajenos quitados, ${data.agregados} propios agregados.`)
    await cargar()
  } catch (e) { fallo(e) }
}

async function guardar(p) {
  try {
    await api.patch(`/admin/productos/${p.id}`, { precio_venta: p.precio_venta, comision: p.comision })
    ok(`${p.nombre} actualizado.`)
  } catch (e) { fallo(e) }
}

async function alternar(p) {
  try { await api.patch(`/admin/productos/${p.id}`, { activo: !p.activo }); await cargar() } catch (e) { fallo(e) }
}

async function eliminar(p) {
  if (!confirm(`¿Eliminar "${p.nombre}"? Si ya tiene ventas se bloqueará y deberás desactivarlo.`)) return
  try { await api.delete(`/admin/productos/${p.id}`); ok(`${p.nombre} eliminado.`); await cargar() } catch (e) { fallo(e) }
}

onMounted(cargar)
</script>

<template>
  <h1 class="text-3xl font-bold mb-4">Sabores y precios</h1>
  <p v-if="error" class="text-chamoy font-bold mb-3" role="alert">{{ error }}</p>
  <p v-if="aviso" class="text-green-700 font-bold mb-3" role="status">{{ aviso }}</p>

  <form class="card space-y-3 mb-5" @submit.prevent="crear">
    <h2 class="text-xl font-bold">Agregar sabor de Boing</h2>
    <div class="grid sm:grid-cols-2 gap-3">
      <div><label class="label" for="s-nombre">Nombre del sabor</label><input id="s-nombre" v-model="nuevo.sabor" class="input" placeholder="Fresa" required /></div>
      <div>
        <label class="label" for="s-base">Copiar la receta de</label>
        <select id="s-base" v-model="nuevo.base_sabor" class="input" required>
          <option v-for="s in saboresBase" :key="s" :value="s">{{ s }}</option>
        </select>
      </div>
    </div>
    <fieldset>
      <legend class="label">Tamaños</legend>
      <div class="flex gap-4">
        <label v-for="t in [250, 500]" :key="t" class="inline-flex items-center gap-2"><input type="checkbox" v-model="nuevo.tamanos" :value="t" class="size-4 accent-uva" />{{ t }} ml</label>
      </div>
    </fieldset>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3" v-if="nuevo.tamanos.length">
      <template v-for="t in [250, 500]" :key="t">
        <div v-if="nuevo.tamanos.includes(t)"><label class="label" :for="`np-${t}`">Precio {{ t }} ml</label><input :id="`np-${t}`" v-model.number="nuevo[`precio_${t}`]" type="number" min="0" step="0.01" class="input" placeholder="Igual al base" /></div>
        <div v-if="nuevo.tamanos.includes(t)"><label class="label" :for="`nc-${t}`">Ganancia {{ t }} ml</label><input :id="`nc-${t}`" v-model.number="nuevo[`comision_${t}`]" type="number" min="0" step="0.01" class="input" placeholder="Igual al base" /></div>
      </template>
    </div>
    <p class="text-xs text-tinta/60">Se crea el Boing de ese sabor y tamaño en el almacén (caja de 27 pzas para 250 ml y de 24 para 500 ml). Después puedes ajustar su receta en Recetas.</p>
    <button class="btn-primary" :disabled="!nuevo.tamanos.length">Crear sabor</button>
  </form>

  <section class="card overflow-x-auto">
    <div class="flex items-center justify-between gap-2 mb-2 flex-wrap">
      <h2 class="text-xl font-bold">Productos</h2>
      <button class="btn-ghost text-sm" @click="reparar">Reparar Boing de las recetas</button>
    </div>
    <table class="w-full min-w-[40rem]">
      <thead><tr><th class="th">Producto</th><th class="th">Precio</th><th class="th">Ganancia vendedor</th><th class="th"></th></tr></thead>
      <tbody>
        <tr v-for="p in productos" :key="p.id" :class="p.activo ? '' : 'opacity-60'">
          <td class="td font-bold">{{ p.nombre }}<span v-if="!p.activo" class="ml-2 text-xs text-chamoy">Inactivo</span></td>
          <td class="td"><input v-model.number="p.precio_venta" type="number" min="0" step="0.01" class="input !w-24 !py-1" :aria-label="`Precio de ${p.nombre}`" @change="guardar(p)" /></td>
          <td class="td"><input v-model.number="p.comision" type="number" min="0" step="0.01" class="input !w-24 !py-1" :aria-label="`Ganancia de ${p.nombre}`" @change="guardar(p)" /></td>
          <td class="td text-right whitespace-nowrap">
            <button class="text-uva font-bold text-sm mr-3" @click="alternar(p)">{{ p.activo ? 'Desactivar' : 'Activar' }}</button>
            <button class="text-chamoy font-bold text-sm" @click="eliminar(p)">Eliminar</button>
          </td>
        </tr>
      </tbody>
    </table>
    <p class="text-xs text-tinta/60 mt-2">Desactivar oculta el producto en el POS sin perder su historial.</p>
  </section>
</template>
