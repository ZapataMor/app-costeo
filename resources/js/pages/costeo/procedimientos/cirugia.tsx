import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Calculator, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import { DesgloseCosto } from '@/components/cirugias/desglose-costo';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cop } from '@/lib/formato';
import type { CirugiaDetalle, CostoCirugia } from '@/types/cirugias';
import type { ProcedimientoResumen } from '@/types/costeo';

export default function ProcedimientoCosteoCirugia({
    procedimiento,
    cirugia,
    costo,
}: {
    procedimiento: ProcedimientoResumen;
    cirugia: CirugiaDetalle;
    costo: CostoCirugia | null;
}) {
    const [calculando, setCalculando] = useState(false);

    const esRealizada = cirugia.estado === 'realizada';

    const calcular = () => {
        router.post(
            `/cirugias/${cirugia.id}/calcular-costo`,
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
            <Head
                title={`${procedimiento.nombre} · ${cirugia.fecha ?? 'sin fecha'}`}
            />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={`${procedimiento.nombre} · ${cirugia.fecha ?? 'sin fecha'}`}
                        description={
                            cirugia.paciente
                                ? `Paciente: ${cirugia.paciente.nombres} ${cirugia.paciente.apellidos}`
                                : undefined
                        }
                    />
                    <div className="flex gap-2">
                        <Button asChild variant="outline">
                            <Link
                                href={`/costeo/procedimientos/${procedimiento.id}`}
                            >
                                <ArrowLeft className="size-4" />
                                Volver
                            </Link>
                        </Button>
                        {esRealizada && (
                            <Button onClick={calcular} disabled={calculando}>
                                <Calculator className="size-4" />
                                {costo
                                    ? 'Recalcular costo'
                                    : 'Calcular costo TDABC'}
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Datos generales
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
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Procedimientos y anotaciones
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
                                {cirugia.observaciones ? (
                                    <p className="whitespace-pre-line">
                                        {cirugia.observaciones}
                                    </p>
                                ) : (
                                    <p className="text-muted-foreground">
                                        Sin anotaciones registradas.
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {costo ? (
                    <DesgloseCosto costo={costo} />
                ) : (
                    <>
                        <Alert className="border-amber-300/70 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                            <TriangleAlert className="size-4" />
                            <AlertTitle>Sin costo TDABC calculado</AlertTitle>
                            <AlertDescription className="text-amber-800 dark:text-amber-300/90">
                                {esRealizada ? (
                                    <p>
                                        Usa «Calcular costo TDABC» para generar
                                        el desglose de costos de esta cirugía.
                                    </p>
                                ) : (
                                    <p>
                                        Solo se costean cirugías en estado
                                        «realizada». Esta cirugía está «
                                        {cirugia.estado.replace('_', ' ')}».
                                    </p>
                                )}
                            </AlertDescription>
                        </Alert>

                        <div className="grid gap-4 lg:grid-cols-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Personas involucradas
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b text-left text-muted-foreground">
                                                <th className="py-1.5 font-medium">
                                                    Persona
                                                </th>
                                                <th className="py-1.5 font-medium">
                                                    Rol
                                                </th>
                                                <th className="py-1.5 text-right font-medium">
                                                    Minutos
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {cirugia.equipo.map(
                                                (miembro, i) => (
                                                    <tr
                                                        key={i}
                                                        className="border-b last:border-0"
                                                    >
                                                        <td className="py-1.5">
                                                            {miembro.nombre ??
                                                                '—'}
                                                        </td>
                                                        <td className="py-1.5 capitalize">
                                                            {miembro.rol}
                                                        </td>
                                                        <td className="py-1.5 text-right tabular-nums">
                                                            {
                                                                miembro.minutos_participacion
                                                            }
                                                        </td>
                                                    </tr>
                                                ),
                                            )}
                                            {cirugia.equipo.length === 0 && (
                                                <tr>
                                                    <td
                                                        colSpan={3}
                                                        className="py-3 text-center text-muted-foreground"
                                                    >
                                                        Sin equipo quirúrgico
                                                        registrado
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Insumos y equipos utilizados
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b text-left text-muted-foreground">
                                                <th className="py-1.5 font-medium">
                                                    Ítem
                                                </th>
                                                <th className="py-1.5 text-right font-medium">
                                                    Cantidad / Min
                                                </th>
                                                <th className="py-1.5 text-right font-medium">
                                                    Costo
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {cirugia.consumos.map(
                                                (consumo, i) => (
                                                    <tr
                                                        key={`c-${i}`}
                                                        className="border-b last:border-0"
                                                    >
                                                        <td className="py-1.5">
                                                            {consumo.insumo ??
                                                                '—'}
                                                        </td>
                                                        <td className="py-1.5 text-right tabular-nums">
                                                            {consumo.cantidad}{' '}
                                                            {consumo.unidad}
                                                        </td>
                                                        <td className="py-1.5 text-right tabular-nums">
                                                            {cop(
                                                                Number(
                                                                    consumo.costo_total,
                                                                ),
                                                            )}
                                                        </td>
                                                    </tr>
                                                ),
                                            )}
                                            {cirugia.equipos_medicos.map(
                                                (equipo, i) => (
                                                    <tr
                                                        key={`e-${i}`}
                                                        className="border-b last:border-0"
                                                    >
                                                        <td className="py-1.5">
                                                            {equipo.nombre}
                                                        </td>
                                                        <td className="py-1.5 text-right tabular-nums">
                                                            {equipo.minutos_uso ??
                                                                '—'}{' '}
                                                            min
                                                        </td>
                                                        <td className="py-1.5 text-right text-muted-foreground">
                                                            al costear
                                                        </td>
                                                    </tr>
                                                ),
                                            )}
                                            {cirugia.consumos.length === 0 &&
                                                cirugia.equipos_medicos
                                                    .length === 0 && (
                                                    <tr>
                                                        <td
                                                            colSpan={3}
                                                            className="py-3 text-center text-muted-foreground"
                                                        >
                                                            Sin insumos ni
                                                            equipos registrados
                                                        </td>
                                                    </tr>
                                                )}
                                        </tbody>
                                    </table>
                                </CardContent>
                            </Card>
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

ProcedimientoCosteoCirugia.layout = {
    breadcrumbs: [
        { title: 'Costeo quirúrgico', href: '/costeo' },
        { title: 'Procedimientos', href: '/costeo/procedimientos' },
        { title: 'Detalle de la cirugía', href: '#' },
    ],
};
