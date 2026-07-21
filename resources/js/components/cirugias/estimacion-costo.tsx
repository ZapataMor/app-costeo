import { Calculator, TriangleAlert } from 'lucide-react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cop } from '@/lib/formato';
import type {
    CatalogoEquipoMedico,
    CatalogoInsumo,
    CatalogoRecurso,
    CatalogoSala,
    DatosCirugia,
    ParametrosTdabc,
} from '@/types/cirugias';

export type Estimacion = {
    recursoHumano: number;
    sala: number;
    equipos: number;
    insumos: number;
    directo: number;
    indirecto: number;
    total: number;
};

/**
 * Réplica en el navegador de `TdabcCostingService`: costo mensual congelado ×
 * minutos ÷ minutos disponibles para el personal, tarifa/hora prorrateada
 * para sala y equipos, precio × cantidad para insumos, y el factor indirecto
 * del hospital sobre el costo directo.
 *
 * Es una estimación, no la fuente de verdad: el costo que se guarda lo
 * calcula siempre el backend al registrar.
 */
export function calcularEstimacion({
    datos,
    salas,
    recursos,
    insumos,
    equiposMedicos,
    parametros,
    duracionMinutos,
}: {
    datos: DatosCirugia;
    salas: CatalogoSala[];
    recursos: CatalogoRecurso[];
    insumos: CatalogoInsumo[];
    equiposMedicos: CatalogoEquipoMedico[];
    parametros: ParametrosTdabc;
    duracionMinutos: number | null;
}): Estimacion | null {
    const minutosDisponibles = parametros.minutos_disponibles_mes;

    if (minutosDisponibles === null || minutosDisponibles <= 0) {
        return null;
    }

    const recursoHumano = datos.equipo.reduce((suma, fila) => {
        const recurso = recursos.find(
            (r) => String(r.id) === fila.recurso_humano_id,
        );
        const minutos = Number(fila.minutos_participacion);

        if (recurso === undefined || !Number.isFinite(minutos) || minutos <= 0) {
            return suma;
        }

        return (
            suma +
            Math.round(
                ((Number(recurso.costo_mensual) * minutos) /
                    minutosDisponibles) *
                    100,
            ) /
                100
        );
    }, 0);

    const sala = salas.find((s) => String(s.id) === datos.sala_operatoria_id);
    const costoSala =
        sala !== undefined && duracionMinutos !== null && duracionMinutos > 0
            ? Math.round(
                  ((Number(sala.costo_hora) * duracionMinutos) / 60) * 100,
              ) / 100
            : 0;

    const costoEquipos = datos.equipos_medicos.reduce((suma, fila) => {
        const equipo = equiposMedicos.find((e) => String(e.id) === fila.id);
        const minutos = Number(fila.minutos_uso);

        if (equipo === undefined || !Number.isFinite(minutos) || minutos <= 0) {
            return suma;
        }

        return (
            suma +
            Math.round(((Number(equipo.costo_hora) * minutos) / 60) * 100) / 100
        );
    }, 0);

    const costoInsumos = datos.consumos.reduce((suma, fila) => {
        const insumo = insumos.find((i) => String(i.id) === fila.insumo_id);
        const cantidad = Number(fila.cantidad);

        if (insumo === undefined || !Number.isFinite(cantidad) || cantidad <= 0) {
            return suma;
        }

        return (
            suma +
            Math.round(Number(insumo.costo_unitario) * cantidad * 100) / 100
        );
    }, 0);

    const directo =
        Math.round((recursoHumano + costoSala + costoEquipos + costoInsumos) * 100) / 100;
    const indirecto =
        Math.round(directo * (parametros.factor_indirecto ?? 0) * 100) / 100;

    return {
        recursoHumano: Math.round(recursoHumano * 100) / 100,
        sala: costoSala,
        equipos: costoEquipos,
        insumos: costoInsumos,
        directo,
        indirecto,
        total: Math.round((directo + indirecto) * 100) / 100,
    };
}

/**
 * Panel de costo estimado en vivo. Antes el usuario digitaba a ciegas y solo
 * veía el resultado después de guardar; ahora un consumo mal tecleado se nota
 * en el momento.
 */
export function EstimacionCosto({
    estimacion,
    duracionMinutos,
}: {
    estimacion: Estimacion | null;
    duracionMinutos: number | null;
}) {
    if (estimacion === null) {
        return null;
    }

    const componentes = [
        { etiqueta: 'Talento humano', valor: estimacion.recursoHumano },
        { etiqueta: 'Sala operatoria', valor: estimacion.sala },
        { etiqueta: 'Equipos médicos', valor: estimacion.equipos },
        { etiqueta: 'Insumos', valor: estimacion.insumos },
        { etiqueta: 'Indirectos', valor: estimacion.indirecto },
    ];

    return (
        <Card className="border-primary/30 bg-primary/[0.03]">
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                    <Calculator className="size-4 text-muted-foreground" />
                    Costo estimado
                    <span className="ml-auto text-xl font-semibold tabular-nums">
                        {cop(estimacion.total)}
                    </span>
                </CardTitle>
                <CardDescription>
                    Cálculo TDABC con lo capturado hasta ahora
                    {duracionMinutos !== null
                        ? ` (${duracionMinutos} min de duración)`
                        : ''}
                    . El costo definitivo lo calcula el sistema al guardar.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <ul className="space-y-1.5 text-sm">
                    {componentes.map((componente) => (
                        <li
                            key={componente.etiqueta}
                            className="flex items-center gap-3"
                        >
                            <span className="w-36 shrink-0 text-muted-foreground">
                                {componente.etiqueta}
                            </span>
                            <div className="h-1.5 flex-1 overflow-hidden rounded bg-muted">
                                <div
                                    className="h-full rounded bg-primary"
                                    style={{
                                        width:
                                            estimacion.total > 0
                                                ? `${(componente.valor / estimacion.total) * 100}%`
                                                : '0%',
                                    }}
                                />
                            </div>
                            <span className="w-28 text-right tabular-nums">
                                {cop(componente.valor)}
                            </span>
                        </li>
                    ))}
                </ul>

                {duracionMinutos === null && (
                    <p className="mt-3 flex items-start gap-2 text-xs text-muted-foreground">
                        <TriangleAlert className="mt-0.5 size-3.5 shrink-0" />
                        Sin hora de finalización no hay duración real: la sala
                        todavía no suma al costo.
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
