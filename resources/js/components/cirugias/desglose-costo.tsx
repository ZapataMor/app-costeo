import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cop, etiqueta, fechaHora, numero } from '@/lib/formato';
import type { CostoCirugia } from '@/types/cirugias';

function FilaTotal({
    etiqueta,
    valor,
    destacado = false,
}: {
    etiqueta: string;
    valor: number;
    destacado?: boolean;
}) {
    return (
        <div
            className={`flex items-center justify-between ${destacado ? 'text-base font-semibold' : 'text-sm'}`}
        >
            <span>{etiqueta}</span>
            <span className="tabular-nums">{cop(valor)}</span>
        </div>
    );
}

/**
 * Desglose TDABC del costo de una cirugía: talento humano, sala,
 * equipos, insumos e indirectos, línea a línea desde el JSON `detalle`.
 */
export function DesgloseCosto({ costo }: { costo: CostoCirugia }) {
    const detalle = costo.detalle;

    return (
        <div className="space-y-4">
            <Card>
                <CardHeader>
                    <CardTitle className="text-base">
                        Costo total TDABC
                    </CardTitle>
                    <CardDescription>
                        {costo.calculado_en
                            ? `Calculado el ${fechaHora(costo.calculado_en)}`
                            : 'Sin fecha de cálculo'}
                        {detalle &&
                            ` · ${numero(detalle.minutos_disponibles_mes)} minutos disponibles/mes`}
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-1.5">
                    <FilaTotal
                        etiqueta="Recurso humano"
                        valor={Number(costo.costo_recurso_humano)}
                    />
                    <FilaTotal
                        etiqueta="Sala operatoria"
                        valor={Number(costo.costo_sala)}
                    />
                    <FilaTotal
                        etiqueta="Equipos médicos"
                        valor={Number(costo.costo_equipos)}
                    />
                    <FilaTotal
                        etiqueta="Insumos"
                        valor={Number(costo.costo_insumos)}
                    />
                    <div className="my-2 border-t" />
                    <FilaTotal
                        etiqueta="Costo directo"
                        valor={Number(costo.costo_directo)}
                    />
                    <FilaTotal
                        etiqueta="Costo indirecto (factor del hospital)"
                        valor={Number(costo.costo_indirecto)}
                    />
                    <div className="my-2 border-t" />
                    <FilaTotal
                        etiqueta="TOTAL"
                        valor={Number(costo.costo_total)}
                        destacado
                    />
                </CardContent>
            </Card>

            {detalle?.por_fase && (
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Costo directo por fase
                        </CardTitle>
                        <CardDescription>
                            Dónde se consume el dinero a lo largo del ciclo del
                            paciente. La sala y los equipos médicos se imputan
                            íntegros a la fase quirúrgica.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-1.5">
                        <FilaTotal
                            etiqueta="Pre-quirúrgica"
                            valor={detalle.por_fase.pre}
                        />
                        <FilaTotal
                            etiqueta="Quirúrgica"
                            valor={detalle.por_fase.quirurgica}
                        />
                        <FilaTotal
                            etiqueta="Post-quirúrgica"
                            valor={detalle.por_fase.post}
                        />
                    </CardContent>
                </Card>
            )}

            {detalle && (
                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Recurso humano
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
                                            Min
                                        </th>
                                        {/* Se mostraba «833,333» ($/min con
                                            tres decimales), que se lee como
                                            833 pesos. Por hora es una cifra
                                            que un jefe de servicio reconoce. */}
                                        <th className="py-1.5 text-right font-medium">
                                            $/hora
                                        </th>
                                        <th className="py-1.5 text-right font-medium">
                                            Costo
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {detalle.recurso_humano.map((linea, i) => (
                                        <tr
                                            key={i}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-1.5">
                                                {linea.nombre}
                                            </td>
                                            <td className="py-1.5">
                                                {etiqueta(linea.rol)}
                                            </td>
                                            <td className="py-1.5 text-right tabular-nums">
                                                {linea.minutos}
                                            </td>
                                            <td className="py-1.5 text-right tabular-nums">
                                                {cop(
                                                    linea.costo_por_minuto * 60,
                                                )}
                                            </td>
                                            <td className="py-1.5 text-right tabular-nums">
                                                {cop(linea.costo)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Insumos consumidos
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-1.5 font-medium">
                                            Insumo
                                        </th>
                                        <th className="py-1.5 text-right font-medium">
                                            Cantidad
                                        </th>
                                        <th className="py-1.5 text-right font-medium">
                                            Unitario
                                        </th>
                                        <th className="py-1.5 text-right font-medium">
                                            Costo
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {detalle.insumos.map((linea, i) => (
                                        <tr
                                            key={i}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-1.5">
                                                {linea.nombre ?? '—'}
                                            </td>
                                            <td className="py-1.5 text-right tabular-nums">
                                                {linea.cantidad}
                                            </td>
                                            <td className="py-1.5 text-right tabular-nums">
                                                {cop(linea.costo_unitario)}
                                            </td>
                                            <td className="py-1.5 text-right tabular-nums">
                                                {cop(linea.costo)}
                                            </td>
                                        </tr>
                                    ))}
                                    {detalle.insumos.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={4}
                                                className="py-3 text-center text-muted-foreground"
                                            >
                                                Sin consumos registrados
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
                                Sala operatoria
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {detalle.sala ? (
                                <div className="space-y-1.5 text-sm">
                                    <div className="flex justify-between">
                                        <span>{detalle.sala.nombre}</span>
                                        <span className="tabular-nums">
                                            {cop(detalle.sala.costo)}
                                        </span>
                                    </div>
                                    <p className="text-muted-foreground">
                                        {detalle.sala.minutos} min ×{' '}
                                        {cop(detalle.sala.costo_hora)}/hora
                                    </p>
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Sin sala asignada o sin duración registrada.
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Equipos médicos
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-1.5 font-medium">
                                            Equipo
                                        </th>
                                        <th className="py-1.5 text-right font-medium">
                                            Min
                                        </th>
                                        <th className="py-1.5 text-right font-medium">
                                            $/hora
                                        </th>
                                        <th className="py-1.5 text-right font-medium">
                                            Costo
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {detalle.equipos.map((linea, i) => (
                                        <tr
                                            key={i}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-1.5">
                                                {linea.nombre}
                                            </td>
                                            <td className="py-1.5 text-right tabular-nums">
                                                {linea.minutos}
                                            </td>
                                            <td className="py-1.5 text-right tabular-nums">
                                                {cop(linea.costo_hora)}
                                            </td>
                                            <td className="py-1.5 text-right tabular-nums">
                                                {cop(linea.costo)}
                                            </td>
                                        </tr>
                                    ))}
                                    {detalle.equipos.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={4}
                                                className="py-3 text-center text-muted-foreground"
                                            >
                                                Sin equipos registrados
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                </div>
            )}
        </div>
    );
}
