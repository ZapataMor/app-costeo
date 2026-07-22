import type { FaseCiclo } from '@/types/cirugias';

/** Nombre de cada fase del ciclo, tal como se le habla al usuario. */
export const ETIQUETA_FASE: Record<FaseCiclo, string> = {
    pre: 'Pre-quirúrgica',
    quirurgica: 'Quirúrgica',
    post: 'Post-quirúrgica',
};
