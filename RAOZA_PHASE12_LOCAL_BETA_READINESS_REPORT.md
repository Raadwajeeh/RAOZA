# RAOZA Phase 12 — Local Beta Readiness Report

**Date:** 18 October 2026  
**Repository baseline:** `203becbd2107d5fec2cbba503e10e7a95aab94e5` on `main`  
**Target assessed:** local internal beta  
**Public production assessment:** documentation only; no production systems were changed

## 1. الملخص التنفيذي

تمت استعادة بيئة RAOZA المحلية والتحقق منها فعليًا، وليس بمجرد تشغيل أمر. يعمل PHP وHerd وComposer وLaravel وPostgreSQL وVite، وتم فتح المتجر والإدارة في Chromium على قياسات سطح المكتب واللوحي والهاتف. نُفذت رحلة شراء كاملة على قاعدة PostgreSQL المحلية باستخدام دفع تجريبي فقط، كما نُفذت رحلة إدارة للمنتج والطلب، وتمت إعادة بيانات المنتج المؤقتة إلى قيمتها الأصلية.

ملفات Herd التي سببت العطل السابق أصبحت موجودة في تثبيت Herd الحالي، لذلك لم يكن من الآمن أو الضروري تعطيل `auto_prepend_file` أو امتدادات Redis/MongoDB/Herd. أُخذت نسخ احتياطية مؤرخة من `php.ini` قبل أي احتمال للتعديل، وبقي الملف نفسه دون تغيير. تم تحديث Composer العام من 2.10.2 إلى 2.10.3 لإزالة تحذير أمني عالي ومتوسط، مع نسخة احتياطية قابلة للرجوع.

تم اكتشاف عيب واجهة حقيقي وإصلاحه: صفحة الشحن والإنتاج في الإدارة كانت تسجل ثلاثة أخطاء Console بسبب تعبير HTML غير صالح في حقول رمز شركة الشحن. بعد تهريب الشرطة داخل النمط، أصبحت الصفحة بلا أخطاء Console، وبقي تحقق الإدخال صحيحًا.

**الحكم:** **READY** لنسخة تجريبية داخلية محلية.  
**الحكم العام للإطلاق الإنتاجي:** **NOT READY**؛ ما زالت الموافقات القانونية والضريبية والمحاسبية، وبيانات البائع، والنسخ الاحتياطي/المراقبة الإنتاجية، ومزودات الدفع والبريد والشحن الحقيقية غير معتمدة أو غير مفعلة عمدًا.

## 2. الحالة الأولية والمشكلات الجذرية

### Git عند البداية

- الفرع: `main`.
- `HEAD` و`origin/main`: `203becb`.
- تغييرات Phase 11 كانت موجودة وغير ملتزمة في `.env.example` و`README.md` و`composer.json` مع ملفات جديدة للـ launcher والتقرير واختبار الأصول.
- `playwright.config.ts` كان محذوفًا محليًا، مع حذف سابق لـ 11 تقريرًا متتبعًا. تم الحفاظ على حذف التقارير كما هو.
- لم يُنفذ reset أو clean أو force push أو commit أو push.

### PHP/Herd

العطل المبلغ عنه سابقًا كان متسقًا مع تثبيت Herd ناقص/قديم الموارد: كان `php.ini` يشير إلى أربعة ملفات تحت `C:\Program Files\Herd\resources\app.asar.unpacked\resources`، وكانت غير موجودة وقت الخطأ، ما جعل `auto_prepend_file` قاتلًا قبل بدء Laravel.

الفحص الحالي أكد:

- Herd 2.0.1 موجود في `C:\Program Files\Herd\Herd.exe`، وآخر تعديل للتثبيت 16 أكتوبر 2026.
- الملفات الأربعة المشار إليها موجودة الآن في المسارات التي يحددها `php.ini`: loader وRedis وMongoDB وHerd DLL.
- wrapper الفعلي `C:\Users\rrqwa\.config\herd\bin\php.bat` يشغل `php84\php.exe`.
- PHP CLI هو 8.4.26 ولا يصدر تحذيرات startup.
- الاستنتاج المؤكد: الإعداد لم يحتج تغيير مسارات؛ موارد Herd نفسها أُعيدت/توفرت بعد العطل. لا يوجد دليل على انتقال Herd إلى مسار آخر.
- لم يتم تعطيل أي امتداد، وبالتالي لا يوجد أثر سلبي جديد على مشاريع Herd الأخرى.

### خادم الصور السابق

أكد تقرير Phase 11 والاختبارات الحالية أن الأمر الخام المحظور كان يمرر طلبات الملفات العامة إلى Laravel ويعيد HTML بدل الصورة. launcher الحالي يستخدم `php artisan serve --no-reload` ويعيد MIME الصحيح.

## 3. الملفات والإعدادات المتغيرة

### تغييرات Phase 12

- `resources/js/Pages/Admin/Fulfillment/Queue.tsx`
  - تغيير `[a-z0-9-]+` إلى `[a-z0-9\-]+` في حقول carrier code.
  - السبب: منع أخطاء RegExp في Chromium الحديث مع الحفاظ على نفس قواعد الإدخال.
- `playwright.config.ts`
  - استعيدت النسخة المطابقة تمامًا لـ `HEAD` لأن حذفها كان يعطل `npm run test:e2e` وGitHub Actions.
  - لا يظهر الملف كتغيير نهائي لأنه عاد إلى حالة المستودع.
- `RAOZA_PHASE12_LOCAL_BETA_READINESS_REPORT.md`
  - هذا التقرير.

### تغييرات Phase 11 المحفوظة وغير الملتزمة

- `.env.example`
- `README.md`
- `composer.json`
- `scripts/start-local.ps1`
- `tests/Feature/Storefront/StorefrontAssetReadinessTest.php`
- `RAOZA_LOCAL_STOREFRONT_RECOVERY_PHASE11_REPORT.md`

### إعدادات خارج Git

- نسختان متطابقتان مؤرختان من `php.ini`، كل منهما 71,292 بايت وتطابق SHA-256 مع الملف الحالي:
  - `php.ini.phase12-20261018-014900.bak`
  - `php.ini.phase12-20261018-014909.bak`
- لم يتغير `php.ini`.
- Composer العام حُدث إلى 2.10.3. النسخة السابقة محفوظة في:
  - `C:\Users\rrqwa\.config\herd\bin\composer.phar.phase12-20261018-020641.bak`
- هذا تحديث عام لأداة Composer المستخدمة من مشاريع Herd الأخرى؛ لم تتغير dependencies الخاصة بأي مشروع.

## 4. نتيجة إصلاح PHP/Herd

| فحص | النتيجة |
|---|---|
| PHP CLI | PASS — 8.4.26 |
| php.ini المحمل | PASS — ملف Herd php84 المتوقع |
| auto-prepend loader | PASS — الملف موجود ويُحمّل |
| Redis / MongoDB / Herd extensions | PASS — محملة |
| Laravel/PostgreSQL extensions | PASS — `pdo_pgsql` و`pgsql` محملان |
| امتدادات Laravel المطلوبة | PASS — curl, DOM, fileinfo, GD, intl, mbstring, OpenSSL, sodium, XML, ZIP |
| Composer | PASS — 2.10.3 |
| Composer diagnose | PASS بالكامل |
| Laravel Artisan | PASS — Laravel 12.69.2، environment محلي |
| PostgreSQL connection | PASS — `migrate:status` قرأ القاعدة، 26/26 migrations Ran |

## 5. الخادم المحلي والروابط

تم تشغيل واختبار كلا الوضعين:

- `composer run dev:windows`
- `composer run dev:windows:queue`

النتائج:

| URL / خدمة | النتيجة |
|---|---|
| `http://127.0.0.1:8080` | PASS — HTTP 200 |
| `http://127.0.0.1:8080/health/ready` | PASS — HTTP 200 JSON |
| `http://127.0.0.1:5175/@vite/client` | PASS — HTTP 200 JavaScript |
| PNG logo | PASS — `image/png` |
| WebP hero | PASS — `image/webp` |
| WOFF2 font | PASS — `font/woff2` |
| Queue worker | PASS — بدأ وعالج مهمتي إشعار عبر log mail |
| Failed jobs | PASS — صفر |
| Duplicate listeners | PASS — مستمع واحد لكل منفذ أثناء التشغيل |
| Safety overrides | PASS — demo payments، log mail، analytics none، localhost cookie |

أُوقفت عمليات الفحص التي بدأها Phase 12 بعد انتهاء التدقيق لتجنب ترك عمليات مخفية أو تعارض منافذ. لا يعني ذلك فشل الخدمة؛ الروابط أعلاه موثقة من تشغيل فعلي.

## 6. قاعدة البيانات والكتالوج

لم يتم تشغيل seeder في Phase 12 لأن البيانات المطلوبة كانت موجودة وسليمة. لم يتم تنفيذ `migrate:fresh` أو wipe أو reset على PostgreSQL.

### قبل رحلة الشراء

| عنصر | نتيجة |
|---|---|
| المنتجات | 6، كلها active ومنشورة |
| المتغيرات | 48 |
| المتغيرات active | 36 |
| صور المنتجات | 30 |
| الفئات | 2 |
| المجموعات | 2 |
| مخزون on-hand | 483 |
| reserved | 0 |
| متغيرات متاحة | 46 |
| صور/متغيرات/مخزون يتيم | 0 |
| ملفات صور مفقودة | 0 |
| روابط الفئات | 6؛ لا منتج نشط بلا فئة |
| روابط المجموعات | 6؛ لا منتج نشط بلا مجموعة |
| الحسابات | حساب admin اصطناعي واحد بنطاق `.test`، ولا حسابات غير اختبارية |
| الطلبات | 0 |

كل منتج يملك 8 متغيرات وصورًا مطابقة لمساره. الأسعار الأساسية هي 32.95–69.95 يورو، ولا توجد أسعار متغير مخالفة في بيانات QA. المنتج Deep Burgundy Hoodie يتضمن متغير XL ظاهرًا بوضوح كـ out of stock.

### بعد رحلة Phase 12

- أُنشئ طلب ضيف اصطناعي واحد، مدفوع عبر simulator فقط، ثم نُقل إداريًا إلى `printing`.
- استُخدم خصم QA صالح مرة واحدة، ورفض التطبيق كودًا غير صالح قبل ذلك.
- انخفض المخزون الفعلي بوحدة واحدة إلى 482، و`reserved = 0`.
- لا توجد failed jobs أو jobs معلقة؛ سُجل إشعاران log-only.
- لم يُنشأ مستخدم عميل ولم تُمس حسابات حقيقية.
- تعديل وصف المنتج المؤقت انعكس على storefront ثم أُعيد إلى النص الأصلي وتأكدت الاستعادة.

## 7. التدقيق البصري ولقطات الشاشة

تم استخدام Chromium حقيقي عبر Playwright CLI، وفُحصت اللقطات بصريًا. الهوية المعتمدة والألوان والخطوط والصور بقيت كما هي.

اللقطات المحلية المحفوظة تحت `output/playwright/phase12/` (المجلد ignored وغير مقترح للـ Git):

- `home-desktop-1440.png`
- `home-tablet-768.png`
- `home-mobile-375.png`
- `mobile-menu-open-375.png`
- `order-confirmation-mobile.png`

النتائج:

- لا صور مكسورة في الصفحة الرئيسية أو sweeps العامة/الإدارية.
- لا horizontal overflow في 1440 و768 و375 CSS px.
- hero، logo، campaign photography، product cards، typography وspacing ظهرت بصورة متماسكة.
- القائمة الهاتفية تفتح بحالة expanded صحيحة وتحتوي روابط المتجر والمجموعات والحساب والمساعدة.
- معرض المنتج يغير الصورة، والخيارات والحالة والسعر والمخزون واضحة.
- لا Console errors أو warnings في الرحلة الناجحة.
- فحص الأداء المحلي للصفحة الرئيسية: DOMContentLoaded نحو 766 ms، load نحو 772 ms، 34 resource، ونحو 1.28 MB transferred. هذا قياس محلي إرشادي وليس بديلًا عن قياس production.
- صفحة 404 الحالية سليمة وظيفيًا وتعيد 404، لكنها صفحة Laravel بسيطة وغير متسقة بصريًا مع العلامة.

## 8. مصفوفة رحلات العميل

| الرحلة | الحالة | الدليل |
|---|---|---|
| Homepage → collection/shop → product → variant → cart → checkout → demo order | PASS | نُفذت حيًا على PostgreSQL وانتهت بتأكيد paid |
| Product search/filter → product → cart | PASS | search URL وfilters ظهرت وعملت؛ E2E يغطي التفاعل الكامل |
| Out-of-stock / invalid variant | PASS | XL Deep Burgundy ظهر out of stock وتعذر الإضافة |
| Invalid checkout fields and recovery | PASS | submit الفارغ أبقى الصفحة وركز أول حقل؛ الإدخال الصحيح أكمل |
| Discount rejection / acceptance | PASS | كود غير صالح رُفض؛ خصم QA الصحيح طُبق وحُسب على الخادم |
| Customer registration/login/account/order history | PASS (isolated E2E) | registration verification، saved address، order ownership/history مغطاة |
| Cart quantity update and removal | PASS | quantity حيًا 1→2→1؛ removal مغطى باختبارات PHP/E2E |
| Shipping selection / totals / VAT / terms | PASS | ظهرت وعُدلت server-side؛ reconciliation tests تمر |
| Legal/contact/FAQ/returns/shipping/privacy pages | PASS | 9 صفحات content + account/order access أعادت 200 |
| Empty/unavailable/error states | PASS with P2 note | empty cart redirects checkout؛ missing product 404 غير branded |
| Mobile shopping journey | PASS | رحلة variant-to-cart الآلية + الرحلة الحية 375 px |
| Accessibility/keyboard | PASS | axe mobile/desktop + focus containment/restoration |

## 9. مصفوفة الإدارة

| المجال | الحالة | الدليل |
|---|---|---|
| Admin login and authorization | PASS | login حي؛ اختبارات customer/staff denial تمر |
| Dashboard | PASS | 200، counts صحيحة، الطلب الحديث ظاهر |
| Products / variants / stock | PASS | 6 منتجات/48 متغيرًا؛ update حي وانعكاس storefront ثم revert |
| Categories / collections | PASS | صفحات 200 وعلاقات البيانات سليمة |
| Orders | PASS | الطلب المدفوع ظهر بالتفاصيل والتسويات |
| Order safe status update | PASS | `processing → printing` مع success state/history |
| Customer management | PASS | صفحة 200 واختبارات ownership/guest detail تمر |
| Discounts | PASS | صفحة 200؛ usage limit/concurrency tests تمر |
| Shipping configuration | PASS بعد إصلاح | صفحة 200؛ carrier patterns صالحة؛ صفر Console errors |
| Content management | PASS | صفحة 200 واختبارات staging/approval تمر |
| Image upload / asset management | PASS (automated) | upload/type rejection/public storage tests تمر |
| Roles / permissions | PASS | owner/support/fulfillment/customer restrictions تمر |
| Validation / failure handling | PASS | backend validation، CSRF، immutable audit tests تمر |
| Admin page sweep | PASS | 16 صفحة: كلها 200، بلا broken images/overflow/console/network failures |

## 10. نتائج التحقق الآلي

| التحقق | النتيجة الدقيقة |
|---|---|
| PHPUnit / Laravel | **232 passed, 1,615 assertions** |
| Storefront asset tests | ضمن المجموعة الكاملة؛ 30 صورة ومسارات case-sensitive سليمة |
| TypeScript | PASS — `tsc --noEmit` |
| Vite production build | PASS — 830 modules |
| Build verifier | PASS — 58 manifest entries / 59 files |
| Playwright E2E | **20 passed** في Chromium + Firefox smoke + WebKit smoke |
| Accessibility | PASS ضمن E2E: axe mobile/desktop + focus + checkout semantics |
| Composer validate | PASS strict |
| Composer project audit | PASS — no advisories |
| npm audit | PASS — 0 vulnerabilities |
| Composer tool diagnose | PASS بعد 2.10.3 |
| PostgreSQL migrations | PASS — 26/26 Ran |
| Local launcher | PASS على 8080/5175 |
| Queue launcher | PASS؛ job queue drained؛ 0 failed |
| GitHub Actions | لم تُشغل remotely ولم يُدفع commit؛ نفس الأوامر المحلية المطلوبة في CI مرت |

ملاحظة P3: Playwright/Node يطبع deprecation warning عن `shell: true` في global setup على Windows. الاختبارات تمر، لكنه يستحق إزالة الاعتماد على shell في مرحلة لاحقة.

## 11. العيوب المتبقية حسب الأولوية

### P0

- لا يوجد عيب P0 معروف للنسخة المحلية.

### P1

- لا يوجد عيب P1 معروف للنسخة المحلية.
- للإنتاج فقط: الموافقات القانونية/الضريبية/المحاسبية وملف البائع والترقيم الرسمي غير مكتملة؛ لذلك الإنتاج NOT READY.

### P2

- صفحة 404 غير branded رغم أن status صحيح.
- التشغيل الآمن يعتمد على launcher؛ ملف `.env` المحلي نفسه ما زال يحل إلى provider غير demo عند التشغيل اليدوي. لم تُستخدم أي مفاتيح أو اتصالات حية، والـ launcher يفرض demo/log/none.
- السجلات المحلية قديمة وكبيرة نسبيًا وتحتوي أخطاء تاريخية من مراحل سابقة؛ تحتاج سياسة تدوير/تنظيف مدروسة، لا حذفًا أعمى.
- لا توجد مراقبة أو نسخ احتياطي production مُثبت ومجرب ضمن Phase 12.

### P3

- تحذير Node `DEP0190` في E2E global setup على Windows.
- full-page screenshots تحتاج scrolling لتحميل lazy images؛ تم ذلك في اللقطات النهائية.
- لا يوجد فحص gitleaks محلي؛ GitHub workflow لديه job مخصص، لكن لم يُشغل عن بعد في هذه المرحلة.

## 12. المخاطر الأمنية والتشغيلية

- demo payment route مقفلة server-side خارج local/testing، وPhase 12 لم يجر أي دفع حقيقي.
- البريد بقي log-only؛ queue عالج إشعارات محلية ولم يرسل بريدًا خارجيًا.
- analytics بقي disabled.
- invoice issuance بقي disabled، وصفحة الطلب تعرض أسباب الإيقاف بوضوح.
- PostNL/DHL بقيا placeholders غير فعّالين، ولم يحدث carrier HTTP.
- authorization وCSRF وsession regeneration وupload content validation وguest-order isolation مغطاة باختبارات ناجحة.
- Composer العام كان أداة معرضة لثغرات معروفة وأصبح 2.10.3 بلا advisories.
- ملف browser storage المؤقت الذي احتوى session cookies حُذف بعد الفحص.
- لقطات QA والسجلات وقاعدة SQLite الاختبارية ignored ولن تدخل Git.
- PostgreSQL المحلي يحتوي الآن طلب QA اصطناعيًا واحدًا وحركة مخزون حقيقية واحدة؛ يجب اعتباره بيانات اختبار محلية وعدم نقله إلى production.

## 13. حالة Git ونطاق الالتزام المقترح

لم يتم commit أو push.

النطاق المقترح بعد موافقة المالك:

1. ملفات Phase 11 الستة الخاصة بالاستعادة/launcher/assets/report.
2. إصلاح `resources/js/Pages/Admin/Fulfillment/Queue.tsx`.
3. هذا التقرير.

يجب استبعاد:

- `output/playwright/**`
- `database/e2e.sqlite`
- `public/build/**`
- أي credentials أو ملفات `.env`
- نسخ Herd/Composer الاحتياطية
- الطلب/قاعدة PostgreSQL المحلية
- حذوفات التقارير الـ11 السابقة إلى أن يؤكد المالك أنها مقصودة

استعادة `playwright.config.ts` لا تضيف diff لأنها أعادته إلى حالة `HEAD`.

## 14. الحكم النهائي

### A. Local internal beta

**READY**

المتجر قابل للاستخدام محليًا، الكتالوج والصور والمخزون متماسكة، الدفع تجريبي، البريد log-only، الرحلات الأساسية والإدارة تعمل، والاختبارات الآلية والبصرية ناجحة. لا توجد P0/P1 محلية معروفة.

### B. Public production launch

**NOT READY**

لا ينبغي تفعيل hosting أو Mollie live أو email live أو carrier APIs أو refunds حقيقية أو invoice issuance. المتبقي يتطلب قرارات وموافقات مهنية وتجهيز تشغيل production مستقل.

## 15. خطة Phase 13 ذات الأولوية

1. اعتماد نطاق commit انتقائي لـ Phase 11/12 وتسوية حذوفات التقارير السابقة.
2. إضافة 404/500 branded وآمنة مع اختبارات.
3. إزالة `shell: true` من E2E setup على Windows.
4. تدوير logs المحلية ووضع سياسة retention موثقة دون حذف سجلات لازمة.
5. إضافة فحص gitleaks محلي أو تشغيل CI بعد commit المقترح.
6. إعداد runbook نسخ احتياطي/استعادة ومراقبة production دون نشر فعلي.
7. جلسة قرارات المالك مع المستشار القانوني/المحاسبي حول seller identity وVAT وinvoice numbering وprivacy/returns terms.
8. بعد الموافقات فقط: خطة staging منفصلة لاختبار providers الحقيقية، مع بقاء live actions معطلة حتى sign-off.

## 16. قرارات مطلوبة من المالك

- هل حذوفات التقارير الـ11 السابقة مقصودة أم يجب إبقاؤها خارج commit الحالي فقط؟
- الموافقة على نطاق commit الانتقائي المقترح، دون screenshots أو بيانات محلية.
- هل يبقى الطلب الاصطناعي في قاعدة QA كسجل قبول، أم يُعالج لاحقًا بسياسة تنظيف QA معتمدة؟ لم يتم حذفه الآن.
- اعتماد النصوص القانونية وسياسة المرتجعات والخصوصية والضرائب والفواتير مع مختصين قبل أي إطلاق عام.
- تحديد سياسة backup/monitoring وبيئة staging قبل مناقشة production.

