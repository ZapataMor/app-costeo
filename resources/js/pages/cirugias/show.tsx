import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Calculator } from 'lucide-react';
import { useState } from 'react';
import CirugiaController from '@/actions/App/Http/Controllers/Cirugias/CirugiaController';
import { DesgloseCosto } from '@/components/cirugias/desglose-costo';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { CirugiaDetalle, CostoCirugia } from '@/types/cirugias';

export default function CirugiasShow({ cirugia, costo }: { cirugia: CirugiaDetalle; costo: CostoCirugia | null }) {
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
            <Head title={`Cirugía #${cirugia.id}`} />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={`Cirugía #${cirugia.id} · ${cirugia.fecha ?? 'sin fecha'}`}
                        description={
                            cirugia.paciente ? `Paciente: ${cirugia.paciente.nombres} ${cirugia.paciente.apellidos}` : undefined
                        }
                    />
                    <div className="flex gap-2">
                        <Button asChild variant="outline">
                            <Link href={CirugiaController.index.url()}>
                                <ArrowLeft className="size-4" />
                                Volver
                            </Link>
                        </Button>
                        <Button onClick={calcular} disabled={calculando}>
                            <Calculator className="size-4" />
                            {costo ? 'Recalcular costo' : 'Calcular costo TDABC'}
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Datos de la cirugía</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Inicio</span>
                                <span className="tabular-nums">{cirugia.hora_inicio ?? '—'}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Fin</span>
                                <span className="tabular-nums">{cirugia.hora_fin ?? '—'}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Duración</span>
                                <span className="tabular-nums">
                                    {cirugia.duracion_minutos !== null ? `${cirugia.duracion_minutos} min` : '—'}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Tipo / Estado</span>
                                <span className="capitalize">
                                    {cirugia.tipo} / {cirugia.estado}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Sala</span>
                                <span>{cirugia.sala?.nombre ?? 'Sin sala'}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Diagnóstico CIE-10</span>
                                <span className="font-mono">{cirugia.diagnostico_cie10 ?? '—'}</span>
                            </div>
                            {cirugia.observaciones && (
                                <p className="pt-2 text-muted-foreground">{cirugia.observaciones}</p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Procedimientos y equipo</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            <div className="space-y-1">
                                {cirugia.procedimientos.map((proc) => (
                                    <div key={proc.id} className="flex items-center gap-2">
                                        <span className="font-mono text-xs text-muted-foreground">{proc.codigo_cups}</span>
                                        <span>{proc.nombre}</span>
                                        {proc.es_principal && <Badge variant="secondary">Principal</Badge>}
                                    </div>
                                ))}
                            </div>
                            <div className="border-t pt-3">
                                {cirugia.equipo.map((miembro, i) => (
                                    <div key={i} className="flex items-center justify-between py-0.5">
                                        <span>
                                            {miembro.nombre ?? '—'}{' '}
                                            <span className="capitalize text-muted-foreground">({miembro.rol})</span>
                                        </span>
                                        <span className="tabular-nums">{miembro.minutos_participacion} min</span>
                                    </div>
                                ))}
                                {cirugia.equipo.length === 0 && (
                                    <p className="text-muted-foreground">Sin equipo quirúrgico registrado.</p>
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
                            Esta cirugía aún no tiene costo calculado. Usa «Calcular costo TDABC» para generar el desglose.
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

CirugiasShow.layout = {
    breadcrumbs: [
        { title: 'Cirugías', href: '/cirugias' },
        { title: 'Detalle', href: '#' },
    ],
};
