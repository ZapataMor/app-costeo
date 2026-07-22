import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-9 shrink-0 items-center justify-center rounded-[9px] bg-[#161B2F] text-[#D4CDCB] shadow-[inset_0_1px_1px_rgba(255,255,255,.12)]">
                <AppLogoIcon className="size-[18px]" />
            </div>
            <div className="ml-0.5 min-w-0 flex-1 group-data-[collapsible=icon]:hidden">
                <span className="block truncate font-serif text-xl leading-none font-bold text-[#D4CDCB]">
                    SICOPH
                </span>
                <span className="mt-1 block truncate text-[8px] font-semibold tracking-[2.4px] text-[#5B687C] uppercase">
                    Costeo de procedimientos
                </span>
            </div>
        </>
    );
}
