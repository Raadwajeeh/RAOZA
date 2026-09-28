import { router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import Money from '../../Components/Money';
import SeoHead, { Seo } from '../../Components/SeoHead';
import StorefrontLayout from '../../Layouts/StorefrontLayout';

type Image={id?:number;url:string;alt:string};
type OptionValue={id:number;value:string;metadata?:unknown};
type Option={id:number;name:string;values:OptionValue[]};
type Variant={id:number;sku:string;price:number;availableQuantity:number;available:boolean;optionValueIds:number[];options:Record<string,string>;images:Image[]};
type Product={id:number;name:string;slug:string;description:string|null;seoTitle:string|null;seoDescription:string|null;basePrice:number;images:Image[];options:Option[];variants:Variant[]};

export default function ProductPage({product,seo}:{product:Product;seo:Seo}) {
    const [selected,setSelected]=useState<Record<number,number>>({});
    const [quantity,setQuantity]=useState(1);
    const [adding,setAdding]=useState(false);
    const [activeImage,setActiveImage]=useState(0);
    const page=usePage<{errors?:Record<string,string>;flash?:{success?:string}}>();

    const selectedIds=Object.values(selected);
    const variant=useMemo(
        ()=>product.variants.find(v=>v.optionValueIds.length===selectedIds.length&&v.optionValueIds.every(id=>selectedIds.includes(id))),
        [product.variants,selectedIds.join(',')]
    );
    const images=variant?.images.length?variant.images:product.images;
    const complete=product.options.length===selectedIds.length;
    const maxQuantity=variant?.available?Math.min(20,variant.availableQuantity):1;

    useEffect(()=>{setActiveImage(0);setQuantity(1)},[variant?.id]);
    useEffect(()=>{if(activeImage>=images.length)setActiveImage(0)},[images.length,activeImage]);

    const valuePossible=(optionId:number,valueId:number)=>{
        const candidate={...selected,[optionId]:valueId};
        const ids=Object.values(candidate);
        return product.variants.some(v=>ids.every(id=>v.optionValueIds.includes(id)));
    };

    const choose=(optionId:number,valueId:number)=>{
        const candidate={...selected,[optionId]:valueId};
        const candidateIds=Object.values(candidate);
        if(product.variants.some(v=>candidateIds.every(id=>v.optionValueIds.includes(id)))) {
            setSelected(candidate);
            return;
        }
        // A shopper may change an earlier choice even when a later choice conflicts.
        // Keep the new choice and discard only selections that cannot coexist with it.
        const next:Record<number,number>={[optionId]:valueId};
        Object.entries(selected).forEach(([key,currentValue])=>{
            const id=Number(key);
            if(id===optionId)return;
            const trialIds=Object.values({...next,[id]:currentValue});
            if(product.variants.some(v=>trialIds.every(value=>v.optionValueIds.includes(value)))) next[id]=currentValue;
        });
        setSelected(next);
    };

    const stockMessage=variant?.available
        ? variant.availableQuantity<=3?`Only ${variant.availableQuantity} left`:'In stock'
        : variant?'Out of stock':'Choose a variant';

    const renderImage=(image:Image|undefined, decorative=false)=>(
        image?.url
            ? <img src={image.url} alt={decorative?'':image.alt} className="h-full w-full object-cover"/>
            : <div className="flex h-full items-center justify-center font-display text-5xl text-raoza-primary/20">RAOZA</div>
    );

    return <StorefrontLayout><SeoHead seo={seo}/>
        <main className="raoza-product-page">
            <div className="raoza-product-shell">
                <section className="raoza-orbit-gallery" aria-label={`${product.name} gallery`}>
                    <div className="raoza-orbit-line raoza-orbit-line-a" aria-hidden="true"/>
                    <div className="raoza-orbit-line raoza-orbit-line-b" aria-hidden="true"/>
                    <div className="raoza-orbit-main">
                        {renderImage(images[activeImage])}
                        <span className="raoza-orbit-index" aria-hidden="true">{String(activeImage+1).padStart(2,'0')}</span>
                    </div>
                    {images.length>1&&<div className="raoza-orbit-thumbs" aria-label="Product images">
                        {images.slice(0,5).map((image,i)=><button type="button" key={`${image.url}-${i}`} onClick={()=>setActiveImage(i)} aria-label={`View image ${i+1}`} aria-pressed={i===activeImage} className={`raoza-orbit-thumb raoza-orbit-thumb-${i+1} ${i===activeImage?'is-active':''}`}>
                            {renderImage(image,true)}
                        </button>)}
                    </div>}
                    <p className="raoza-orbit-caption">RAOZA / EDIT {String(product.id).padStart(2,'0')}</p>
                </section>

                <section className="raoza-product-info">
                    <div className="raoza-product-breadcrumb">RAOZA <span>/</span> Printed apparel</div>
                    <h1 className="raoza-product-title">{product.name}</h1>
                    <p className="raoza-product-price"><Money amount={variant?.price??product.basePrice}/></p>

                    <div className="raoza-product-options">{product.options.map(option=><fieldset key={option.id} className="raoza-product-option"><legend><span>{option.name}</span><span>{option.values.find(v=>v.id===selected[option.id])?.value||'Select'}</span></legend><div className="raoza-option-values">{option.values.map(value=>{const possible=valuePossible(option.id,value.id);const active=selected[option.id]===value.id;return <button type="button" key={value.id} disabled={!possible&&Object.keys(selected).length===0} aria-pressed={active} onClick={()=>choose(option.id,value.id)} className={`${active?'is-active':''} ${!possible?'is-conflict':''}`}>{value.value}</button>})}</div></fieldset>)}</div>

                    <div className="raoza-purchase-panel">
                        {complete&&!variant&&<p className="raoza-product-alert" role="alert">This combination is not available.</p>}
                        {variant&&!variant.available&&<p className="raoza-product-alert" role="alert">This variant is currently out of stock.</p>}
                        {variant?.available&&<div className="raoza-quantity-row"><label htmlFor="product-quantity">Quantity</label><select id="product-quantity" value={quantity} onChange={e=>setQuantity(Number(e.target.value))}>{Array.from({length:maxQuantity},(_,i)=>i+1).map(q=><option key={q} value={q}>{q}</option>)}</select></div>}
                        <button disabled={!complete||!variant?.available||adding} onClick={()=>{if(!variant)return;setAdding(true);router.post('/cart/items',{variant_id:variant.id,quantity},{preserveScroll:true,onFinish:()=>setAdding(false)})}} className="raoza-button raoza-product-add">{adding?'Adding…':!complete?'Select options':variant?.available?'Add to bag':'Out of stock'}</button>
                        {page.props.errors?.quantity&&<p className="raoza-product-alert" role="alert">{page.props.errors.quantity}</p>}{page.props.errors?.variant&&<p className="raoza-product-alert" role="alert">{page.props.errors.variant}</p>}{page.props.flash?.success&&<p className="raoza-product-success" role="status">{page.props.flash.success}</p>}
                    </div>

                    <div className="raoza-product-meta"><div><span>Availability</span><strong className={variant?.available&&variant.availableQuantity<=3?'is-low':''}>{stockMessage}</strong></div><div><span>SKU</span><strong>{variant?.sku||'—'}</strong></div></div>

                    {product.description&&<details className="raoza-product-details" open><summary>Description <span>+</span></summary><p>{product.description}</p></details>}
                    <details className="raoza-product-details"><summary>Size & fit <span>+</span></summary><p>Choose the available size above. Final fit information will be added with the production product data.</p></details>
                    <details className="raoza-product-details"><summary>Delivery & returns <span>+</span></summary><p>Delivery and return information is shown according to the final store policy at checkout.</p></details>
                </section>
            </div>
        </main>
    </StorefrontLayout>;
}
