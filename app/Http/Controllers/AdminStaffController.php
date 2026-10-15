<?php

namespace App\Http\Controllers;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Services\AuditService;
use App\Domain\Admin\Services\StaffManagementService;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class AdminStaffController extends Controller
{
    public function __construct(private StaffManagementService $staff,private AuditService $audit){}
    public function index(Request $request):Response
    {
        $this->assertOwner($request);
        $roles=collect(AdminRole::cases())->reject(fn($r)=>$r===AdminRole::Customer)->map(fn($r)=>['value'=>$r->value,'label'=>$r->label(),'permissions'=>$r->permissions()])->values();
        $staff=User::query()->where(fn($q)=>$q->where('role','!=',AdminRole::Customer->value)->orWhere('is_admin',true))->orderBy('name')->get(['id','name','email','role','is_admin','staff_disabled_at','created_at']);
        return Inertia::render('Admin/Staff/Index',compact('staff','roles'));
    }
    public function store(Request $request):RedirectResponse
    {
        $this->assertOwner($request);
        $data=$request->validate($this->rules(null,true));$this->confirmPassword($request);
        $created=$this->staff->create($data);
        $this->audit->record($request,'staff.created',$created,null,['name'=>$created->name,'email'=>$created->email,'role'=>$created->role->value,'active'=>true]);
        return back()->with('success','Staff account created. No email was sent.');
    }
    public function update(Request $request,User $staff):RedirectResponse
    {
        $this->assertOwner($request);
        $data=$request->validate($this->rules($staff,false));$this->confirmPassword($request);
        $before=['name'=>$staff->name,'email'=>$staff->email,'role'=>$staff->role?->value,'active'=>$staff->staff_disabled_at===null];
        try{$updated=$this->staff->update($request->user(),$staff,$data);}catch(RuntimeException $e){return back()->withErrors(['staff'=>$e->getMessage()]);}
        $this->audit->record($request,'staff.updated',$updated,$before,['name'=>$updated->name,'email'=>$updated->email,'role'=>$updated->role->value,'active'=>$updated->staff_disabled_at===null]);
        return back()->with('success','Staff access updated and active sessions revoked when disabled.');
    }
    private function rules(?User $staff,bool $creating):array
    {
        $roles=collect(AdminRole::cases())->reject(fn($r)=>$r===AdminRole::Customer)->pluck('value')->all();
        $rules=['name'=>['required','string','max:120'],'email'=>['required','email:rfc','max:254',Rule::unique('users','email')->ignore($staff?->id)],'role'=>['required',Rule::in($roles)],'disabled'=>['required','boolean'],'current_password'=>['required','string']];
        if($creating)$rules['password']=['required','confirmed',Password::min(12)->mixedCase()->letters()->numbers()->symbols()];
        return $rules;
    }
    private function confirmPassword(Request $request):void
    {
        if(!Hash::check((string)$request->input('current_password'),$request->user()->password))throw ValidationException::withMessages(['current_password'=>'Your current password is incorrect.']);
    }
    private function assertOwner(Request $request):void
    {
        abort_unless($request->user()?->role === AdminRole::Owner, 403);
    }
}
