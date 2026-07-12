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
            <Badge variant="secondary" className="max-w-72 gap-1.5">
                <Building2 className="size-3.5 shrink-0" />
                <span className="truncate">{hospital.activo.nombre}</span>
            </Badge>
        );
    }

    if (auth.user.role === 'super_admin') {
        return (
            <Badge variant="outline" className="gap-1.5">
                <Globe className="size-3.5" />
                Consolidado — todos los hospitales
            </Badge>
        );
    }

    return null;
}
