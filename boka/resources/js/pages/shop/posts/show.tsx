import { Link, usePage } from '@inertiajs/react';
import { MotifDivider } from '@/components/shop/brand';
import { Seo } from '@/components/shop/seo';
import { formatDate } from '@/lib/format';
import { useLocalePath, useT } from '@/lib/i18n';
import type { PostCard } from '@/types/boka';

export default function Post({ post }: { post: PostCard & { body: string; gallery: string[] } }) {
    const t = useT();
    const localePath = useLocalePath();
    const { locale } = usePage().props;

    return (
        <article className="mx-auto max-w-3xl px-4 py-8">
            <Seo title={post.title} description={post.excerpt} image={post.cover} path={`/kabar/${post.slug}`} />
            <Link href={localePath('/kabar')} className="text-sm text-muted-foreground hover:text-primary">← {t('home.posts')}</Link>
            <p className="mt-4 text-xs font-bold tracking-[0.16em] text-primary uppercase">{t(`post.type_${post.type}`)}</p>
            <h1 className="mt-1 text-3xl font-extrabold tracking-tight text-balance sm:text-4xl">{post.title}</h1>
            <p className="mt-2 text-sm text-muted-foreground">{formatDate(post.published_at, locale)}{post.location && ` · ${post.location}`}</p>
            {post.cover && <img src={post.cover} alt="" className="mt-6 aspect-[16/9] w-full rounded-2xl object-cover" />}
            <MotifDivider className="my-8" />
            {/* Isi sudah disaring HTMLPurifier di server (bagian 11). */}
            <div className="prose-boka text-lg" dangerouslySetInnerHTML={{ __html: post.body }} />
            {post.gallery.length > 0 && (
                <div className="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-3">
                    {post.gallery.map((src) => <img key={src} src={src} alt="" loading="lazy" className="aspect-square w-full rounded-xl object-cover" />)}
                </div>
            )}
        </article>
    );
}
