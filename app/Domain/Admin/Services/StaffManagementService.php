<?php

namespace App\Domain\Admin\Services;

use App\Domain\Admin\Enums\AdminRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class StaffManagementService
{
    public function create(array $data): User
    {
        $user = new User([
            'name'=>$data['name'],'email'=>mb_strtolower(trim($data['email'])),'password'=>$data['password'],
            'is_admin'=>false,'role'=>AdminRole::from($data['role']),
        ]);
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    public function update(User $actor,User $staff,array $data):User
    {
        return DB::transaction(function()use($actor,$staff,$data){
            $locked=User::query()->whereKey($staff->id)->lockForUpdate()->firstOrFail();
            if(!$locked->isStaff())throw new RuntimeException('Customer accounts cannot be modified from staff management.');
            $newRole=AdminRole::from($data['role']);
            $disable=(bool)$data['disabled'];
            if($locked->id===$actor->id&&$disable)throw new RuntimeException('You cannot disable your own active session.');
            if($locked->role===AdminRole::Owner&&($disable||$newRole!==AdminRole::Owner))$this->assertAnotherActiveOwner($locked->id);
            $locked->update([
                'name'=>$data['name'],'email'=>mb_strtolower(trim($data['email'])),'role'=>$newRole,'is_admin'=>false,
                'staff_disabled_at'=>$disable?($locked->staff_disabled_at?:now()):null,
                'staff_disabled_by'=>$disable?$actor->id:null,
                'remember_token'=>$disable?Str::random(60):$locked->remember_token,
            ]);
            if($disable)DB::table('sessions')->where('user_id',$locked->id)->delete();
            return $locked->refresh();
        },3);
    }

    private function assertAnotherActiveOwner(int $excluding):void
    {
        $exists=User::query()->where('role',AdminRole::Owner->value)->whereNull('staff_disabled_at')->whereKeyNot($excluding)->lockForUpdate()->exists();
        if(!$exists)throw new RuntimeException('The last active owner cannot be disabled or demoted.');
    }
}
