import { Head, Link } from '@inertiajs/react';
import { Info, Pencil } from 'lucide-react';
import ConceptoCostoIndirectoController from '@/actions/App/Http/Controllers/Parametros/ConceptoCostoIndirectoController';
import { FiltrosListado } from '@/components/filtros-listado';
import { ActivacionCategoriasCif } from '@/components/parametros/activacion-categorias-cif';
import { ConfirmarEliminacion } from '@/components/parametros/confirmar-eliminacion';
import { EncabezadoListado } from '@/components/parametros/encabezado-listado';
import { ConceptoCostoIndirectoForm } from '@/components/parametros/forms/concepto-costo-indirecto-form';
import { ModalFormulario } from '@/components/parametros/modal-formulario';
import { NivelConfiabilidadBadge } from '@/components/parametros/nivel-confiabilidad-badge';
import { Paginacion } from '@/components/parametros/paginacion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { opcionesDesdeValores } from '@/lib/filtros';
import { cop } from '@/lib/formato';
import type {
    BaseAsignacionOpcion,
    CapacidadesCif,
    CategoriaCifOpcion,
    ConceptoCostoIndirectoParam,
    Paginado,
} from '@/types/parametros';

const etiquetaCategoria = (valor: string) =>
    valor.charAt(0).toUpperCase() + valor.slice(1).replace(/_/g, ' ');

const fecha = (valor: string | null) =>
    valor === null
        ? 'indefinido'
        : new Date(`${valor.slice(0, 10)}T00:00:00`).toLocaleDateString(
              'es-CO',
          );

export default function CostosIndirectosIndex({
    conceptos,
    categorias,
    basesAsignacion,
    nivelesConfiabilidad,
    capacidades,
    filtros,
}: {
    conceptos: Paginado<ConceptoCostoIndirectoParam>;
    categorias: CategoriaCifOpcion[];
    basesAsignacion: BaseAsignacionOpcion[];
    nivelesConfiabilidad: string[];
    capacidades: CapacidadesCif;
    filtros: Record<string, string>;
}) {
    return (
        <>
            <Head title="Costos indirectos" />
            <div className="flex flex-col gap-4 p-4">
                <EncabezadoListado
                    hrefAtras="/parametros"
                    titulo="Costos indirectos"
                    descripcion="Bolsas de costo indirecto (CIF) con su inductor y vigencia."
                    accion={
                        <ModalFormulario
                            titulo="Nuevo concepto de costo indirecto"
                            textoBoton="Nuevo concepto"
                        >
                            {(cerrar) => (
                                <ConceptoCostoIndirectoForm
                                    action={ConceptoCostoIndirectoController.store.form()}
                                    categorias={categorias}
                                    basesAsignacion={basesAsignacion}
                                    nivelesConfiabilidad={nivelesConfiabilidad}
                                    capacidades={capacidades}
                                    onSuccess={cerrar}
                                />
                            )}
                        </ModalFormulario>
                    }
                />

                <div className="flex gap-3 rounded-lg border bg-muted/30 p-4 text-sm">
                    <Info className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                    <p className="text-muted-foreground">
                        Los conceptos se capturan <strong>inactivos</strong>. Al
                        activar una categoría, sus bolsas se reparten entre las
                        cirugías por su inductor y el componente equivalente
                        deja de sumarse al costo directo, para no contarlo dos
                        veces. Mientras haya bolsas activas, el{' '}
                        <strong>factor indirecto del hospital se ignora</strong>
                        : son dos métodos alternativos. Las cirugías ya
                        registradas conservan el costeo de su día.
                    </p>
                </div>

                <ActivacionCategoriasCif categorias={categorias} />

                <FiltrosListado
                    url="/parametros/costos-indirectos"
                    valores={filtros}
                    placeholderBusqueda="Nombre del concepto…"
                    filtros={[
                        {
                            clave: 'categoria',
                            etiqueta: 'Categoría',
                            opciones: categorias.map((c) => ({
                                valor: c.valor,
                                etiqueta: etiquetaCategoria(c.valor),
                            })),
                        },
                        {
                            clave: 'base',
                            etiqueta: 'Base',
                            opciones: basesAsignacion.map((b) => ({
                                valor: b.valor,
                                etiqueta: b.etiqueta,
                            })),
                        },
                        {
                            clave: 'activo',
                            etiqueta: 'Estado',
                            opciones: [
                                { valor: '1', etiqueta: 'Activos' },
                                { valor: '0', etiqueta: 'Inactivos' },
                            ],
                        },
                    ]}
                />

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50 text-left text-muted-foreground">
                                <th className="p-3 font-medium">Concepto</th>
                                <th className="p-3 font-medium">Categoría</th>
                                <th className="p-3 font-medium">Base</th>
                                <th className="p-3 text-right font-medium">
                                    Monto / %
                                </th>
                                <th className="p-3 font-medium">Vigencia</th>
                                <th className="p-3 font-medium">
                                    Confiabilidad
                                </th>
                                <th className="p-3 font-medium">Estado</th>
                                <th className="p-3 text-right font-medium">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {conceptos.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={8}
                                        className="p-6 text-center text-muted-foreground"
                                    >
                                        No hay conceptos de costo indirecto
                                        registrados.
                                    </td>
                                </tr>
                            )}
                            {conceptos.data.map((concepto) => (
                                <tr
                                    key={concepto.id}
                                    className="border-b last:border-0"
                                >
                                    <td className="p-3">{concepto.nombre}</td>
                                    <td className="p-3 text-muted-foreground">
                                        {etiquetaCategoria(concepto.categoria)}
                                    </td>
                                    <td className="p-3 text-muted-foreground">
                                        {basesAsignacion.find(
                                            (b) =>
                                                b.valor ===
                                                concepto.base_asignacion,
                                        )?.etiqueta ?? concepto.base_asignacion}
                                    </td>
                                    <td className="p-3 text-right tabular-nums">
                                        {concepto.monto_mensual !== null
                                            ? `${cop(Number(concepto.monto_mensual))} /mes`
                                            : `${(Number(concepto.porcentaje) * 100).toFixed(2)} %`}
                                    </td>
                                    <td className="p-3 whitespace-nowrap text-muted-foreground">
                                        {fecha(concepto.vigente_desde)} →{' '}
                                        {fecha(concepto.vigente_hasta)}
                                    </td>
                                    <td className="p-3">
                                        <NivelConfiabilidadBadge
                                            nivel={concepto.nivel_confiabilidad}
                                        />
                                    </td>
                                    <td className="p-3">
                                        <Badge
                                            variant={
                                                concepto.activo
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {concepto.activo
                                                ? 'Activo'
                                                : 'Inactivo'}
                                        </Badge>
                                    </td>
                                    <td className="p-3 text-right whitespace-nowrap">
                                        <Button
                                            asChild
                                            variant="ghost"
                                            size="icon"
                                            aria-label="Editar"
                                        >
                                            <Link
                                                href={ConceptoCostoIndirectoController.edit.url(
                                                    concepto.id,
                                                )}
                                                prefetch
                                            >
                                                <Pencil className="size-4" />
                                            </Link>
                                        </Button>
                                        <ConfirmarEliminacion
                                            url={ConceptoCostoIndirectoController.destroy.url(
                                                concepto.id,
                                            )}
                                            descripcion={`Se eliminará el concepto «${concepto.nombre}». Esta acción no se puede deshacer.`}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Paginacion
                    links={conceptos.links}
                    total={conceptos.total}
                    from={conceptos.from}
                    to={conceptos.to}
                />
            </div>
        </>
    );
}

CostosIndirectosIndex.layout = {
    breadcrumbs: [
        { title: 'Costos indirectos', href: '/parametros/costos-indirectos' },
    ],
};
