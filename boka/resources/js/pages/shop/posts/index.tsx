import { Link, usePage } from '@inertiajs/react';
import { Walasuji } from '@/components/shop/brand';
import { Seo } from '@/components/shop/seo';
import { formatDate } from '@/lib/format';
import { useLocalePath, useT } from '@/lib/i18n';
import type { PostCard } from '@/types/boka';

export default function Posts({ posts }: { posts: PostCard[] }) {
    const t = useT();
    const localePath = useLocalePath();
    const { locale } = usePage().props;

    return (
        <div className="mx-auto max-w-7xl px-4 py-8">
            <Seo title={t('home.posts')} description={t('post.meta_description')} path="/kabar" />
            <p className="text-xs font-bold tracking-[0.16em] text-primary uppercase">{t('home.posts_kicker')}</p>
            <h1 className="mb-6 text-3xl font-extrabold tracking-tight">{t('home.posts')}</h1>
            <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                {posts.map((post) => (
                    <Link key={post.slug} href={localePath(`/kabar/${post.slug}`)} className="group overflow-hidden rounded-2xl border bg-card">
                        <div className="relative aspect-[16/9] bg-primary-soft">
                            {post.cover ? <img src={post.cover} alt="" loading="lazy" className="size-full object-cover" /> : <Walasuji className="text-primary opacity-20" id={`w-${post.slug}`} />}
                        </div>
                        <div className="space-y-2 p-5">
                            <p className="text-xs text-muted-foreground">{t(`post.type_${post.type}`)} · {formatDate(post.published_at, locale)}{post.location && ` · ${post.location}`}</p>
                            <h2 className="text-lg font-bold group-hover:text-primary">{post.title}</h2>
                            <p className="line-clamp-3 text-sm text-muted-foreground">{post.excerpt}</p>
                        </div>
                    </Link>
                ))}
            </div>
        </div>
    );
}
