import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import HospitalConfiguracionController from '@/actions/App/Http/Controllers/Parametros/HospitalConfiguracionController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { HospitalConfig } from '@/types/parametros';

export default function HospitalConfiguracion({
    configuracion: hospital,
    minutosDisponiblesMes,
    bolsasActivas,
}: {
    configuracion: HospitalConfig;
    minutosDisponiblesMes: number;
    bolsasActivas: number;
}) {
    return (
        <>
            <Head title="Configuración del hospital" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-start gap-2">
                    <Button
                        asChild
                        variant="ghost"
                        size="icon"
                        aria-label="Volver"
                    >
                        <Link href="/parametros" prefetch>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <Heading
                        title="Configuración del hospital"
                        description={`${hospital.nombre} · NIT ${hospital.nit}`}
                    />
                </div>

                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle className="text-base">
                            Capacidad TDABC
                        </CardTitle>
                        <CardDescription>
                            Con la configuración actual el hospital dispone de{' '}
                            <strong>
                                {minutosDisponiblesMes.toLocaleString('es-CO')}{' '}
                                minutos/mes
                            </strong>{' '}
                            ({hospital.horas_dia} h/día × {hospital.dias_mes}{' '}
                            días/mes × {hospital.minutos_efectivos_hora} min
                            efectivos/hora), denominador del costo por minuto de
                            cada recurso.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...HospitalConfiguracionController.update.form()}
                            options={{ preserveScroll: true }}
                            className="space-y-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                        <div className="grid gap-2">
                                            <Label htmlFor="horas_dia">
                                                Horas por día
                                            </Label>
                                            <Input
                                                id="horas_dia"
                                                name="horas_dia"
                                                type="number"
                                                min="1"
                                                max="24"
                                                defaultValue={
                                                    hospital.horas_dia
                                                }
                                                required
                                            />
                                            <InputError
                                                message={errors.horas_dia}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="dias_mes">
                                                Días por mes
                                            </Label>
                                            <Input
                                                id="dias_mes"
                                                name="dias_mes"
                                                type="number"
                                                min="1"
                                                max="31"
                                                defaultValue={hospital.dias_mes}
                                                required
                                            />
                                            <InputError
                                                message={errors.dias_mes}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="minutos_efectivos_hora">
                                                Minutos efectivos por hora
                                            </Label>
                                            <Input
                                                id="minutos_efectivos_hora"
                                                name="minutos_efectivos_hora"
                                                type="number"
                                                min="1"
                                                max="60"
                                                defaultValue={
                                                    hospital.minutos_efectivos_hora
                                                }
                                                required
                                            />
                                            <InputError
                                                message={
                                                    errors.minutos_efectivos_hora
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="factor_indirecto">
                                                Factor indirecto (0–1)
                                            </Label>
                                            <Input
                                                id="factor_indirecto"
                                                name="factor_indirecto"
                                                type="number"
                                                step="0.0001"
                                                min="0"
                                                max="1"
                                                defaultValue={
                                                    hospital.factor_indirecto
                                                }
                                                required
                                            />
                                            <InputError
                                                message={
                                                    errors.factor_indirecto
                                                }
                                            />
                                        </div>
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        Los{' '}
                                        <strong>
                                            minutos efectivos por hora
                                        </strong>{' '}
                                        son la parte productiva de cada hora (60
                                        = hora completa; 40 descuenta
                                        alistamiento, aseo y tiempos muertos).
                                        Cambiarlos{' '}
                                        <strong>
                                            altera todas las tarifas por minuto
                                            futuras
                                        </strong>
                                        : bajar de 60 a 40 reduce la capacidad
                                        un 33 % y encarece cada minuto un 50 %.
                                        Las cirugías ya registradas conservan la
                                        capacidad congelada en su costeo; los
                                        indicadores de utilización sí se
                                        recalculan con el valor vigente.
                                    </p>
                                    {bolsasActivas > 0 ? (
                                        <p className="text-xs text-amber-600">
                                            Este hospital tiene{' '}
                                            <strong>
                                                {bolsasActivas} bolsa
                                                {bolsasActivas === 1
                                                    ? ''
                                                    : 's'}{' '}
                                                de costo indirecto activa
                                                {bolsasActivas === 1 ? '' : 's'}
                                            </strong>
                                            , así que el factor indirecto{' '}
                                            <strong>no se aplica</strong> a las
                                            cirugías nuevas: el indirecto se
                                            reparte por el inductor de cada
                                            bolsa. El valor se conserva para las
                                            cirugías costeadas antes de
                                            activarlas.
                                        </p>
                                    ) : (
                                        <p className="text-xs text-muted-foreground">
                                            El factor indirecto se aplica sobre
                                            el costo directo de cada cirugía (p.
                                            ej. 0.12 = 12 %). Usa 0 solo si los
                                            indirectos ya están asignados dentro
                                            de los recursos; en cero y sin
                                            bolsas activas,{' '}
                                            <strong>
                                                las cirugías se costean sin
                                                ningún costo indirecto
                                            </strong>
                                            .
                                        </p>
                                    )}
                                    <Button disabled={processing}>
                                        Guardar configuración
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

HospitalConfiguracion.layout = {
    breadcrumbs: [
        { title: 'Configuración del hospital', href: '/parametros/hospital' },
    ],
};
