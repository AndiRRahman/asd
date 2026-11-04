import { Head, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/** Admin mengatur nama dan password sendiri dari tautan undangan (bagian 2). Panel hanya berbahasa Indonesia. */
export default function AcceptInvitation({ email, role, action }: { email: string; role: string; action: string }) {
    const form = useForm({ name: '', password: '', password_confirmation: '' });

    return (
        <>
            <Head title="Terima undangan Admin" />
            <form onSubmit={(e) => { e.preventDefault(); form.post(action); }} className="grid gap-5">
                <p className="rounded-lg bg-muted p-3 text-sm">Anda diundang sebagai <b>{role.replace('_', ' ')}</b> dengan email <b>{email}</b>. Setelah masuk, aktifkan 2FA sebelum membuka panel.</p>
                <div className="grid gap-2">
                    <Label htmlFor="name">Nama lengkap</Label>
                    <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required autoFocus />
                    <InputError message={form.errors.name} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="password">Password</Label>
                    <PasswordInput id="password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} required autoComplete="new-password" />
                    <InputError message={form.errors.password} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="password_confirmation">Ulangi password</Label>
                    <PasswordInput id="password_confirmation" value={form.data.password_confirmation} onChange={(e) => form.setData('password_confirmation', e.target.value)} required autoComplete="new-password" />
                </div>
                <Button type="submit" size="lg" disabled={form.processing}>Simpan dan masuk</Button>
            </form>
        </>
    );
}

AcceptInvitation.layout = {
    title: 'Terima undangan Admin Boka',
    description: 'Atur nama dan password Anda sendiri.',
};
