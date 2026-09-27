import { Link } from '@inertiajs/react';
import Money from './Money';
export type ProductCardData = { id:number; name:string; slug:string; price:number; priceVaries:boolean; image:string|null; imageAlt:string; available:boolean };
export default function ProductCard({ product }: { product: ProductCardData }) {
 return <article className="group min-w-0">
  <Link href={`/products/${product.slug}`} className="relative block aspect-[4/5] overflow-hidden bg-[#efe4d2]">
   {product.image?<img src={product.image} alt={product.imageAlt} className="h-full w-full object-cover transition duration-700 ease-out group-hover:scale-[1.025]" loading="lazy"/>:<div className="flex h-full flex-col items-center justify-center border border-raoza-primary/10 px-6 text-center"><span className="font-display text-3xl tracking-[.12em] text-raoza-primary/28 md:text-4xl">RAOZA</span><span className="mt-3 text-[8px] uppercase tracking-[.22em] text-raoza-primary/35">Product image</span></div>}
   {!product.available&&<span className="absolute left-3 top-3 bg-raoza-cream px-3 py-2 text-[9px] font-semibold uppercase tracking-[.17em] text-raoza-primary">Out of stock</span>}
  </Link>
  <div className="flex items-start justify-between gap-3 pt-4"><div className="min-w-0"><Link href={`/products/${product.slug}`} className="block truncate text-sm font-semibold transition-colors hover:text-raoza-primary">{product.name}</Link><p className="mt-1 text-[9px] uppercase tracking-[.14em] text-raoza-black/45">RAOZA Apparel</p></div><p className="shrink-0 text-sm">{product.priceVaries&&<span className="mr-1 text-[10px] text-raoza-black/45">From</span>}<Money amount={product.price}/></p></div>
 </article>;
}
