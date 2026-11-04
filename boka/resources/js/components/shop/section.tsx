import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { Reveal } from './reveal';

export function Section({
    title,
    kicker,
    href,
    children,
    className,
    action,
}: {
    title: string;
    kicker?: string;
    href?: string;
    children: React.ReactNode;
    className?: string;
    action?: React.ReactNode;
}) {
    const t = useT();

    return (
        <Reveal as="section" className={cn('mx-auto max-w-7xl px-4 py-8', className)}
        >
            <div className="mb-4 flex items-end justify-between gap-4">
                <div>
                    {kicker && <p className="mb-1 text-xs font-bold tracking-[0.16em] text-primary uppercase">{kicker}</p>}
                    <h2 className="text-xl font-extrabold tracking-tight sm:text-2xl">{title}</h2>
                </div>
                {action}
                {href && (
                    <Link href={href} className="flex shrink-0 items-center gap-1 text-sm font-semibold text-primary hover:underline">
                        {t('common.see_all')} <ArrowRight className="size-4" aria-hidden="true" />
                    </Link>
                )}
            </div>
            {children}
        </Reveal>
    );
}
