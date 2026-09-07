# CIF — implementación del módulo de costos indirectos

Estado: **completo y en funcionamiento**. Auditado el 2026-09-06, motor de
asignación implementado el 2026-09-07.

Documento hermano: [`cif-vacios-diseno.md`](cif-vacios-diseno.md) (las decisiones de
diseño previas). Este documenta **cómo funciona lo construido y por qué está así**.

---

## 1. Las dos vías del costo indirecto

Una cirugía recibe su costo indirecto por **una** de dos vías, nunca por las dos:

| Vía | Cuándo | Fórmula |
|---|---|---|
| `factor` | el hospital no tiene bolsas activas y vigentes | `costo_directo × factor_indirecto` |
| `bolsas` | hay al menos una bolsa CIF activa y vigente a la fecha | Σ de cada bolsa repartida por su propio inductor |

La primera es el costeo tradicional: un porcentaje único sobre el directo. La
segunda es ABC: cada bolsa se reparte por el inductor que refleja su consumo real.
**El `factor_indirecto` se ignora cuando la vía es `bolsas`** (decisión 6 del
diseño); son métodos alternativos, no acumulativos, y la pantalla de configuración
del hospital lo advierte cuando hay bolsas activas.

Por qué importa: sobre las 3.100 cirugías del pipeline doctoral, la razón
CIF/directo va del **2,92 % al 4,17 %** según el tipo de cirugía. Un factor único
del 3,69 % desvía el CIF asignado **6,8 % en promedio y hasta 26,3 %** en un
procedimiento concreto. Ese error es exactamente lo que el ABC existe para
eliminar.

### Bases de asignación (lista cerrada)

| Base | Denominador | Unidades de la cirugía |
|---|---|---|
| `minuto_quirofano` | salas activas × horas_dia × dias_mes × minutos_efectivos_hora | duración real |
| `minuto_personal` | personal quirúrgico activo × (misma capacidad) | Σ minutos de participación del equipo |
| `porcentaje_directo` | — | costo directo × porcentaje |

El denominador de las bases por minuto es la capacidad **sumada** del hospital
(`Hospital::capacidadQuirofanosMes()`, `capacidadPersonalQuirurgicoMes()`), no la de
un recurso individual. La bolsa mensual de energía cubre los tres quirófanos a la
vez; dividirla entre la capacidad de uno solo triplicaría la tasa. `MotorCifTest::
test_la_tasa_baja_cuando_el_hospital_tiene_mas_salas` fija esa diferencia.

Coincide con la metodología de la tesis, que asigna el CIF a **420 COP por minuto de
quirófano** (`Documentos contexto SICOPH/Guia_Campos_Base_Datos_TDABC_MILP.docx`), y
con el Excel de recolección del profesor, cuyas hojas 6 a 9 son cuatro bolsas con
inductor propio.

---

## 2. El invariante anti-doble-conteo

> Un componente de costo nunca puede estar contado por el catálogo directo y por
> una bolsa CIF a la vez, ni desaparecer de ambos.

Tres campos del catálogo ya contienen costos indirectos por dentro:

| Categoría CIF | Componente directo que duplicaría |
|---|---|
| `infraestructura` | `salas_operatorias.costo_hora` |
| `depreciacion_equipos` | `equipos_medicos.costo_hora` |
| `personal_indirecto` | `recursos_humanos.costos_indirectos_mensuales` |
| `administracion`, `servicios_generales` | ninguno — no hay solape posible |

El invariante se sostiene por construcción, no por disciplina:

1. `activo` está fuera del `$fillable` de `ConceptoCostoIndirecto`; las columnas
   `origen_*` están fuera del `$fillable` de `Hospital`. Ni el CRUD del catálogo ni
   la configuración del hospital pueden moverlos.
2. `ActivarCategoriaCif` es el **único** escritor de ambos, y los escribe en una
   sola transacción. No existe un estado intermedio observable.
3. Activar exige confirmación explícita si el componente equivalente sigue digitado
   con valor > 0, y el diálogo **nombra los registros con su valor** antes de pedirla.
4. Un valor en cero (sala prestada, equipo donado) no bloquea: cero no duplica nada.
5. Un concepto activo no se puede borrar, ni cambiarle monto, porcentaje, base,
   categoría o inicio de vigencia (§5).
6. En el motor, un componente solo se marca `derivado_de_cif` si además **existe una
   bolsa vigente de esa categoría**. Una bolsa que vence y no se renueva devuelve el
   componente al costo directo en lugar de dejarlo fuera de las dos vías.

El componente duplicado se **marca**, no se pone a cero: el dato digitado sobrevive
para comparar «método actual vs. método por bolsas» en la tesis, y su `fuente` /
`nivel_confiabilidad` siguen siendo verdaderas sobre un valor real.

---

## 3. El snapshot: por qué una cirugía vieja no cambia

`cirugias.parametros_cif_registrados` (json) congela, al registrar la cirugía:

```json
{
  "via": "bolsas",
  "origenes": {"infraestructura": "derivado_de_cif", ...},
  "denominadores": {"minuto_quirofano": 56160, "minuto_personal": 18720},
  "conceptos": [
    {"id": 1, "nombre": "Energía eléctrica", "categoria": "infraestructura",
     "base_asignacion": "minuto_quirofano", "monto_mensual": 28080000.0,
     "porcentaje": null, "denominador": 56160, "tasa": 500.0}
  ]
}
```

Lo escribe `AsignadorCif::congelar()` desde `RegistrarCirugia`. El motor de costeo
lee esa foto, no el catálogo de hoy: recostear una cirugía del año pasado da el
mismo número aunque entretanto se hayan activado bolsas, cambiado montos o abierto
salas. Las cirugías anteriores a este módulo tienen la foto en `null` y caen en la
vía `factor`, que es como se costearon en su día.

La exclusión de los indirectos del personal viaja por otro camino: cuando la bolsa
de `personal_indirecto` está activa, `RegistrarCirugia` congela
`costo_mensual_registrado` **sin** esos indirectos
(`RecursoHumano::costoMensualTotal(false)`). Así el SQL agregado de
`PersonalCosteoService`, que consume esa misma columna, queda alineado con la ficha
de costo sin duplicar la regla.

### La tabla `cirugia_concepto_indirecto`

Una fila por bolsa aplicada, con monto mensual, denominador, tasa, unidades y monto
asignado. Permite rehacer la cuenta sin consultar el catálogo:
«$28.080.000 ÷ 56.160 min = $500/min × 120 min = $60.000». La UI la muestra en el
desglose del costo, y recostear la reescribe en vez de acumular filas.

---

## 4. Redondeo una sola vez

Cada bolsa se calcula a precisión completa; el total se redondea a centavos **una
vez**; las líneas se cuadran contra ese total por **mayor resto**
(`AsignadorCif::repartirPorMayorResto()`). El mismo mecanismo prorratea el indirecto
entre las fases del ciclo, con pesos = participación de cada fase en el costo
directo.

Redondear línea a línea puede sobrar o faltar tanto como líneas haya. Las tres sumas
cuadran al centavo y hay pruebas que lo fijan:

- `Σ cirugia_concepto_indirecto.monto_asignado == costos_cirugia.costo_indirecto`
- `Σ detalle.indirecto_por_fase == costo_indirecto`

---

## 5. Guardas

| Situación | Qué pasa |
|---|---|
| Activar una bolsa por minuto sin capacidad activa (sin salas / sin personal) | `CapacidadCifNoDisponibleException` con el arreglo nombrado. Jamás se divide por cero. |
| Editar monto, porcentaje, base, categoría o `vigente_desde` de una bolsa **activa** | Bloqueado: la forma de cambiar el precio de una bolsa es cerrar su vigencia y abrir otra. Nombre, fuente, confiabilidad y `vigente_hasta` sí se corrigen. |
| Borrar un concepto activo | Bloqueado. |
| Dos vigencias del mismo concepto que se pisan | Bloqueado. La identidad del concepto es **nombre + categoría**: «Mantenimiento» de infraestructura y «Mantenimiento» de servicios generales son dos bolsas distintas. |
| `factor_indirecto = 0` y sin bolsas activas | La pantalla del hospital avisa que las cirugías se costean **sin ningún costo indirecto**. |

---

## 6. Mapa de archivos

**Motor y dominio**

| Archivo | Papel |
|---|---|
| `app/Services/Costing/AsignadorCif.php` | congela parámetros, calcula tasas, reparte por mayor resto |
| `app/Services/Costing/TdabcCostingService.php` | elige la vía, aplica exclusiones, escribe el pivote |
| `app/Services/Costing/ActivarCategoriaCif.php` / `DesactivarCategoriaCif.php` | único escritor de `activo` + `origen_*` |
| `app/Models/CirugiaConceptoIndirecto.php` | la línea auditable del pivote |
| `app/Models/Hospital.php` | capacidades y denominadores |
| `app/Enums/CategoriaCif.php`, `BaseAsignacionCif.php`, `OrigenComponente.php` | vocabulario del módulo |
| `app/Exceptions/CapacidadCifNoDisponibleException.php`, `SolapeDeCostoIndirectoException.php` | las dos guardas duras |

**Migraciones**

- `2026_09_03_000001` — capacidad práctica efectiva (`minutos_efectivos_hora`)
- `2026_09_04_000001` — catálogo de bolsas y columnas `origen_*`
- `2026_09_06_000001` — snapshot CIF en `cirugias` y pivote `cirugia_concepto_indirecto`

**Interfaz**

- `resources/js/components/parametros/activacion-categorias-cif.tsx` — encender y
  apagar categorías, con el diálogo que nombra los conflictos
- `resources/js/components/parametros/forms/concepto-costo-indirecto-form.tsx` — tasa
  por minuto en vivo mientras se escribe el monto
- `resources/js/components/cirugias/desglose-costo.tsx` — el desglose por bolsa
- `resources/js/pages/parametros/hospital.tsx` — avisos sobre el factor indirecto

**Rutas**

```
POST parametros/costos-indirectos/activar      { categoria, confirmado }
POST parametros/costos-indirectos/desactivar   { categoria }
```

---

## 7. Pruebas

```
php artisan test   →  299 tests, 299 passed, 1.521 assertions
```

(Requiere `npm run build` antes: las páginas Inertia se resuelven por el manifiesto
de Vite y sin compilar el listado responde 500.)

`tests/Feature/Costeo/MotorCifTest.php` (14 casos) cubre el motor:

| Caso | Qué fija |
|---|---|
| sin bolsas el indirecto es el factor plano | la vía tradicional sigue intacta |
| bolsa por minuto de quirófano | $28.080.000 ÷ 56.160 min × 120 min = $60.000 |
| la tasa baja con más salas | denominador = capacidad sumada, no de una sala |
| bolsa por minuto de personal | denominador = capacidad del equipo quirúrgico |
| bolsa por porcentaje | se calcula sobre el directo ya excluido |
| activar infraestructura saca la sala del directo | `costo_sala = 0`, tarifa aún visible, costo entra por la bolsa |
| bolsa de personal indirecto | $150/min digitado → $100/min derivado |
| el factor se ignora con bolsas | no se suman las dos vías |
| las líneas del pivote suman el indirecto | mayor resto, caso con residuo |
| el indirecto por fase suma el indirecto | mismo mecanismo |
| recostear reemplaza las líneas | no duplica el pivote |
| cirugía registrada antes de activar | conserva su vía y su costo de sala |
| bolsa fuera de vigencia | no entra, y la sala vuelve al directo |
| activar sin capacidad | excepción con el arreglo nombrado |

`ActivarCategoriaCifTest` (12) cubre el invariante y las rutas de activación;
`ConceptoCostoIndirectoTest` (16) el catálogo, las validaciones excluyentes, las
vigencias y las guardas de edición.

Verificación estática: `phpstan --level=7` no reporta ningún error en los archivos
del módulo; `tsc --noEmit`, `pint` y `prettier` limpios.

---

## 8. Cómo se pone en marcha en un hospital

1. **Capacidad práctica** — en `Parámetros → Hospital`, fijar
   `minutos_efectivos_hora`. Kaplan & Anderson recomiendan ~80 % (48 min); el MILP de
   la tesis usa ese mismo 80 %. El default 60 reproduce el comportamiento anterior.
2. **Registrar las bolsas** — en `Parámetros → Costos indirectos`, una por concepto
   con su monto mensual, su inductor y su vigencia. El formulario muestra la tasa por
   minuto que resultará. Nacen inactivas.
3. **Activar la categoría** — el panel superior enciende cada categoría. Si el
   componente equivalente sigue digitado, el diálogo lo nombra y pide confirmación
   explícita antes de marcarlo como derivado de CIF.
4. **Registrar cirugías** — desde ese momento cada cirugía nueva congela los
   parámetros CIF vigentes y su indirecto se reparte por inductor. Lo ya registrado
   no se toca.

> El orden importa: las bolsas solo aplican a cirugías registradas **después** de
> activarlas. Es deliberado —el snapshot protege la historia— y significa que la
> comparación «método actual vs. método por bolsas» de la tesis se hace entre
> periodos, no reprocesando el pasado.
