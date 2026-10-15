<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StorefrontCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:80'],
            'category' => ['nullable', 'string', 'max:100'],
            'collection' => ['nullable', 'string', 'max:100'],
            'size' => ['nullable', 'array', 'max:12'],
            'size.*' => ['string', 'max:80', 'distinct'],
            'color' => ['nullable', 'array', 'max:12'],
            'color.*' => ['string', 'max:80', 'distinct'],
            'min_price' => ['nullable', 'decimal:0,2', 'min:0', 'max:100000'],
            'max_price' => ['nullable', 'decimal:0,2', 'min:0', 'max:100000'],
            'in_stock' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['recommended', 'newest', 'price_asc', 'price_desc'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->filled('min_price') && $this->filled('max_price')
                && (float) $this->input('min_price') > (float) $this->input('max_price')) {
                $validator->errors()->add('max_price', 'The maximum price must be at least the minimum price.');
            }
        }];
    }

    /** @return array{q:string,category:string,collection:string,size:list<string>,color:list<string>,min_price:string,max_price:string,in_stock:bool,sort:string} */
    public function catalogFilters(): array
    {
        $data = $this->validated();

        return [
            'q' => trim((string) ($data['q'] ?? '')),
            'category' => (string) ($data['category'] ?? ''),
            'collection' => (string) ($data['collection'] ?? ''),
            'size' => array_values($data['size'] ?? []),
            'color' => array_values($data['color'] ?? []),
            'min_price' => isset($data['min_price']) ? number_format((float) $data['min_price'], 2, '.', '') : '',
            'max_price' => isset($data['max_price']) ? number_format((float) $data['max_price'], 2, '.', '') : '',
            'in_stock' => $this->boolean('in_stock'),
            'sort' => (string) ($data['sort'] ?? 'recommended'),
        ];
    }
}
