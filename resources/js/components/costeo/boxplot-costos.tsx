import { router } from '@inertiajs/react';
import { cop, fecha } from '@/lib/formato';
import { COLOR } from '@/lib/graficas';
import type { GrupoOutliers } from '@/types/costeo';

/**
 * Diagrama de caja por procedimiento, en un solo gráfico.
 *
 * Antes había un scatter alto por procedimiento —cuatro pantallas de scroll
 * para señalar un outlier— y el eje X era un índice sin etiquetas, así que un
 * punto atípico no se podía identificar ni abrir. Aquí cada procedimiento es
 * una caja, los atípicos son puntos y cada punto lleva a su cirugía.
 */
export function BoxplotCostos({ grupos }: { grupos: GrupoOutliers[] }) {
    const conCaja = grupos.filter((grupo) => grupo.n > 0);

    if (conCaja.length === 0) {
        return (
            <p className="py-10 text-center text-sm text-muted-foreground">
                No hay cirugías costeadas suficientes para comparar.
            </p>
        );
    }

    // Escala común a todos los procedimientos: comparar cajas en escalas
    // distintas no diría nada.
    const maximo = Math.max(...conCaja.map((g) => g.caja.maximo));
    const minimo = Math.min(...conCaja.map((g) => g.caja.minimo));
    const rango = Math.max(1, maximo - minimo);

    /** Posición horizontal (0–100) de un valor dentro de la escala común. */
    const x = (valor: number) => ((valor - minimo) / rango) * 100;

    return (
        <div className="space-y-1">
            <div className="space-y-5">
                {conCaja.map((grupo) => {
                    const { caja } = grupo;
                    const atipicos = grupo.puntos.filter((p) => p.es_outlier);

                    return (
                        <div key={grupo.procedimiento.id}>
                            <div className="mb-2 flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                <span className="text-sm font-medium">
                                    {grupo.procedimiento.nombre}
                                    <span className="ml-2 font-mono text-xs font-normal text-muted-foreground">
                                        {grupo.procedimiento.codigo_cups}
                                    </span>
                                </span>
                                <span className="text-xs text-muted-foreground tabular-nums">
                                    n={grupo.n} · mediana {cop(caja.mediana)} ·{' '}
                                    {atipicos.length === 0
                                        ? 'sin atípicos'
                                        : `${atipicos.length} atípico${atipicos.length > 1 ? 's' : ''}`}
                                </span>
                            </div>

                            <div className="relative h-9">
                                {/* Bigotes */}
                                <div
                                    className="absolute top-1/2 h-px -translate-y-1/2 bg-border"
                                    style={{
                                        left: `${x(caja.bigote_inferior)}%`,
                                        width: `${x(caja.bigote_superior) - x(caja.bigote_inferior)}%`,
                                    }}
                                />
                                {[
                                    caja.bigote_inferior,
                                    caja.bigote_superior,
                                ].map((valor) => (
                                    <div
                                        key={valor}
                                        className="absolute top-1/2 h-3 w-px -translate-y-1/2 bg-border"
                                        style={{ left: `${x(valor)}%` }}
                                    />
                                ))}

                                {/* Caja intercuartílica: la mitad central */}
                                <div
                                    className="absolute top-1/2 h-6 -translate-y-1/2 rounded-[3px] border"
                                    style={{
                                        left: `${x(caja.q1)}%`,
                                        width: `${Math.max(0.4, x(caja.q3) - x(caja.q1))}%`,
                                        background: `${COLOR.neutro}22`,
                                        borderColor: `${COLOR.neutro}66`,
                                    }}
                                    title={`Q1 ${cop(caja.q1)} — Q3 ${cop(caja.q3)}`}
                                />

                                {/* Mediana */}
                                <div
                                    className="absolute top-1/2 h-6 w-[2px] -translate-y-1/2 rounded"
                                    style={{
                                        left: `${x(caja.mediana)}%`,
                                        background: COLOR.neutro,
                                    }}
                                    title={`Mediana ${cop(caja.mediana)}`}
                                />

                                {/* Atípicos: clicables hacia su cirugía */}
                                {atipicos.map((punto) => (
                                    <button
                                        key={punto.cirugia_id}
                                        type="button"
                                        onClick={() =>
                                            router.visit(
                                                `/costeo/procedimientos/${grupo.procedimiento.id}/cirugias/${punto.cirugia_id}`,
                                            )
                                        }
                                        className="absolute top-1/2 size-3 -translate-x-1/2 -translate-y-1/2 rounded-full ring-2 ring-background transition-transform hover:scale-150 focus-visible:scale-150 focus-visible:outline-2"
                                        style={{
                                            left: `${x(punto.costo_total)}%`,
                                            background: COLOR.alerta,
                                        }}
                                        title={`${cop(punto.costo_total)} · ${fecha(punto.fecha)} — abrir la cirugía`}
                                        aria-label={`Cirugía atípica del ${fecha(punto.fecha)} por ${cop(punto.costo_total)}. Abrir detalle.`}
                                    />
                                ))}
                            </div>
                        </div>
                    );
                })}
            </div>

            <div className="flex flex-wrap items-center gap-x-5 gap-y-1 border-t pt-3 text-xs text-muted-foreground">
                <span className="flex items-center gap-1.5">
                    <span
                        className="inline-block h-3 w-5 rounded-[3px] border"
                        style={{
                            background: `${COLOR.neutro}22`,
                            borderColor: `${COLOR.neutro}66`,
                        }}
                    />
                    Mitad central de los casos (Q1–Q3)
                </span>
                <span className="flex items-center gap-1.5">
                    <span
                        className="inline-block h-3 w-[2px]"
                        style={{ background: COLOR.neutro }}
                    />
                    Mediana
                </span>
                <span className="flex items-center gap-1.5">
                    <span
                        className="inline-block size-2.5 rounded-full"
                        style={{ background: COLOR.alerta }}
                    />
                    Atípico (clic para abrirlo)
                </span>
                <span className="ml-auto tabular-nums">
                    escala {cop(minimo)} — {cop(maximo)}
                </span>
            </div>
        </div>
    );
}
