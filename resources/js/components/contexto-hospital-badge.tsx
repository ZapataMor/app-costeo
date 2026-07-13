import { usePage } from '@inertiajs/react';
import { Building2, Globe } from 'lucide-react';
import { Badge } from '@/components/ui/badge';

/**
 * Indica sobre qué datos se está trabajando: el hospital activo, o el
 * consolidado de todos los hospitales (super_admin sin hospital elegido).
 */
export function ContextoHospitalBadge() {
    const { auth, hospital } = usePage().props;

    if (!auth.user) {
        return null;
    }

    if (hospital.activo) {
        return (
            <Badge
                variant="outline"
                className="hidden h-9 max-w-[340px] gap-2 rounded-full border-[#5B687C]/30 px-[15px] text-[12.5px] font-normal text-[#161B2F] sm:flex dark:text-[#D4CDCB]"
            >
                <Building2 className="size-3.5 shrink-0" />
                <span className="truncate">{hospital.activo.nombre}</span>
            </Badge>
        );
    }

    if (auth.user.role === 'super_admin') {
        return (
            <Badge
                variant="outline"
                className="hidden h-9 gap-2 rounded-full border-[#5B687C]/30 px-[15px] text-[12.5px] font-normal text-[#161B2F] sm:flex dark:text-[#D4CDCB]"
            >
                <Globe className="size-3.5" />
                Consolidado — todos los hospitales
            </Badge>
        );
    }

    return null;
}
