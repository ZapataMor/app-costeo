import { Moon, Sun } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { ContextoHospitalBadge } from '@/components/contexto-hospital-badge';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useAppearance } from '@/hooks/use-appearance';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const dark = resolvedAppearance === 'dark';

    return (
        <header className="flex h-[70px] shrink-0 items-center justify-between gap-3 px-4 md:px-[34px]">
            <div className="flex min-w-0 items-center gap-3.5">
                <SidebarTrigger className="size-9 shrink-0 rounded-[9px] border border-[#5B687C]/30 text-[#5B687C] shadow-none hover:border-[#5B687C]/50 hover:bg-transparent hover:text-[#161B2F] dark:border-[#A6AAB2]/25 dark:text-[#A6AAB2] dark:hover:text-[#F3F0ED]" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <div className="flex min-w-0 items-center gap-3">
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    className="size-9 rounded-[9px] border-[#5B687C]/30 bg-transparent text-[#5B687C] shadow-none hover:border-[#5B687C]/50 hover:bg-transparent hover:text-[#161B2F] dark:border-[#A6AAB2]/25 dark:text-[#A6AAB2] dark:hover:text-[#F3F0ED]"
                    aria-label={
                        dark ? 'Activar modo claro' : 'Activar modo oscuro'
                    }
                    title={dark ? 'Activar modo claro' : 'Activar modo oscuro'}
                    onClick={() => updateAppearance(dark ? 'light' : 'dark')}
                >
                    {dark ? (
                        <Sun className="size-[17px]" />
                    ) : (
                        <Moon className="size-[17px]" />
                    )}
                </Button>
                <ContextoHospitalBadge />
            </div>
        </header>
    );
}
