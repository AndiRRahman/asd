import { Head } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';
import { logout } from '@/routes';

export default function VerifyEmail() {
    const t = useT();

    return (
        <>
            <Head title={t('auth.verify_title')} />
            <div className="space-y-6 text-center">
                <p className="text-sm text-muted-foreground">Verifikasi email tidak lagi diperlukan. Anda dapat langsung menggunakan akun Anda.</p>
                <TextLink href={logout()} className="mx-auto block text-sm">
                    {t('nav.logout')}
                </TextLink>
            </div>
        </>
    );
}

VerifyEmail.layout = {
    title: 'auth.verify_title',
    description: 'auth.verify_description',
};
