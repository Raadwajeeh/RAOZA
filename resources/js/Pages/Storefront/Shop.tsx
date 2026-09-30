import { Form, Link } from '@inertiajs/react';
import SeoHead, { Seo } from '../../Components/SeoHead';
import ProductCard, { ProductCardData } from '../../Components/ProductCard';
import StorefrontLayout from '../../Layouts/StorefrontLayout';

type Pagination={data:ProductCardData[];current_page:number;last_page:number;prev_page_url:string|null;next_page_url:string|null;total:number};
type PageType='shop'|'category'|'collection';

const campaignByTitle:Record<string,string>={
    'T-Shirts':'/campaign/tshirts-campaign.webp',
    'Hoodies':'/campaign/hoodies-campaign.webp',
    'Drop 01':'/campaign/drop01-campaign.webp',
    'Core Essentials':'/campaign/core-essentials-campaign.webp',
};

function BrowseHeader({title,pageType,intro,query}:{title:string;pageType:PageType;intro?:string|null;query:{q:string}}){
    const campaign=campaignByTitle[title];
    const isDrop=title==='Drop 01';
    const isCore=title==='Core Essentials';
    return <header className={`raoza-browse-hero is-${pageType}${isDrop?' is-drop':''}${isCore?' is-core':''}`}>
        <div className="raoza-browse-copy">
            <nav aria-label="Breadcrumb" className="raoza-browse-breadcrumb"><Link href="/">Home</Link><span aria-hidden="true">/</span>{pageType!=='shop'&&<><Link href="/shop">Shop</Link><span aria-hidden="true">/</span></>}<span aria-current="page">{title}</span></nav>
            <p className="raoza-eyebrow">{pageType==='collection'?'Collection study':pageType==='category'?'Product category':'RAOZA / Complete edit'}</p>
            <h1>{title}</h1>
            {intro&&<p className="raoza-browse-intro">{intro}</p>}
            {pageType==='shop'?<Form action="/shop" method="get" className="raoza-catalog-search"><label htmlFor="catalog-search" className="sr-only">Search products</label><input id="catalog-search" name="q" defaultValue={query.q} placeholder="Search the collection"/><button>Search <span aria-hidden="true">→</span></button></Form>:<div className="raoza-browse-links"><Link href="/shop">Shop all</Link><Link href={pageType==='category'?'/#collections':'/categories/t-shirts'}>{pageType==='category'?'View collections':'Shop T-shirts'}</Link></div>}
        </div>
        {campaign&&<div className="raoza-browse-campaign"><img src={campaign} alt={`${title} campaign by RAOZA`} width="1200" height="1500" fetchPriority="high"/><span>{isDrop?'01 / Expressive':isCore?'02 / Essential':pageType==='category'?'Category / Current':'RAOZA / Edit'}</span></div>}
    </header>;
}

export default function Shop({products,query,title,pageType='shop',intro,seo}:{products:Pagination;query:{q:string};title:string;pageType?:PageType;intro?:string|null;seo:Seo}){
    return <StorefrontLayout><SeoHead seo={seo}/><main>
        <BrowseHeader title={title} pageType={pageType} intro={intro} query={query}/>
        <section className="raoza-catalog" aria-live="polite">
            <div className="raoza-catalog-meta"><span>{products.total} {products.total===1?'piece':'pieces'}</span><span>Page {products.current_page} / {products.last_page}</span></div>
            {products.data.length?<div className="raoza-catalog-grid">{products.data.map((product,index)=><ProductCard key={product.id} product={product} priority={index<2}/>)}</div>:<div className="raoza-catalog-empty"><h2>No pieces found.</h2><p>{query.q?'Try another search or return to the full shop.':'This selection has no available pieces.'}</p><Link href="/shop" className="raoza-button raoza-button-outline">{query.q?'Clear search':'View all pieces'}</Link></div>}
            {(products.prev_page_url||products.next_page_url)&&<nav aria-label="Product pages" className="raoza-catalog-pagination">{products.prev_page_url&&<Link href={products.prev_page_url} preserveScroll rel="prev" className="raoza-button raoza-button-outline">Previous</Link>}{products.next_page_url&&<Link href={products.next_page_url} preserveScroll rel="next" className="raoza-button">Next</Link>}</nav>}
        </section>
    </main></StorefrontLayout>;
}
