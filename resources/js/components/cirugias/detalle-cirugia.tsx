import { Link, router } from '@inertiajs/react';
import { Calculator, Pencil, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import CirugiaController from '@/actions/App/Http/Controllers/Cirugias/CirugiaController';
import { CalculoDetallado } from '@/components/cirugias/desglose-costo';
import { FacturacionCard } from '@/components/cirugias/facturacion-card';
import { ResultadoClinicoCard } from '@/components/cirugias/resultado-clinico-card';
import { ResumenCosto } from '@/components/cirugias/resumen-costo';
import { NombreDato } from '@/components/nombre-dato';
import { ConfirmarEliminacion } from '@/components/parametros/confirmar-eliminacion';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    cop,
    etiqueta,
    fechaHora,
    fecha as formatearFecha,
} from '@/lib/formato';
import { separarMarcaSemilla } from '@/lib/semilla';
import type {
    CirugiaDetalle,
    CostoCirugia,
    FaseCiclo,
    Facturacion,
    ResultadoClinico,
} from '@/types/cirugias';

/**
 * Lo que el servidor manda para ver una cirugía: lo usan la página de
 * detalle y el modal que se abre desde el listado.
 */
export type DatosDetalleCirugia = {
    cirugia: CirugiaDetalle;
    costo: CostoCirugia | null;
    facturacion: Facturacion | null;
    resultadoClinico: ResultadoClinico | null;
};

/** «Cirugía #12 · 17 oct 2026». */
export const tituloCirugia = (cirugia: CirugiaDetalle) =>
    `Cirugía #${cirugia.id} · ${cirugia.fecha ? formatearFecha(cirugia.fecha) : 'sin fecha'}`;

export const descripcionCirugia = (cirugia: CirugiaDetalle) =>
    cirugia.paciente
        ? `Paciente: ${cirugia.paciente.nombres} ${cirugia.paciente.apellidos}`
        : undefined;

/** Etiquetas de fase para el detalle; el valor guardado es abreviado. */
const ETIQUETA_FASE: Record<FaseCiclo, string> = {
    pre: 'pre-quirúrgica',
    quirurgica: 'quirúrgica',
    post: 'post-quirúrgica',
};

/** Una cesárea registra más de cuarenta insumos: arriba, los que pesan. */
const INSUMOS_VISIBLES = 8;

/**
 * «17 oct 2026, 09:44 p. m.». El servidor manda «2026-10-17 21:44»: el
 * espacio se cambia por la «T» para que todos los navegadores lo lean.
 */
const momento = (valor: string | null) =>
    valor ? fechaHora(valor.replace(' ', 'T')) : '—';

/** Corregir, (re)calcular el costo y eliminar. */
export function AccionesCirugia({
    cirugia,
    costo,
}: Pick<DatosDetalleCirugia, 'cirugia' | 'costo'>) {
    const [calculando, setCalculando] = useState(false);

    const calcular = () => {
        router.post(
            CirugiaController.calcular.url(cirugia.id),
            {},
            {
                preserveScroll: true,
                onStart: () => setCalculando(true),
                onFinish: () => setCalculando(false),
            },
        );
    };

    return (
        <>
            <Button asChild variant="outline">
                <Link href={CirugiaController.edit.url(cirugia.id)}>
                    <Pencil className="size-4" />
                    Corregir
                </Link>
            </Button>
            <Button
                onClick={calcular}
                disabled={calculando || cirugia.estado !== 'realizada'}
            >
                <Calculator className="size-4" />
                {costo ? 'Recalcular costo' : 'Calcular costo TDABC'}
            </Button>
            <ConfirmarEliminacion
                url={CirugiaController.destroy.url(cirugia.id)}
                descripcion="Se eliminará la cirugía con su equipo quirúrgico, consumos, equipos, costo calculado, facturación y resultado clínico. Esta acción no se puede deshacer."
            />
        </>
    );
}

/** Alertas, resumen de costo, datos de captura, facturación y cálculo. */
export function DetalleCirugia({
    cirugia,
    costo,
    facturacion,
    resultadoClinico,
}: DatosDetalleCirugia) {
    const sinTerminar = cirugia.hora_fin === null;
    const sinEstadoRealizada = cirugia.estado !== 'realizada';
    const noContabilizada = sinTerminar || sinEstadoRealizada;
    // «quirúrgica» al lado de cada persona era ruido cuando todo el equipo
    // estuvo en la misma fase: la fase solo se nombra si hay más de una.
    const variasFases = new Set(cirugia.equipo.map((m) => m.fase)).size > 1;
    const totalInsumos = cirugia.consumos.reduce(
        (suma, consumo) => suma + Number(consumo.costo_total),
        0,
    );
    const [todosLosInsumos, setTodosLosInsumos] = useState(false);
    const consumosOrdenados = [...cirugia.consumos].sort(
        (a, b) => Number(b.costo_total) - Number(a.costo_total),
    );
    const consumosVisibles = todosLosInsumos
        ? consumosOrdenados
        : consumosOrdenados.slice(0, INSUMOS_VISIBLES);

    return (
        <>
            {noContabilizada && (
                <Alert className="border-amber-300/70 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                    <TriangleAlert className="size-4" />
                    <AlertTitle>
                        Esta cirugía no se está contando en los indicadores
                    </AlertTitle>
                    <AlertDescription className="text-amber-800 dark:text-amber-300/90">
                        {sinTerminar && (
                            <p>
                                No se ha marcado como terminada: falta la hora
                                de finalización.
                            </p>
                        )}
                        {sinEstadoRealizada && (
                            <p>
                                Su estado es «
                                {etiqueta(cirugia.estado).toLowerCase()}»: solo
                                las cirugías realizadas se costean y entran a
                                los indicadores.
                            </p>
                        )}
                    </AlertDescription>
                </Alert>
            )}

            {/* El veredicto va primero; los datos de captura y el
            cálculo línea a línea, después. */}
            {costo ? (
                <ResumenCosto
                    costo={costo}
                    facturacion={facturacion}
                    estado={cirugia.estado}
                    contabilizada={!noContabilizada}
                    duracionMinutos={cirugia.duracion_minutos}
                />
            ) : (
                <Card>
                    <CardContent className="py-8 text-center text-muted-foreground">
                        Esta cirugía aún no tiene costo calculado. Usa «Calcular
                        costo TDABC» para generar el desglose.
                    </CardContent>
                </Card>
            )}

            <div className="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Datos de la cirugía
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm">
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">
                                Entrada a sala
                            </span>
                            <span className="tabular-nums">
                                {momento(cirugia.hora_inicio)}
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">
                                Salida de sala
                            </span>
                            <span className="tabular-nums">
                                {momento(cirugia.hora_fin)}
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">
                                Duración
                            </span>
                            <span className="tabular-nums">
                                {cirugia.duracion_minutos !== null
                                    ? `${cirugia.duracion_minutos} min`
                                    : '—'}
                            </span>
                        </div>
                        <div className="flex justify-between">
                            {/* El estado ya está en el resumen de arriba. */}
                            <span className="text-muted-foreground">Tipo</span>
                            <span>{etiqueta(cirugia.tipo)}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">Sala</span>
                            <span>
                                {cirugia.sala ? (
                                    <NombreDato texto={cirugia.sala.nombre} />
                                ) : (
                                    'Sin sala'
                                )}
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">
                                Diagnóstico CIE-10
                            </span>
                            <span className="font-mono">
                                {cirugia.diagnostico_cie10 ?? '—'}
                            </span>
                        </div>
                        {cirugia.observaciones && (
                            <p className="pt-2 text-muted-foreground">
                                <NombreDato texto={cirugia.observaciones} />
                            </p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Procedimientos y equipo quirúrgico
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm">
                        <div className="space-y-1">
                            {cirugia.procedimientos.map((proc) => (
                                <div
                                    key={proc.id}
                                    className="flex items-center gap-2"
                                >
                                    <span className="font-mono text-xs text-muted-foreground">
                                        {proc.codigo_cups}
                                    </span>
                                    <span>
                                        <NombreDato texto={proc.nombre} />
                                    </span>
                                    {proc.es_principal && (
                                        <Badge variant="secondary">
                                            Principal
                                        </Badge>
                                    )}
                                </div>
                            ))}
                        </div>
                        <div className="border-t pt-3">
                            {cirugia.equipo.map((miembro, i) => (
                                <div
                                    key={i}
                                    className="flex items-center justify-between py-0.5"
                                >
                                    <span>
                                        <NombreDato
                                            texto={miembro.nombre ?? '—'}
                                        />{' '}
                                        <span className="text-muted-foreground">
                                            ({etiqueta(miembro.rol)})
                                        </span>{' '}
                                        {variasFases && (
                                            <Badge
                                                variant="outline"
                                                className="align-middle text-xs font-normal"
                                            >
                                                {ETIQUETA_FASE[miembro.fase]}
                                            </Badge>
                                        )}
                                    </span>
                                    <span className="shrink-0 whitespace-nowrap tabular-nums">
                                        {miembro.minutos_participacion} min
                                    </span>
                                </div>
                            ))}
                            {cirugia.equipo.length === 0 && (
                                <p className="text-muted-foreground">
                                    Sin equipo quirúrgico registrado.
                                </p>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Lo consumido estaba solo dentro del cálculo plegado, y los
            insumos suelen ser la tajada más grande del costo: se ven
            sin abrir nada, de mayor a menor y los primeros ocho. */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-baseline justify-between gap-2 text-base">
                        Insumos y equipos usados
                        {cirugia.consumos.length > 0 && (
                            <span className="text-sm font-normal text-muted-foreground tabular-nums">
                                {cirugia.consumos.length} insumos ·{' '}
                                {cop(totalInsumos)}
                            </span>
                        )}
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-3 text-sm">
                    {consumosVisibles.length > 0 ? (
                        <div className="grid gap-x-10 sm:grid-cols-2">
                            {consumosVisibles.map((consumo, i) => (
                                <div
                                    key={i}
                                    className="flex items-baseline justify-between gap-3 border-b py-1.5"
                                >
                                    <span className="min-w-0 truncate">
                                        <NombreDato
                                            texto={consumo.insumo ?? '—'}
                                        />{' '}
                                        <span className="text-muted-foreground tabular-nums">
                                            ×{' '}
                                            {Number(
                                                consumo.cantidad,
                                            ).toLocaleString('es-CO')}
                                            {consumo.unidad
                                                ? ` ${consumo.unidad}`
                                                : ''}
                                        </span>
                                    </span>
                                    <span className="shrink-0 tabular-nums">
                                        {cop(Number(consumo.costo_total))}
                                    </span>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="text-muted-foreground">
                            Sin insumos registrados.
                        </p>
                    )}
                    {consumosOrdenados.length > INSUMOS_VISIBLES && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setTodosLosInsumos((v) => !v)}
                        >
                            {todosLosInsumos
                                ? 'Ver solo los de mayor costo'
                                : `Ver los ${consumosOrdenados.length} insumos`}
                        </Button>
                    )}
                    {cirugia.equipos_medicos.length > 0 && (
                        <p className="border-t pt-3 text-muted-foreground">
                            <span className="font-medium text-foreground">
                                Equipos:
                            </span>{' '}
                            {cirugia.equipos_medicos
                                .map(
                                    (equipo) =>
                                        `${separarMarcaSemilla(equipo.nombre).limpio} (${
                                            equipo.minutos_uso !== null
                                                ? `${equipo.minutos_uso} min`
                                                : 'todo el tiempo'
                                        })`,
                                )
                                .join(' · ')}
                        </p>
                    )}
                </CardContent>
            </Card>

            <div className="grid gap-4 lg:grid-cols-2">
                <FacturacionCard
                    cirugiaId={cirugia.id}
                    facturacion={facturacion}
                    costoTotal={
                        costo !== null ? Number(costo.costo_total) : null
                    }
                />
                <ResultadoClinicoCard
                    cirugiaId={cirugia.id}
                    resultado={resultadoClinico}
                />
            </div>

            {/* El cálculo completo (estándar por componente, costo por
            fase, bolsas CIF con su fórmula, línea a línea) es para
            auditar, no para la primera lectura: queda plegado. */}
            {costo && <CalculoDetallado costo={costo} />}
        </>
    );
}
