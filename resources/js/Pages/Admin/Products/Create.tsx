import { Head, Link, useForm } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';

const field = 'mt-1 min-h-11 w-full border border-raoza-primary/20 bg-white px-3 py-2 text-sm focus:border-raoza-primary focus:outline-none focus:ring-2 focus:ring-raoza-gold/40';

export default function CreateProduct({ categories, collections }: any) {
    const form = useForm({
        name: '', slug: '', description: '', short_description: '', position: 0, base_price: 0, status: 'draft', published_at: '',
        category_ids: [] as number[], collection_ids: [] as number[], seo_title: '', seo_description: '',
        canonical_url: '', indexable: true,
    });
    const toggle = (key: 'category_ids' | 'collection_ids', id: number) => {
        const values = form.data[key];
        form.setData(key, values.includes(id) ? values.filter(value => value !== id) : [...values, id]);
    };

    return <AdminLayout>
        <Head title="Add product" />
        <div className="flex flex-wrap items-end justify-between gap-4">
            <div><Link href="/admin/products" className="text-sm underline">← Products</Link><h1 className="mt-2 font-display text-4xl text-raoza-primary sm:text-5xl">Add product</h1><p className="mt-2 max-w-2xl text-sm text-raoza-black/60">Start with the commercial essentials. After saving, the product workspace guides you through media, options, variants and inventory.</p></div>
            <span className="border border-raoza-primary/15 bg-white px-3 py-2 text-xs uppercase tracking-widest">Step 1 of 2</span>
        </div>
        <form onSubmit={event => { event.preventDefault(); form.post('/admin/products'); }} className="mt-8 grid gap-6 xl:grid-cols-[1fr_.7fr]">
            <div className="space-y-6">
                <section className="border border-raoza-primary/15 bg-white p-5 sm:p-6"><p className="text-xs uppercase tracking-[.18em] text-raoza-secondary">01 · Product</p><h2 className="mt-2 font-display text-2xl">Basic information</h2><div className="mt-5 grid gap-4 sm:grid-cols-2">
                    <label className="text-sm font-medium sm:col-span-2">Product name *<input autoFocus required className={field} value={form.data.name} onChange={e => form.setData('name', e.target.value)} /></label>
                    <label className="text-sm font-medium">Slug <input className={field} placeholder="Generated from name when empty" value={form.data.slug} onChange={e => form.setData('slug', e.target.value)} /></label>
                    <label className="text-sm font-medium">Base price (€) *<input required type="number" min="0" step="0.01" inputMode="decimal" className={field} value={(form.data.base_price / 100).toFixed(2)} onChange={e => form.setData('base_price', Math.round(Number(e.target.value) * 100))} /><span className="mt-1 block text-xs font-normal opacity-55">Enter the customer-facing price including cents.</span></label>
                    <label className="text-sm font-medium sm:col-span-2">Short description<textarea rows={2} className={field} value={form.data.short_description} onChange={e => form.setData('short_description', e.target.value)} /></label>
                    <label className="text-sm font-medium sm:col-span-2">Description<textarea rows={5} className={field} value={form.data.description} onChange={e => form.setData('description', e.target.value)} /></label>
                    <label className="text-sm font-medium">Catalog position<input type="number" min="0" max="10000" className={field} value={form.data.position} onChange={e => form.setData('position', Number(e.target.value))} /></label>
                </div></section>
                <section className="border border-raoza-primary/15 bg-white p-5 sm:p-6"><p className="text-xs uppercase tracking-[.18em] text-raoza-secondary">02 · Placement</p><h2 className="mt-2 font-display text-2xl">Categories & collections</h2><div className="mt-5 grid gap-5 sm:grid-cols-2">
                    <fieldset><legend className="text-sm font-semibold">Categories</legend><div className="mt-2 space-y-2">{categories.map((item: any) => <label key={item.id} className="flex min-h-11 items-center gap-3 border p-3 text-sm"><input type="checkbox" checked={form.data.category_ids.includes(item.id)} onChange={() => toggle('category_ids', item.id)} />{item.name}<span className="ml-auto text-xs opacity-50">{item.status}</span></label>)}</div></fieldset>
                    <fieldset><legend className="text-sm font-semibold">Collections</legend><div className="mt-2 space-y-2">{collections.map((item: any) => <label key={item.id} className="flex min-h-11 items-center gap-3 border p-3 text-sm"><input type="checkbox" checked={form.data.collection_ids.includes(item.id)} onChange={() => toggle('collection_ids', item.id)} />{item.name}<span className="ml-auto text-xs opacity-50">{item.status}</span></label>)}</div></fieldset>
                </div></section>
            </div>
            <div className="space-y-6">
                <section className="border border-raoza-primary/15 bg-white p-5 sm:p-6"><p className="text-xs uppercase tracking-[.18em] text-raoza-secondary">03 · Publishing</p><h2 className="mt-2 font-display text-2xl">Visibility</h2><div className="mt-5 space-y-4">
                    <label className="block text-sm font-medium">Status<select className={field} value={form.data.status} onChange={e => form.setData('status', e.target.value)}><option value="draft">Draft — hidden</option><option value="active">Active — publish</option><option value="archived">Archived — hidden</option></select></label>
                    {form.data.status === 'active' && <label className="block text-sm font-medium">Publish date<input type="datetime-local" className={field} value={form.data.published_at} onChange={e => form.setData('published_at', e.target.value)} /><span className="mt-1 block text-xs font-normal opacity-55">Leave empty to publish immediately.</span></label>}
                    <label className="flex min-h-11 items-center gap-3 text-sm"><input type="checkbox" checked={form.data.indexable} onChange={e => form.setData('indexable', e.target.checked)} />Allow search indexing</label>
                </div></section>
                <section className="border border-raoza-primary/15 bg-white p-5 sm:p-6"><p className="text-xs uppercase tracking-[.18em] text-raoza-secondary">04 · SEO</p><h2 className="mt-2 font-display text-2xl">Search presentation</h2><div className="mt-5 space-y-4"><label className="block text-sm font-medium">SEO title<input className={field} value={form.data.seo_title} onChange={e => form.setData('seo_title', e.target.value)} /></label><label className="block text-sm font-medium">Meta description<textarea rows={3} className={field} value={form.data.seo_description} onChange={e => form.setData('seo_description', e.target.value)} /></label><label className="block text-sm font-medium">Canonical URL<input type="url" className={field} value={form.data.canonical_url} onChange={e => form.setData('canonical_url', e.target.value)} /></label></div></section>
                {Object.keys(form.errors).length > 0 && <div role="alert" className="border border-red-700/30 bg-red-50 p-4 text-sm text-red-900"><b>Product could not be saved.</b><ul className="mt-2 list-disc pl-5">{Object.entries(form.errors).map(([key, message]) => <li key={key}>{message}</li>)}</ul></div>}
                <button disabled={form.processing || !form.data.name} className="min-h-12 w-full bg-raoza-primary px-5 text-xs font-semibold uppercase tracking-widest text-raoza-cream disabled:cursor-not-allowed disabled:opacity-40">{form.processing ? 'Creating product…' : 'Create product & continue'}</button>
            </div>
        </form>
    </AdminLayout>;
}
