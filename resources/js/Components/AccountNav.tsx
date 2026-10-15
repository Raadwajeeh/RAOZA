import { Link, usePage } from '@inertiajs/react';

const links = [
    { href: '/account', label: 'Orders' },
    { href: '/account/addresses', label: 'Addresses' },
    { href: '/account/returns', label: 'Returns' },
    { href: '/account/settings', label: 'Account settings' },
];

export default function AccountNav() {
    const path = usePage().url.split('?')[0];

    return <nav aria-label="Customer account" className="mt-8 flex gap-2 overflow-x-auto border-y border-raoza-primary/20 py-3">
        {links.map(link => {
            const active = link.href === '/account' ? path === '/account' || path.startsWith('/account/orders/') : path.startsWith(link.href);
            return <Link key={link.href} href={link.href} aria-current={active ? 'page' : undefined} className={`whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wider ${active ? 'bg-raoza-primary text-raoza-cream' : 'hover:bg-white'}`}>{link.label}</Link>;
        })}
    </nav>;
}
