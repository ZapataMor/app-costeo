import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { cop, pct, pctVariacion } from '@/lib/formato';
import { etiquetaEje } from '@/lib/graficas';
import type { ProcedimientoPracticas } from '@/types/conocimiento';

export default function MejoresPracticas({
    procedimientos,
    filtros,
}: {
    procedimientos: ProcedimientoPracticas[];
    filtros: { q: string };
}) {
    const [q, setQ] = useState(filtros.q);
    const porEspecialidad = procedimientos.reduce<
        Record<string, ProcedimientoPracticas[]>
    >((grupos, p) => {
        (grupos[p.especialidad] ??= []).push(p);

        return grupos;
    }, {});

    return (
        <>
            <Head title="Mejores prácticas" />
            <div className="flex flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Mejores prácticas</h1>
                    <p className="max-w-3xl text-sm text-muted-foreground">
                        Para cada procedimiento con al menos 10 casos: qué hacen
                        distinto las cirugías de menor costo que además salieron
                        bien (sin complicaciones y con estancia normal), cómo se
                        comparan los profesionales de forma anónima y qué
                        lecciones hay publicadas.
                    </p>
                </div>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get(
                            '/conocimiento/mejores-practicas',
                            { q },
                            { replace: true },
                        );
                    }}
                    className="relative w-80"
                >
                    <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                    <Input
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder="Procedimiento, CUPS o especialidad"
                        className="pl-8"
                    />
                </form>

                {Object.entries(porEspecialidad).map(
                    ([especialidad, lista]) => (
                        <div key={especialidad} className="space-y-2">
                            <h2 className="text-sm font-semibold tracking-[1px] text-[#5B687C] uppercase">
                                {especialidad}
                            </h2>
                            <Card>
                                <CardContent className="overflow-x-auto p-0">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b text-left text-muted-foreground">
                                                <th className="p-3 font-medium">
                                                    Procedimiento
                                                </th>
                                                <th className="p-3 text-right font-medium">
                                                    Casos
                                                </th>
                                                <th className="p-3 text-right font-medium">
                                                    Costo promedio
                                                </th>
                                                <th className="p-3 text-right font-medium">
                                                    Desviación vs. estándar
                                                </th>
                                                <th className="p-3 text-right font-medium">
                                                    En rango
                                                </th>
                                                <th className="p-3 text-right font-medium">
                                                    Lecciones
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {lista.map((p) => (
                                                <tr
                                                    key={p.id}
                                                    className="border-b last:border-0"
                                                >
                                                    <td className="p-3">
                                                        {p.analizable ? (
                                                            <Link
                                                                href={`/conocimiento/mejores-practicas/${p.id}`}
                                                                className="hover:underline"
                                                            >
                                                                {etiquetaEje(
                                                                    p.nombre,
                                                                    60,
                                                                )}
                                                            </Link>
                                                        ) : (
                                                            <span className="text-muted-foreground">
                                                                {etiquetaEje(
                                                                    p.nombre,
                                                                    60,
                                                                )}
                                                            </span>
                                                        )}
                                                        <span className="ml-2 font-mono text-xs text-muted-foreground">
                                                            {p.codigo_cups}
                                                        </span>
                                                    </td>
                                                    <td className="p-3 text-right tabular-nums">
                                                        {p.n}
                                                    </td>
                                                    <td className="p-3 text-right tabular-nums">
                                                        {cop(p.costo_promedio)}
                                                    </td>
                                                    <td
                                                        className={`p-3 text-right tabular-nums ${(p.desviacion_promedio ?? 0) > 0.05 ? 'text-[#9E3B3B]' : ''}`}
                                                    >
                                                        {pctVariacion(
                                                            p.desviacion_promedio,
                                                        )}
                                                    </td>
                                                    <td className="p-3 text-right tabular-nums">
                                                        {pct(p.en_rango_pct, 0)}
                                                    </td>
                                                    <td className="p-3 text-right">
                                                        {p.lecciones > 0 ? (
                                                            <Badge variant="secondary">
                                                                {p.lecciones}
                                                            </Badge>
                                                        ) : (
                                                            '—'
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </CardContent>
                            </Card>
                        </div>
                    ),
                )}
            </div>
        </>
    );
}

MejoresPracticas.layout = {
    breadcrumbs: [
        { title: 'Conocimiento', href: '/conocimiento/lecciones' },
        { title: 'Mejores prácticas', href: '/conocimiento/mejores-practicas' },
    ],
};
