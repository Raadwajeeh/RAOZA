<?php
namespace App\Domain\Commerce\Services;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Enums\VariantStatus;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Commerce\Enums\CartStatus;
use App\Domain\Commerce\Models\Cart;
use App\Domain\Commerce\Models\CartItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function add(Cart $cart, int $variantId, int $quantity): CartItem
    {
        return DB::transaction(function () use ($cart, $variantId, $quantity) {
            $variant = $this->sellableVariant($variantId, true);
            $item = CartItem::query()->where('cart_id',$cart->id)->where('variant_id',$variant->id)->lockForUpdate()->first();
            $requested = ($item?->quantity ?? 0) + $quantity;
            $this->assertQuantity($variant, $requested);
            if ($item) { $item->update(['quantity'=>$requested]); return $item->refresh(); }
            return $cart->items()->create(['variant_id'=>$variant->id,'quantity'=>$quantity]);
        });
    }

    public function update(Cart $cart, CartItem $item, int $quantity): CartItem
    {
        abort_unless($item->cart_id === $cart->id, 404);
        return DB::transaction(function () use ($item, $quantity) {
            $locked = CartItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $variant = $this->sellableVariant($locked->variant_id, true);
            $this->assertQuantity($variant, $quantity);
            $locked->update(['quantity'=>$quantity]);
            return $locked->refresh();
        });
    }

    public function remove(Cart $cart, CartItem $item): void
    {
        abort_unless($item->cart_id === $cart->id, 404);
        $item->delete();
    }

    public function summary(Cart $cart): array
    {
        $cart->load(['items.variant.product.images','items.variant.images','items.variant.optionValues.option','items.variant.inventory']);
        $items = $cart->items->map(function (CartItem $item) {
            $variant = $item->variant;
            $product = $variant->product;
            $available = max(0, $variant->inventory?->availableQuantity() ?? 0);
            $sellable = $product->status === ProductStatus::Active && (!$product->published_at || $product->published_at->isPast()) && $variant->status === VariantStatus::Active;
            $unitPrice = $variant->effectivePrice();
            $image = $variant->images()->orderBy('position')->first() ?? $product->images->first();
            return [
                'id'=>$item->id,'variantId'=>$variant->id,'productName'=>$product->name,'productSlug'=>$product->slug,'sku'=>$variant->sku,
                'options'=>$variant->optionValues->mapWithKeys(fn($value)=>[$value->option->name=>$value->value]),
                'quantity'=>$item->quantity,'unitPrice'=>$unitPrice,'lineTotal'=>$unitPrice*$item->quantity,
                'availableQuantity'=>$available,'valid'=>$sellable && $item->quantity <= $available,
                'image'=>$this->imageUrl($image?->path),'imageAlt'=>$image?->alt_text ?: $product->name,
            ];
        })->values();
        return ['token'=>$cart->token,'currency'=>$cart->currency,'count'=>$items->sum('quantity'),'subtotal'=>$items->sum('lineTotal'),'valid'=>$items->every('valid'),'items'=>$items];
    }

    private function sellableVariant(int $variantId, bool $lock): ProductVariant
    {
        $query = ProductVariant::query()->with(['product','inventory']);
        if ($lock) $query->lockForUpdate();
        $variant = $query->findOrFail($variantId);
        $published = $variant->product->status === ProductStatus::Active && (!$variant->product->published_at || $variant->product->published_at->isPast());
        if (!$published || $variant->status !== VariantStatus::Active) throw ValidationException::withMessages(['variant'=>'This product variant is not available.']);
        return $variant;
    }

    private function assertQuantity(ProductVariant $variant, int $quantity): void
    {
        if ($quantity < 1 || $quantity > 20) throw ValidationException::withMessages(['quantity'=>'Choose a quantity between 1 and 20.']);
        $available = max(0, $variant->inventory?->availableQuantity() ?? 0);
        if ($quantity > $available) throw ValidationException::withMessages(['quantity'=>'Only '.$available.' item(s) are currently available.']);
    }

    private function imageUrl(?string $path): ?string
    {
        if (!$path) return null;
        if (str_starts_with($path,'http://') || str_starts_with($path,'https://') || str_starts_with($path,'/')) return $path;
        return '/storage/'.ltrim($path,'/');
    }
}
