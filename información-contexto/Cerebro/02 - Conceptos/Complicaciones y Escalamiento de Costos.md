---
tags: [concepto, nucleo-del-proyecto]
---

# Complicaciones y Escalamiento de Costos

**El corazón del proyecto.** Una complicación es un evento clínico no planeado que desvía la atención del curso esperado y **dispara la [[Cuenta del Paciente]] por encima de lo cotizado**.

## Ejemplo guía (apendicitis)
| Escenario | Eventos | Costo relativo |
|---|---|---|
| Esperado | Apendicectomía + 2 días de hospitalización | 1x (lo cotizado) |
| Complicación leve | Infección del sitio quirúrgico → antibióticos + días extra | ~2x |
| Complicación grave | Peritonitis → reintervención + UCI | ~5–10x |
| Escalamiento máximo | Sepsis → UCI prolongada + traslado/remisión a mayor complejidad | >10x |

## Tipos de eventos que escalan costos
- **Reintervención**: una segunda (o tercera) cirugía.
- **Remisión a UCI**: la estancia en UCI cuesta varias veces la de habitación general, por día.
- **Traslado / remisión**: mover al paciente a una IPS de mayor complejidad (ambulancia, avión ambulancia).
- **Estancia prolongada**: cada día extra suma habitación, medicamentos, enfermería.
- **Infecciones asociadas a la atención (IAAS)**: alargan todo lo anterior.

## La idea del profesor (según lo entendido)
El aplicativo debe permitir a los centros de salud **prevenir/prepararse financieramente**:
1. Saber el costo casi exacto de cada procedimiento ([[Cotizacion de Procedimientos]]).
2. Conocer **a cuánto puede escalar** según los sucesos posibles (escenarios de complicación).
3. Con eso, la [[Hospital o Clinica (IPS)]] puede provisionar recursos y negociar mejor con la [[Asegurador (EPS)]].

## ✅ Cómo lo trata realmente la tesis (actualizado 2026-07-05)
Los documentos del profesor ([[Fuente - Capitulo 5 Tesis Doctoral SGC]]) reubican esta idea:
- Las complicaciones se miden como **KPIs de calidad** (tasa de complicaciones intra/postoperatorias, infección del sitio quirúrgico, reingresos a 30 días) y explican la **variabilidad de costos** entre cirugías del mismo tipo.
- El conocimiento sobre complicaciones se captura vía **lecciones aprendidas** y comunidades de práctica (capa 4 del [[Sistema de Gestion del Conocimiento (SGC)]]).
- Los **modelos predictivos de costos** son una meta de madurez avanzada (Nivel 5 CMM), no el punto de partida del aplicativo.

## Fuentes
[[Fuente - Capitulo 5 Tesis Doctoral SGC]] · [[Fuente - Libro Costeo ABC Hospital San Jose de Maicao]]
