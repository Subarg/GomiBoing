# Gomiboing — POS e inventario fraccional

Laravel 11/12 + Sanctum (API) · Vue 3 + Pinia + Tailwind (SPA) · PostgreSQL/MySQL · Render.

```
backend/    archivos que se superponen a un Laravel nuevo (migraciones, modelos, controladores, rutas, seeder, Dockerfile)
frontend/   SPA completa (Vite)
render.yaml Blueprint de despliegue
```

## Desarrollo local

```bash
./setup-backend.sh                         # crea api-gomiboing/ con Laravel + estos archivos
cd api-gomiboing                           # configura DB_* en .env
php artisan migrate --seed && php artisan serve

cd ../frontend
cp .env.example .env && npm install && npm run dev
```

Usuarios de prueba (seeder): `admin@gomiboing.test` / `admin1234` y `vendedor@gomiboing.test` / `vendedor1234`.
Cámbialos antes de producción (`SEED_ADMIN_PASSWORD`, `SEED_VENDEDOR_PASSWORD`).

## Flujo de uso

1. Admin → **Insumos y compras**: registra compras (presentación editable por compra, ej. chamoy de 1.5 L o 5.1 L), compras de **Boing surtido** (caja de 250 ml = 27 pzas, de 500 ml = 24 pzas, con piezas por sabor) y edita o elimina insumos.
2. Admin → **Recetas**: edita la receta de cada producto o aplica cambios en bloque (agregar gomitas tiburón/araña, quitar palitos de tamarindo, reemplazar un insumo).
3. Admin → **Asignar turno**: define el fondo de caja (cambio) y transfiere solo insumos con existencia.
4. Vendedor → **Vender**: solo aparecen los sabores que aún puede preparar con su lote. Se descuenta la receta de *su* lote.
5. Vendedor → **Mi inventario**: registra mermas (costo $0, sin comisión).
6. Vendedor → **Corte de caja**: saldo inicial, Boings de 500/250 ml vendidos, total vendido, ganancia y monto a entregar (saldo inicial + efectivo − ganancia); sobrante se conserva o se devuelve.
7. Admin → **Panel**: monitor cada 15 s, utilidad, más vendidos; campana con alertas de stock mínimo.

## Decisiones de diseño

- Todo movimiento de inventario ocurre dentro de transacciones con bloqueo de filas; si falta un insumo, la venta completa se rechaza.
- Cada insumo guarda su presentación (kilo, bote, botella) para capturar compras en presentaciones y convertir a gr/ml/pza.
- Se agregó la tabla `cortes` (no estaba en el esquema original) para cerrar turnos y no recontar ventas; `ventas.corte_id` la enlaza, y `ventas.costo_total` guarda el costo de insumos al momento de vender para calcular utilidad.
- `users` conserva `name` (estándar de Laravel) y agrega `rol` y `activo`.

## Despliegue en Render

1. Sube la carpeta `api-gomiboing/` (ya generada) y `frontend/` a un repositorio, con `render.yaml` en la raíz.
2. En Render: **New → Blueprint**. Completa `APP_KEY`, `FRONTEND_URL`, `VITE_API_URL` y las contraseñas del seeder.
3. El contenedor corre migraciones y seeder al arrancar (`docker/start.sh`).

## Actualizar una base ya desplegada

Corre `php artisan migrate`: crea `fondos_caja`, agrega columnas a `cortes` y ajusta las cajas de Boing existentes (27 y 24 pzas). El seeder ahora solo siembra una base vacía, así un reinicio no revierte recetas, contraseñas ni insumos editados.

## Si aparece "The route api/... could not be found"

El servidor está corriendo una versión anterior del backend (o con rutas en caché). Ejecuta
`./actualizar-backend.sh api-gomiboing`: copia los archivos, migra, limpia cachés y muestra las rutas nuevas
(`fondos`, `entradas/surtido`, `recetas`). Si ya estás en Render, sube también los cambios de `api-gomiboing/` y vuelve a desplegar.

## Notas de la versión 3

- **Zona horaria:** define `APP_TIMEZONE=America/Mexico_City` en el `.env` (en Render ya va en el Dockerfile) para que "hoy" y las horas del panel sean las de México.
- **Sesión:** los tokens de Sanctum no expiran. La app solo cierra la sesión si el servidor responde 401; un corte de red o un servidor dormido ya no la cierra.
- **Sabor eliminado:** al eliminar un Boing, sus productos se desactivan (un producto sin Boing no se puede preparar). La migración desactiva los que ya hayan quedado así.
- **Ventas eliminadas:** el vendedor puede eliminar ventas del turno abierto (los insumos regresan a su inventario) y el admin las ve en el panel.
- Después de actualizar: `./actualizar-backend.sh api-gomiboing`.

## Versión 4: aviso de backend desactualizado

La app consulta `/api/estado` al iniciar sesión. Si el servidor es una versión anterior o le faltan migraciones, aparece una
franja roja arriba con el motivo. Para corregirlo: copia `backend/` sobre tu proyecto Laravel, ejecuta
`./actualizar-backend.sh api-gomiboing` y reinicia el servidor.

Vendedores: el admin puede ver y editar correo y contraseña. Solo se muestran las contraseñas asignadas desde el panel
(se guardan cifradas con `APP_KEY`); las cuentas anteriores aparecen como "no disponible" hasta asignarles una nueva.
