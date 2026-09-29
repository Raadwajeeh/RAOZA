<?php
namespace App\Http\Controllers;

use App\Domain\Admin\Services\AuditService;
use App\Domain\Catalog\Models\{Category,Collection,Product,ProductImage,ProductOption,ProductOptionValue,ProductVariant};
use App\Domain\Catalog\VariantSignature;
use App\Domain\Inventory\Enums\InventoryMovementType;
use App\Domain\Inventory\Models\{Inventory,InventoryMovement};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AdminCatalogController
{
    public function __construct(private AuditService $audit) {}

    public function products(Request $r)
    {
        $q = Product::withCount('variants')->with(['categories:id,name','collections:id,name'])->latest();
        if ($r->filled('q')) $q->where(fn($x) => $x->where('name','ilike','%'.$r->q.'%')->orWhere('slug','ilike','%'.$r->q.'%'));
        return Inertia::render('Admin/Products/Index',[
            'products'=>$q->paginate(20)->withQueryString(),
            'filters'=>['q'=>$r->string('q')->toString()],
        ]);
    }

    public function createProduct()
    {
        return Inertia::render('Admin/Products/Create', [
            'categories'=>Category::orderBy('position')->orderBy('name')->get(['id','name','status']),
            'collections'=>Collection::orderBy('name')->get(['id','name','status']),
        ]);
    }

    public function showProduct(Product $product)
    {
        $product->load([
            'categories:id,name','collections:id,name',
            'options'=>fn($q)=>$q->with(['values'=>fn($v)=>$v->withCount('variants')]),
            'variants'=>fn($q)=>$q->with(['optionValues.option','inventory'])->orderBy('sku'),
            'images'=>fn($q)=>$q->orderBy('position'),
        ]);
        return Inertia::render('Admin/Products/Show',[
            'product'=>$product,
            'categories'=>Category::where('status','active')->orderBy('name')->get(['id','name']),
            'collections'=>Collection::where('status','active')->orderBy('name')->get(['id','name']),
        ]);
    }

    public function storeProduct(Request $r)
    {
        $d=$r->validate([
            'name'=>'required|string|max:160','slug'=>'nullable|string|max:180|unique:products,slug','description'=>'nullable|string','short_description'=>'nullable|string|max:500',
            'fit_notes'=>'nullable|string|max:5000','product_details'=>'nullable|string|max:5000','care_instructions'=>'nullable|string|max:5000',
            'base_price'=>'required|integer|min:0','status'=>['required',Rule::in(['draft','active','archived'])],
            'published_at'=>'nullable|date','seo_title'=>'nullable|string|max:255','seo_description'=>'nullable|string|max:500',
            'canonical_url'=>'nullable|url|max:2048','indexable'=>'boolean','category_ids'=>'array','category_ids.*'=>'integer|exists:categories,id',
            'collection_ids'=>'array','collection_ids.*'=>'integer|exists:collections,id','position'=>'nullable|integer|min:0|max:10000',
        ]);
        $d['slug']=$d['slug']?:Str::slug($d['name']);
        $d['published_at']=$d['status']==='active'?($d['published_at']??now()):null;
        $p=DB::transaction(function() use($d){
            $p=Product::create(collect($d)->except(['category_ids','collection_ids'])->all());
            $p->categories()->sync($d['category_ids']??[]); $p->collections()->sync($d['collection_ids']??[]);
            return $p;
        });
        $this->audit->record($r,'product.created',$p,null,$p->toArray());
        return redirect()->route('admin.products.show',$p)->with('success','Product created. Continue with media, options and variants.');
    }

    public function updateProduct(Request $r,Product $product)
    {
        $before=$product->toArray();
        $d=$r->validate([
            'name'=>'required|string|max:160','slug'=>['required','string','max:180',Rule::unique('products','slug')->ignore($product->id)],
            'description'=>'nullable|string','short_description'=>'nullable|string|max:500','fit_notes'=>'nullable|string|max:5000','product_details'=>'nullable|string|max:5000','care_instructions'=>'nullable|string|max:5000',
            'base_price'=>'required|integer|min:0','status'=>['required',Rule::in(['draft','active','archived'])],
            'seo_title'=>'nullable|string|max:255','seo_description'=>'nullable|string|max:500',
            'canonical_url'=>'nullable|url|max:2048','indexable'=>'boolean','published_at'=>'nullable|date',
            'category_ids'=>'array','category_ids.*'=>'integer|exists:categories,id','collection_ids'=>'array','collection_ids.*'=>'integer|exists:collections,id','position'=>'required|integer|min:0|max:10000',
        ]);
        DB::transaction(function() use($product,$d){
            $product->update(collect($d)->except(['category_ids','collection_ids'])->merge(['published_at'=>$d['status']==='active'?($d['published_at']??$product->published_at??now()):null])->all());
            $product->categories()->sync($d['category_ids']??[]); $product->collections()->sync($d['collection_ids']??[]);
        });
        $this->audit->record($r,'product.updated',$product,$before,$product->fresh()->toArray());
        return back()->with('success','Product details saved.');
    }

    public function storeOption(Request $r, Product $product)
    {
        $d=$r->validate(['name'=>['required','string','max:80',Rule::unique('product_options','name')->where('product_id',$product->id)],'position'=>'nullable|integer|min:0|max:1000']);
        $o=$product->options()->create(['name'=>$d['name'],'position'=>$d['position']??($product->options()->max('position')+1)]);
        $this->audit->record($r,'product.option.created',$o,null,$o->toArray()); return back()->with('success','Option created.');
    }

    public function storeOptionValue(Request $r, Product $product, ProductOption $option)
    {
        abort_unless($option->product_id===$product->id,404);
        $d=$r->validate(['value'=>['required','string','max:80',Rule::unique('product_option_values','value')->where('product_option_id',$option->id)],'swatch'=>'nullable|string|max:32']);
        $v=$option->values()->create(['value'=>$d['value'],'metadata'=>$d['swatch']?['swatch'=>$d['swatch']]:null,'position'=>($option->values()->max('position')??0)+1]);
        $this->audit->record($r,'product.option_value.created',$v,null,$v->toArray()); return back()->with('success','Option value added.');
    }

    public function deleteOption(Request $r, Product $product, ProductOption $option)
    {
        abort_unless($option->product_id===$product->id,404);
        if($option->values()->whereHas('variants')->exists()) throw ValidationException::withMessages(['option'=>'This option is used by existing variants. Keep it for order/catalog integrity and deactivate variants instead.']);
        $before=$option->load('values')->toArray(); $option->delete();
        $this->audit->record($r,'product.option.deleted',$product,$before,null); return back()->with('success','Unused option removed.');
    }

    public function deleteOptionValue(Request $r, Product $product, ProductOption $option, ProductOptionValue $value)
    {
        abort_unless($option->product_id===$product->id&&$value->product_option_id===$option->id,404);
        if($value->variants()->exists()) throw ValidationException::withMessages(['option'=>'This value is used by existing variants. Keep it for order/catalog integrity and deactivate variants instead.']);
        $before=$value->toArray(); $value->delete();
        $this->audit->record($r,'product.option_value.deleted',$product,$before,null); return back()->with('success','Unused option value removed.');
    }

    public function storeVariant(Request $r, Product $product)
    {
        $d=$r->validate(['sku'=>'required|string|max:120|unique:product_variants,sku','price_override'=>'nullable|integer|min:0','status'=>'required|in:active,inactive','option_value_ids'=>'required|array|min:1','option_value_ids.*'=>'integer|exists:product_option_values,id','opening_stock'=>'required|integer|min:0|max:1000000']);
        $values=ProductOptionValue::with('option:id,product_id')->whereIn('id',$d['option_value_ids'])->get();
        abort_unless($values->count()===count(array_unique($d['option_value_ids'])) && $values->every(fn($v)=>$v->option->product_id===$product->id),422);
        if($values->pluck('product_option_id')->unique()->count()!==$product->options()->count()) return back()->withErrors(['option_value_ids'=>'Choose exactly one value for every product option.']);
        $signature=VariantSignature::fromOptionValueIds($d['option_value_ids']);
        if($product->variants()->where('option_signature',$signature)->exists()) return back()->withErrors(['option_value_ids'=>'This variant combination already exists.']);
        $variant=DB::transaction(function() use($product,$d,$signature,$r){
            $v=$product->variants()->create(['sku'=>$d['sku'],'price_override'=>$d['price_override'],'status'=>$d['status'],'option_signature'=>$signature]);
            $v->optionValues()->sync($d['option_value_ids']);
            Inventory::create(['variant_id'=>$v->id,'quantity_on_hand'=>$d['opening_stock'],'quantity_reserved'=>0]);
            if($d['opening_stock']>0) InventoryMovement::create(['variant_id'=>$v->id,'type'=>InventoryMovementType::StockReceived,'quantity'=>$d['opening_stock'],'reference_type'=>'admin_opening_stock','reference_id'=>(string)$r->user()->id,'note'=>'Opening stock at variant creation','created_at'=>now()]);
            return $v;
        });
        $this->audit->record($r,'product.variant.created',$variant,null,$variant->toArray()); return back()->with('success','Variant created with inventory.');
    }

    public function generateVariants(Request $r, Product $product)
    {
        $d=$r->validate(['sku_prefix'=>'required|string|max:60','opening_stock'=>'required|integer|min:0|max:1000000','status'=>'required|in:active,inactive']);
        $options=$product->options()->with('values')->orderBy('position')->get();
        if($options->isEmpty() || $options->contains(fn($o)=>$o->values->isEmpty())) {
            throw ValidationException::withMessages(['options'=>'Every option needs at least one value before combinations can be generated.']);
        }
        $combinations=$options->reduce(fn($carry,$option)=>collect($carry)->flatMap(fn($combo)=>$option->values->map(fn($value)=>[...$combo,$value]))->all(), [[]]);
        if(count($combinations)>100) throw ValidationException::withMessages(['options'=>'This would create more than 100 variants. Reduce the option values first.']);
        $created=0;
        DB::transaction(function() use($product,$d,$combinations,$r,&$created){
            foreach($combinations as $combo){
                $ids=collect($combo)->pluck('id')->all(); $signature=VariantSignature::fromOptionValueIds($ids);
                if($product->variants()->where('option_signature',$signature)->exists()) continue;
                $suffix=collect($combo)->map(fn($v)=>Str::upper(Str::substr(Str::slug($v->value,''),0,4)))->implode('-');
                $base=Str::upper(trim($d['sku_prefix'],'-')).'-'.$suffix; $sku=$base; $attempt=1;
                while(ProductVariant::where('sku',$sku)->exists()) $sku=$base.'-'.++$attempt;
                $variant=$product->variants()->create(['sku'=>$sku,'status'=>$d['status'],'option_signature'=>$signature]);
                $variant->optionValues()->sync($ids);
                Inventory::create(['variant_id'=>$variant->id,'quantity_on_hand'=>$d['opening_stock'],'quantity_reserved'=>0]);
                if($d['opening_stock']>0) InventoryMovement::create(['variant_id'=>$variant->id,'type'=>InventoryMovementType::StockReceived,'quantity'=>$d['opening_stock'],'reference_type'=>'admin_opening_stock','reference_id'=>(string)$r->user()->id,'note'=>'Opening stock from variant generator','created_at'=>now()]);
                $created++;
            }
        });
        return back()->with('success',$created.' missing variant combination'.($created===1?'':'s').' created.');
    }

    public function updateVariant(Request $r, Product $product, ProductVariant $variant)
    {
        abort_unless($variant->product_id===$product->id,404); $before=$variant->toArray();
        $d=$r->validate(['sku'=>['required','string','max:120',Rule::unique('product_variants','sku')->ignore($variant->id)],'price_override'=>'nullable|integer|min:0','status'=>'required|in:active,inactive']);
        $variant->update($d); $this->audit->record($r,'product.variant.updated',$variant,$before,$variant->fresh()->toArray()); return back()->with('success','Variant updated.');
    }

    public function storeImage(Request $r, Product $product)
    {
        $d=$r->validate(['images'=>'required|array|min:1|max:10','images.*'=>'required|image|mimes:jpg,jpeg,png,webp|max:5120','alt_text'=>'nullable|string|max:255']);
        $position=(int)($product->images()->max('position')??-1);
        foreach($d['images'] as $upload){
            $path=$upload->store('products/'.$product->id,'public');
            [$width,$height]=getimagesize($upload->getRealPath())?:[null,null];
            $img=$product->images()->create(['path'=>$path,'alt_text'=>$d['alt_text']?:$product->name,'position'=>++$position,'width'=>$width,'height'=>$height]);
            $this->audit->record($r,'product.image.created',$img,null,$img->toArray());
        }
        return back()->with('success',count($d['images']).' product image'.(count($d['images'])===1?'':'s').' uploaded.');
    }

    public function reorderImages(Request $r, Product $product)
    {
        $d=$r->validate(['image_ids'=>'required|array','image_ids.*'=>'integer']);
        $owned=$product->images()->whereIn('id',$d['image_ids'])->pluck('id')->all();
        if(count($owned)!==count(array_unique($d['image_ids'])) || count($owned)!==$product->images()->count()) abort(422);
        DB::transaction(fn()=>collect($d['image_ids'])->each(fn($id,$position)=>ProductImage::whereKey($id)->update(['position'=>$position])));
        return back()->with('success','Image order saved. The first image is the storefront primary image.');
    }

    public function deleteImage(Request $r, Product $product, ProductImage $image)
    {
        abort_unless($image->product_id===$product->id,404); $before=$image->toArray();
        if(!str_starts_with($image->path,'/') && !str_starts_with($image->path,'http')) Storage::disk('public')->delete($image->path);
        $image->delete();
        $this->audit->record($r,'product.image.deleted',$product,$before,null); return back()->with('success','Product image removed.');
    }

    public function categories(){return Inertia::render('Admin/Taxonomy/Index',['kind'=>'Categories','items'=>Category::with(['parent:id,name','products:id,name'])->withCount('products')->orderBy('position')->orderBy('name')->get(),'products'=>Product::orderBy('name')->get(['id','name','status'])]);}
    public function storeCategory(Request $r){$d=$this->categoryData($r);$d['slug']=$d['slug']?:Str::slug($d['name']);$m=DB::transaction(function()use($d){$m=Category::create(collect($d)->except('product_ids')->all());$m->products()->sync($d['product_ids']??[]);return $m;});$this->audit->record($r,'category.created',$m,null,$m->toArray());return back()->with('success','Category created.');}
    public function updateCategory(Request $r,Category $category){$before=$category->toArray();$d=$this->categoryData($r,$category);if((int)($d['parent_id']??0)===$category->id)return back()->withErrors(['parent_id'=>'A category cannot be its own parent.']);$d['slug']=$d['slug']?:Str::slug($d['name']);DB::transaction(function()use($category,$d){$category->update(collect($d)->except('product_ids')->all());$category->products()->sync($d['product_ids']??[]);});$this->audit->record($r,'category.updated',$category,$before,$category->fresh()->toArray());return back()->with('success','Category updated.');}
    private function categoryData(Request $r,?Category $category=null):array{return $r->validate(['name'=>'required|string|max:120','slug'=>['nullable','string','max:140',Rule::unique('categories','slug')->ignore($category?->id)],'description'=>'nullable|string','seo_title'=>'nullable|string|max:255','seo_description'=>'nullable|string|max:500','canonical_url'=>'nullable|url|max:2048','indexable'=>'boolean','parent_id'=>['nullable','integer','exists:categories,id'],'status'=>'required|in:active,inactive','position'=>'required|integer|min:0|max:10000','product_ids'=>'array','product_ids.*'=>'integer|exists:products,id']);}

    public function collections(){return Inertia::render('Admin/Taxonomy/Index',['kind'=>'Collections','items'=>Collection::with('products:id,name')->withCount('products')->orderBy('name')->get(),'products'=>Product::orderBy('name')->get(['id','name','status'])]);}
    public function storeCollection(Request $r){$d=$this->collectionData($r);$d['slug']=$d['slug']?:Str::slug($d['name']);$d['published_at']=$d['status']==='active'?now():null;$m=DB::transaction(function()use($d){$m=Collection::create(collect($d)->except('product_ids')->all());$m->products()->sync($d['product_ids']??[]);return $m;});$this->audit->record($r,'collection.created',$m,null,$m->toArray());return back()->with('success','Collection created.');}
    public function updateCollection(Request $r,Collection $collection){$before=$collection->toArray();$d=$this->collectionData($r,$collection);$d['slug']=$d['slug']?:Str::slug($d['name']);$d['published_at']=$d['status']==='active'?($collection->published_at?:now()):null;DB::transaction(function()use($collection,$d){$collection->update(collect($d)->except('product_ids')->all());$collection->products()->sync($d['product_ids']??[]);});$this->audit->record($r,'collection.updated',$collection,$before,$collection->fresh()->toArray());return back()->with('success','Collection updated.');}
    private function collectionData(Request $r,?Collection $collection=null):array{return $r->validate(['name'=>'required|string|max:120','slug'=>['nullable','string','max:140',Rule::unique('collections','slug')->ignore($collection?->id)],'description'=>'nullable|string','status'=>'required|in:draft,active,archived','seo_title'=>'nullable|string|max:255','seo_description'=>'nullable|string|max:500','canonical_url'=>'nullable|url|max:2048','indexable'=>'boolean','product_ids'=>'array','product_ids.*'=>'integer|exists:products,id']);}
}
