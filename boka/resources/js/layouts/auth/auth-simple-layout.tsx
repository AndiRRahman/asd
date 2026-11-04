import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { BokaLogo, Walasuji } from '@/components/shop/brand';
import { useFlashToast } from '@/hooks/use-flash-toast';
import { useT } from '@/lib/i18n';
import type { AuthLayoutProps } from '@/types';

/** Judul dan deskripsi berupa kunci terjemahan; teks Inggris lama tetap tampil apa adanya. */
export default function AuthSimpleLayout({ children, title, description }: AuthLayoutProps) {
    const t = useT();
    useFlashToast();

    return (
        <div className="relative flex min-h-svh flex-col items-center justify-center gap-6 overflow-hidden bg-background p-6 md:p-10">
            <Walasuji className="text-primary opacity-[0.05]" id="walasuji-auth" />
            <Link href="/" className="relative flex min-h-11 w-full max-w-md items-center gap-2 self-center text-sm font-semibold text-muted-foreground hover:text-primary">
                <ArrowLeft className="size-4" aria-hidden="true" /> {t('auth.back_home')}
            </Link>
            <div className="relative w-full max-w-md rounded-2xl border bg-card p-6 shadow-sm sm:p-8">
                <div className="flex flex-col gap-6">
                    <div className="flex flex-col items-center gap-4">
                        <Link href="/" aria-label="Boka">
                            <BokaLogo />
                        </Link>
                        <div className="space-y-1.5 text-center">
                            <h1 className="text-xl font-extrabold">{title ? t(title) : ''}</h1>
                            <p className="text-center text-sm text-muted-foreground">{description ? t(description) : ''}</p>
                        </div>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
