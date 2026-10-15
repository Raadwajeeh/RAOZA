<?php

namespace App\Models;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Commerce\Models\Order;
use App\Domain\Customer\Models\CustomerAddress;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'phone', 'password', 'is_admin', 'role', 'staff_disabled_at', 'staff_disabled_by'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'is_admin' => 'boolean', 'role' => AdminRole::class, 'staff_disabled_at' => 'datetime'];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function canDo(string $permission): bool
    {
        if ($this->is_admin && ($this->role === null || $this->role === AdminRole::Customer)) {
            return true;
        }

        return $this->role?->allows($permission) ?? false;
    }

    public function isStaff(): bool
    {
        return ($this->role?->isStaff() ?? false) || $this->is_admin;
    }

    public function isActiveStaff(): bool
    {
        return $this->isStaff() && $this->staff_disabled_at === null;
    }

}
