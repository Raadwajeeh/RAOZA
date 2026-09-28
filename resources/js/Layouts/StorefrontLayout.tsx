import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, useEffect, useState } from 'react';
import ConsentBanner from '../Components/ConsentBanner';

const NavLink=({href,children,onClick,className='',active=false}:{href:string;children:React.ReactNode;onClick?:()=>void;className?:string;active?:boolean})=><Link href={href} onClick={onClick} aria-current={active?'page':undefined} className={`transition-opacity hover:opacity-55 ${active?'font-bold underline decoration-raoza-gold decoration-2 underline-offset-8':''} ${className}`}>{children}</Link>;

export default function StorefrontLayout({ children }: PropsWithChildren) {
 const [open,setOpen]=useState(false);
 const [scrolled,setScrolled]=useState(false);
 const page=usePage<{cart?:{count:number};demo?:{enabled:boolean};navigation?:{categories:{name:string;slug:string}[];collections:{name:string;slug:string}[]}}>();
 const cartCount=page.props.cart?.count??0;
 const demo=page.props.demo?.enabled??false;
 const categories=page.props.navigation?.categories??[];
 const collections=page.props.navigation?.collections??[];
 const collection=collections[0];
 const path=page.url.split('?')[0];
 const catalogActive=path==='/shop'||path.startsWith('/products/')||path.startsWith('/categories/')||path.startsWith('/collections/');
 useEffect(()=>{setOpen(false)},[page.url]);
 useEffect(()=>{document.body.style.overflow=open?'hidden':'';return()=>{document.body.style.overflow=''}},[open]);
 useEffect(()=>{const onScroll=()=>setScrolled(window.scrollY>18);onScroll();window.addEventListener('scroll',onScroll,{passive:true});return()=>window.removeEventListener('scroll',onScroll)},[]);
 return <div className="min-h-screen bg-raoza-cream text-raoza-black">
  <a href="#main-content" className="fixed left-3 top-3 z-[100] -translate-y-24 bg-raoza-primary px-4 py-3 text-xs text-raoza-cream focus:translate-y-0">Skip to content</a>
  {demo&&<div className="bg-raoza-gold px-4 py-2 text-center text-[9px] font-bold uppercase tracking-[.18em] text-raoza-primary">Local demo mode · sample products, rates and policies · no real payment</div>}
  <div className="bg-raoza-primary px-4 py-2.5 text-center text-[9px] font-semibold uppercase tracking-[.22em] text-raoza-cream sm:text-[10px]">Design-led printed apparel · Netherlands</div>
  <header className={`sticky top-0 z-40 border-b border-raoza-primary/15 bg-raoza-cream/95 backdrop-blur-md transition-shadow duration-300 ${scrolled?'shadow-[0_8px_30px_rgba(51,3,19,.06)]':''}`}>
   <div className="raoza-container grid h-[68px] grid-cols-[1fr_auto_1fr] items-center md:h-[82px]">
    <button className="justify-self-start text-[10px] font-semibold uppercase tracking-[.18em] md:hidden" onClick={()=>setOpen(!open)} aria-expanded={open} aria-controls="mobile-nav">{open?'Close':'Menu'}</button>
    <nav aria-label="Primary" className="hidden items-center gap-7 text-[10px] font-semibold uppercase tracking-[.17em] md:flex"><NavLink href="/" active={path==='/' }>Home</NavLink><NavLink href="/shop" active={catalogActive}>Shop</NavLink></nav>
    <Link href="/" aria-label="RAOZA home" className="font-display text-[27px] font-semibold tracking-[.13em] text-raoza-primary md:text-[31px]">RAOZA</Link>
    <div className="flex items-center justify-self-end gap-5 text-[10px] font-semibold uppercase tracking-[.17em]"><NavLink className="hidden sm:block" href="/shop?q=">Search</NavLink><NavLink href="/cart">Bag <span aria-hidden="true">({cartCount})</span><span className="sr-only">with {cartCount} items</span></NavLink></div>
   </div>
   <nav aria-label="Shop sections" className="hidden border-t border-raoza-primary/10 md:block"><div className="raoza-container flex h-11 items-center gap-7 overflow-x-auto text-[9px] font-semibold uppercase tracking-[.17em]"><span className="text-raoza-black/40">Categories</span>{categories.map(category=><NavLink key={category.slug} active={path===`/categories/${category.slug}`} href={`/categories/${category.slug}`}>{category.name}</NavLink>)}<span className="ml-2 text-raoza-black/40">Collections</span>{collections.map(collection=><NavLink key={collection.slug} active={path===`/collections/${collection.slug}`} href={`/collections/${collection.slug}`}>{collection.name}</NavLink>)}</div></nav>
   {open&&<nav aria-label="Mobile" id="mobile-nav" className="fixed inset-x-0 top-full h-[calc(100svh-68px)] overflow-y-auto border-t border-raoza-primary/15 bg-raoza-cream px-5 py-9 md:hidden"><div className="flex flex-col gap-5 font-display text-[clamp(2.7rem,13vw,4.4rem)] leading-none text-raoza-primary"><NavLink href="/" active={path==='/' }>Home</NavLink><NavLink href="/shop" active={catalogActive}>Shop</NavLink></div><div className="mt-10 border-t border-raoza-primary/15 pt-6"><p className="mb-4 text-[9px] font-semibold uppercase tracking-[.2em] text-raoza-black/45">Categories</p><div className="flex flex-col gap-4 font-display text-3xl text-raoza-primary">{categories.map(category=><NavLink key={category.slug} active={path===`/categories/${category.slug}`} href={`/categories/${category.slug}`}>{category.name}</NavLink>)}</div><p className="mb-4 mt-7 text-[9px] font-semibold uppercase tracking-[.2em] text-raoza-black/45">Collections</p><div className="flex flex-col gap-4 font-display text-3xl text-raoza-primary">{collections.map(collection=><NavLink key={collection.slug} active={path===`/collections/${collection.slug}`} href={`/collections/${collection.slug}`}>{collection.name}</NavLink>)}</div></div><div className="mt-10 grid grid-cols-2 gap-4 border-t border-raoza-primary/15 pt-6 text-[10px] uppercase tracking-[.17em]"><NavLink href="/pages/about">About</NavLink><NavLink href="/pages/shipping">Shipping</NavLink><NavLink href="/pages/returns">Returns</NavLink><NavLink href="/cart">Bag ({cartCount})</NavLink></div><div className="mt-10 text-[9px] uppercase tracking-[.2em] text-raoza-black/45">RAOZA / Urban Editorial / NL</div></nav>}
  </header>
  <div id="main-content">{children}</div>
  <footer className="mt-20 bg-raoza-primary text-raoza-cream md:mt-28"><div className="raoza-container grid gap-12 py-14 md:grid-cols-[1.5fr_1fr_1fr] md:py-20"><div><div className="font-display text-4xl tracking-[.12em] md:text-5xl">RAOZA</div><p className="mt-5 max-w-sm text-sm leading-7 text-raoza-cream/65">Design-led printed apparel with a modern urban editorial point of view.</p></div><div><p className="text-[10px] uppercase tracking-[.2em] text-raoza-gold">Explore</p><div className="mt-5 flex flex-col gap-3 text-sm"><NavLink href="/shop">Shop</NavLink>{collection&&<NavLink href={`/collections/${collection.slug}`}>{collection.name}</NavLink>}<NavLink href="/cart">Bag</NavLink></div></div><div><p className="text-[10px] uppercase tracking-[.2em] text-raoza-gold">Information</p><div className="mt-5 flex flex-col gap-3 text-sm"><NavLink href="/pages/about">About</NavLink><NavLink href="/pages/shipping">Shipping & delivery</NavLink><NavLink href="/pages/returns">Returns & refunds</NavLink></div><p className="mt-6 text-sm text-raoza-cream/60">hello@raoza.nl</p></div></div><div className="border-t border-raoza-cream/15"><div className="raoza-container flex flex-col gap-2 py-5 text-[9px] uppercase tracking-[.16em] text-raoza-cream/50 sm:flex-row sm:justify-between"><span>© {new Date().getFullYear()} RAOZA</span><span>Built for considered everyday wear</span></div></div></footer>
  <ConsentBanner/>
 </div>;
}
