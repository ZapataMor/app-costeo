import { router } from '@inertiajs/react';
import { AlertTriangle, Power, PowerOff } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cop } from '@/lib/formato';
import type { CategoriaCifOpcion } from '@/types/parametros';

const etiqueta = (valor: string) =>
    valor.charAt(0).toUpperCase() + valor.slice(1).replace(/_/g, ' ');

/**
 * Encendido y apagado de las bolsas por categoría.
 *
 * Activar no es un interruptor más: excluye del costo directo el componente
 * que la bolsa pasa a cubrir. Por eso el diálogo nombra los registros que
 * quedarían duplicados —con su valor— antes de pedir confirmación, en vez de
 * limitarse a preguntar «¿seguro?».
 */
export function ActivacionCategoriasCif({
    categorias,
}: {
    categorias: CategoriaCifOpcion[];
}) {
    const [confirmando, setConfirmando] = useState<CategoriaCifOpcion | null>(
        null,
    );
    const [procesando, setProcesando] = useState(false);

    const conBolsas = categorias.filter((c) => c.total > 0);

    if (conBolsas.length === 0) {
        return null;
    }

    const enviar = (
        categoria: string,
        activar: boolean,
        confirmado = false,
    ) => {
        router.post(
            `/parametros/costos-indirectos/${activar ? 'activar' : 'desactivar'}`,
            { categoria, confirmado },
            {
                preserveScroll: true,
                onStart: () => setProcesando(true),
                onFinish: () => {
                    setProcesando(false);
                    setConfirmando(null);
                },
            },
        );
    };

    const alActivar = (categoria: CategoriaCifOpcion) => {
        // Sin componentes digitados no hay nada que confirmar: se enciende.
        if (categoria.conflictos.length === 0) {
            enviar(categoria.valor, true);

            return;
        }

        setConfirmando(categoria);
    };

    return (
        <>
            <div className="rounded-lg border">
                <div className="border-b bg-muted/50 p-3 text-sm font-medium">
                    Categorías en el costeo
                </div>
                <ul className="divide-y">
                    {conBolsas.map((categoria) => {
                        const activa = categoria.activos > 0;

                        return (
                            <li
                                key={categoria.valor}
                                className="flex flex-wrap items-center justify-between gap-3 p-3 text-sm"
                            >
                                <div className="min-w-0">
                                    <div className="flex items-center gap-2">
                                        <span className="font-medium">
                                            {etiqueta(categoria.valor)}
                                        </span>
                                        <Badge
                                            variant={
                                                activa ? 'secondary' : 'outline'
                                            }
                                        >
                                            {activa
                                                ? `${categoria.activos} en el costeo`
                                                : 'Sin aplicar'}
                                        </Badge>
                                    </div>
                                    <p className="text-muted-foreground">
                                        {categoria.total} bolsa
                                        {categoria.total === 1 ? '' : 's'}{' '}
                                        registrada
                                        {categoria.total === 1 ? '' : 's'}
                                        {categoria.solape !== null &&
                                            ` · sustituye ${categoria.solape}`}
                                    </p>
                                </div>

                                <Button
                                    variant={activa ? 'outline' : 'default'}
                                    size="sm"
                                    disabled={procesando}
                                    onClick={() =>
                                        activa
                                            ? enviar(categoria.valor, false)
                                            : alActivar(categoria)
                                    }
                                >
                                    {activa ? (
                                        <>
                                            <PowerOff className="size-4" />
                                            Desactivar
                                        </>
                                    ) : (
                                        <>
                                            <Power className="size-4" />
                                            Activar
                                        </>
                                    )}
                                </Button>
                            </li>
                        );
                    })}
                </ul>
            </div>

            <Dialog
                open={confirmando !== null}
                onOpenChange={(abierto) => !abierto && setConfirmando(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <AlertTriangle className="size-4 text-amber-600" />
                            Esto se contaría dos veces
                        </DialogTitle>
                        <DialogDescription>
                            {confirmando !== null && (
                                <>
                                    Estos registros todavía tienen{' '}
                                    {confirmando.solape} digitado. Ese valor ya
                                    incluye lo que cubriría la bolsa de{' '}
                                    {etiqueta(confirmando.valor).toLowerCase()}.
                                </>
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    <ul className="max-h-48 space-y-1 overflow-y-auto rounded-md border bg-muted/30 p-3 text-sm">
                        {confirmando?.conflictos.map((conflicto) => (
                            <li
                                key={conflicto.nombre}
                                className="flex justify-between gap-4"
                            >
                                <span className="truncate">
                                    {conflicto.nombre}
                                </span>
                                <span className="text-muted-foreground tabular-nums">
                                    {cop(conflicto.valor)}
                                </span>
                            </li>
                        ))}
                    </ul>

                    <p className="text-sm text-muted-foreground">
                        Al continuar, esos valores dejan de sumarse al costo
                        directo y pasan a entrar por la bolsa. Se conservan tal
                        cual en el catálogo, para poder comparar los dos
                        métodos.
                    </p>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirmando(null)}
                        >
                            Cancelar
                        </Button>
                        <Button
                            disabled={procesando}
                            onClick={() =>
                                confirmando !== null &&
                                enviar(confirmando.valor, true, true)
                            }
                        >
                            Marcar como derivado de CIF y activar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
