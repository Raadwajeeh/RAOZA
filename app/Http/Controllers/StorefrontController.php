<?php
namespace App\Http\Controllers;

use App\Domain\Catalog\Enums\CategoryStatus;
use App\Domain\Catalog\Enums\CollectionStatus;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Enums\VariantStatus;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Collection;
use App\Domain\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Domain\Content\Services\SeoService;

class StorefrontController extends Controller
{
    public function __construct(private SeoService $seo) {}

    public function home(): Response
    {
        $products = $this->publishedProducts()->limit(4)->get()->map(fn (Product $product) => $this->productCard($product));
        $collections = Collection::query()->where('status', CollectionStatus::Active)->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))->orderByDesc('published_at')->limit(3)->get(['id','name','slug','description']);
        return Inertia::render('Storefront/Home', ['featuredProducts'=>$products, 'collections'=>$collections, 'seo'=>$this->seo->basic('RAOZA — Printed Apparel','Design-led printed apparel with an urban editorial point of view.',route('home'))]);
    }

    public function shop(Request $request): Response
    {
        $query = $this->publishedProducts();
        if ($search = trim((string) $request->query('q'))) {
            $query->where(fn (Builder $q) => $q->where('name','ilike','%'.$search.'%')->orWhere('description','ilike','%'.$search.'%'));
        }
        $products = $query->paginate(24)->withQueryString()->through(fn (Product $product) => $this->productCard($product));
        return Inertia::render('Storefront/Shop', ['products'=>$products, 'query'=>['q'=>$search ?? ''], 'title'=>'Shop', 'pageType'=>'shop', 'intro'=>'A curated selection of RAOZA printed apparel.', 'seo'=>$this->seo->basic('Shop — RAOZA','Shop RAOZA printed apparel.',route('shop'))]);
    }

    public function category(Category $category): Response
    {
        abort_unless($category->status === CategoryStatus::Active, 404);
        $products = $this->publishedProducts()->whereHas('categories', fn (Builder $q) => $q->whereKey($category->id))->paginate(24)->through(fn (Product $product) => $this->productCard($product));
        return Inertia::render('Storefront/Shop', ['products'=>$products, 'query'=>['q'=>''], 'title'=>$category->name, 'pageType'=>'category', 'intro'=>$category->description ?: 'Explore RAOZA '.$category->name.'.', 'seo'=>$this->seo->basic($category->seo_title ?: $category->name.' — RAOZA',$category->seo_description ?: 'Explore RAOZA '.$category->name.'.',$category->canonical_url ?: route('categories.show',$category),$category->indexable)]);
    }

    public function collection(Collection $collection): Response
    {
        abort_unless($collection->status === CollectionStatus::Active && (!$collection->published_at || $collection->published_at->isPast()), 404);
        $products = $this->publishedProducts()->whereHas('collections', fn (Builder $q) => $q->whereKey($collection->id))->paginate(24)->through(fn (Product $product) => $this->productCard($product));
        return Inertia::render('Storefront/Shop', ['products'=>$products, 'query'=>['q'=>''], 'title'=>$collection->name, 'pageType'=>'collection', 'intro'=>$collection->description, 'seo'=>$this->seo->collection($collection)]);
    }

    public function product(Product $product): Response
    {
        abort_unless($product->status === ProductStatus::Active && (!$product->published_at || $product->published_at->isPast()), 404);
        $product->load(['images','options.values','variants'=>fn ($q) => $q->where('status', VariantStatus::Active)->with(['optionValues.option','inventory','images'])]);
        return Inertia::render('Storefront/Product', ['product'=>$this->productDetail($product), 'seo'=>$this->seo->product($product)]);
    }

    private function publishedProducts(): Builder
    {
        return Product::query()->where('status', ProductStatus::Active)->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at','<=',now()))->with(['images','variants'=>fn ($q) => $q->where('status', VariantStatus::Active)->with('inventory')])->orderByDesc('published_at')->orderByDesc('id');
    }

    private function productCard(Product $product): array
    {
        $prices = $product->variants->map(fn ($variant) => $variant->price_override ?? $product->base_price);
        $available = $product->variants->contains(fn ($variant) => $variant->inventory && ($variant->inventory->quantity_on_hand - $variant->inventory->quantity_reserved) > 0);
        return ['id'=>$product->id,'name'=>$product->name,'slug'=>$product->slug,'price'=>$prices->min() ?? $product->base_price,'priceVaries'=>$prices->unique()->count()>1,'image'=>$this->imageUrl($product->images->first()?->path),'imageAlt'=>$product->images->first()?->alt_text ?: $product->name,'available'=>$available];
    }

    private function productDetail(Product $product): array
    {
        return ['id'=>$product->id,'name'=>$product->name,'slug'=>$product->slug,'description'=>$product->description,'seoTitle'=>$product->seo_title,'seoDescription'=>$product->seo_description,'basePrice'=>$product->base_price,
            'images'=>$product->images->map(fn ($image)=>['id'=>$image->id,'url'=>$this->imageUrl($image->path),'alt'=>$image->alt_text ?: $product->name])->values(),
            'options'=>$product->options->map(fn ($option)=>['id'=>$option->id,'name'=>$option->name,'values'=>$option->values->map(fn ($value)=>['id'=>$value->id,'value'=>$value->value,'metadata'=>$value->metadata])->values()])->values(),
            'variants'=>$product->variants->map(function ($variant) use ($product) { $available=max(0,($variant->inventory?->quantity_on_hand ?? 0)-($variant->inventory?->quantity_reserved ?? 0)); return ['id'=>$variant->id,'sku'=>$variant->sku,'price'=>$variant->price_override ?? $product->base_price,'availableQuantity'=>$available,'available'=>$available>0,'optionValueIds'=>$variant->optionValues->pluck('id')->values(),'options'=>$variant->optionValues->mapWithKeys(fn ($value)=>[$value->option->name=>$value->value]),'images'=>$variant->images->map(fn ($image)=>['url'=>$this->imageUrl($image->path),'alt'=>$image->alt_text ?: $product->name])->values()]; })->values()];
    }

    private function imageUrl(?string $path): ?string
    {
        if (!$path) return null;
        if (str_starts_with($path,'http://') || str_starts_with($path,'https://') || str_starts_with($path,'/')) return $path;
        return '/storage/'.ltrim($path,'/');
    }
}
