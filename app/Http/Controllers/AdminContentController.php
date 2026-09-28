<?php
namespace App\Http\Controllers;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\ContentPage;
use App\Domain\Content\Models\SiteContent;
use App\Domain\Admin\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
class AdminContentController extends Controller {
 public function __construct(private AuditService $audit){}
 public function index(){return Inertia::render('Admin/Content/Index',['pages'=>ContentPage::orderBy('title')->get(),'home'=>SiteContent::where('key','homepage')->value('value')??[]]);}
 public function storePage(Request $r){$d=$r->validate(['title'=>'required|string|max:160','slug'=>'required|alpha_dash|max:160|unique:content_pages,slug','key'=>'required|alpha_dash|max:100|unique:content_pages,key','seo_title'=>'nullable|string|max:255','seo_description'=>'nullable|string|max:500']);$p=ContentPage::create($d+['status'=>ContentStatus::Draft,'content'=>[]]);$this->audit->record($r,'content.page_created',$p,null,$p->toArray());return back()->with('success','Content page created.');}
 public function updatePage(Request $r,ContentPage $page){$before=$page->toArray();$d=$r->validate(['title'=>'required|string|max:160','slug'=>['required','alpha_dash','max:160',Rule::unique('content_pages','slug')->ignore($page->id)],'status'=>'required|in:draft,published','seo_title'=>'nullable|string|max:255','seo_description'=>'nullable|string|max:500','canonical_url'=>'nullable|url|max:2048','og_image'=>'nullable|string|max:2048','indexable'=>'required|boolean','content'=>'nullable|array']);$page->update($d+['published_at'=>$d['status']==='published'?($page->published_at?:now()):null]);$this->audit->record($r,'content.page_updated',$page,$before,$page->fresh()->toArray());return back()->with('success','Content page updated.');}
}
