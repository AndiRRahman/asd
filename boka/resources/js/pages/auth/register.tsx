import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/lib/i18n';
import { login } from '@/routes';
import { store } from '@/routes/register';

type Props = {
    passwordRules: string;
};

/** Registrasi: nama, email, nomor WhatsApp, password, dan persetujuan kebijakan privasi (3.2, UU PDP). */
export default function Register({ passwordRules }: Props) {
    const t = useT();

    return (
        <>
            <Head title={t('auth.register_title')} />
            <Form {...store.form()} resetOnSuccess={['password', 'password_confirmation']} disableWhileProcessing className="flex flex-col gap-6">
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-5">
                            {/* Honeypot: tidak terlihat oleh manusia, hanya bot yang mengisinya. */}
                            <input type="text" name="website" tabIndex={-1} autoComplete="off" aria-hidden="true" className="absolute -left-[9999px] h-0 w-0 opacity-0" />
                            <div className="grid gap-2">
                                <Label htmlFor="name">{t('auth.name')}</Label>
                                <Input id="name" type="text" required autoFocus tabIndex={1} autoComplete="name" name="name" />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="email">{t('auth.email')}</Label>
                                <Input id="email" type="email" required tabIndex={2} autoComplete="email" name="email" placeholder="nama@email.com" />
                                <InputError message={errors.email} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="phone">{t('auth.whatsapp')}</Label>
                                <Input id="phone" type="tel" inputMode="tel" required tabIndex={3} autoComplete="tel" name="phone" placeholder="08xxxxxxxxxx" />
                                <InputError message={errors.phone} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="password">{t('auth.password')}</Label>
                                <PasswordInput id="password" required tabIndex={4} autoComplete="new-password" name="password" passwordrules={passwordRules} />
                                <InputError message={errors.password} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">{t('auth.password_confirm')}</Label>
                                <PasswordInput id="password_confirmation" required tabIndex={5} autoComplete="new-password" name="password_confirmation" passwordrules={passwordRules} />
                                <InputError message={errors.password_confirmation} />
                            </div>
                            <label className="flex items-start gap-3 text-sm">
                                <input type="checkbox" name="privacy" value="1" required tabIndex={6} className="mt-0.5 size-5 shrink-0 accent-[var(--primary)]" />
                                <span>
                                    {t('auth.privacy_consent')}{' '}
                                    <Link href="/halaman/kebijakan-privasi" className="font-semibold text-primary underline" target="_blank">{t('footer.privacy')}</Link>
                                </span>
                            </label>
                            <InputError message={errors.privacy} />
                            <Button type="submit" size="lg" className="w-full" tabIndex={7} data-test="register-user-button">
                                {processing && <Spinner />}
                                {t('auth.create_account')}
                            </Button>
                        </div>
                        <div className="text-center text-sm text-muted-foreground">
                            {t('auth.have_account')}{' '}
                            <TextLink href={login()} tabIndex={8}>{t('nav.login')}</TextLink>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

Register.layout = {
    title: 'auth.register_title',
    description: 'auth.register_description',
};
