<?php

namespace App\Console\Commands;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

final class ProvisionOwner extends Command
{
    protected $signature = 'admin:provision-owner {email} {--name=} {--confirm : Confirm controlled first-owner provisioning}';
    protected $description = 'Securely provision the first RAOZA owner from the server console';

    public function handle(): int
    {
        if (! $this->option('confirm')) {
            $this->error('Refusing owner provisioning without --confirm.');
            return self::FAILURE;
        }
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $name = trim((string) ($this->option('name') ?: ''));
        $password = (string) $this->secret('Owner password (input is hidden)');
        $validation = Validator::make(compact('email','name','password'), [
            'email'=>['required','email:rfc','max:254'], 'name'=>['required','string','max:120'],
            'password'=>['required', Password::min(12)->mixedCase()->letters()->numbers()->symbols()],
        ]);
        if ($validation->fails()) {
            $this->error(implode(' ', $validation->errors()->all()));
            return self::FAILURE;
        }
        if (User::query()->where('role', AdminRole::Owner->value)->exists()) {
            $this->error('An OWNER already exists; use an authenticated staff-management procedure.');
            return self::FAILURE;
        }
        if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            $this->error('A user with this email already exists and will not be promoted implicitly.');
            return self::FAILURE;
        }
        $owner = User::query()->create(['name'=>$name,'email'=>$email,'password'=>Hash::make($password),'is_admin'=>false,'role'=>AdminRole::Owner]);
        AuditLog::query()->create(['actor_id'=>null,'action'=>'owner.provisioned','subject_type'=>User::class,'subject_id'=>(string)$owner->id,'after'=>['email'=>$email,'role'=>AdminRole::Owner->value],'created_at'=>now()]);
        $this->info('OWNER provisioned. The password was not printed or logged.');
        return self::SUCCESS;
    }
}
