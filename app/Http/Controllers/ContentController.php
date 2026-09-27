<?php
namespace App\Http\Controllers; use App\Domain\Content\Models\ContentPage; use App\Domain\Content\Services\SeoService; use Inertia\Inertia; use Inertia\Response;
class ContentController extends Controller {public function __construct(private SeoService $seo){} public function show(string $slug):Response{$p=ContentPage::where('slug',$slug)->firstOrFail();abort_unless($p->isPublished(),404);return Inertia::render('Storefront/ContentPage',['page'=>$p->only('title','slug','content'),'seo'=>$this->seo->page($p)]);}}
