import { useEffect, useRef, useState } from 'react';
import type { AuthLayoutProps } from '@/types';

// Fotografías del muro quirúrgico (public/images/login/). Si un archivo no
// existe, el mosaico correspondiente muestra un panel oscuro degradado.
const image = (name: string) => `/images/login/${name}`;

// Retratos: solo se usan en mosaicos verticales de dos filas.
const VERTICAL_POOL = [
    'med-hero.png',
    'med-2.png',
    'med-3.png',
    'med-6.png',
    'med-13.png',
    'med-16.png',
    'med-17.png',
    'med-18.png',
    'med-19.png',
].map(image);

// Escenas: se recortan en los mosaicos horizontales del diseño original.
const HORIZONTAL_POOL = [
    'med-4.png',
    'med-5.png',
    'med-7.png',
    'med-8.png',
    'med-9.png',
    'med-10.png',
    'med-11.png',
    'med-12.png',
    'med-14.png',
    'med-15.png',
    'med-20.png',
].map(image);

type Orientation = 'v' | 'h';

// Rejilla 4x4 tomada del diseño SICOQ: los retratos ocupan dos filas.
const GRID_LAYOUT: { col: number; row: string; orient: Orientation }[] = [
    { col: 1, row: '1 / span 2', orient: 'v' },
    { col: 1, row: '3 / span 2', orient: 'v' },
    { col: 2, row: '1', orient: 'h' },
    { col: 2, row: '2 / span 2', orient: 'v' },
    { col: 2, row: '4', orient: 'h' },
    { col: 3, row: '1 / span 2', orient: 'v' },
    { col: 3, row: '3', orient: 'h' },
    { col: 3, row: '4', orient: 'h' },
    { col: 4, row: '1', orient: 'h' },
    { col: 4, row: '2 / span 2', orient: 'v' },
    { col: 4, row: '4', orient: 'h' },
];

type Tile = {
    col: number;
    row: string;
    orient: Orientation;
    src: string;
    transform: string;
    transition: string;
};

// Índices de mosaicos del lado visible (oscuro) de la diagonal
const ELIGIBLE_INDICES = GRID_LAYOUT.flatMap((cell, i) =>
    cell.col <= 3 ? [i] : [],
);

function shuffle<T>(items: T[]): T[] {
    const result = items.slice();

    for (let i = result.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [result[i], result[j]] = [result[j], result[i]];
    }

    return result;
}

function buildTiles(): Tile[] {
    const vertical = shuffle(VERTICAL_POOL);
    const horizontal = shuffle(HORIZONTAL_POOL);
    let vi = 0;
    let hi = 0;

    return GRID_LAYOUT.map((cell) => ({
        col: cell.col,
        row: cell.row,
        orient: cell.orient,
        src: cell.orient === 'v' ? vertical[vi++] : horizontal[hi++],
        transform: 'none',
        transition: 'none',
    }));
}

// Paneles de respaldo cuando la fotografía aún no está disponible
const FALLBACK_GRADIENTS = [
    'linear-gradient(155deg, #1a2230 0%, #0e1319 80%)',
    'linear-gradient(200deg, #161b2f 0%, #0e1319 70%)',
    'linear-gradient(130deg, #24303f 0%, #0e1319 90%)',
];

function CollageWall() {
    const [tiles, setTiles] = useState<Tile[]>(buildTiles);
    const [loaded, setLoaded] = useState<Set<string>>(new Set());
    const loadedRef = useRef<Set<string>>(new Set());

    useEffect(() => {
        let alive = true;

        [...VERTICAL_POOL, ...HORIZONTAL_POOL].forEach((src) => {
            const img = new Image();

            img.onload = () => {
                if (alive) {
                    loadedRef.current.add(src);
                    setLoaded(new Set(loadedRef.current));
                }
            };
            img.src = src;
        });

        return () => {
            alive = false;
        };
    }, []);

    useEffect(() => {
        const timeouts: ReturnType<typeof setTimeout>[] = [];

        const updateTile = (index: number, patch: Partial<Tile>) => {
            setTiles((prev) =>
                prev.map((tile, i) =>
                    i === index ? { ...tile, ...patch } : tile,
                ),
            );
        };

        const flipRandom = () => {
            if (loadedRef.current.size === 0) {
                return;
            }

            const index =
                ELIGIBLE_INDICES[
                    Math.floor(Math.random() * ELIGIBLE_INDICES.length)
                ];
            const options: ['rotateX' | 'rotateY', number][] = [
                ['rotateX', 1],
                ['rotateX', -1],
                ['rotateY', 1],
                ['rotateY', -1],
            ];
            const [axis, sign] =
                options[Math.floor(Math.random() * options.length)];

            // 1) girar hasta el canto (la imagen desaparece)
            updateTile(index, {
                transform: `${axis}(${sign * 90}deg)`,
                transition: 'transform .32s cubic-bezier(.4,0,.7,.4)',
            });

            timeouts.push(
                setTimeout(() => {
                    // 2) cambiar por una imagen nueva de la misma orientación
                    //    que no esté ya visible en otro mosaico
                    setTiles((prev) => {
                        const tile = prev[index];
                        const pool =
                            tile.orient === 'v'
                                ? VERTICAL_POOL
                                : HORIZONTAL_POOL;
                        const shown = new Set(
                            prev
                                .filter((_, i) => i !== index)
                                .map((t) => t.src),
                        );
                        const candidates = pool.filter(
                            (src) =>
                                loadedRef.current.has(src) &&
                                !shown.has(src) &&
                                src !== tile.src,
                        );
                        const next = candidates.length
                            ? candidates[
                                  Math.floor(Math.random() * candidates.length)
                              ]
                            : tile.src;

                        return prev.map((t, i) =>
                            i === index
                                ? {
                                      ...t,
                                      src: next,
                                      transform: `${axis}(${-sign * 90}deg)`,
                                      transition: 'none',
                                  }
                                : t,
                        );
                    });

                    // 3) asentar en plano (la imagen nueva aparece girando)
                    timeouts.push(
                        setTimeout(() => {
                            updateTile(index, {
                                transform: `${axis}(0deg)`,
                                transition:
                                    'transform .34s cubic-bezier(.3,.6,.3,1)',
                            });
                        }, 30),
                    );
                }, 330),
            );
        };

        const interval = setInterval(flipRandom, 1150);

        return () => {
            clearInterval(interval);
            timeouts.forEach(clearTimeout);
        };
    }, []);

    return (
        <div className="absolute inset-0 grid grid-cols-4 grid-rows-4 gap-1.5 bg-[#0E1319]">
            {tiles.map((tile, index) => (
                <div
                    key={index}
                    className="overflow-hidden bg-[#0E1319]"
                    style={{
                        gridColumn: tile.col,
                        gridRow: tile.row,
                        perspective: '700px',
                    }}
                >
                    <div
                        className="h-full w-full"
                        style={{
                            backgroundImage: loaded.has(tile.src)
                                ? `url("${tile.src}")`
                                : FALLBACK_GRADIENTS[
                                      index % FALLBACK_GRADIENTS.length
                                  ],
                            backgroundSize: 'cover',
                            backgroundPosition:
                                tile.orient === 'v'
                                    ? 'center 22%'
                                    : 'center 46%',
                            backgroundRepeat: 'no-repeat',
                            filter: 'saturate(.92) contrast(1.03) brightness(.9)',
                            transform: tile.transform,
                            transition: tile.transition,
                            backfaceVisibility: 'hidden',
                        }}
                    />
                </div>
            ))}
        </div>
    );
}

export default function AuthSicoqLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    // El diseño SICOQ del acceso es de apariencia única (greige + quirófano
    // oscuro): se desactiva el modo oscuro mientras esta pantalla esté activa.
    useEffect(() => {
        const root = document.documentElement;
        const hadDark = root.classList.contains('dark');

        root.classList.remove('dark');

        return () => {
            if (hadDark) {
                root.classList.add('dark');
            }
        };
    }, []);

    return (
        <div className="relative min-h-svh overflow-hidden bg-[#D4CDCB]">
            {/* Lado izquierdo: collage médico recortado en diagonal */}
            <div
                className="absolute inset-0 hidden bg-[#0E1319] lg:block"
                style={{
                    clipPath: 'polygon(0 0, 46% 0, 62% 100%, 0 100%)',
                }}
            >
                <CollageWall />

                {/* Velo de cohesión azul noche */}
                <div
                    className="pointer-events-none absolute inset-0"
                    style={{
                        background:
                            'linear-gradient(115deg, rgba(22,27,47,.06) 0%, rgba(22,27,47,.34) 100%), linear-gradient(0deg, rgba(14,19,25,.34) 0%, rgba(14,19,25,0) 26%)',
                    }}
                />

                {/* Textura fina de retícula */}
                <div
                    className="pointer-events-none absolute inset-0"
                    style={{
                        backgroundImage:
                            'repeating-linear-gradient(0deg, transparent 0 3px, rgba(255,255,255,.018) 3px 4px), repeating-linear-gradient(90deg, transparent 0 3px, rgba(255,255,255,.018) 3px 4px)',
                    }}
                />

                {/* Micro-rótulo editorial */}
                <div className="pointer-events-none absolute bottom-[34px] left-10">
                    <div className="mb-3 h-px w-[34px] bg-[#4C837C]" />
                    <div className="text-[10px] font-medium tracking-[3.5px] text-[#8D8F8E] uppercase">
                        Costeo Quirúrgico · TDABC
                    </div>
                    <div className="mt-1 text-[10px] tracking-[3.5px] text-[#D4CDCB]/55 uppercase">
                        Gestión hospitalaria · Colombia
                    </div>
                </div>
            </div>

            {/* Filo diagonal (acento azul pizarra) */}
            <svg
                viewBox="0 0 100 100"
                preserveAspectRatio="none"
                className="pointer-events-none absolute inset-0 hidden h-full w-full lg:block"
            >
                <line
                    x1="46"
                    y1="0"
                    x2="62"
                    y2="100"
                    stroke="#5B687C"
                    strokeWidth="1.4"
                    opacity="0.85"
                    vectorEffect="non-scaling-stroke"
                />
                <line
                    x1="46.5"
                    y1="0"
                    x2="62.5"
                    y2="100"
                    stroke="rgba(212,205,203,.4)"
                    strokeWidth="1"
                    opacity="0.6"
                    vectorEffect="non-scaling-stroke"
                />
            </svg>

            {/* Card de vidrio (pisa la diagonal; centrada en pantallas pequeñas) */}
            <div className="sicoq-login-position relative z-[5] flex min-h-svh items-center justify-center p-6 lg:p-3">
                <div
                    className="w-full max-w-[577px] animate-[sicoq-card-in_.7s_cubic-bezier(.22,1,.36,1)_both] rounded-2xl border border-white/50 px-7 py-8 lg:flex lg:min-h-[min(694px,calc(100svh-24px))] lg:flex-col lg:justify-center lg:px-12 lg:py-10"
                    style={{
                        background:
                            'linear-gradient(155deg, rgba(255,255,255,.34) 0%, rgba(212,205,203,.20) 100%)',
                        backdropFilter: 'blur(26px) saturate(1.15)',
                        WebkitBackdropFilter: 'blur(26px) saturate(1.15)',
                        boxShadow:
                            '0 32px 64px -18px rgba(14,19,25,.5), 0 8px 24px -12px rgba(22,27,47,.35), inset 0 1px 1px rgba(255,255,255,.6)',
                    }}
                >
                    {/* Identidad SICOQ */}
                    <div className="mb-1 flex items-center gap-[18px]">
                        <div
                            className="flex h-[53px] w-[53px] shrink-0 items-center justify-center rounded-xl bg-[#161B2F]"
                            style={{
                                boxShadow:
                                    'inset 0 1px 1px rgba(255,255,255,.12)',
                            }}
                        >
                            <svg
                                width="26"
                                height="26"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="#D4CDCB"
                                strokeWidth="1.6"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                            >
                                <path d="M3 12h3l2 5 3-11 2 8 1.5-4H21" />
                            </svg>
                        </div>
                        <div className="font-serif text-[37px] leading-none font-bold tracking-[1px] text-[#161B2F]">
                            SICOQ
                        </div>
                    </div>
                    <div className="mt-[9px] mb-[25px] text-[13px] font-semibold tracking-[3.4px] text-[#5B687C] uppercase">
                        Sistema de Costeo Quirúrgico · TDABC
                    </div>

                    <div
                        className="mb-[25px] h-px"
                        style={{
                            background:
                                'linear-gradient(90deg, rgba(91,104,124,.3), rgba(91,104,124,0))',
                        }}
                    />

                    {title && (
                        <h1 className="mb-1 font-serif text-[35px] leading-tight font-semibold text-[#161B2F]">
                            {title}
                        </h1>
                    )}
                    {description && (
                        <p className="mb-8 text-[17px] text-[#8D8F8E]">
                            {description}
                        </p>
                    )}

                    {children}
                </div>
            </div>

            {/* Pie */}
            <div className="sicoq-login-footer absolute right-[34px] bottom-[22px] z-[6] text-[11px] text-[#8D8F8E]">
                © 2026 · Hecho para la gestión hospitalaria en Colombia
            </div>
        </div>
    );
}
