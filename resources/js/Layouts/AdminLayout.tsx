import { Link, router, usePage } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

const nav = [
    { section: 'Dashboard', items: [['Overview', '/admin']] },
    { section: 'Catalog', items: [['Products', '/admin/products'], ['Categories', '/admin/categories'], ['Collections', '/admin/collections']] },
    { section: 'Inventory', items: [['Stock & movements', '/admin/inventory']] },
    { section: 'Sales', items: [['Orders', '/admin/orders'], ['Production & shipping', '/admin/fulfillment'], ['Returns & refunds', '/admin/returns']] },
    { section: 'Customers', items: [['Customer records', '/admin/customers']] },
    { section: 'Marketing', items: [['Discounts', '/admin/discounts'], ['Content', '/admin/content']] },
    { section: 'Store', items: [['Store & shipping', '/admin/store']] },
] as const;

export default function AdminLayout({ children }: PropsWithChildren) {
    const page = usePage<{ flash?: { success?: string } }>();
    const active = (href: string) => href === '/admin' ? page.url === href : page.url === href || page.url.startsWith(`${href}/`);
    const current = nav.flatMap(group => group.items.map(item => ({ section: group.section, name: item[0], href: item[1] }))).find(item => active(item.href));
    const logout = () => router.post('/logout');

    return <div className="min-h-screen bg-raoza-cream text-raoza-black">
        <a href="#admin-main" className="fixed left-3 top-3 z-[100] -translate-y-24 bg-raoza-cream px-4 py-3 text-xs text-raoza-primary focus:translate-y-0">Skip to admin content</a>
        <aside className="border-b border-raoza-primary/15 bg-raoza-primary text-raoza-cream lg:fixed lg:inset-y-0 lg:w-64 lg:border-b-0 lg:border-r">
            <div className="p-6">
                <Link href="/admin" className="font-display text-3xl tracking-[.16em]">RAOZA</Link>
                <p className="mt-1 text-[10px] uppercase tracking-[.25em] opacity-70">Operations</p>
            </div>
            <nav aria-label="Admin" className="flex gap-5 overflow-x-auto px-3 pb-4 lg:block lg:max-h-[calc(100vh-190px)] lg:space-y-5 lg:overflow-y-auto">
                {nav.map(group => <div key={group.section} className="shrink-0"><p className="mb-1 px-3 text-[9px] font-semibold uppercase tracking-[.2em] text-raoza-gold">{group.section}</p>{group.items.map(([name, href]) => {
                    const isCurrent = active(href);
                    return <Link key={href} href={href} aria-current={isCurrent ? 'page' : undefined} className={`block whitespace-nowrap px-3 py-2 text-sm ${isCurrent ? 'raoza-admin-nav-active' : 'hover:bg-white/10'}`}>{name}</Link>;
                })}</div>)}
                <button type="button" onClick={logout} className="whitespace-nowrap border border-raoza-cream/40 px-3 py-2 text-sm lg:hidden">Logout</button>
            </nav>
            <button type="button" onClick={logout} className="m-6 hidden border border-raoza-cream/40 px-4 py-2 text-xs uppercase tracking-widest transition-colors hover:bg-raoza-cream hover:text-raoza-primary lg:block">Logout</button>
        </aside>
        <main id="admin-main" className="lg:ml-64">
            <header className="flex items-center justify-between border-b border-raoza-primary/10 px-5 py-4 lg:px-8">
                <span className="text-xs uppercase tracking-[.18em] text-raoza-secondary">{current ? `${current.section} / ${current.name}` : 'Admin'}</span>
                <Link href="/" className="text-sm underline underline-offset-4">View store</Link>
            </header>
            <div className="p-5 lg:p-8">
                {page.props.flash?.success && <div role="status" className="mb-5 border border-raoza-primary/20 bg-white p-3 text-sm">{page.props.flash.success}</div>}
                {children}
            </div>
        </main>
    </div>;
}
