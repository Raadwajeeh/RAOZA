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

function ProductFeature({ product, index, className = '' }: { product: HomeProduct; index: number; className?: string }) {
    return <article className={`group ${className}`}>
        <Link href={`/products/${product.slug}`} className="block overflow-hidden bg-[#eadfce]">
            <EditorialImage source={image(product, 0)} className="aspect-[4/5] h-full w-full object-cover transition-transform duration-700 ease-out group-hover:scale-[1.025]" />
        </Link>
        <div className="mt-4 grid grid-cols-[auto_1fr_auto] items-start gap-3 border-t border-raoza-primary/20 pt-3">
            <span className="text-[10px] font-semibold tracking-[.18em] text-raoza-secondary">{number(product.position || index + 1)}</span>
            <div><Link href={`/products/${product.slug}`} className="text-xs font-semibold leading-5 hover:underline hover:underline-offset-4 sm:text-sm">{product.name}</Link><p className="mt-1 text-[9px] uppercase tracking-[.15em] text-raoza-black/45">{product.category?.name ?? 'RAOZA Apparel'}</p></div>
            <p className="text-xs font-semibold"><Money amount={product.price} /></p>
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
    const featured = [signature, editorial, essential, burgundy].filter((product): product is HomeProduct => Boolean(product));

    return <StorefrontLayout><SeoHead seo={seo} />
        <main className="overflow-hidden">
            <section className="raoza-home-hero" aria-labelledby="hero-heading">
                <div className="raoza-home-hero-copy">
                    <p className="raoza-hero-kicker"><span>RAOZA</span><span>Urban Editorial / 01—06</span></p>
                    <div>
                        <h1 id="hero-heading">A quiet form<br />of <em>attitude.</em></h1>
                        <p className="raoza-hero-intro">Design-led printed apparel shaped by clean silhouettes, graphic identity and a controlled urban point of view.</p>
                        <div className="raoza-hero-actions"><Link href="/shop" className="raoza-button raoza-button-light">Shop now</Link>{drop && <Link href={`/collections/${drop.slug}`} className="raoza-text-link raoza-text-link-light">Explore Drop 01 <span aria-hidden="true">↗</span></Link>}</div>
                    </div>
                    <p className="raoza-hero-foot">T-shirts / Hoodies <span>Netherlands</span></p>
                </div>
                <div className="raoza-home-hero-image">
                    <EditorialImage source={image(editorial, 4, 3)} eager className="h-full w-full object-cover object-[52%_35%]" />
                    {burgundy && <Link href={`/products/${burgundy.slug}`} className="raoza-hero-orbit" aria-label={`View ${burgundy.name}`}><EditorialImage source={image(burgundy, 2)} className="h-full w-full object-cover" /><span>{number(burgundy.position)}</span></Link>}
                    <div className="raoza-hero-image-label"><span>Current edit</span><span>RAOZA / 2026</span></div>
                </div>
            </section>

            {drop && <section className="raoza-home-intro raoza-container" aria-labelledby="drop-heading">
                <div><p className="raoza-eyebrow">Current collection</p><p className="raoza-section-number">01</p></div>
                <div><h2 id="drop-heading">Drop 01.<br /><em>Graphic expression,</em><br />held in balance.</h2><p>{drop.description}</p><Link href={`/collections/${drop.slug}`} className="raoza-text-link">Discover the collection <span aria-hidden="true">↗</span></Link></div>
            </section>}

            {featured.length > 0 && <section className="raoza-featured raoza-container" aria-labelledby="featured-heading">
                <header className="raoza-section-head"><div><p className="raoza-eyebrow">The current edit</p><h2 id="featured-heading">Selected <em>pieces.</em></h2></div><Link href="/shop" className="raoza-text-link">View all six <span aria-hidden="true">→</span></Link></header>
                <div className="raoza-featured-grid">{featured.map((product, index) => <ProductFeature key={product.id} product={product} index={index} className={index === 1 ? 'raoza-featured-high' : index === 2 ? 'raoza-featured-low' : ''} />)}</div>
            </section>}

            <section className="raoza-brand-moment" aria-labelledby="brand-moment-heading">
                <div className="raoza-brand-type" aria-hidden="true">URBAN<br /><em>EDITORIAL</em></div>
                <div className="raoza-brand-orbit"><EditorialImage source={image(mark, 3)} className="h-full w-full object-cover object-top" /></div>
                <div className="raoza-brand-copy"><p className="raoza-eyebrow">The RAOZA position</p><h2 id="brand-moment-heading">Clarity in form.<br /><em>Identity in detail.</em></h2><p>A controlled wardrobe of printed pieces, considered graphics and confident everyday silhouettes.</p></div>
            </section>

            {mark && structured && <section className="raoza-categories" aria-labelledby="category-heading">
                <header className="raoza-container raoza-section-head"><div><p className="raoza-eyebrow">Shop by category</p><h2 id="category-heading">Two forms.<br /><em>One language.</em></h2></div></header>
                <div className="raoza-category-grid">
                    <Link href="/categories/t-shirts" className="raoza-category raoza-category-light"><EditorialImage source={image(mark, 4, 3)} className="h-full w-full object-cover object-top transition-transform duration-700 group-hover:scale-[1.025]" /><span className="raoza-category-count">01 / 03</span><span className="raoza-category-title">T-Shirts <i>Explore ↗</i></span></Link>
                    <Link href="/categories/hoodies" className="raoza-category raoza-category-dark"><EditorialImage source={image(structured, 4, 3)} className="h-full w-full object-cover object-top transition-transform duration-700 group-hover:scale-[1.025]" /><span className="raoza-category-count">02 / 03</span><span className="raoza-category-title">Hoodies <i>Explore ↗</i></span></Link>
                </div>
            </section>}

            {drop && core && editorial && signature && <section className="raoza-collections raoza-container" aria-labelledby="collections-heading">
                <header className="raoza-section-head"><div><p className="raoza-eyebrow">Collection stories</p><h2 id="collections-heading">Expression / <em>Essentials.</em></h2></div></header>
                <div className="raoza-collection-grid">
                    <Link href={`/collections/${drop.slug}`} className="raoza-collection-card raoza-collection-drop"><div className="raoza-collection-media"><EditorialImage source={image(burgundy, 3)} className="h-full w-full object-cover object-top transition-transform duration-700 group-hover:scale-[1.025]" /></div><div className="raoza-collection-copy"><span>01 / Expressive</span><h3>{drop.name}</h3><p>{drop.description}</p><i>Explore collection ↗</i></div></Link>
                    <Link href={`/collections/${core.slug}`} className="raoza-collection-card raoza-collection-core"><div className="raoza-collection-media"><EditorialImage source={image(signature, 4, 3)} className="h-full w-full object-cover object-top transition-transform duration-700 group-hover:scale-[1.025]" /></div><div className="raoza-collection-copy"><span>02 / Brand-led</span><h3>{core.name}</h3><p>{core.description}</p><i>Explore collection ↗</i></div></Link>
                </div>
            </section>}

            <section className="raoza-about-preview">
                <div className="raoza-container"><p className="raoza-eyebrow">About RAOZA</p><p className="raoza-about-statement">A design-led printed apparel brand with an <em>Urban Editorial</em> point of view.</p><Link href="/pages/about" className="raoza-text-link">Read our perspective <span aria-hidden="true">→</span></Link></div>
            </section>

            <section className="raoza-final-cta" aria-label="Shop RAOZA"><p>01—06 / Current collection</p><h2>Find your<br /><em>point of view.</em></h2><Link href="/shop" className="raoza-button raoza-button-light">Shop the collection</Link></section>
        </main>
    </StorefrontLayout>;
}
