import { ClasificacionBadge } from '@/components/conocimiento/clasificacion-badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cop, etiqueta, fechaHora, numero, pctVariacion } from '@/lib/formato';
import type { CostoCirugia, CostoEstandar } from '@/types/cirugias';

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
                        etiqueta={
                            detalle?.indirecto?.via === 'bolsas'
                                ? 'Costo indirecto (bolsas CIF por inductor)'
                                : 'Costo indirecto (factor del hospital)'
                        }
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

            {detalle?.estandar && (
                <ComparacionEstandar
                    costo={costo}
                    estandar={detalle.estandar}
                />
            )}

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

            {detalle?.indirecto?.via === 'bolsas' &&
                detalle.indirecto.bolsas.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Costos indirectos por bolsa
                            </CardTitle>
                            <CardDescription>
                                Cada bolsa se reparte por su propio inductor, no
                                por un porcentaje del costo directo. La cuenta
                                queda a la vista para poder auditarla sin
                                consultar el catálogo.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="overflow-x-auto p-0">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="p-3 font-medium">
                                            Bolsa
                                        </th>
                                        <th className="p-3 font-medium">
                                            Cómo se reparte
                                        </th>
                                        <th className="p-3 text-right font-medium">
                                            Costo
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {detalle.indirecto.bolsas.map((bolsa) => (
                                        <tr
                                            key={
                                                bolsa.concepto_costo_indirecto_id
                                            }
                                            className="border-b last:border-0"
                                        >
                                            <td className="p-3">
                                                {bolsa.nombre}
                                                <span className="block text-xs text-muted-foreground">
                                                    {etiqueta(bolsa.categoria)}
                                                </span>
                                            </td>
                                            <td className="p-3 text-xs text-muted-foreground">
                                                {bolsa.denominador !== null ? (
                                                    <>
                                                        {cop(
                                                            bolsa.monto_mensual ??
                                                                0,
                                                        )}
                                                        /mes ÷{' '}
                                                        {numero(
                                                            bolsa.denominador,
                                                        )}{' '}
                                                        min = {cop(bolsa.tasa)}
                                                        /min ×{' '}
                                                        {numero(
                                                            bolsa.unidades,
                                                        )}{' '}
                                                        min
                                                    </>
                                                ) : (
                                                    <>
                                                        {(
                                                            bolsa.tasa * 100
                                                        ).toFixed(2)}{' '}
                                                        % del costo directo (
                                                        {cop(bolsa.unidades)})
                                                    </>
                                                )}
                                            </td>
                                            <td className="p-3 text-right tabular-nums">
                                                {cop(bolsa.monto)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
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

/**
 * Real frente al estándar del protocolo, componente por componente.
 */
function ComparacionEstandar({
    costo,
    estandar,
}: {
    costo: CostoCirugia;
    estandar: CostoEstandar;
}) {
    const filas: [string, number, number][] = [
        [
            'Recurso humano',
            estandar.recurso_humano,
            Number(costo.costo_recurso_humano),
        ],
        ['Sala operatoria', estandar.sala, Number(costo.costo_sala)],
        ['Equipos médicos', estandar.equipos, Number(costo.costo_equipos)],
        ['Insumos', estandar.insumos, Number(costo.costo_insumos)],
        ['Indirecto', estandar.indirecto, Number(costo.costo_indirecto)],
        ['Total', estandar.total, Number(costo.costo_total)],
    ];

    return (
        <Card>
            <CardHeader>
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <CardTitle className="text-base">
                        Real frente al estándar del protocolo
                    </CardTitle>
                    <ClasificacionBadge
                        clasificacion={costo.clasificacion_desviacion}
                    />
                </div>
                <CardDescription>
                    Lo que debió costar con la duración estimada (
                    {estandar.minutos_sala} min) y la plantilla del
                    procedimiento, a las mismas tarifas. La diferencia mide uso
                    de tiempo, personal e insumos, no cambios de precio.
                </CardDescription>
            </CardHeader>
            <CardContent className="overflow-x-auto p-0">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b text-left text-muted-foreground">
                            <th className="p-3 font-medium">Componente</th>
                            <th className="p-3 text-right font-medium">
                                Estándar
                            </th>
                            <th className="p-3 text-right font-medium">Real</th>
                            <th className="p-3 text-right font-medium">
                                Diferencia
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {filas.map(([nombre, esperado, real]) => (
                            <tr
                                key={nombre}
                                className={`border-b last:border-0 ${nombre === 'Total' ? 'font-semibold' : ''}`}
                            >
                                <td className="p-3">{nombre}</td>
                                <td className="p-3 text-right tabular-nums">
                                    {cop(esperado)}
                                </td>
                                <td className="p-3 text-right tabular-nums">
                                    {cop(real)}
                                </td>
                                <td
                                    className={`p-3 text-right tabular-nums ${real - esperado > 0 ? 'text-red-700 dark:text-red-400' : 'text-emerald-700 dark:text-emerald-400'}`}
                                >
                                    {cop(real - esperado)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <p className="p-3 text-xs text-muted-foreground">
                    Desviación total:{' '}
                    <strong>
                        {pctVariacion(
                            costo.desviacion_pct === null
                                ? null
                                : Number(costo.desviacion_pct),
                        )}
                    </strong>{' '}
                    sobre el estándar.
                </p>
            </CardContent>
        </Card>
    );
}
