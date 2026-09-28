import { Link } from '@inertiajs/react';
import Money from './Money';
export type ProductCardData = { id:number; name:string; slug:string; price:number; priceVaries:boolean; image:string|null; imageAlt:string; available:boolean };
export default function ProductCard({ product, priority=false }: { product: ProductCardData; priority?:boolean }) {
 return <article className="group min-w-0">
  <Link href={`/products/${product.slug}`} className="raoza-card-media relative block aspect-[4/5] overflow-hidden bg-[#efe4d2]" aria-label={`View ${product.name}`}>
   {product.image?<img src={product.image} alt={product.imageAlt} className="h-full w-full object-cover transition duration-700 ease-out group-hover:scale-[1.035]" loading={priority?'eager':'lazy'}/>:<div className="flex h-full flex-col items-center justify-center border border-raoza-primary/10 px-6 text-center"><span className="font-display text-3xl tracking-[.12em] text-raoza-primary/28 md:text-4xl">RAOZA</span><span className="mt-3 text-[8px] uppercase tracking-[.22em] text-raoza-primary/35">Product image</span></div>}
   <span className="absolute inset-x-0 bottom-0 translate-y-full bg-raoza-primary px-4 py-3 text-center text-[9px] font-bold uppercase tracking-[.18em] text-raoza-cream transition-transform duration-300 group-hover:translate-y-0 max-md:hidden">View piece</span>
   {!product.available&&<span className="absolute left-3 top-3 bg-raoza-cream px-3 py-2 text-[9px] font-semibold uppercase tracking-[.17em] text-raoza-primary">Out of stock</span>}
  </Link>
  <div className="flex flex-col items-start gap-2 pt-4 transition-transform duration-300 group-hover:translate-x-0.5 sm:flex-row sm:justify-between sm:gap-3"><div className="min-w-0"><Link href={`/products/${product.slug}`} className="block text-xs font-semibold leading-5 transition-colors hover:text-raoza-primary sm:text-sm">{product.name}</Link><p className="mt-1 text-[9px] uppercase tracking-[.14em] text-raoza-black/45">RAOZA Apparel</p></div><p className="shrink-0 text-sm">{product.priceVaries&&<span className="mr-1 text-[10px] text-raoza-black/45">From</span>}<Money amount={product.price}/></p></div>
 </article>;
}
