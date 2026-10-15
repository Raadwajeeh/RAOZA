import { Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import SeoHead, { Seo } from '../../Components/SeoHead';
import ProductCard, { ProductCardData } from '../../Components/ProductCard';
import StorefrontLayout from '../../Layouts/StorefrontLayout';

type Pagination={data:ProductCardData[];current_page:number;last_page:number;prev_page_url:string|null;next_page_url:string|null;total:number};
type PageType='shop'|'category'|'collection';
type Filters={q:string;category:string;collection:string;size:string[];color:string[];min_price:string;max_price:string;in_stock:boolean;sort:string};
type Facets={categories:{name:string;slug:string}[];collections:{name:string;slug:string}[];sizes:{value:string}[];colors:{value:string;swatch?:string|null}[]};

function queryFor(filters:Filters){
    const query:Record<string,string|string[]|number>={};
    for(const key of ['q','category','collection','min_price','max_price'] as const) if(filters[key]) query[key]=filters[key];
    if(filters.size.length) query.size=filters.size;
    if(filters.color.length) query.color=filters.color;
    if(filters.in_stock) query.in_stock=1;
    if(filters.sort!=='recommended') query.sort=filters.sort;
    return query;
}

const campaignByTitle:Record<string,string>={'T-Shirts':'/campaign/tshirts-campaign.webp','Hoodies':'/campaign/hoodies-campaign.webp','Drop 01':'/campaign/drop01-campaign.webp','Core Essentials':'/campaign/core-essentials-campaign.webp'};

function BrowseHeader({title,pageType,intro}:{title:string;pageType:PageType;intro?:string|null}){
    const campaign=campaignByTitle[title]; const isDrop=title==='Drop 01'; const isCore=title==='Core Essentials';
    return <header className={`raoza-browse-hero is-${pageType}${isDrop?' is-drop':''}${isCore?' is-core':''}`}>
        <div className="raoza-browse-copy"><nav aria-label="Breadcrumb" className="raoza-browse-breadcrumb"><Link href="/">Home</Link><span aria-hidden="true">/</span>{pageType!=='shop'&&<><Link href="/shop">Shop</Link><span aria-hidden="true">/</span></>}<span aria-current="page">{title}</span></nav><p className="raoza-eyebrow">{pageType==='collection'?'Collection study':pageType==='category'?'Product category':'RAOZA / Complete edit'}</p><h1>{title}</h1>{intro&&<p className="raoza-browse-intro">{intro}</p>}</div>
        {campaign&&<div className="raoza-browse-campaign"><img src={campaign} alt={`${title} campaign by RAOZA`} width="1200" height="1500" fetchPriority="high"/><span>{isDrop?'01 / Expressive':isCore?'02 / Essential':pageType==='category'?'Category / Current':'RAOZA / Edit'}</span></div>}
    </header>;
}

function FilterFields({value,facets,setValue,prefix}:{value:Filters;facets:Facets;setValue:(update:(current:Filters)=>Filters)=>void;prefix:string}){
    const toggle=(key:'size'|'color',item:string)=>setValue(current=>({...current,[key]:current[key].includes(item)?current[key].filter(x=>x!==item):[...current[key],item]}));
    return <div className="raoza-filter-fields">
        <label className="raoza-filter-search" htmlFor={`${prefix}-search`}><span>Search</span><input id={`${prefix}-search`} value={value.q} maxLength={80} onChange={e=>setValue(current=>({...current,q:e.target.value}))} placeholder="Name or description"/></label>
        <label>Category<select value={value.category} onChange={e=>setValue(current=>({...current,category:e.target.value}))}><option value="">All categories</option>{facets.categories.map(x=><option value={x.slug} key={x.slug}>{x.name}</option>)}</select></label>
        <label>Collection<select value={value.collection} onChange={e=>setValue(current=>({...current,collection:e.target.value}))}><option value="">All collections</option>{facets.collections.map(x=><option value={x.slug} key={x.slug}>{x.name}</option>)}</select></label>
        <fieldset><legend>Size</legend><div className="raoza-filter-options">{facets.sizes.map(x=><label key={x.value}><input type="checkbox" checked={value.size.includes(x.value)} onChange={()=>toggle('size',x.value)}/><span>{x.value}</span></label>)}</div></fieldset>
        <fieldset><legend>Color</legend><div className="raoza-filter-options is-colors">{facets.colors.map(x=><label key={x.value}><input type="checkbox" checked={value.color.includes(x.value)} onChange={()=>toggle('color',x.value)}/><span>{x.swatch&&<i style={{backgroundColor:x.swatch}} aria-hidden="true"/>}{x.value}</span></label>)}</div></fieldset>
        <fieldset><legend>Price (€)</legend><div className="raoza-price-range"><label htmlFor={`${prefix}-min`}>Minimum<input id={`${prefix}-min`} type="number" inputMode="decimal" min="0" max="100000" step="0.01" value={value.min_price} onChange={e=>setValue(current=>({...current,min_price:e.target.value}))}/></label><span aria-hidden="true">—</span><label htmlFor={`${prefix}-max`}>Maximum<input id={`${prefix}-max`} type="number" inputMode="decimal" min="0" max="100000" step="0.01" value={value.max_price} onChange={e=>setValue(current=>({...current,max_price:e.target.value}))}/></label></div></fieldset>
        <label className="raoza-stock-toggle"><input type="checkbox" checked={value.in_stock} onChange={e=>setValue(current=>({...current,in_stock:e.target.checked}))}/><span>In-stock pieces only</span></label>
    </div>;
}

export default function Shop({products,filters,facets,browsePath,title,pageType='shop',intro,seo}:{products:Pagination;filters:Filters;facets:Facets;browsePath:string;title:string;pageType?:PageType;intro?:string|null;seo:Seo}){
    const [draft,setDraft]=useState(filters); const draftRef=useRef(filters); const dialog=useRef<HTMLDialogElement>(null);
    const serverFilterState=JSON.stringify(filters);
    useEffect(()=>{draftRef.current=filters;setDraft(filters)},[serverFilterState]);
    const updateDraft=(update:(current:Filters)=>Filters)=>{const next=update(draftRef.current);draftRef.current=next;setDraft(next)};
    const activeCount=filters.size.length+filters.color.length+Number(Boolean(filters.q))+Number(Boolean(filters.category))+Number(Boolean(filters.collection))+Number(Boolean(filters.min_price))+Number(Boolean(filters.max_price))+Number(filters.in_stock);
    const apply=(next?:Filters)=>router.get(browsePath,queryFor(next??draftRef.current),{preserveScroll:true,preserveState:true,replace:true});
    const clear=()=>router.get(browsePath,{}, {preserveScroll:true,replace:true});
    return <StorefrontLayout><SeoHead seo={seo}/><main>
        <BrowseHeader title={title} pageType={pageType} intro={intro}/>
        <section className="raoza-catalog" aria-labelledby="catalog-results">
            <div className="raoza-catalog-tools">
                <button type="button" className="raoza-mobile-filter" onClick={()=>dialog.current?.showModal()} aria-haspopup="dialog">Filters{activeCount>0&&<span>{activeCount}</span>}</button>
                <p id="catalog-results" aria-live="polite"><strong>{products.total}</strong> {products.total===1?'piece':'pieces'}</p>
                <label className="raoza-sort">Sort by<select aria-label="Sort products" value={filters.sort} onChange={e=>apply({...filters,sort:e.target.value})}><option value="recommended">Recommended</option><option value="newest">Newest</option><option value="price_asc">Price: low to high</option><option value="price_desc">Price: high to low</option></select></label>
            </div>
            {activeCount>0&&<div className="raoza-active-filters" aria-label="Active filters"><span>{activeCount} active</span><button type="button" onClick={clear}>Clear all</button></div>}
            <div className="raoza-catalog-layout">
                <aside className="raoza-desktop-filters" aria-label="Product filters"><form onSubmit={e=>{e.preventDefault();apply()}}><div className="raoza-filter-heading"><h2>Filter the edit</h2>{activeCount>0&&<button type="button" onClick={clear}>Clear</button>}</div><FilterFields value={draft} facets={facets} setValue={updateDraft} prefix="desktop"/><button className="raoza-button raoza-filter-apply">Apply filters</button></form></aside>
                <div className="raoza-catalog-results">
                    {products.data.length?<div className="raoza-catalog-grid">{products.data.map((product,index)=><ProductCard key={product.id} product={product} priority={index<2}/>)}</div>:<div className="raoza-catalog-empty"><h2>No pieces found.</h2><p>Adjust the filters or return to the full edit.</p><button type="button" onClick={clear} className="raoza-button raoza-button-outline">Clear filters</button></div>}
                    {(products.prev_page_url||products.next_page_url)&&<nav aria-label="Product pages" className="raoza-catalog-pagination">{products.prev_page_url&&<Link href={products.prev_page_url} preserveScroll rel="prev" className="raoza-button raoza-button-outline">Previous</Link>}<span>Page {products.current_page} / {products.last_page}</span>{products.next_page_url&&<Link href={products.next_page_url} preserveScroll rel="next" className="raoza-button">Next</Link>}</nav>}
                </div>
            </div>
        </section>
        <dialog ref={dialog} className="raoza-filter-dialog" aria-labelledby="mobile-filter-title"><form method="dialog" className="raoza-filter-dialog-bar"><h2 id="mobile-filter-title">Filter the edit</h2><button aria-label="Close filters">×</button></form><form onSubmit={e=>{e.preventDefault();dialog.current?.close();apply()}}><FilterFields value={draft} facets={facets} setValue={updateDraft} prefix="mobile"/><div className="raoza-filter-dialog-actions"><button type="button" onClick={()=>{dialog.current?.close();clear()}} className="raoza-button raoza-button-outline">Clear</button><button className="raoza-button">Apply filters</button></div></form></dialog>
    </main></StorefrontLayout>;
}
