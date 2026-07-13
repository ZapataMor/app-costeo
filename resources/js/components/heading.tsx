export default function Heading({
    title,
    description,
    variant = 'default',
}: {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
}) {
    return (
        <header className={variant === 'small' ? '' : 'mb-6 space-y-1'}>
            <h2
                className={
                    variant === 'small'
                        ? 'mb-1 text-lg font-semibold'
                        : 'text-[28px] leading-tight font-semibold'
                }
            >
                {title}
            </h2>
            {description && (
                <p className="text-sm text-muted-foreground">{description}</p>
            )}
        </header>
    );
}
