<?php

namespace App\Console\Commands;

use App\Domain\Admin\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

final class ResetStaffPassword extends Command
{
    protected $signature = 'admin:reset-password {email} {--confirm : Confirm emergency server-side reset}';
    protected $description = 'Securely reset a staff password and invalidate existing sessions';

    public function handle(): int
    {
        if (! $this->option('confirm')) {
            $this->error('Refusing password reset without --confirm.');
            return self::FAILURE;
        }
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $user || ! $user->isStaff()) {
            $this->error('No staff account is eligible for this reset.');
            return self::FAILURE;
        }
        $password = (string) $this->secret('New staff password (input is hidden)');
        $validation = Validator::make(['password'=>$password], ['password'=>['required',Password::min(12)->mixedCase()->letters()->numbers()->symbols()]]);
        if ($validation->fails()) {
            $this->error(implode(' ', $validation->errors()->all()));
            return self::FAILURE;
        }
        DB::transaction(function () use ($user,$password,$email): void {
            $user->password = Hash::make($password);
            $user->remember_token = Str::random(60);
            $user->save();
            DB::table('sessions')->where('user_id',$user->id)->delete();
            DB::table('password_reset_tokens')->where('email',$email)->delete();
            AuditLog::query()->create(['actor_id'=>null,'action'=>'staff.password_reset_cli','subject_type'=>User::class,'subject_id'=>(string)$user->id,'after'=>['sessions_invalidated'=>true],'created_at'=>now()]);
        });
        $this->info('Staff password reset and existing sessions invalidated. The password was not printed or logged.');
        return self::SUCCESS;
    }
}
