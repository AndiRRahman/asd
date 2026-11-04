import { useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';

/**
 * Muncul bertahap saat digulir (12.3) tanpa pustaka animasi: IntersectionObserver + transform/opacity.
 * HTML dari SSR tetap terlihat; hanya bagian yang masih di bawah layar disembunyikan setelah hidrasi.
 */
export function Reveal({ as: Tag = 'div', delay = 0, className, children }: { as?: 'div' | 'section'; delay?: number; className?: string; children: React.ReactNode }) {
    const ref = useRef<HTMLElement>(null);
    const [state, setState] = useState<'idle' | 'pending' | 'shown'>('idle');

    useEffect(() => {
        const el = ref.current;

        if (!el || window.matchMedia('(prefers-reduced-motion: reduce)').matches || el.getBoundingClientRect().top < window.innerHeight) {
            return;
        }

        setState('pending');
        const observer = new IntersectionObserver(([entry]) => {
            if (entry.isIntersecting) {
                setState('shown');
                observer.disconnect();
            }
        }, { rootMargin: '0px 0px -60px 0px' });
        observer.observe(el);

        return () => observer.disconnect();
    }, []);

    return (
        <Tag
            ref={ref as React.Ref<HTMLDivElement>}
            style={{ transitionDelay: state === 'shown' ? `${delay}ms` : undefined }}
            className={cn('transition-[opacity,transform] duration-300 ease-out', state === 'pending' && 'translate-y-4 opacity-0', className)}
        >
            {children}
        </Tag>
    );
}
