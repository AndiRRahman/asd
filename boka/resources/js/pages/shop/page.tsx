import { MotifDivider } from '@/components/shop/brand';
import { Seo } from '@/components/shop/seo';

export default function Page({ page }: { page: { title: string; body: string; slug: string } }) {
    return (
        <article className="mx-auto max-w-3xl px-4 py-10">
            <Seo title={page.title} path={`/halaman/${page.slug}`} />
            <h1 className="text-3xl font-extrabold tracking-tight">{page.title}</h1>
            <MotifDivider className="my-6" />
            {/* Isi disaring HTMLPurifier di server (bagian 11). */}
            <div className="prose-boka" dangerouslySetInnerHTML={{ __html: page.body }} />
        </article>
    );
}
