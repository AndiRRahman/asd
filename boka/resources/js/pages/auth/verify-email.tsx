import { Form, Head } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/lib/i18n';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

/** Aktivasi lewat tautan email bertanda tangan; checkout baru terbuka setelah email terverifikasi (3.2). */
export default function VerifyEmail({ status }: { status?: string }) {
    const t = useT();

    return (
        <>
            <Head title={t('auth.verify_title')} />

            {status === 'verification-link-sent' && (
                <div className="rounded-lg bg-leaf-soft p-3 text-center text-sm font-medium text-leaf">{t('auth.verify_sent')}</div>
            )}

            <Form {...send.form()} className="space-y-6 text-center">
                {({ processing }) => (
                    <>
                        <Button disabled={processing} variant="secondary">
                            {processing && <Spinner />}
                            {t('auth.verify_resend')}
                        </Button>
                        <TextLink href={logout()} className="mx-auto block text-sm">
                            {t('nav.logout')}
                        </TextLink>
                    </>
                )}
            </Form>
        </>
    );
}

VerifyEmail.layout = {
    title: 'auth.verify_title',
    description: 'auth.verify_description',
};
