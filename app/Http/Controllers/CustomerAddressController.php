<?php

namespace App\Http\Controllers;

use App\Domain\Customer\Services\CustomerAddressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CustomerAddressController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Account/Addresses', [
            'addresses' => $request->user()->addresses()->orderByDesc('is_default_shipping')->orderByDesc('is_default_billing')->oldest('id')->get(),
        ]);
    }

    public function store(Request $request, CustomerAddressService $service): RedirectResponse
    {
        $service->create($request->user(), $this->validated($request));

        return back()->with('success', 'Address added.');
    }

    public function update(Request $request, int $address, CustomerAddressService $service): RedirectResponse
    {
        $service->update($request->user(), $address, $this->validated($request));

        return back()->with('success', 'Address updated.');
    }

    public function destroy(Request $request, int $address, CustomerAddressService $service): RedirectResponse
    {
        $service->delete($request->user(), $address);

        return back()->with('success', 'Address removed.');
    }

    public function setDefault(Request $request, int $address, CustomerAddressService $service): RedirectResponse
    {
        $data = $request->validate(['type' => ['required', Rule::in(['shipping', 'billing'])]]);
        $service->setDefault($request->user(), $address, $data['type']);

        return back()->with('success', 'Default address updated.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:80'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'company' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'street' => ['required', 'string', 'max:150'],
            'house_number' => ['required', 'string', 'max:32'],
            'addition' => ['nullable', 'string', 'max:32'],
            'postal_code' => ['required', 'string', 'max:24'],
            'city' => ['required', 'string', 'max:120'],
            'country_code' => ['required', Rule::in(['NL'])],
            'is_shipping' => ['required', 'boolean'],
            'is_billing' => ['required', 'boolean'],
            'is_default_shipping' => ['nullable', 'boolean'],
            'is_default_billing' => ['nullable', 'boolean'],
        ]);

        if (! $data['is_shipping'] && ! $data['is_billing']) {
            throw ValidationException::withMessages(['address_type' => 'Choose shipping, billing, or both.']);
        }

        foreach (['label', 'company', 'phone', 'addition'] as $field) {
            $data[$field] = isset($data[$field]) && trim((string) $data[$field]) !== '' ? trim($data[$field]) : null;
        }
        $data['postal_code'] = strtoupper(trim($data['postal_code']));
        $data['country_code'] = strtoupper($data['country_code']);

        return $data;
    }
}
