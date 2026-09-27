import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, useEffect, useState } from 'react';
import ConsentBanner from '../Components/ConsentBanner';

const NavLink=({href,children,onClick}:{href:string;children:React.ReactNode;onClick?:()=>void})=><Link href={href} onClick={onClick} className="transition-opacity hover:opacity-55">{children}</Link>;
export default function StorefrontLayout({ children }: PropsWithChildren) {
 const [open,setOpen]=useState(false); const page=usePage<{cart?:{count:number};demo?:{enabled:boolean}}>(); const cartCount=page.props.cart?.count??0; const demo=page.props.demo?.enabled??false;
 useEffect(()=>{setOpen(false)},[page.url]);
 return <div className="min-h-screen bg-raoza-cream text-raoza-black">
  <a href="#main-content" className="fixed left-3 top-3 z-[100] -translate-y-24 bg-raoza-primary px-4 py-3 text-xs text-raoza-cream focus:translate-y-0">Skip to content</a>
  {demo&&<div className="bg-raoza-gold px-4 py-2 text-center text-[9px] font-bold uppercase tracking-[.18em] text-raoza-primary">Local demo mode · sample products, rates and policies · no real payment</div>}<div className="bg-raoza-primary px-4 py-2.5 text-center text-[9px] font-semibold uppercase tracking-[.22em] text-raoza-cream sm:text-[10px]">Design-led printed apparel · Netherlands</div>
  <header className="sticky top-0 z-40 border-b border-raoza-primary/15 bg-raoza-cream/95 backdrop-blur-md">
   <div className="raoza-container grid h-[72px] grid-cols-[1fr_auto_1fr] items-center md:h-[82px]">
    <button className="justify-self-start text-[10px] font-semibold uppercase tracking-[.18em] md:hidden" onClick={()=>setOpen(!open)} aria-expanded={open} aria-controls="mobile-nav">{open?'Close':'Menu'}</button>
    <nav className="hidden items-center gap-7 text-[10px] font-semibold uppercase tracking-[.17em] md:flex"><NavLink href="/shop">Shop</NavLink><NavLink href="/categories/t-shirts">T-Shirts</NavLink><NavLink href="/categories/hoodies">Hoodies</NavLink></nav>
    <Link href="/" aria-label="RAOZA home" className="font-display text-[27px] font-semibold tracking-[.13em] text-raoza-primary md:text-[31px]">RAOZA</Link>
    <div className="flex items-center justify-self-end gap-5 text-[10px] font-semibold uppercase tracking-[.17em]"><Link className="hidden transition-opacity hover:opacity-55 sm:block" href="/shop">Search</Link><Link className="transition-opacity hover:opacity-55" href="/cart">Bag <span aria-hidden="true">({cartCount})</span><span className="sr-only">with {cartCount} items</span></Link></div>
   </div>
   {open&&<nav id="mobile-nav" className="border-t border-raoza-primary/15 bg-raoza-cream px-5 py-8 md:hidden"><div className="flex flex-col gap-6 font-display text-4xl text-raoza-primary"><NavLink onClick={()=>setOpen(false)} href="/shop">Shop</NavLink><NavLink onClick={()=>setOpen(false)} href="/categories/t-shirts">T-Shirts</NavLink><NavLink onClick={()=>setOpen(false)} href="/categories/hoodies">Hoodies</NavLink><NavLink onClick={()=>setOpen(false)} href="/collections/drop-01">Collections</NavLink></div><div className="mt-8 border-t border-raoza-primary/15 pt-6 text-[10px] uppercase tracking-[.17em] text-raoza-black/55">RAOZA / Urban Editorial</div></nav>}
  </header>
  <div id="main-content">{children}</div>
  <footer className="mt-20 bg-raoza-primary text-raoza-cream md:mt-28"><div className="raoza-container grid gap-12 py-14 md:grid-cols-[1.5fr_1fr_1fr] md:py-20"><div><div className="font-display text-4xl tracking-[.12em] md:text-5xl">RAOZA</div><p className="mt-5 max-w-sm text-sm leading-7 text-raoza-cream/65">Design-led printed apparel with a modern urban editorial point of view.</p></div><div><p className="text-[10px] uppercase tracking-[.2em] text-raoza-gold">Explore</p><div className="mt-5 flex flex-col gap-3 text-sm"><NavLink href="/shop">Shop</NavLink><NavLink href="/collections/drop-01">Collections</NavLink><NavLink href="/cart">Bag</NavLink></div></div><div><p className="text-[10px] uppercase tracking-[.2em] text-raoza-gold">Information</p><div className="mt-5 flex flex-col gap-3 text-sm"><NavLink href="/pages/about">About</NavLink><NavLink href="/pages/shipping">Shipping & delivery</NavLink><NavLink href="/pages/returns">Returns & refunds</NavLink></div><p className="mt-6 text-sm text-raoza-cream/60">hello@raoza.nl</p></div></div><div className="border-t border-raoza-cream/15"><div className="raoza-container flex flex-col gap-2 py-5 text-[9px] uppercase tracking-[.16em] text-raoza-cream/50 sm:flex-row sm:justify-between"><span>© {new Date().getFullYear()} RAOZA</span><span>Built for considered everyday wear</span></div></div></footer>
  <ConsentBanner/>
 </div>;
}
