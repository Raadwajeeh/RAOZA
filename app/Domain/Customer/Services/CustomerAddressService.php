<?php

namespace App\Domain\Customer\Services;

use App\Domain\Customer\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CustomerAddressService
{
    public function create(User $user, array $data): CustomerAddress
    {
        return DB::transaction(function () use ($user, $data): CustomerAddress {
            $this->lockCustomer($user);
            $address = $user->addresses()->create($data);
            $this->applyRequestedDefaults($user, $address, $data);
            $this->normalizeDefaults($user);

            return $address->refresh();
        }, 3);
    }

    public function update(User $user, int $addressId, array $data): CustomerAddress
    {
        return DB::transaction(function () use ($user, $addressId, $data): CustomerAddress {
            $this->lockCustomer($user);
            $address = CustomerAddress::query()->where('user_id', $user->id)->lockForUpdate()->findOrFail($addressId);
            $address->update($data);
            $this->applyRequestedDefaults($user, $address, $data);
            $this->normalizeDefaults($user);

            return $address->refresh();
        }, 3);
    }

    public function delete(User $user, int $addressId): void
    {
        DB::transaction(function () use ($user, $addressId): void {
            $this->lockCustomer($user);
            CustomerAddress::query()->where('user_id', $user->id)->lockForUpdate()->findOrFail($addressId)->delete();
            $this->normalizeDefaults($user);
        }, 3);
    }

    public function setDefault(User $user, int $addressId, string $type): CustomerAddress
    {
        return DB::transaction(function () use ($user, $addressId, $type): CustomerAddress {
            $this->lockCustomer($user);
            $address = CustomerAddress::query()->where('user_id', $user->id)->lockForUpdate()->findOrFail($addressId);
            $capability = $type === 'shipping' ? 'is_shipping' : 'is_billing';
            $default = $type === 'shipping' ? 'is_default_shipping' : 'is_default_billing';
            abort_unless($address->{$capability}, 422);
            CustomerAddress::query()->where('user_id', $user->id)->update([$default => false]);
            $address->update([$default => true]);

            return $address->refresh();
        }, 3);
    }

    private function applyRequestedDefaults(User $user, CustomerAddress $address, array $data): void
    {
        foreach (['shipping', 'billing'] as $type) {
            $capability = "is_{$type}";
            $default = "is_default_{$type}";

            if (! $address->{$capability}) {
                $address->update([$default => false]);
            } elseif (! empty($data[$default])) {
                CustomerAddress::query()->where('user_id', $user->id)->whereKeyNot($address->id)->update([$default => false]);
                $address->update([$default => true]);
            }
        }
    }

    private function normalizeDefaults(User $user): void
    {
        foreach (['shipping', 'billing'] as $type) {
            $capability = "is_{$type}";
            $default = "is_default_{$type}";
            CustomerAddress::query()->where('user_id', $user->id)->where($capability, false)->update([$default => false]);

            if (! CustomerAddress::query()->where('user_id', $user->id)->where($capability, true)->where($default, true)->exists()) {
                CustomerAddress::query()->where('user_id', $user->id)->where($capability, true)->oldest('id')->first()?->update([$default => true]);
            }
        }
    }

    private function lockCustomer(User $user): void
    {
        User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
    }
}
