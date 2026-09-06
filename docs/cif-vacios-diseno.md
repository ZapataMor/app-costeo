# CIF — cierre de vacíos de diseño previos a la fase 1

Estado: **decisiones cerradas, pendiente de aprobación para implementar**.
No se ha escrito código todavía.
Alcance: los 3 bloqueantes + los 3 menores del encargo.

---

## Problema 1 — Doble conteo

### 1.1 Hallazgos: por dónde entra hoy cada campo al costo directo

**(a) `salas_operatorias.costo_hora` → `costo_sala`**

| Punto | Archivo:línea | Qué hace |
|---|---|---|
| Congelado | `app/Services/Cirugias/RegistrarCirugia.php:60` | `costo_hora_sala_registrado = sala->costo_hora` |
| Recongelado | `app/Services/Cirugias/ActualizarCirugia.php:71` | solo si cambia de sala |
| Consumido | `app/Services/Costing/TdabcCostingService.php:103-121` | `costoSala = round(costoHoraSala * duracion / 60, 2)` |
| Sumado al directo | `TdabcCostingService.php:171` | |
| Espejo en UI | `resources/js/components/cirugias/estimacion-costo.tsx:90` | estimación en vivo |

**(b) `equipos_medicos.costo_hora` → `costo_equipos`**

| Punto | Archivo:línea | Qué hace |
|---|---|---|
| Congelado | `RegistrarCirugia.php:112` | pivote `costo_hora_registrado` |
| Preservado | `ActualizarCirugia.php:135` | conserva el del registro |
| Consumido | `TdabcCostingService.php:125-146` | `round(costoHora * minutosUso / 60, 2)` |
| Sumado al directo | `TdabcCostingService.php:171` | |
| Espejo en UI | `estimacion-costo.tsx:104` | |

**(c) `recursos_humanos.costos_indirectos_mensuales` → `costo_recurso_humano`**

| Punto | Archivo:línea | Qué hace |
|---|---|---|
| Origen | `app/Models/RecursoHumano.php:62-67` | `costoMensualTotal() = salario + prestaciones + indirectos` |
| Congelado | `RegistrarCirugia.php:90` | `costo_mensual_registrado` |
| Preservado | `ActualizarCirugia.php:106` | |
| Consumido | `TdabcCostingService.php:79-100` | `round(costoMensual * minutos / minutosDisponibles, 2)` |
| **Réplica en SQL** | `app/Services/Indicators/PersonalCosteoService.php:529-531` | reimplementa la fórmula en SQL crudo |
| Espejo en UI | `CirugiaController.php:527-531` → `estimacion-costo.tsx` | expone `costo_mensual` ya sumado |
| CRUD | `app/Http/Requests/StoreRecursoHumanoRequest.php:29` | campo obligatorio hoy |

> **Punto ciego encontrado:** `PersonalCosteoService` reimplementa la fórmula en SQL.
> Cualquier exclusión aplicada solo en `TdabcCostingService` dejaría los indicadores
> de personal desalineados con la ficha de costo. Va en el mismo corte.

### 1.2 Mecanismo recomendado

**Recomiendo (a) — marcar el origen del componente por hospital y excluirlo del
directo — y NO (b) forzar el campo a cero.**

Diseño concreto:

- Tres columnas en `hospitales`: `origen_infraestructura`, `origen_depreciacion_equipos`,
  `origen_personal_indirecto`, enum `digitado | derivado_de_cif`, default `digitado`.
- El componente marcado `derivado_de_cif` **se omite del costo directo** en el motor;
  el dato digitado permanece intacto en la tabla de parámetros.
- El mapa de orígenes se **congela en la cirugía** (columna nueva, ver §4), así que
  recostear una cirugía vieja nunca cambia por un switch posterior.
- Categorías CIF sin equivalente directo (administración, servicios generales, etc.)
  no participan de este mecanismo: no hay solapamiento posible.

Por qué (a) sobre (b):

1. **No destructivo.** Poner `costo_hora` en cero borra un dato que costó recolectar
   y que la tesis necesita para comparar "método actual vs. método por bolsas".
   Revertir exigiría re-digitar; con el marcador es un cambio de configuración.
2. **Auditable.** Las columnas `fuente` y `nivel_confiabilidad`
   (`2026_07_11_000002_add_trazabilidad_a_parametros_tables.php`) siguen siendo
   verdaderas sobre el valor digitado. Un cero las convierte en mentira.
3. **Un solo lugar de verdad para el estado.** El invariante es por hospital y por
   categoría, no por fila. Con ceros por fila, un hospital con 2 salas puede quedar
   con una en cero y otra no → el doble conteo vuelve por la puerta de atrás.
4. **La confiabilidad del cero es indistinguible.** Un `costo_hora = 0` legítimo
   (sala prestada) y uno forzado por CIF quedarían idénticos en base de datos.

### 1.3 Imposibilidad del estado intermedio

La activación **no** es "crear el concepto y luego cambiar el switch". Es **una sola
acción transaccional**, `ActivarCategoriaCif`:

```
DB::transaction:
  1. valida precondiciones (§1.4)
  2. activa los conceptos de la categoría
  3. cambia hospitales.origen_<componente> = 'derivado_de_cif'
```

y su inversa `DesactivarCategoriaCif`. `activo` en `conceptos_costo_indirecto` y
`origen_*` en `hospitales` solo se escriben por estos dos servicios; el CRUD del
catálogo crea conceptos siempre `activo = false`. No existe una ruta que deje ambos
prendidos.

### 1.4 Validación de activación

`ActivarCategoriaCifRequest` (o regla dedicada) bloquea si el componente equivalente
sigue digitado con valor > 0:

| Categoría | Condición que bloquea |
|---|---|
| `infraestructura` | existe sala activa del hospital con `costo_hora > 0` |
| `depreciacion_equipos` | existe equipo activo con `costo_hora > 0` |
| `personal_indirecto` | existe recurso activo con `costos_indirectos_mensuales > 0` |

Mensaje (nombra los registros, no un error genérico):

> No se puede activar la bolsa de **infraestructura**: 2 salas activas todavía tienen
> costo/hora digitado (Sala 1: $180.000/h, Sala 2: $165.000/h). Esos valores ya
> incluyen servicios públicos, mantenimiento y depreciación del inmueble; activar la
> bolsa sin resolverlos contaría esos costos dos veces.
> **Marca el componente como derivado de CIF** (conserva los valores digitados para
> comparación) o ajústalos a cero manualmente.

El botón "marcar como derivado de CIF" del mensaje ejecuta la misma acción
transaccional, ahora con la precondición satisfecha por decisión explícita del usuario.

---

## Problema 2 — Denominador del inductor por minuto

### 2.1 Hallazgos

- `salas_operatorias` es `hasMany` por hospital (`Hospital.php:118`), con
  `unique(hospital_id, nombre)` y bandera `activa`. **Nada limita a una sala.** El seeder
  ya crea un hospital con 2 salas y otro con 1
  (`CatalogoQuirurgicoSeeder.php:287,362`).
- El costeo **no** asume una sola sala: `costo_sala` sale de la sala concreta de la cirugía.
- `Hospital::minutosDisponiblesMes()` (`Hospital.php:57-60`) es capacidad **por recurso
  individual**, correcta como denominador de una persona.
- `KpiService::utilizacionSalas()` (`KpiService.php:236-247`) confirma esa lectura:
  asigna `minutosDisponiblesEntre()` **a cada sala** y suma → `totalDisponibles = N × capacidad`.
  Es decir, en el código actual 18.720 ya significa "capacidad de UNA sala".

Conclusión: usar 18.720 como denominador de una bolsa mensual del hospital infla la
tasa exactamente N veces, tal como se señala. El error no está en el modelo de salas
sino en reutilizar un denominador *por recurso* como si fuera *del hospital*.

### 2.2 Recomendación: bolsa a nivel hospital ÷ capacidad sumada de salas activas

```
capacidadQuirofanosMes = count(salas activas) × horas_dia × dias_mes × minutos_efectivos_hora
tasa_por_minuto        = monto_mensual / capacidadQuirofanosMes
```

Denominador por base de asignación (lista cerrada, decisión 2 y 3):

| `base_asignacion` | Denominador | Unidades aplicadas a la cirugía |
|---|---|---|
| `minuto_quirofano` | `count(salas activas) × horas_dia × dias_mes × minutos_efectivos_hora` | duración real de la cirugía |
| `minuto_personal` | `count(rh quirúrgico activo) × horas_dia × dias_mes × minutos_efectivos_hora` | Σ minutos de participación del equipo |
| `porcentaje_directo` | — (no hay denominador) | `costo_directo × porcentaje` |

**Descarto `sala_id` nullable en el concepto.** Razones:

1. **La fuente del dato es del hospital, no de la sala.** Una factura de energía, la
   depreciación del inmueble o la nómina administrativa llegan consolidadas. Repartirlas
   por sala exigiría un criterio de reparto que el hospital no puede sustentar — sería
   inventar un valor de negocio.
2. **Duplica el modelo sin cambiar la respuesta.** Con bolsas por sala habría dos
   fórmulas de tasa según si `sala_id` es null, y la tasa de una cirugía dependería de
   en qué sala ocurrió. Más superficie en motor, UI y snapshot para el mismo número.
3. **El caso legítimo ya está cubierto.** Si una sala sí tiene un costo propio
   verificable, ese costo pertenece a `salas_operatorias.costo_hora` y el hospital deja
   `origen_infraestructura = digitado`. Los dos mecanismos no compiten.

**Contrapartida que asumo explícitamente:** activar o desactivar una sala cambia todas
las tasas *futuras* del hospital. Es el comportamiento correcto (más capacidad ⇒ menor
tasa por minuto) y el snapshot deja la historia intacta.

Guarda: si `capacidadQuirofanosMes = 0` (hospital sin salas activas) la activación se
bloquea con mensaje propio; jamás se divide por cero.

### 2.3 Visibilidad

En el formulario del concepto, bajo `monto_mensual`, una línea viva:

> 3 salas activas × 12 h × 26 d × 60 min = **56.160 min/mes**
> $28.000.000 ÷ 56.160 = **$498,58 por minuto de quirófano**

El mismo par (denominador efectivo, tasa) se persiste en el pivote (§4).

---

## Problema 3 — Capacidad práctica 60 vs 40 min/hora

### 3.1 Campo

`hospitales.minutos_efectivos_hora`, `unsignedTinyInteger`, **default 60**, rango 1–60,
en la misma migración de la fase 1. Con el default, todo cálculo da idéntico a hoy.

### 3.2 Todos los puntos del 60

**Capacidad — deben leer la configuración:**

| Archivo:línea | Hoy | Cambio |
|---|---|---|
| `app/Models/Hospital.php:59` | `horas_dia * dias_mes * 60` | `* minutos_efectivos_hora` |
| `app/Models/Hospital.php:94` | `horas_dia * 60 * dias * (dias_mes/30.4375)` | ídem |
| `app/Services/Indicators/PersonalCosteoService.php:534` | literal `* 60` en SQL | `* hospitales.minutos_efectivos_hora` |
| `resources/js/pages/parametros/hospital.tsx:56` | texto "(horas/día × días/mes × 60)" | texto dinámico |
| `resources/js/components/cirugias/estimacion-costo.tsx:55` | usa `minutos_disponibles_mes` | ya derivado; correcto si el backend lo calcula bien |

**Conversión de unidad hora→minuto — NO se tocan** (son horas de reloj, no capacidad):
`TdabcCostingService.php:111` y `:134`; `estimacion-costo.tsx:90,104`;
`desglose-costo.tsx:166`; `procedimiento-form.tsx:63-64`.

**Congelado histórico — no se toca:**
`2026_07_13_100000_snapshots_...php:82` (backfill de una migración ya aplicada).

### 3.3 Snapshot

`cirugias.minutos_efectivos_hora_registrado` (nullable). `minutos_disponibles_mes_registrado`
ya congela el producto, así que las cirugías existentes no requieren backfill; el campo
nuevo documenta *cómo* se llegó a ese número.

### 3.4 UI de parámetros

En `parametros/hospital.tsx`, junto al campo:

> 12 h/día × 26 d/mes × **40 min efectivos/hora** = **12.480 min/mes** por recurso
> ⚠️ Cambiar este valor altera **todas las tasas por minuto futuras** (personal y CIF).
> Las cirugías ya registradas conservan su capacidad congelada.

Con 60 → 40 el denominador cae 33 % y **el costo por minuto sube 50 %** (18.720/12.480 = 1,5).
Conviene enunciar las dos cifras en la UI y en la tesis: son el mismo hecho leído al revés.

---

## Menores

### 4. Snapshot completo — `cirugia_concepto_indirecto`

Una fila por concepto aplicado, reproducible sin consultar el catálogo:

| Columna | Tipo |
|---|---|
| `cirugia_id`, `concepto_costo_indirecto_id` | FK |
| `hospital_id` | FK (multi-tenencia) |
| `categoria_registrada` | string |
| `base_asignacion_registrada` | string |
| `monto_mensual_registrado` | decimal(14,2) nullable |
| `porcentaje_registrado` | decimal(8,4) nullable |
| `denominador_registrado` | unsignedInteger nullable *(minutos de la ventana usada)* |
| `tasa_registrada` | decimal(16,6) *(sin redondear a 2: es una tasa, no un peso)* |
| `unidades_aplicadas` | decimal(12,2) *(minutos de quirófano, de personal, etc.)* |
| `monto_asignado` | decimal(14,2) *(ya distribuido, §5)* |

Más en `cirugias`: `minutos_efectivos_hora_registrado` y `parametros_cif_registrados`
(json: mapa de orígenes por componente, denominadores por base de asignación, y la vía
de indirectos aplicada —`factor` o `bolsas`— según la decisión 6).
Van en JSON porque son de longitud variable y crecen con las categorías; los escalares
siguen la convención de columnas discretas ya usada en el proyecto.

### 5. Redondeo una sola vez

Hoy `TdabcCostingService` redondea por línea (`:87, :111, :134`) y el directo cuadra
porque suma valores ya de 2 decimales. Para el indirecto:

1. Cada bolsa se calcula **a precisión completa**.
2. Se redondea el total una vez → `costo_indirecto`.
3. Los `monto_asignado` del pivote se obtienen por **mayor resto** (largest remainder)
   sobre ese total, de modo que sumen exacto.
4. El prorrateo por fase (decisión 5) usa el mismo mayor resto sobre `costo_indirecto`,
   con pesos = participación de cada fase en el costo directo.

Tests: `sum(pivote.monto_asignado) === costo_indirecto`,
`sum(detalle.indirectos) === costo_indirecto` y
`sum(por_fase.indirectos) === costo_indirecto`, con un caso construido para producir
residuo (p. ej. 3 bolsas sobre un total terminado en .01).

### 6. Validaciones del catálogo

- `porcentaje` **required_if** `base_asignacion = porcentaje_directo`, **prohibited** en el resto.
- `monto_mensual` **required** para toda base ≠ `porcentaje_directo`, **prohibited** en ella.
- Vigencias sin solape para el mismo `(hospital_id, concepto)`: regla dedicada que
  consulta `vigente_desde` / `vigente_hasta` (null = abierto) excluyendo el propio id en update.
  Índice `(hospital_id, categoria, vigente_desde)` para que la consulta no escanee.

---

## Plan de migración y archivos

### Migración única de fase 1
`database/migrations/2026_09_XX_000001_cif_capacidad_efectiva_y_conceptos.php`

1. `hospitales`: + `minutos_efectivos_hora` (default 60), + `origen_infraestructura`,
   `origen_depreciacion_equipos`, `origen_personal_indirecto` (default `digitado`).
2. `conceptos_costo_indirecto`: id, hospital_id, nombre, `categoria`, `monto_mensual`,
   `base_asignacion`, `porcentaje`, `vigente_desde`, `vigente_hasta`, `activo` (default false),
   `fuente`, `nivel_confiabilidad`, timestamps.
3. `cirugias`: + `minutos_efectivos_hora_registrado`, + `parametros_cif_registrados` (json).
4. `cirugia_concepto_indirecto` según §4.
5. Sin backfill: los defaults reproducen el comportamiento actual.

### Archivos nuevos
- `app/Enums/CategoriaCif.php`, `BaseAsignacionCif.php`, `OrigenComponente.php`
- `app/Models/ConceptoCostoIndirecto.php` (`BelongsToHospital`, `Auditable`)
- `app/Services/Costing/AsignadorCif.php` (tasas, denominadores, mayor resto)
- `app/Services/Costing/ActivarCategoriaCif.php` / `DesactivarCategoriaCif.php`
- `app/Http/Controllers/Parametros/ConceptoCostoIndirectoController.php`
- `app/Http/Requests/StoreConceptoCostoIndirectoRequest.php`, `Update...`, `ActivarCategoriaCifRequest.php`
- `app/Rules/SinSolapeDeVigencias.php`
- `resources/js/pages/parametros/costos-indirectos/*`, `resources/js/types/costos-indirectos.ts`

### Archivos a modificar
- `app/Models/Hospital.php` — capacidad efectiva + `capacidadQuirofanosMes()` + salas activas
- `app/Models/RecursoHumano.php` — `costoMensualTotal()` con exclusión opcional de indirectos
- `app/Models/Cirugia.php` — fillable/casts de los campos nuevos
- `app/Services/Costing/TdabcCostingService.php` — exclusiones por origen + motor CIF + redondeo único
- `app/Services/Cirugias/RegistrarCirugia.php` — congelar capacidad efectiva, orígenes y conceptos
- `app/Services/Cirugias/ActualizarCirugia.php` — preservar el snapshot CIF en correcciones
- `app/Services/Indicators/PersonalCosteoService.php:529-534` — SQL alineado (exclusión + minutos efectivos)
- `app/Http/Controllers/Cirugias/CirugiaController.php:540-545` — `parametrosTdabc` con capacidad efectiva y orígenes
- `app/Http/Controllers/Parametros/HospitalConfiguracionController.php` + `UpdateHospitalRequest.php`
- `resources/js/pages/parametros/hospital.tsx`, `.../index.tsx`
- `resources/js/components/cirugias/estimacion-costo.tsx`, `desglose-costo.tsx`
- `resources/js/components/parametros/forms/recurso-humano-form.tsx` — campo en solo lectura con aviso (decisión 7)
- `database/factories/HospitalFactory.php` (defaults explícitos), seeders

### Cortes de trabajo (cada uno con `npm run build && php artisan test`)
1. **Capacidad efectiva** (problema 3, aislado) — migración parcial + `Hospital` +
   `PersonalCosteoService` + snapshot + UI. Con default 60 los 248 tests deben pasar sin tocarlos.
2. **Catálogo + orígenes + validaciones** (problemas 1 y 6) — tabla, modelo, CRUD, reglas,
   servicios de activación. Conceptos siempre inactivos: el costeo no cambia.
3. **Motor + snapshot + redondeo** (problemas 2, 4, 5) — `AsignadorCif`, pivote, exclusiones
   en `TdabcCostingService`, habilitación de la activación. Test de identidad
   "sin conceptos ⇒ `costo_directo × factor_indirecto`".

---

## Decisiones tomadas (2026-09-03)

1. **Cortes.** Fase 1 crea conceptos siempre `activo = false`; la acción de activación
   se habilita junto con el motor, **dentro del mismo release**. Nunca existe un estado
   con el componente directo excluido y sin bolsa que lo sustituya.
2. **`base_asignacion` es una lista cerrada de tres:** `minuto_quirofano`,
   `minuto_personal`, `porcentaje_directo`. Nada de `por_cirugia` ni `por_m2`.
3. **Denominador de `minuto_personal`:** Σ de la capacidad de los recursos humanos
   **activos con rol quirúrgico** (cirujano, ayudante, anestesiólogo, instrumentador,
   circulante), es decir `count(rh quirúrgico activo) × horas_dia × dias_mes ×
   minutos_efectivos_hora`. No entra el personal no quirúrgico.
4. **40 min/hora = tarifa por hora de reloj.** Solo baja el denominador de capacidad;
   `costo_hora` de sala y equipos sigue siendo por hora de reloj y los `/60` de
   `TdabcCostingService.php:111,134` **no se tocan**. Efecto esperado: el costo de
   personal sube 50 %, el de sala y equipos no cambia.
5. **Fase del CIF:** prorrateo por la participación de cada fase en el costo directo
   de la cirugía. El mismo mecanismo de mayor resto de §5 cuadra el prorrateo por fase.
6. **`factor_indirecto` se ignora** cuando el hospital tiene al menos un concepto CIF
   activo, con aviso en la UI de configuración del hospital. El snapshot registra cuál
   de las dos vías se aplicó, para que una cirugía vieja siga reproduciéndose.
7. **`costos_indirectos_mensuales`** queda **visible en solo lectura** en el CRUD de
   recursos humanos mientras `origen_personal_indirecto = derivado_de_cif`, con la nota
   "cubierto por la bolsa CIF de personal indirecto". El valor digitado se conserva
   para la comparación de la tesis.
