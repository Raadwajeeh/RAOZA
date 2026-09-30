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
const legalKeys=['privacy','cookies','terms'];
const sectionId=(heading:string,index:number)=>`section-${index+1}-${heading.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/(^-|-$)/g,'')}`;

export default function ContentPage({ page, shippingMethods = [], seo }: { page: Page; shippingMethods?: ShippingMethod[]; seo: Seo }) {
    const store = usePage<{ store: Store }>().props.store;
    const content = page.content ?? {};
    const sections=Array.isArray(content.sections)?content.sections:[];
    const isAbout=page.key==='about';
    const isLegal=legalKeys.includes(page.key);

    return <StorefrontLayout><SeoHead seo={seo}/><main className={`raoza-content-page${isAbout?' is-about':''}${isLegal?' is-legal':''}`}>
        {isAbout?<header className="raoza-about-hero">
            <div className="raoza-about-hero-media"><img src="/campaign/editorial-wide.webp" alt="RAOZA apparel photographed in an urban editorial setting" width="1800" height="1200" fetchPriority="high"/><span>RAOZA / Netherlands</span></div>
            <div className="raoza-about-hero-copy"><nav aria-label="Breadcrumb"><Link href="/">Home</Link><span>/</span><span aria-current="page">About</span></nav><p className="raoza-eyebrow">Independent point of view / 01</p><h1>{page.title}</h1>{content.intro&&<p>{content.intro}</p>}<Link href="/shop" className="raoza-button raoza-button-light">Shop the current edit <span aria-hidden="true">→</span></Link></div>
        </header>:<header className="raoza-content-hero"><div className="raoza-content-wrap"><nav aria-label="Breadcrumb"><Link href="/">Home</Link><span>/</span><span aria-current="page">{page.title}</span></nav><p className="raoza-eyebrow">{isLegal?'RAOZA / Legal':'RAOZA / Customer care'}</p><h1>{page.title}</h1>{content.intro&&<p>{content.intro}</p>}</div></header>}

        <div className="raoza-content-wrap raoza-content-layout">
            <article className="raoza-content-sections">{sections.map((section,index)=><section id={sectionId(section.heading,index)} key={`${section.heading}-${index}`}><p>{String(index+1).padStart(2,'0')}</p><h2>{section.heading}</h2><div>{section.body}</div></section>)}
                {page.key==='shipping'&&shippingMethods.length>0&&<section className="raoza-shipping-options"><p>Live checkout data</p><h2>Current checkout options</h2><div>{shippingMethods.map(method=><div key={method.id}><div><h3>{method.name}</h3>{method.description&&<p>{method.description}</p>}</div><strong><Money amount={method.price}/></strong></div>)}</div><small>Checkout remains authoritative for availability and price.</small></section>}
            </article>

            <aside className="raoza-content-aside">
                {isLegal&&sections.length>0?<><p>On this page</p><nav aria-label="Page sections">{sections.map((section,index)=><a key={sectionId(section.heading,index)} href={`#${sectionId(section.heading,index)}`}>{String(index+1).padStart(2,'0')} — {section.heading}</a>)}</nav></>:<><p>Customer care</p><div>{store.customer_service}</div><a href={`mailto:${store.support_email}`}>{store.support_email}</a>{store.contact_phone&&<a href={`tel:${store.contact_phone}`}>{store.contact_phone}</a>}<nav aria-label="Related customer information">{related.filter(item=>item.href!==`/pages/${page.slug}`).map(item=><Link key={item.href} href={item.href}>{item.label}</Link>)}</nav></>}
                {['contact','privacy','terms'].includes(page.key)&&<div className="raoza-company-details"><strong>{store.company_name}</strong>{store.city&&<span>{store.city}, {store.country}</span>}{store.registration_number&&<span>Registration: {store.registration_number}</span>}{store.vat_number&&<span>VAT: {store.vat_number}</span>}</div>}
            </aside>
        </div>
        {isAbout&&<section className="raoza-about-end"><p>RAOZA / Current expression</p><h2>Graphic identity,<br/><em>worn every day.</em></h2><Link href="/shop" className="raoza-button raoza-button-light">Explore all apparel <span aria-hidden="true">→</span></Link></section>}
    </main></StorefrontLayout>;
}
