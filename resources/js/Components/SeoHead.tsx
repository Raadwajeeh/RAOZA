import { Head } from '@inertiajs/react';

export type Seo = {
    title: string;
    description?: string | null;
    canonical: string;
    og?: string | null;
    indexable: boolean;
    jsonLd?: Record<string, unknown> | null;
};

export default function SeoHead({ seo }: { seo: Seo }) {
    const description = seo.description ?? '';

    return (
        <Head title={seo.title}>
            <meta head-key="description" name="description" content={description} />
            <link head-key="canonical" rel="canonical" href={seo.canonical} />
            <meta
                head-key="robots"
                name="robots"
                content={seo.indexable ? 'index,follow' : 'noindex,nofollow'}
            />
            <meta head-key="og-title" property="og:title" content={seo.title} />
            <meta
                head-key="og-description"
                property="og:description"
                content={description}
            />
            <meta head-key="og-url" property="og:url" content={seo.canonical} />
            <meta head-key="og-type" property="og:type" content="website" />
            <meta head-key="og-site-name" property="og:site_name" content="RAOZA" />
            <meta head-key="twitter-card" name="twitter:card" content={seo.og ? 'summary_large_image' : 'summary'} />
            <meta head-key="twitter-title" name="twitter:title" content={seo.title} />
            <meta head-key="twitter-description" name="twitter:description" content={description} />

            {seo.og ? (
                <meta head-key="og-image" property="og:image" content={seo.og} />
            ) : null}

            {seo.jsonLd && Object.keys(seo.jsonLd).length > 0 ? (
                <script head-key="json-ld" type="application/ld+json">
                    {JSON.stringify(seo.jsonLd)}
                </script>
            ) : null}
        </Head>
    );
}
