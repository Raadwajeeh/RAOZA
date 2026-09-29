import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, useEffect, useState } from 'react';
import ConsentBanner from '../Components/ConsentBanner';

type NavItem = { name: string; slug: string };
const NavLink = ({ href, children, onClick, className = '', active = false }: { href: string; children: React.ReactNode; onClick?: () => void; className?: string; active?: boolean }) => <Link href={href} onClick={onClick} aria-current={active ? 'page' : undefined} className={`raoza-nav-link ${active ? 'is-active' : ''} ${className}`}>{children}</Link>;

export default function StorefrontLayout({ children }: PropsWithChildren) {
    const [open, setOpen] = useState(false);
    const [scrolled, setScrolled] = useState(false);
    const page = usePage<{ cart?: { count: number }; store?: { brand_name: string; support_email: string; customer_service: string; instagram_url?: string | null }; navigation?: { categories: NavItem[]; collections: NavItem[] } }>();
    const cartCount = page.props.cart?.count ?? 0;
    const store = page.props.store ?? { brand_name: 'RAOZA', support_email: 'hello@raoza.nl', customer_service: '' };
    const categories = page.props.navigation?.categories ?? [];
    const collections = page.props.navigation?.collections ?? [];
    const path = page.url.split('?')[0];
    const active = (href: string) => href === '/shop' ? path === '/shop' || path.startsWith('/products/') : path === href;

    useEffect(() => { setOpen(false); }, [page.url]);
    useEffect(() => { document.body.style.overflow = open ? 'hidden' : ''; return () => { document.body.style.overflow = ''; }; }, [open]);
    useEffect(() => { const onScroll = () => setScrolled(window.scrollY > 12); onScroll(); window.addEventListener('scroll', onScroll, { passive: true }); return () => window.removeEventListener('scroll', onScroll); }, []);
    useEffect(() => { const onKey = (event: KeyboardEvent) => event.key === 'Escape' && setOpen(false); window.addEventListener('keydown', onKey); return () => window.removeEventListener('keydown', onKey); }, []);

    return <div className="min-h-screen bg-raoza-cream text-raoza-black">
        <a href="#main-content" className="fixed left-3 top-3 z-[100] -translate-y-24 bg-raoza-primary px-4 py-3 text-xs text-raoza-cream focus:translate-y-0">Skip to content</a>
        <div className="raoza-announcement"><span>Design-led printed apparel</span><span aria-hidden="true">✦</span><span>Free delivery is shown when available at checkout</span></div>
        <header className={`raoza-site-header ${scrolled ? 'is-scrolled' : ''}`}>
            <div className="raoza-header-inner">
                <button type="button" className="raoza-menu-button" onClick={() => setOpen(!open)} aria-expanded={open} aria-controls="mobile-nav" aria-label={open ? 'Close menu' : 'Open menu'}><span className={open ? 'is-open' : ''} /><span className={open ? 'is-open' : ''} /><b>{open ? 'Close' : 'Menu'}</b></button>
                <nav aria-label="Primary" className="raoza-desktop-nav raoza-desktop-nav-left">
                    <NavLink href="/shop" active={active('/shop')}>Shop</NavLink>
                    {categories.map(category => <NavLink key={category.slug} href={`/categories/${category.slug}`} active={path === `/categories/${category.slug}`}>{category.name}</NavLink>)}
                </nav>
                <Link href="/" aria-label="RAOZA home" className="raoza-header-logo"><img src="/brand/raoza-wordmark-burgundy.png" alt="RAOZA" width="592" height="117" /></Link>
                <nav aria-label="Brand and collections" className="raoza-desktop-nav raoza-desktop-nav-right">
                    {collections.map(collection => <NavLink key={collection.slug} href={`/collections/${collection.slug}`} active={path === `/collections/${collection.slug}`}>{collection.name}</NavLink>)}
                    <NavLink href="/pages/about" active={path === '/pages/about'}>About</NavLink>
                </nav>
                <NavLink href="/cart" className="raoza-cart-link" active={path === '/cart'}>Bag <span aria-hidden="true">({String(cartCount).padStart(2, '0')})</span><span className="sr-only"> with {cartCount} items</span></NavLink>
            </div>
            <nav aria-label="Mobile" id="mobile-nav" aria-hidden={!open} className={`raoza-mobile-nav ${open ? 'is-open' : ''}`}>
                <div className="raoza-mobile-primary"><span>Menu / RAOZA</span><NavLink href="/shop">Shop all</NavLink>{categories.map(category => <NavLink key={category.slug} href={`/categories/${category.slug}`}>{category.name}</NavLink>)}</div>
                <div className="raoza-mobile-secondary"><div><p>Collections</p>{collections.map(collection => <NavLink key={collection.slug} href={`/collections/${collection.slug}`}>{collection.name}</NavLink>)}</div><div><p>RAOZA</p><NavLink href="/pages/about">About</NavLink><NavLink href="/pages/contact">Contact</NavLink><NavLink href="/pages/faq">Help & FAQ</NavLink></div></div>
                <div className="raoza-mobile-foot"><NavLink href="/cart">Bag ({String(cartCount).padStart(2, '0')})</NavLink><span>Urban Editorial / NL</span></div>
            </nav>
        </header>
        <div id="main-content">{children}</div>
        <footer className="raoza-footer">
            <div className="raoza-footer-lead raoza-container"><div><p>RAOZA / Urban Editorial</p><img src="/brand/raoza-wordmark-light.png" alt="RAOZA" width="592" height="117" /></div><p>Design-led printed apparel with a modern, urban and editorial point of view.</p></div>
            <div className="raoza-footer-grid raoza-container">
                <div><p className="raoza-footer-label">Shop</p><NavLink href="/shop">All apparel</NavLink>{categories.map(category => <NavLink key={category.slug} href={`/categories/${category.slug}`}>{category.name}</NavLink>)}{collections.map(collection => <NavLink key={collection.slug} href={`/collections/${collection.slug}`}>{collection.name}</NavLink>)}</div>
                <div><p className="raoza-footer-label">Customer care</p><NavLink href="/pages/contact">Contact</NavLink><NavLink href="/pages/faq">Help & FAQ</NavLink><NavLink href="/pages/size-guide">Size guide</NavLink><NavLink href="/pages/shipping">Shipping & delivery</NavLink><NavLink href="/pages/returns">Returns & refunds</NavLink></div>
                <div><p className="raoza-footer-label">RAOZA</p><NavLink href="/pages/about">About RAOZA</NavLink><a href={`mailto:${store.support_email}`}>{store.support_email}</a>{store.instagram_url && <a href={store.instagram_url} rel="noreferrer" target="_blank">Instagram</a>}</div>
                <div><p className="raoza-footer-label">Legal</p><NavLink href="/pages/privacy">Privacy</NavLink><NavLink href="/pages/cookies">Cookies</NavLink><NavLink href="/pages/terms">Terms & conditions</NavLink><button type="button" onClick={() => window.dispatchEvent(new Event('raoza:cookie-preferences'))}>Cookie preferences</button></div>
            </div>
            <div className="raoza-footer-bottom raoza-container"><span>© {new Date().getFullYear()} {store.brand_name}</span><span>Deep Burgundy / Warm Cream</span><span>Netherlands</span></div>
        </footer>
        <ConsentBanner />
    </div>;
}
