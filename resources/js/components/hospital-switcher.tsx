import { router, usePage } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import HospitalActivoController from '@/actions/App/Http/Controllers/HospitalActivoController';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

const TODOS = 'todos';

/**
 * Selector de hospital del super_admin: fija el hospital activo de la
 * sesión o vuelve a la vista consolidada de todos los hospitales.
 */
export function HospitalSwitcher() {
    const { auth, hospital } = usePage().props;

    if (auth.user?.role !== 'super_admin') {
        return null;
    }

    const cambiarHospital = (valor: string) => {
        router.post(
            HospitalActivoController.store.url(),
            { hospital_id: valor === TODOS ? null : Number(valor) },
            { preserveScroll: true, preserveState: false },
        );
    };

    return (
        <div className="px-1 group-data-[collapsible=icon]:hidden">
            <Select
                value={hospital.activo ? String(hospital.activo.id) : TODOS}
                onValueChange={cambiarHospital}
            >
                <SelectTrigger
                    aria-label="Hospital activo"
                    className="w-full border-white/10 bg-white/[.035] text-[#D4CDCB] shadow-none hover:bg-white/[.06]"
                >
                    <Building2 className="size-4 shrink-0 text-[#8D8F8E]" />
                    <SelectValue placeholder="Seleccione hospital" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={TODOS}>Todos los hospitales</SelectItem>
                    {hospital.disponibles.map((h) => (
                        <SelectItem key={h.id} value={String(h.id)}>
                            {h.nombre}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
