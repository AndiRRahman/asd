import { Landmark, ShieldAlert } from 'lucide-react';
import { Seo } from '@/components/shop/seo';
import { useT } from '@/lib/i18n';

/** Halaman publik rekening resmi (11.1 T5). */
export default function OfficialAccounts({ accounts }: { accounts: { bank_name: string; account_number: string; account_holder: string }[] }) {
    const t = useT();

    return (
        <div className="mx-auto max-w-3xl space-y-6 px-4 py-10">
            <Seo title={t('footer.official_accounts')} path="/rekening-resmi" />
            <h1 className="text-3xl font-extrabold tracking-tight">{t('footer.official_accounts')}</h1>
            <p className="flex items-start gap-3 rounded-2xl border-2 border-gold bg-gold-soft p-5">
                <ShieldAlert className="mt-0.5 size-6 shrink-0" /> <span>{t('accounts.warning')}</span>
            </p>
            <ul className="space-y-3">
                {accounts.map((a) => (
                    <li key={a.account_number} className="flex items-center gap-4 rounded-2xl border bg-card p-5">
                        <span className="flex size-12 items-center justify-center rounded-xl bg-primary-soft"><Landmark className="size-6 text-primary" /></span>
                        <span>
                            <span className="block text-sm text-muted-foreground">{a.bank_name}</span>
                            <span className="price block text-xl font-extrabold">{a.account_number}</span>
                            <span className="block text-sm">a.n. {a.account_holder}</span>
                        </span>
                    </li>
                ))}
            </ul>
            <p className="text-sm text-muted-foreground">{t('accounts.note')}</p>
        </div>
    );
}
