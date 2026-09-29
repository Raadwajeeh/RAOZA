import { Link, usePage } from '@inertiajs/react';
import Money from '../../Components/Money';
import SeoHead, { Seo } from '../../Components/SeoHead';
import StorefrontLayout from '../../Layouts/StorefrontLayout';

type Section = { heading: string; body: string };
type Store = { brand_name: string; company_name: string; support_email: string; contact_email: string; contact_phone?: string | null; city?: string | null; country: string; registration_number?: string | null; vat_number?: string | null; customer_service: string };
type ShippingMethod = { id: number; name: string; price: number; description?: string | null };
type Page = { key: string; title: string; slug: string; content?: { intro?: string; sections?: Section[] } };

const related = [
    { href: '/pages/faq', label: 'Help & FAQ' },
    { href: '/pages/shipping', label: 'Shipping' },
    { href: '/pages/returns', label: 'Returns' },
    { href: '/pages/contact', label: 'Contact' },
];

export default function ContentPage({ page, shippingMethods = [], seo }: { page: Page; shippingMethods?: ShippingMethod[]; seo: Seo }) {
    const store = usePage<{ store: Store }>().props.store;
    const content = page.content ?? {};

    return <StorefrontLayout><SeoHead seo={seo}/><main className="mx-auto max-w-5xl px-5 py-14 md:px-10 md:py-20">
        <nav aria-label="Breadcrumb" className="flex gap-2 text-[9px] font-semibold uppercase tracking-[.18em] text-raoza-black/50"><Link href="/">Home</Link><span aria-hidden="true">/</span><span aria-current="page">{page.title}</span></nav>
        <header className="mt-7 border-b border-raoza-primary/20 pb-10"><p className="text-[10px] uppercase tracking-[.2em] text-raoza-secondary">RAOZA / Customer information</p><h1 className="mt-4 max-w-4xl font-display text-5xl leading-[.92] text-raoza-primary md:text-7xl">{page.title}</h1>{content.intro&&<p className="mt-8 max-w-3xl text-lg leading-8 text-raoza-black/75">{content.intro}</p>}</header>

        <div className="grid gap-12 py-4 lg:grid-cols-[1fr_18rem] lg:gap-20">
            <div>{Array.isArray(content.sections)&&content.sections.map((section,index)=><section key={`${section.heading}-${index}`} className="border-b border-raoza-primary/15 py-9 last:border-b-0"><p className="text-[9px] uppercase tracking-[.18em] text-raoza-secondary">{String(index+1).padStart(2,'0')}</p><h2 className="mt-2 font-display text-3xl text-raoza-primary">{section.heading}</h2><p className="mt-4 whitespace-pre-line text-sm leading-7 text-raoza-black/70">{section.body}</p></section>)}
                {page.key==='shipping'&&shippingMethods.length>0&&<section className="mt-8 border border-raoza-primary/20 p-5 sm:p-7"><h2 className="font-display text-3xl text-raoza-primary">Current checkout options</h2><p className="mt-2 text-sm leading-6 text-raoza-black/60">Checkout remains authoritative for availability and price.</p><div className="mt-5 divide-y divide-raoza-primary/15">{shippingMethods.map(method=><div key={method.id} className="flex items-start justify-between gap-6 py-4"><div><h3 className="text-sm font-semibold">{method.name}</h3>{method.description&&<p className="mt-1 text-xs leading-5 text-raoza-black/55">{method.description}</p>}</div><strong className="shrink-0 text-sm"><Money amount={method.price}/></strong></div>)}</div></section>}
            </div>

            <aside className="h-fit border-l border-raoza-primary/20 pl-6 lg:sticky lg:top-32"><p className="text-[9px] uppercase tracking-[.18em] text-raoza-secondary">Customer care</p><p className="mt-4 text-sm leading-6 text-raoza-black/65">{store.customer_service}</p><a href={`mailto:${store.support_email}`} className="mt-5 inline-block break-all text-sm font-semibold underline underline-offset-4">{store.support_email}</a>{store.contact_phone&&<a href={`tel:${store.contact_phone}`} className="mt-2 block text-sm underline underline-offset-4">{store.contact_phone}</a>}
                <nav aria-label="Related customer information" className="mt-8 flex flex-col gap-3 border-t border-raoza-primary/15 pt-6 text-sm">{related.filter(item=>item.href!==`/pages/${page.slug}`).map(item=><Link key={item.href} href={item.href} className="underline decoration-raoza-primary/30 underline-offset-4 hover:decoration-raoza-primary">{item.label}</Link>)}</nav>
                {['contact','privacy','terms'].includes(page.key)&&<div className="mt-8 border-t border-raoza-primary/15 pt-6 text-xs leading-6 text-raoza-black/55"><strong className="block text-raoza-black">{store.company_name}</strong>{store.city&&<span className="block">{store.city}, {store.country}</span>}{store.registration_number&&<span className="block">Registration: {store.registration_number}</span>}{store.vat_number&&<span className="block">VAT: {store.vat_number}</span>}</div>}
            </aside>
        </div>
    </main></StorefrontLayout>;
}
