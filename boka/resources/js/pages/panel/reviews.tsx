import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Stars } from '@/components/shop/bits';
import { PanelPage, Pager, Pill, fieldClass } from '@/components/panel/ui';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import type { Paginated, Review } from '@/types/boka';

function ReviewRow({ review }: { review: Review & { is_hidden: boolean } }) {
    const [reply, setReply] = useState(review.reply ?? '');

    return (
        <li className="space-y-2 rounded-2xl border bg-card p-4">
            <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
                <span><b>{review.author}</b> · {review.product?.name}</span>
                <span className="flex items-center gap-2"><Stars value={review.rating} size="size-3.5" />{review.is_hidden && <Pill tone="bad">Disembunyikan</Pill>}<span className="text-xs text-muted-foreground">{formatDate(review.date, 'id')}</span></span>
            </div>
            {review.body && <p className="text-sm">{review.body}</p>}
            <div className="flex flex-wrap gap-2">
                <input className={`${fieldClass} flex-1`} placeholder="Balas sebagai toko" value={reply} onChange={(e) => setReply(e.target.value)} />
                <Button variant="outline" onClick={() => router.post(`/panel/ulasan/${review.id}`, { reply }, { preserveScroll: true })}>Kirim balasan</Button>
                <Button variant="ghost" onClick={() => router.post(`/panel/ulasan/${review.id}`, { is_hidden: !review.is_hidden }, { preserveScroll: true })}>{review.is_hidden ? 'Tampilkan' : 'Sembunyikan'}</Button>
            </div>
        </li>
    );
}

export default function Reviews({ reviews }: { reviews: Paginated<Review & { is_hidden: boolean }> }) {
    return (
        <PanelPage title="Ulasan" description="Sembunyikan ulasan yang melanggar dan balas sebagai toko. Ulasan hanya dari pesanan Selesai.">
            <ul className="space-y-3">{reviews.data.map((r) => <ReviewRow key={r.id} review={r} />)}</ul>
            <Pager current={reviews.current_page} last={reviews.last_page} />
        </PanelPage>
    );
}
