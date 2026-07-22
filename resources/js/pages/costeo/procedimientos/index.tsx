import { Head, Link, router } from '@inertiajs/react';
import { Eye, Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { EncabezadoCosteo } from '@/components/costeo/encabezado-costeo';
import { Paginacion } from '@/components/parametros/paginacion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cop } from '@/lib/formato';
import type {
    FiltrosProcedimientosCosteo,
    PaginadoProcedimientosCosteo,
} from '@/types/costeo';

const TODOS = 'todos';

export default function ProcedimientosCosteoIndex({
    procedimientos,
    filtros,
    especialidades,
    complejidades,
}: {
    procedimientos: PaginadoProcedimientosCosteo;
    filtros: FiltrosProcedimientosCosteo;
    especialidades: string[];
    complejidades: string[];
}) {
    const [q, setQ] = useState(filtros.q);
    const primeraCarga = useRef(true);

    const aplicar = (parcial: Partial<FiltrosProcedimientosCosteo>) => {
        const datos = { ...filtros, q, ...parcial };
        const query = Object.fromEntries(
            Object.entries(datos).filter(([, v]) => v !== ''),
        );
        router.get('/costeo/procedimientos', query, {
            preserveState: true,
            replace: true,
        });
    };

    useEffect(() => {
        if (primeraCarga.current) {
            primeraCarga.current = false;

            return;
        }

        const temporizador = setTimeout(() => aplicar({ q }), 350);

        return () => clearTimeout(temporizador);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [q]);

    const hayFiltros =
        filtros.q !== '' ||
        filtros.especialidad !== '' ||
        filtros.complejidad !== '';

    return (
        <>
            <Head title="Costo por procedimiento" />
            <div className="flex flex-col gap-4 p-4">
                <EncabezadoCosteo
                    titulo="Costo por procedimiento"
                    descripcion="Cuánto cuesta cada procedimiento del hospital. Entre a uno para ver cada cirugía realizada y su costo TDABC detallado."
                />

                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative w-full max-w-xs">
                        <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Buscar por nombre o código CUPS…"
                            className="pl-8"
                            aria-label="Buscar procedimiento"
                        />
                    </div>
                    <Select
                        value={filtros.especialidad || TODOS}
                        onValueChange={(v) =>
                            aplicar({ especialidad: v === TODOS ? '' : v })
                        }
                    >
                        <SelectTrigger
                            className="w-48"
                            aria-label="Filtrar por especialidad"
                        >
                            <SelectValue placeholder="Especialidad" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={TODOS}>
                                Todas las especialidades
                            </SelectItem>
                            {especialidades.map((especialidad) => (
                                <SelectItem
                                    key={especialidad}
                                    value={especialidad}
                                >
                                    {especialidad}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        value={filtros.complejidad || TODOS}
                        onValueChange={(v) =>
                            aplicar({ complejidad: v === TODOS ? '' : v })
                        }
                    >
                        <SelectTrigger
                            className="w-44"
                            aria-label="Filtrar por complejidad"
                        >
                            <SelectValue placeholder="Complejidad" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={TODOS}>
                                Toda complejidad
                            </SelectItem>
                            {complejidades.map((complejidad) => (
                                <SelectItem
                                    key={complejidad}
                                    value={complejidad}
                                    className="capitalize"
                                >
                                    {complejidad}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {hayFiltros && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                setQ('');
                                aplicar({
                                    q: '',
                                    especialidad: '',
                                    complejidad: '',
                                });
                            }}
                        >
                            <X className="size-4" />
                            Limpiar filtros
                        </Button>
                    )}
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50 text-left text-muted-foreground">
                                <th className="p-3 font-medium">CUPS</th>
                                <th className="p-3 font-medium">
                                    Procedimiento
                                </th>
                                <th className="p-3 font-medium">
                                    Especialidad
                                </th>
                                <th className="p-3 font-medium">Complejidad</th>
                                <th className="p-3 text-right font-medium">
                                    Realizadas
                                </th>
                                <th className="p-3 text-right font-medium">
                                    Costeadas
                                </th>
                                <th className="p-3 text-right font-medium">
                                    Costo promedio
                                </th>
                                <th className="p-3 text-right font-medium">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {procedimientos.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={8}
                                        className="p-6 text-center text-muted-foreground"
                                    >
                                        {hayFiltros
                                            ? 'Ningún procedimiento coincide con los filtros.'
                                            : 'Aún no hay procedimientos registrados en este hospital.'}
                                    </td>
                                </tr>
                            )}
                            {procedimientos.data.map((procedimiento) => (
                                <tr
                                    key={procedimiento.id}
                                    className="border-b last:border-0"
                                >
                                    <td className="p-3 font-mono text-xs">
                                        {procedimiento.codigo_cups}
                                    </td>
                                    <td className="p-3">
                                        <Link
                                            href={`/costeo/procedimientos/${procedimiento.id}`}
                                            prefetch
                                            className="font-medium hover:underline"
                                        >
                                            {procedimiento.nombre}
                                        </Link>
                                    </td>
                                    <td className="p-3">
                                        {procedimiento.especialidad}
                                    </td>
                                    <td className="p-3">
                                        <Badge
                                            variant="outline"
                                            className="capitalize"
                                        >
                                            {procedimiento.complejidad}
                                        </Badge>
                                    </td>
                                    <td className="p-3 text-right tabular-nums">
                                        {procedimiento.n_realizadas}
                                    </td>
                                    <td className="p-3 text-right tabular-nums">
                                        {procedimiento.n_costeadas}
                                    </td>
                                    <td className="p-3 text-right tabular-nums">
                                        {procedimiento.costo_promedio !==
                                        null ? (
                                            cop(procedimiento.costo_promedio)
                                        ) : (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </td>
                                    <td className="p-3 text-right">
                                        <Button
                                            asChild
                                            variant="ghost"
                                            size="icon"
                                            aria-label="Ver cirugías del procedimiento"
                                        >
                                            <Link
                                                href={`/costeo/procedimientos/${procedimiento.id}`}
                                                prefetch
                                            >
                                                <Eye className="size-4" />
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Paginacion
                    links={procedimientos.links}
                    total={procedimientos.total}
                    from={procedimientos.from}
                    to={procedimientos.to}
                />
            </div>
        </>
    );
}

ProcedimientosCosteoIndex.layout = {
    breadcrumbs: [
        { title: 'Costeo quirúrgico', href: '/costeo' },
        { title: 'Costo por procedimiento', href: '/costeo/procedimientos' },
    ],
};
