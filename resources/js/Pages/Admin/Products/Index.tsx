import {Head, Link, router} from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
const eur=(n:number)=>(n/100).toLocaleString('nl-NL',{style:'currency',currency:'EUR'});
export default function Products({products,filters}:any){
 return <AdminLayout><Head title="Products"/>
  <div className="flex flex-wrap items-end justify-between gap-4"><div><h1 className="font-display text-5xl text-raoza-primary">Products</h1><p className="mt-2 text-sm opacity-70">Build and maintain sellable products, media, variants and availability.</p></div><div className="flex flex-wrap gap-3">
   <input defaultValue={filters?.q||''} onKeyDown={e=>{if(e.key==='Enter')router.get('/admin/products',{q:(e.target as HTMLInputElement).value},{preserveState:true})}} className="min-w-64 border p-2" placeholder="Search name or slug…"/><Link href="/admin/products/create" className="bg-raoza-primary px-5 py-3 text-xs uppercase tracking-widest text-raoza-cream">Add product</Link></div>
  </div>
  <div className="mt-6 overflow-x-auto bg-white"><table className="w-full text-sm"><thead><tr className="border-b text-left"><th className="p-3">Product</th><th>Price</th><th>Status</th><th>Variants</th><th></th></tr></thead><tbody>{products.data.map((x:any)=><tr className="border-b" key={x.id}><td className="p-3"><b>{x.name}</b><div className="text-xs opacity-60">/{x.slug}</div></td><td>{eur(x.base_price)}</td><td className="capitalize">{x.status}</td><td>{x.variants_count}</td><td className="pr-3 text-right"><Link className="border px-3 py-1.5 hover:bg-raoza-cream" href={`/admin/products/${x.id}`}>Manage</Link></td></tr>)}</tbody></table></div>
 </AdminLayout>
}
