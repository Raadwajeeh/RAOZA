import { Link } from '@inertiajs/react';
import Money from '../../Components/Money';
import SeoHead, { Seo } from '../../Components/SeoHead';
import { ProductCardData } from '../../Components/ProductCard';
import StorefrontLayout from '../../Layouts/StorefrontLayout';

type ImageData = { url: string; alt: string; width: number | null; height: number | null };
type Taxonomy = { name: string; slug: string } | null;
type HomeProduct = ProductCardData & {
    position: number;
    shortDescription: string | null;
    images: ImageData[];
    category: Taxonomy;
    collection: Taxonomy;
};
type Collection = { id: number; name: string; slug: string; description: string | null };

const number = (position: number) => String(position).padStart(2, '0');
const findProduct = (products: HomeProduct[], slug: string) => products.find(product => product.slug === slug);
const image = (product: HomeProduct | undefined, preferred: number, fallback = 0) => product?.images[preferred] ?? product?.images[fallback];

function EditorialImage({ source, className = '', eager = false }: { source?: ImageData; className?: string; eager?: boolean }) {
    if (!source) return <div className={`grid place-items-center bg-raoza-primary/5 ${className}`}><span className="font-display text-3xl text-raoza-primary/25">RAOZA</span></div>;
    return <img src={source.url} alt={source.alt} width={source.width ?? undefined} height={source.height ?? undefined} loading={eager ? 'eager' : 'lazy'} fetchPriority={eager ? 'high' : 'auto'} className={className} />;
}

function CampaignPanel({ product, source, index }: { product: HomeProduct; source?: ImageData; index: number }) {
    return <article className="raoza-campaign-panel group">
        <Link href={`/products/${product.slug}`} className="raoza-campaign-media">
            <EditorialImage source={source} className="h-full w-full object-cover transition-transform duration-700 ease-out group-hover:scale-[1.025]" />
            <span className="raoza-campaign-index">{String(index + 1).padStart(2, '0')} / 03</span>
        </Link>
        <div className="raoza-campaign-copy">
            <div><p>{product.collection?.name ?? 'RAOZA edit'}</p><h3>{product.name}</h3></div>
            <Link href={`/products/${product.slug}`} aria-label={`View ${product.name}`}>Discover <span aria-hidden="true">↗</span></Link>
        </div>
    </article>;
}

function ProductEdit({ product }: { product: HomeProduct }) {
    return <article className="raoza-product-edit group">
        <Link href={`/products/${product.slug}`} className="raoza-product-edit-media">
            <EditorialImage source={image(product, 0)} className="h-full w-full object-cover transition-transform duration-700 ease-out group-hover:scale-[1.025]" />
        </Link>
        <div className="raoza-product-edit-copy">
            <span>{number(product.position)}</span>
            <div><Link href={`/products/${product.slug}`}>{product.name}</Link><p>{product.category?.name ?? 'RAOZA apparel'}</p></div>
            <Money amount={product.price} />
        </div>
    </article>;
}

export default function Home({ featuredProducts, collections, seo }: { featuredProducts: HomeProduct[]; collections: Collection[]; seo: Seo }) {
    const signature = findProduct(featuredProducts, 'raoza-signature-tee');
    const editorial = findProduct(featuredProducts, 'raoza-editorial-tee');
    const mark = findProduct(featuredProducts, 'raoza-mark-tee');
    const essential = findProduct(featuredProducts, 'raoza-essential-hoodie');
    const structured = findProduct(featuredProducts, 'raoza-structured-hoodie');
    const burgundy = findProduct(featuredProducts, 'raoza-deep-burgundy-hoodie');
    const drop = collections.find(collection => collection.slug === 'drop-01');
    const core = collections.find(collection => collection.slug === 'core-essentials');
    const campaign = [essential, burgundy, structured].filter((product): product is HomeProduct => Boolean(product));
    const productEdit = [signature, editorial, mark].filter((product): product is HomeProduct => Boolean(product));

    return <StorefrontLayout><SeoHead seo={seo} />
        <main className="overflow-hidden">
            <section className="raoza-home-hero" aria-labelledby="hero-heading">
                <div className="raoza-home-hero-copy">
                    <p className="raoza-hero-kicker"><span>RAOZA / Apparel</span><span>Urban Editorial / 01—06</span></p>
                    <div className="raoza-hero-message">
                        <p className="raoza-hero-edition">Drop 01 — Current expression</p>
                        <h1 id="hero-heading">Form,<br /><em>in motion.</em></h1>
                        <p className="raoza-hero-intro">Graphic identity and everyday apparel, composed through an urban editorial point of view.</p>
                        <div className="raoza-hero-actions"><Link href="/shop" className="raoza-button raoza-button-light">Shop now <span aria-hidden="true">→</span></Link>{drop && <Link href={`/collections/${drop.slug}`} className="raoza-text-link raoza-text-link-light">View Drop 01 <span aria-hidden="true">↗</span></Link>}</div>
                    </div>
                    <p className="raoza-hero-foot">T-shirts / Hoodies <span>RAOZA — Netherlands</span></p>
                </div>
                <div className="raoza-home-hero-image">
                    <EditorialImage source={image(editorial, 4, 3)} eager className="h-full w-full object-cover" />
                    {burgundy && <Link href={`/products/${burgundy.slug}`} className="raoza-hero-orbit" aria-label={`View ${burgundy.name}`}><EditorialImage source={image(burgundy, 2)} className="h-full w-full object-cover" /><span>{number(burgundy.position)}</span></Link>}
                    <div className="raoza-hero-image-label"><span>Editorial Tee / 02</span><span>Current edit</span></div>
                </div>
            </section>

            {campaign.length > 0 && <section className="raoza-campaign" aria-labelledby="campaign-heading">
                <header className="raoza-campaign-head raoza-container"><div><p className="raoza-eyebrow">Campaign selection / 01</p><h2 id="campaign-heading">Three studies<br /><em>in silhouette.</em></h2></div>{drop && <Link href={`/collections/${drop.slug}`} className="raoza-text-link">Explore Drop 01 <span aria-hidden="true">→</span></Link>}</header>
                <div className="raoza-campaign-grid">{campaign.map((product, index) => <CampaignPanel key={product.id} product={product} index={index} source={image(product, product.slug === 'raoza-deep-burgundy-hoodie' ? 3 : 4, 3)} />)}</div>
            </section>}

            {productEdit.length > 0 && <section className="raoza-current-edit" aria-labelledby="current-edit-heading">
                <div className="raoza-current-edit-inner raoza-container">
                    <header className="raoza-current-edit-head"><p className="raoza-eyebrow">Current product edit / T-shirts</p><h2 id="current-edit-heading">The graphic<br /><em>wardrobe.</em></h2><p>Three expressions of the RAOZA identity, moving from quiet signature detail to a stronger editorial graphic.</p><Link href="/categories/t-shirts" className="raoza-text-link">Shop T-shirts <span aria-hidden="true">→</span></Link></header>
                    <div className="raoza-current-edit-products">{productEdit.map(product => <ProductEdit key={product.id} product={product} />)}</div>
                    {signature && <Link href={`/products/${signature.slug}`} className="raoza-current-edit-campaign group" aria-label={`View ${signature.name}`}><EditorialImage source={image(signature, 4, 3)} className="h-full w-full object-cover transition-transform duration-700 ease-out group-hover:scale-[1.02]" /><span>Core / 01</span><b>Quiet identity.<br />Everyday form.</b></Link>}
                </div>
            </section>}

            {essential && mark && <section className="raoza-brand-moment" aria-labelledby="brand-moment-heading">
                <div className="raoza-brand-image"><EditorialImage source={image(essential, 4, 3)} className="h-full w-full object-cover" /></div>
                <div className="raoza-brand-orbit"><EditorialImage source={image(mark, 5, 3)} className="h-full w-full object-cover" /></div>
                <div className="raoza-brand-type" aria-hidden="true">URBAN / EDITORIAL</div>
                <div className="raoza-brand-copy"><p className="raoza-eyebrow">Worn in context / 02</p><h2 id="brand-moment-heading">Clarity in form.<br /><em>Identity in detail.</em></h2><Link href={`/products/${essential.slug}`} className="raoza-text-link raoza-text-link-light">View the Essential Hoodie <span aria-hidden="true">↗</span></Link></div>
            </section>}

            {mark && structured && <section className="raoza-categories" aria-labelledby="category-heading">
                <header className="raoza-container raoza-section-head"><div><p className="raoza-eyebrow">Shop by category / 03</p><h2 id="category-heading">Two forms.<br /><em>One language.</em></h2></div></header>
                <div className="raoza-category-grid">
                    <Link href="/categories/t-shirts" className="raoza-category group"><EditorialImage source={image(mark, 4, 3)} className="h-full w-full object-cover transition-transform duration-700 group-hover:scale-[1.025]" /><span className="raoza-category-count">01 / 03</span><span className="raoza-category-title">T-Shirts <i>Explore ↗</i></span></Link>
                    <Link href="/categories/hoodies" className="raoza-category group"><EditorialImage source={image(structured, 4, 3)} className="h-full w-full object-cover transition-transform duration-700 group-hover:scale-[1.025]" /><span className="raoza-category-count">02 / 03</span><span className="raoza-category-title">Hoodies <i>Explore ↗</i></span></Link>
                </div>
            </section>}

            {drop && core && burgundy && signature && <section id="collections" className="raoza-collections raoza-container" aria-labelledby="collections-heading">
                <header className="raoza-section-head"><div><p className="raoza-eyebrow">Collection stories / 04</p><h2 id="collections-heading">Expression / <em>Essentials.</em></h2></div></header>
                <div className="raoza-collection-grid">
                    <Link href={`/collections/${drop.slug}`} className="raoza-collection-card raoza-collection-drop group"><div className="raoza-collection-media"><EditorialImage source={image(burgundy, 3)} className="h-full w-full object-cover transition-transform duration-700 group-hover:scale-[1.025]" /></div><div className="raoza-collection-copy"><span>01 / Expressive</span><h3>{drop.name}</h3><p>{drop.description}</p><i>Explore collection ↗</i></div></Link>
                    <Link href={`/collections/${core.slug}`} className="raoza-collection-card raoza-collection-core group"><div className="raoza-collection-media"><EditorialImage source={image(signature, 4, 3)} className="h-full w-full object-cover transition-transform duration-700 group-hover:scale-[1.025]" /></div><div className="raoza-collection-copy"><span>02 / Brand-led</span><h3>{core.name}</h3><p>{core.description}</p><i>Explore collection ↗</i></div></Link>
                </div>
            </section>}

            <section className="raoza-about-preview"><div className="raoza-container"><p className="raoza-eyebrow">RAOZA / Brand statement</p><p className="raoza-about-statement">A design-led apparel label where <em>graphic identity</em> meets everyday form.</p><Link href="/pages/about" className="raoza-text-link">About RAOZA <span aria-hidden="true">→</span></Link></div></section>
            <section className="raoza-final-cta" aria-label="Shop RAOZA"><p>01—06 / T-shirts + Hoodies</p><h2>Wear the<br /><em>point of view.</em></h2><div><Link href="/shop" className="raoza-button raoza-button-light">Shop all apparel <span aria-hidden="true">→</span></Link><Link href="/pages/contact" className="raoza-text-link raoza-text-link-light">Contact <span aria-hidden="true">↗</span></Link></div></section>
        </main>
    </StorefrontLayout>;
}
