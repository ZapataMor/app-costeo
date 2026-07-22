import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Calculator, Pencil, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import CirugiaController from '@/actions/App/Http/Controllers/Cirugias/CirugiaController';
import { DesgloseCosto } from '@/components/cirugias/desglose-costo';
import { FacturacionCard } from '@/components/cirugias/facturacion-card';
import { ResultadoClinicoCard } from '@/components/cirugias/resultado-clinico-card';
import Heading from '@/components/heading';
import { ConfirmarEliminacion } from '@/components/parametros/confirmar-eliminacion';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type {
    CirugiaDetalle,
    CostoCirugia,
    FaseCiclo,
    Facturacion,
    ResultadoClinico,
} from '@/types/cirugias';

/** Etiquetas de fase para el detalle; el valor guardado es abreviado. */
const ETIQUETA_FASE: Record<FaseCiclo, string> = {
    pre: 'pre-quirúrgica',
    quirurgica: 'quirúrgica',
    post: 'post-quirúrgica',
};

export default function CirugiasShow({
    cirugia,
    costo,
    facturacion,
    resultadoClinico,
}: {
    cirugia: CirugiaDetalle;
    costo: CostoCirugia | null;
    facturacion: Facturacion | null;
    resultadoClinico: ResultadoClinico | null;
}) {
    const [calculando, setCalculando] = useState(false);

    const sinTerminar = cirugia.hora_fin === null;
    const sinEstadoRealizada = cirugia.estado !== 'realizada';
    const noContabilizada = sinTerminar || sinEstadoRealizada;

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
            <Head title={`Procedimiento #${cirugia.id}`} />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={`Procedimiento #${cirugia.id} · ${cirugia.fecha ?? 'sin fecha'}`}
                        description={
                            cirugia.paciente
                                ? `Paciente: ${cirugia.paciente.nombres} ${cirugia.paciente.apellidos}`
                                : undefined
                        }
                    />
                    <div className="flex flex-wrap items-center gap-2">
                        <Button asChild variant="outline">
                            <Link href={CirugiaController.index.url()}>
                                <ArrowLeft className="size-4" />
                                Volver
                            </Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href={CirugiaController.edit.url(cirugia.id)}>
                                <Pencil className="size-4" />
                                Corregir
                            </Link>
                        </Button>
                        <Button
                            onClick={calcular}
                            disabled={calculando || sinEstadoRealizada}
                        >
                            <Calculator className="size-4" />
                            {costo
                                ? 'Recalcular costo'
                                : 'Calcular costo TDABC'}
                        </Button>
                        <ConfirmarEliminacion
                            url={CirugiaController.destroy.url(cirugia.id)}
                            descripcion="Se eliminará el procedimiento con su equipo quirúrgico, consumos, equipos, costo calculado, facturación y resultado clínico. Esta acción no se puede deshacer."
                        />
                    </div>
                </div>

                {noContabilizada && (
                    <Alert className="border-amber-300/70 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                        <TriangleAlert className="size-4" />
                        <AlertTitle>
                            Este procedimiento no se está contando en los
                            indicadores
                        </AlertTitle>
                        <AlertDescription className="text-amber-800 dark:text-amber-300/90">
                            {sinTerminar && (
                                <p>
                                    No se ha marcado como terminada: falta la
                                    hora de finalización.
                                </p>
                            )}
                            {sinEstadoRealizada && (
                                <p>
                                    Su estado es «
                                    {cirugia.estado.replace('_', ' ')}»: solo
                                    los procedimientos realizados se costean y
                                    entran a los indicadores.
                                </p>
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Datos del procedimiento
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">
                                    Inicio
                                </span>
                                <span className="tabular-nums">
                                    {cirugia.hora_inicio ?? '—'}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">
                                    Fin
                                </span>
                                <span className="tabular-nums">
                                    {cirugia.hora_fin ?? '—'}
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
                                <span className="text-muted-foreground">
                                    Tipo / Estado
                                </span>
                                <span className="capitalize">
                                    {cirugia.tipo} /{' '}
                                    {cirugia.estado.replace('_', ' ')}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">
                                    Sala
                                </span>
                                <span>
                                    {cirugia.sala?.nombre ?? 'Sin sala'}
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
                                    {cirugia.observaciones}
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Procedimientos y equipo
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
                                        <span>{proc.nombre}</span>
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
                                            {miembro.nombre ?? '—'}{' '}
                                            <span className="text-muted-foreground capitalize">
                                                ({miembro.rol})
                                            </span>{' '}
                                            <Badge
                                                variant="outline"
                                                className="align-middle text-xs font-normal"
                                            >
                                                {ETIQUETA_FASE[miembro.fase]}
                                            </Badge>
                                        </span>
                                        <span className="tabular-nums">
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

                {costo ? (
                    <DesgloseCosto costo={costo} />
                ) : (
                    <Card>
                        <CardContent className="py-8 text-center text-muted-foreground">
                            Este procedimiento aún no tiene costo calculado. Usa
                            «Calcular costo TDABC» para generar el desglose.
                        </CardContent>
                    </Card>
                )}

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
            </div>
        </>
    );
}

CirugiasShow.layout = {
    breadcrumbs: [
        { title: 'Cirugías realizadas', href: '/cirugias' },
        { title: 'Detalle', href: '#' },
    ],
};
