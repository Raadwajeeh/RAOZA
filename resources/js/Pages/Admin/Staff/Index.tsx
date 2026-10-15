import { Head, useForm } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';

type Role={value:string;label:string;permissions:string[]};
type Staff={id:number;name:string;email:string;role:string;staff_disabled_at:string|null};

export default function StaffIndex({staff,roles}:{staff:Staff[];roles:Role[]}) {
 const create=useForm({name:'',email:'',role:'support',password:'',password_confirmation:'',disabled:false,current_password:''});
 return <AdminLayout><Head title="Staff access"/><div className="space-y-8">
  <header><h1 className="font-display text-3xl">Staff access</h1><p className="mt-2 text-sm text-raoza-secondary">Owner-only access. Every change requires your current password and is recorded.</p></header>
  <form className="grid gap-3 border border-raoza-primary/15 bg-white p-5 md:grid-cols-2" onSubmit={e=>{e.preventDefault();create.post('/admin/staff',{onSuccess:()=>create.reset()})}}>
   <h2 className="font-display text-xl md:col-span-2">Create staff member</h2>
   <input className="raoza-input" placeholder="Name" value={create.data.name} onChange={e=>create.setData('name',e.target.value)}/><input className="raoza-input" type="email" placeholder="Email" value={create.data.email} onChange={e=>create.setData('email',e.target.value)}/>
   <select className="raoza-input" value={create.data.role} onChange={e=>create.setData('role',e.target.value)}>{roles.map(r=><option key={r.value} value={r.value}>{r.label}</option>)}</select><input className="raoza-input" type="password" placeholder="Initial password (12+ characters)" value={create.data.password} onChange={e=>create.setData('password',e.target.value)}/>
   <input className="raoza-input" type="password" placeholder="Confirm initial password" value={create.data.password_confirmation} onChange={e=>create.setData('password_confirmation',e.target.value)}/><input className="raoza-input" type="password" placeholder="Your current password" value={create.data.current_password} onChange={e=>create.setData('current_password',e.target.value)}/>
   {Object.values(create.errors).length>0&&<p className="text-sm text-red-700 md:col-span-2">{Object.values(create.errors).join(' ')}</p>}<button disabled={create.processing} className="bg-raoza-primary px-4 py-3 text-sm text-white md:col-span-2">Create without sending email</button>
  </form>
  <div className="space-y-4">{staff.map(member=><StaffRow key={member.id} member={member} roles={roles}/>)}</div>
 </div></AdminLayout>
}
function StaffRow({member,roles}:{member:Staff;roles:Role[]}) { const form=useForm({name:member.name,email:member.email,role:member.role,disabled:Boolean(member.staff_disabled_at),current_password:''}); return <form className="grid gap-3 border border-raoza-primary/15 bg-white p-5 md:grid-cols-5" onSubmit={e=>{e.preventDefault();form.patch(`/admin/staff/${member.id}`,{preserveScroll:true})}}><input className="raoza-input" value={form.data.name} onChange={e=>form.setData('name',e.target.value)}/><input className="raoza-input" type="email" value={form.data.email} onChange={e=>form.setData('email',e.target.value)}/><select className="raoza-input" value={form.data.role} onChange={e=>form.setData('role',e.target.value)}>{roles.map(r=><option key={r.value} value={r.value}>{r.label}</option>)}</select><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.data.disabled} onChange={e=>form.setData('disabled',e.target.checked)}/>Disabled</label><input className="raoza-input" type="password" placeholder="Your current password" value={form.data.current_password} onChange={e=>form.setData('current_password',e.target.value)}/>{Object.values(form.errors).length>0&&<p className="text-sm text-red-700 md:col-span-5">{Object.values(form.errors).join(' ')}</p>}<button disabled={form.processing} className="border border-raoza-primary px-4 py-2 text-sm md:col-span-5">Save access</button></form> }
