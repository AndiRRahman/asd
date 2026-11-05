import { cn } from '@/lib/utils';

/**
 * Simbol BOKA (Konsep 3): huruf b dari batang hijau (akar pertanian), lingkaran emas (hasil panen
 * yang berputar di pasar), dan belah ketupat marun (inti budaya Bugis). Warna lewat token sehingga
 * otomatis berganti di mode gelap. Di 24 px ke bawah, belah ketupat dihilangkan.
 */
export function BokaMark({ className, small = false }: { className?: string; small?: boolean }) {
    return (
        <svg viewBox="0 0 120 120" aria-hidden="true" className={cn('size-9', className)}>
            <circle cx="68" cy="72" r="27" fill="none" strokeWidth="20" className="stroke-logo-ring" />
            <rect x="22" y="12" width="22" height="98" rx="11" className="fill-logo-stem" />
            {!small && <polygon points="68,60 80,72 68,84 56,72" className="fill-logo-diamond" />}
        </svg>
    );
}

export function BokaLogo({ className, compact = false }: { className?: string; compact?: boolean }) {
    return (
        <span className={cn('inline-flex items-center gap-2', className)}>
            <BokaMark />
            <span className="flex flex-col leading-none">
                <span className="flex items-end gap-0.5 text-[1.375rem] font-extrabold tracking-[-0.05em] text-foreground lowercase">
                    tejaseku
                    <svg viewBox="0 0 24 24" aria-hidden="true" className="mb-1 size-2 fill-logo-ring">
                        <polygon points="12,1 23,12 12,23 1,12" />
                    </svg>
                </span>
                {!compact && <span className="mt-0.5 text-[9px] font-bold tracking-[0.32em] text-muted-foreground uppercase">tejaseku</span>}
            </span>
        </span>
    );
}

/**
 * Motif walasuji: anyaman bambu belah ketupat. Dipakai tipis di latar hero dan pemisah bagian (12.1).
 * Warna mengikuti currentColor sehingga otomatis menyesuaikan mode gelap.
 */
export function Walasuji({ className, id = 'walasuji' }: { className?: string; id?: string }) {
    return (
        <svg aria-hidden="true" className={cn('pointer-events-none absolute inset-0 size-full', className)}>
            <defs>
                <pattern id={id} width="44" height="44" patternUnits="userSpaceOnUse">
                    <path d="M22 1 43 22 22 43 1 22Z" fill="none" stroke="currentColor" strokeWidth="1.2" />
                    <path d="M22 11 33 22 22 33 11 22Z" fill="none" stroke="currentColor" strokeWidth="1.2" />
                    <circle cx="22" cy="22" r="1.6" fill="currentColor" />
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill={`url(#${id})`} />
        </svg>
    );
}

/** Pemisah bagian bermotif belah ketupat kecil. */
export function MotifDivider({ className }: { className?: string }) {
    return (
        <div aria-hidden="true" className={cn('flex items-center gap-3 text-primary/40', className)}>
            <span className="h-px flex-1 bg-border" />
            {[0, 1, 2].map((i) => (
                <span key={i} className={cn('size-2 rotate-45 border border-current', i === 1 && 'bg-gold border-gold')} />
            ))}
            <span className="h-px flex-1 bg-border" />
        </div>
    );
}
