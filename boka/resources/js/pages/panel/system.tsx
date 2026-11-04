import { router } from '@inertiajs/react';
import { Card, PanelPage, Pill, Stat } from '@/components/panel/ui';
import { Button } from '@/components/ui/button';

type Props = { maintenance: boolean; queue: { pending: number; failed: number }; versions: { php: string; laravel: string } };

export default function System({ maintenance, queue, versions }: Props) {
    return (
        <PanelPage title="Sistem" description="Mode pemeliharaan diaktifkan lewat server (php artisan down) agar tidak terkunci dari panel.">
            <div className="grid gap-4 sm:grid-cols-3">
                <Stat label="Antrean menunggu" value={queue.pending} tone={queue.pending > 50 ? 'warn' : 'default'} />
                <Stat label="Tugas gagal" value={queue.failed} tone={queue.failed ? 'warn' : 'default'} />
                <Stat label="Mode pemeliharaan" value={maintenance ? 'Aktif' : 'Mati'} />
            </div>
            <Card title="Versi">
                <p className="flex gap-2 text-sm"><Pill>PHP {versions.php}</Pill><Pill>Laravel {versions.laravel}</Pill></p>
            </Card>
            <Card title="Cache">
                <Button onClick={() => router.post('/panel/sistem/cache')}>Bersihkan cache</Button>
            </Card>
        </PanelPage>
    );
}
