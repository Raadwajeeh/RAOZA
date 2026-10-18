# RAOZA — Phase 13 Cleanup & Image Optimization Report

**Date:** 18 October 2026

**Baseline:** `77a40216a353b204c616f5d6b3c78214653d2146`

**Working branch:** `phase13-cleanup-image-optimization`

**Scope:** local repository cleanup, asset optimization, and regression verification only

## 1. الملخص التنفيذي

تم استئناف Phase 13 من حالتها المحلية القائمة بعد أن توقفت الجلسة السابقة بسبب حد الاستخدام، وليس بسبب عطل معروف. لم تُعَد عملية التنظيف من البداية، ولم تُمسح قاعدة البيانات، ولم تُنفّذ أي عملية نشر أو دفع إلى GitHub.

النتيجة النهائية **PASS**. أزيلت 30 نسخة PNG كبيرة وغير مستخدمة من صور الكتالوج بعد إثبات وجود بدائل WebP مطابقة ومتكاملة مع الواجهة وقاعدة البيانات، بإجمالي توفير مستودعي قدره **57,681,404 بايت**. بقيت 90 صورة WebP للكتالوج: 30 صورة أصلية للعرض، و30 بعرض 640 بكسل، و30 بعرض 960 بكسل. كما أزيل سكربت التحويل الذي لم يعد له مدخلات، واعتماد `sharp` الخاص به، وستة ملفات `.gitkeep` فارغة داخل مجلدات أصبحت تحتوي على كود فعلي.

تم التحقق فعليًا من قاعدة PostgreSQL المحلية، ومن كل ملفات الكتالوج عبر HTTP، ومن العرض المرئي على سطح المكتب واللوحي والهاتف. نجحت اختبارات Laravel وعددها **233** مع **1,678 assertion**، ونجح TypeScript وVite وفحص manifest والتدقيق الأمني، ونجحت **20** حالة Playwright E2E. بقيت أصول العلامة الأصلية ومجلدات المالك والنسخ اليدوية غير المؤكدة والتقارير التاريخية الـ11 دون تغيير.

## 2. حالة Git عند البدء والاستئناف

- كان `HEAD` و`main` و`origin/main` جميعًا عند الالتزام المتوقع `77a40216a353b204c616f5d6b3c78214653d2146`.
- أنشئ فرع العمل المحلي `phase13-cleanup-image-optimization`؛ لم يُدفع إلى البعيد.
- كان خط الأساس نظيفًا عند بدء Phase 13.
- عند الاستئناف، وُجدت فقط تغييرات Phase 13 المذكورة في هذا التقرير؛ لم توجد تغييرات مستخدم غير مرتبطة.
- لا commit، ولا push، ولا merge، ولا إعادة كتابة للتاريخ نُفذت.
- جميع التقارير التاريخية الـ11 المحمية موجودة، ونتيجة مقارنتها مع `HEAD` هي صفر تغييرات.

## 3. جرد الملفات والأصول

### خط الأساس المتتبع

| المقياس | القيمة |
|---|---:|
| الملفات المتتبعة | 598 |
| الحجم الإجمالي للملفات المتتبعة | 68,070,966 بايت |
| أصول الوسائط المتتبعة | 138 ملفًا / 65,983,197 بايت |
| الصور النقطية المتتبعة | 131 ملفًا / 65,979,037 بايت |
| كتالوج `public/catalog` | 120 ملفًا / 63,515,736 بايت |
| PNG مصادر الكتالوج | 30 ملفًا / 57,681,404 بايت |
| WebP الكتالوج | 90 ملفًا / 5,834,332 بايت |
| ناتج Vite production | 60 ملفًا / 1,019,094 بايت |

أكبر مساحات Git كانت `public` بحجم 65,983,522 بايت، ثم ملفات الجذر 730,936 بايت، ثم `app` بحجم 407,581 بايت. أكد ذلك أن صور PNG المكررة وظيفيًا هي فرصة التنظيف الأساسية، وليست شفرة التطبيق.

### التصنيف

- **A — مستخدم فعليًا:** كود Laravel/React، المسارات، الاختبارات، المهاجرات، 90 WebP للكتالوج، 7 WebP للحملات، 4 PNG للعلامة، 6 SVG للبيانات التجريبية، favicon، الخطوط، وإعدادات البناء والتشغيل.
- **B — غير مستخدم ومثبت:** 30 PNG للكتالوج لا تشير إليها قاعدة البيانات أو الواجهة بعد استخدام WebP؛ سكربت التحويل الذي لم تعد له مصادر؛ ستة `.gitkeep` داخل مجلدات غير فارغة.
- **C — نسخ مطابقة:** لم توجد صور متتبعة متطابقة بالـhash. النسخة المحلية `raoza-build - kopie` طابقت `raoza-build` وتم حذف الاثنين باعتبارهما ناتجَي بناء مهملين.
- **D — نواتج مولدة:** حُذفت محليًا مجلدات `artifacts` و`raoza-build` و`raoza-build - kopie` بعد إثبات أنها ignored وغير متتبعة.
- **E — تحتاج قرار مالك:** مجلدات العمل/المصدر اليدوية مثل `7 photos` و`LOGO` و`mix` و`Projecten*` و`public - kopie` و`resources - kopie` ونسخ المسارات/السكربتات/التخزين. لم تُحذف ولم تدخل Git.

## 4. الملفات المحذوفة والأدلة

### أ. 30 مصدر PNG للكتالوج

الجدول التالي يسجل كل PNG محذوف من خلال اسم بديل WebP المقابل. جميع PNG كانت معتمة، وكان حجمها 1122×1402 بكسل عدا `editorial-tee-back` بحجم 1312×1199. لكل صف بديل full-size ونسختان 640/960 مستخدمة عبر `srcset`. عمود التخفيض يقارن PNG القديمة بملف WebP الكامل فقط.

| بديل WebP المتبقي (استُبدل PNG بالامتداد نفسه) | PNG قبل | WebP كامل | 640 | 960 | تخفيض الكامل |
|---|---:|---:|---:|---:|---:|
| `public/catalog/deep-burgundy-hoodie/raoza-deep-burgundy-hoodie-back.webp` | 1,626,574 | 52,996 | 20,552 | 36,256 | 96.74% |
| `public/catalog/deep-burgundy-hoodie/raoza-deep-burgundy-hoodie-detail.webp` | 2,689,166 | 234,438 | 75,266 | 161,566 | 91.28% |
| `public/catalog/deep-burgundy-hoodie/raoza-deep-burgundy-hoodie-editorial.webp` | 1,904,369 | 93,570 | 36,168 | 67,024 | 95.09% |
| `public/catalog/deep-burgundy-hoodie/raoza-deep-burgundy-hoodie-front.webp` | 1,603,198 | 63,974 | 25,686 | 45,876 | 96.01% |
| `public/catalog/editorial-tee/raoza-editorial-tee-back.webp` | 1,787,934 | 63,686 | 12,862 | 26,566 | 96.44% |
| `public/catalog/editorial-tee/raoza-editorial-tee-detail.webp` | 2,317,921 | 187,468 | 73,696 | 139,822 | 91.91% |
| `public/catalog/editorial-tee/raoza-editorial-tee-editorial.webp` | 1,849,097 | 74,114 | 27,032 | 52,644 | 95.99% |
| `public/catalog/editorial-tee/raoza-editorial-tee-front.webp` | 1,773,974 | 56,426 | 12,900 | 31,162 | 96.82% |
| `public/catalog/editorial-tee/raoza-editorial-tee-lifestyle.webp` | 2,064,040 | 106,944 | 32,786 | 72,222 | 94.82% |
| `public/catalog/essential-hoodie/raoza-essential-hoodie-back.webp` | 1,623,260 | 49,468 | 17,280 | 33,762 | 96.95% |
| `public/catalog/essential-hoodie/raoza-essential-hoodie-detail.webp` | 2,153,244 | 147,744 | 51,816 | 104,662 | 93.14% |
| `public/catalog/essential-hoodie/raoza-essential-hoodie-editorial.webp` | 1,606,855 | 55,870 | 23,432 | 40,970 | 96.52% |
| `public/catalog/essential-hoodie/raoza-essential-hoodie-front.webp` | 1,755,049 | 67,356 | 23,702 | 46,336 | 96.16% |
| `public/catalog/essential-hoodie/raoza-essential-hoodie-lifestyle.webp` | 1,762,250 | 76,000 | 28,016 | 53,868 | 95.69% |
| `public/catalog/mark-tee/raoza-mark-tee-back-editorial.webp` | 1,741,064 | 61,426 | 22,918 | 42,754 | 96.47% |
| `public/catalog/mark-tee/raoza-mark-tee-back.webp` | 1,888,235 | 71,400 | 14,508 | 40,232 | 96.22% |
| `public/catalog/mark-tee/raoza-mark-tee-detail.webp` | 2,320,393 | 165,918 | 53,328 | 115,808 | 92.85% |
| `public/catalog/mark-tee/raoza-mark-tee-editorial.webp` | 1,863,401 | 78,190 | 27,656 | 54,828 | 95.80% |
| `public/catalog/mark-tee/raoza-mark-tee-front.webp` | 1,935,336 | 77,944 | 17,346 | 45,990 | 95.97% |
| `public/catalog/mark-tee/raoza-mark-tee-lifestyle.webp` | 1,815,710 | 69,318 | 26,000 | 49,780 | 96.18% |
| `public/catalog/signature-tee/raoza-signature-tee-back.webp` | 1,711,958 | 41,978 | 9,032 | 23,718 | 97.55% |
| `public/catalog/signature-tee/raoza-signature-tee-detail.webp` | 2,695,324 | 229,282 | 78,874 | 162,708 | 91.49% |
| `public/catalog/signature-tee/raoza-signature-tee-editorial.webp` | 2,031,989 | 106,356 | 41,642 | 76,970 | 94.77% |
| `public/catalog/signature-tee/raoza-signature-tee-front.webp` | 1,730,586 | 45,514 | 13,390 | 26,562 | 97.37% |
| `public/catalog/signature-tee/raoza-signature-tee-lifestyle.webp` | 1,544,589 | 42,246 | 17,524 | 30,380 | 97.26% |
| `public/catalog/structured-hoodie/raoza-structured-hoodie-back.webp` | 1,702,170 | 58,730 | 22,008 | 40,654 | 96.55% |
| `public/catalog/structured-hoodie/raoza-structured-hoodie-detail.webp` | 2,943,634 | 285,868 | 92,568 | 200,378 | 90.29% |
| `public/catalog/structured-hoodie/raoza-structured-hoodie-editorial.webp` | 1,885,068 | 85,640 | 33,862 | 62,750 | 95.46% |
| `public/catalog/structured-hoodie/raoza-structured-hoodie-front.webp` | 1,786,847 | 69,832 | 25,080 | 47,976 | 96.09% |
| `public/catalog/structured-hoodie/raoza-structured-hoodie-lifestyle.webp` | 1,568,169 | 58,218 | 23,776 | 41,488 | 96.29% |

دليل الأمان: لا توجد مراجع `.png` للكتالوج في قاعدة البيانات؛ seeder يستخدم WebP صراحة؛ 90 ملف WebP موجود؛ 30 مجموعة responsive كاملة؛ اختبارات assets وE2E والمتصفح نجحت. لم يُعَد ضغط WebP في هذه المرحلة تفاديًا لفقد تراكمي في الجودة.

### ب. ملفات أخرى محذوفة

| الملف | سبب أمان الحذف |
|---|---|
| `scripts/optimize-catalog-images.mjs` | كان يحول PNG التي أزيلت؛ لا مراجع أخرى إليه بعد حذف npm script. |
| `app/Domain/Commerce/.gitkeep` | صفر بايت والمجلد يحتوي ملفات فعلية متتبعة. |
| `app/Domain/Content/.gitkeep` | صفر بايت والمجلد يحتوي ملفات فعلية متتبعة. |
| `app/Domain/Fulfillment/.gitkeep` | صفر بايت والمجلد يحتوي ملفات فعلية متتبعة. |
| `app/Domain/Inventory/.gitkeep` | صفر بايت والمجلد يحتوي ملفات فعلية متتبعة. |
| `app/Domain/Payments/.gitkeep` | صفر بايت والمجلد يحتوي ملفات فعلية متتبعة. |
| `app/Domain/Returns/.gitkeep` | صفر بايت والمجلد يحتوي ملفات فعلية متتبعة. |

إجمالي الملفات المتتبعة المحذوفة: **37**. لا توجد migrations أو اختبارات أو إعدادات تشغيل أو تقارير محذوفة.

## 5. جرد الصور المحفوظة وقرار الصيغة

### أصول العلامة — محفوظة دون تغيير

| المسار | الأبعاد | الحجم | القرار والاستخدام |
|---|---:|---:|---|
| `public/brand/raoza-lockup-gold.png` | 1254×1254 | 419,351 | PNG شفاف؛ مستخدم في العلامة/البريد؛ الحفاظ على الشفافية والدقة. |
| `public/brand/raoza-monogram-gold.png` | 1254×1254 | 450,746 | PNG شفاف؛ أصل علامة معتمد. |
| `public/brand/raoza-wordmark-burgundy.png` | 2172×724 | 397,971 | PNG شفاف؛ رأس/علامة. |
| `public/brand/raoza-wordmark-light.png` | 2172×724 | 489,797 | PNG شفاف؛ استعمال على الخلفيات الداكنة. |

اختبار إعادة ترميز brand غيّر البكسلات؛ لذلك لم يعتمد. لا توجد شعارات SVG بديلة معتمدة لهذه الملفات، ولم تُستبدل الهوية.

### صور الحملات — محفوظة دون تغيير

| المسار | الأبعاد | الحجم |
|---|---:|---:|
| `public/campaign/core-essentials-campaign.webp` | 1200×1500 | 49,550 |
| `public/campaign/drop01-campaign.webp` | 1200×1500 | 93,562 |
| `public/campaign/editorial-wide.webp` | 1800×1200 | 129,868 |
| `public/campaign/home-hero-desktop.webp` | 2560×1440 | 174,070 |
| `public/campaign/home-hero-mobile.webp` | 1200×1500 | 75,320 |
| `public/campaign/hoodies-campaign.webp` | 1200×1500 | 85,116 |
| `public/campaign/tshirts-campaign.webp` | 1200×1500 | 97,950 |

هي بالفعل WebP صغيرة ومستخدمة. يستعمل hero مصدرًا منفصلًا للموبايل عبر `<picture>`. لم يكن AVIF أو إعادة الضغط مبررًا بمكسب مثبت يوازن تعقيد fallback واحتمال فقد الجودة.

### SVG أخرى — محفوظة

`public/demo/product-black.svg` و`product-burgundy.svg` و`product-cream.svg` و`product-editorial.svg` و`product-gold.svg` و`product-rose.svg` (629 بايت لكل ملف) مرتبطة بالـdemo seeder/التوثيق، و`public/favicon.svg` (386 بايت) مستخدم كأيقونة الموقع.

## 6. تغييرات الكود والاعتمادات

- `database/seeders/OfficialCatalogSeeder.php`: استبدال مسارات PNG الثلاثين بـWebP مباشرة، وإزالة تحويلات regex وقت التشغيل. بقيت العملية idempotent والتحقق من وجود/أبعاد الصورة قائمًا.
- `package.json`: حذف `images:optimize` وحذف `sharp` فقط.
- `package-lock.json`: تحديث متسق؛ أزيلت حزم `sharp` الاختيارية متعددة المنصات، دون تغيير بقية toolchain المقصودة.
- `docs/PRODUCTION.md`: توثيق أن release يحتوي WebP reviewed مع 640/960/full، وأن المصادر lossless تبقى خارج الحزمة القابلة للنشر.
- `tests/Feature/Storefront/StorefrontAssetReadinessTest.php`: اختبار يمنع عودة PNG، ويثبت وجود 90 WebP و30 base وكل مشتقات 640/960.
- لم يُحذف كود تجارة أو checkout أو دفع أو مخزون أو صلاحيات، ولم تُجرَ إعادة هيكلة عامة.

## 7. سلامة الكتالوج وقاعدة البيانات

تمت القراءة من PostgreSQL المحلية دون reset أو fresh أو seeding أو تعديل بيانات:

| الفحص | النتيجة |
|---|---:|
| المنتجات | 6، كلها active وpublished وبسعر موجب |
| المتغيرات | 48 (36 active؛ الحالات الأخرى تغطي سيناريوهات عدم التوفر) |
| سجلات المخزون | 48 |
| إجمالي on-hand / reserved | 482 / 0 |
| مخزون متاح سالب | 0 |
| صور المنتجات | 30، كلها WebP |
| مراجع PNG | 0 |
| ملفات مفقودة | 0 |
| alt مفقود | 0 |
| عدم تطابق أبعاد DB والملف | 0 |
| صور مربوطة بمتغير محدد | 0؛ الصور مرتبطة بالمنتج كما صمم الكتالوج |
| categories / collections | 2 / 2 |
| روابط المنتجات بالفئات / المجموعات | 6 / 6 |

تم طلب ملفات الكتالوج الـ90 جميعًا من Laravel المحلي؛ النتيجة **90/90 HTTP 200** و`Content-Type: image/webp`، وصفر استجابة HTML أو MIME خاطئ. لا توجد مراجع غير متوقعة إلى PNG المحذوفة.

## 8. القياسات قبل/بعد

| المقياس | قبل | بعد Phase 13 | الفرق |
|---|---:|---:|---:|
| الملفات المتتبعة (يشمل هذا التقرير الجديد) | 598 | 562 | −36 صافيًا (37 حذفًا + تقرير واحد) |
| الحجم الإجمالي المتوقع للملفات المتتبعة | 68,070,966 | 10,395,715 بايت | −57,675,251 بايت صافيًا |
| أصول الوسائط المتتبعة | 138 | 108 | −30 |
| حجم أصول الوسائط | 65,983,197 | 8,301,793 بايت | −57,681,404 |
| الصور النقطية | 65,979,037 | 8,297,633 بايت | −57,681,404 |
| ملفات الكتالوج | 120 | 90 | −30 |
| حجم الكتالوج | 63,515,736 | 5,834,332 بايت | −57,681,404 (90.81%) |
| WebP محفوظة | 90 | 90 | لا تغيير |
| تحويلات صور جديدة في Phase 13 | 0 | 0 | البدائل كانت موجودة مسبقًا |
| ناتج Vite production | 60 ملفًا / 1,019,094 بايت | 60 ملفًا / 1,019,094 بايت | لا تغيير |

حجم التقرير الجديد 25,003 بايت. يختلف صافي حجم الملفات المتتبعة عن توفير الصور وحده بسبب تعديل lockfile وإضافة التقرير. التوفير المؤكد في أصول Git هو **57,681,404 بايت**؛ لا يُخلط معه توفير الاعتمادات المحلية.

محليًا فقط، حُذفت نواتج ignored موثقة: `artifacts` ‏296,674,839 بايت، و`raoza-build` ‏973,332 بايت، ونسخته المطابقة ‏973,332 بايت، أي **298,621,503 بايت**. أدى `npm prune` بعد حذف `sharp` إلى خفض `node_modules` من 150,134,811 إلى 119,421,245 بايت، أي **30,713,566 بايت** إضافية. وبعد إغلاق المتصفحات والخوادم أزيلت مخلفات التحقق القابلة لإعادة الإنشاء (`.playwright-cli` و`public/build` و`database/e2e.sqlite` و`test-results` و`.phpunit.result.cache`) بحجم **17,198,659 بايت**. إجمالي توفير القرص المحلي الموثق **346,533,728 بايت**، وهو ليس حجم commit. بقيت لقطات Phase 13 المقصودة فقط في `output/playwright` كدليل ignored.

أحجام نقل صفحات dev لا تُظهر تحسنًا مقصودًا لأن المتصفح كان يستخدم WebP نفسها قبل حذف مصادر PNG غير المطلوبة. القياسات الكاملة بعد تحميل lazy assets كانت: الصفحة الرئيسية 2,845,861 بايت (صور 1,738,770)، المتجر 2,150,757 (1,066,596)، وصفحة المنتج 2,305,069 (1,210,206). هذه أرقام بيئة Vite/HMR وليست ادعاء أداء production. مكسب Phase 13 هنا هو حجم المستودع/النشر، لا تغيير bytes التي كان العميل ينزلها أصلًا.

## 9. الاختبارات الآلية

| الفحص | النتيجة |
|---|---|
| Laravel `php artisan test` | PASS — 233 tests، 1,678 assertions، 37.89s |
| Asset + catalog + local seeder targeted | PASS — 7 tests، 344 assertions |
| TypeScript `tsc --noEmit` | PASS |
| Vite production build | PASS — 830 modules، 60 files، 1,019,094 bytes |
| `production:verify-build` | PASS — 58 manifest entries / 59 referenced files |
| Composer validate strict | PASS |
| Composer diagnose | PASS |
| Composer audit | PASS — no advisories |
| npm audit | PASS — 0 vulnerabilities |
| Playwright E2E | PASS — 20/20 (Chromium 15، Firefox smoke 3، WebKit smoke 2)، 1.5 min |
| Accessibility | PASS ضمن E2E باستخدام axe واختبارات لوحة المفاتيح/focus/mobile menu |

غطت E2E checkout التجريبي، التجارة، الحساب، الإدارة، الشحن، المرتجعات، الفلاتر، التفويض، والاستجابة. لم تُنفذ دفعة أو رسالة بريد أو شحنة أو refund حقيقية.

## 10. المتصفح والفحص المرئي

تم تشغيل التطبيق محليًا على `http://127.0.0.1:8080` وVite على `http://127.0.0.1:5175` بالـlauncher المعتمد. فُحصت الصفحات التالية بمتصفح Chromium حقيقي: الصفحة الرئيسية، المتجر، T-shirts، Drop 01، منتجان، السلة، About، دخول العميل، ودخول الإدارة.

- صفر صور مكسورة، وصفر overflow أفقي، وصفر console errors/warnings في sweep النهائي.
- صفر طلب catalog PNG.
- product cards وgallery وhero والحملات والشعارات ظهرت صحيحة.
- فحصت الصفحة الرئيسية عند 1440px و768px و375px، وصفحة المنتج عند 1440px.
- لقطات الدليل المحلية (ignored وغير مقترحة للـcommit):
  - `output/playwright/phase13-final/home-desktop-1440.png`
  - `output/playwright/phase13-final/home-tablet-768.png`
  - `output/playwright/phase13-final/home-mobile-375.png`
  - `output/playwright/phase13-final/product-desktop-1440.png`
- مراجعة اللقطات أكدت سلامة النسب، الحدة، الهوية اللونية، responsive composition، وعدم وجود تداخل أو كسر ناتج عن التنظيف. شريط تفضيلات الخصوصية الظاهر هو حالة واجهة متوقعة وليس regression.

## 11. الملفات التي حُفظت عمدًا

- التقارير التاريخية الـ11: محمية ولم تتغير.
- PNG العلامة الأربعة: شفافة ومعتمدة ومستخدمة.
- WebP الحملات والكتالوج: مستخدمة وحجمها مناسب؛ لم تُضغط مرة أخرى.
- SVG demo وfavicon: مرتبطة بمسارات تشغيل/توثيق.
- migrations والاختبارات والـfixtures وإعدادات CI والتشغيل: لا يوجد دليل آمن لحذفها.
- uploads وبيانات PostgreSQL: لم تُمس.
- مجلدات المالك والنسخ اليدوية المذكورة في الفئة E: الاستخدام/قيمة المصدر غير مؤكدة، فتركت للمراجعة.
- `output/playwright` السابق والجديد: تاريخ QA مرتبط بالتقارير، بقي محليًا وignored.

## 12. المخاطر والملاحظات المتبقية

- تحذير Node `[DEP0190]` يظهر من طريقة shell في بنية Playwright الحالية؛ لم يفشل أي اختبار. يصنف P3 ويمكن علاجه منفصلًا دون خلطه بتنظيف الصور.
- الملفات اليدوية الكبيرة غير المتتبعة قد تستحق أرشفة خارج مجلد المشروع بقرار المالك؛ لم تُحذف لأنها قد تكون مصادر أصلية.
- صور admin المرفوعة ديناميكيًا ما زالت تقبل JPG/PNG/WebP ولا تُفترض لها مشتقات static؛ هذا سلوك مقصود وليس فجوة Phase 13.
- لم يضف AVIF لعدم وجود مكسب مقاس يبرر التعقيد وإعادة الضغط.
- الإنتاج العام ما زال خارج النطاق وغير جاهز وفق تقييم Phase 12؛ PASS هنا يخص تنظيف المستودع والـlocal internal beta.

## 13. نطاق commit الانتقائي المقترح

بعد موافقة المالك فقط:

- تعديل `database/seeders/OfficialCatalogSeeder.php`
- تعديل `docs/PRODUCTION.md`
- تعديل `package.json` و`package-lock.json`
- تعديل `tests/Feature/Storefront/StorefrontAssetReadinessTest.php`
- حذف `scripts/optimize-catalog-images.mjs`
- حذف ملفات `.gitkeep` الستة المذكورة
- حذف ملفات PNG الثلاثين المقابلة للجدول
- إضافة `RAOZA_PHASE13_CLEANUP_AND_IMAGE_OPTIMIZATION_REPORT.md`

يُستبعد صراحة: `.env`، قواعد البيانات، build، logs، caches، screenshots، test output، مجلدات المالك، وأي ملف غير مذكور. لم يُنفذ commit أو push.

### حالة Git النهائية المحلية

- 30 حذف PNG مرتبطة بالكتالوج ظاهرة حاليًا كحذف staged نتيجة استخدام `git rm` الانتقائي في جلسة التنفيذ الأولى.
- 5 ملفات نصية معدلة unstaged: seeder، production documentation، `package.json`، `package-lock.json`، واختبار assets.
- 7 حذوفات unstaged: سكربت التحويل وستة `.gitkeep`.
- ملف واحد untracked مقصود: هذا التقرير.
- لا تغييرات على `.env` أو قاعدة بيانات أو screenshot أو build أو التقارير التاريخية.
- فحص الأنماط الحساسة في الأسطر المضافة والتقرير: صفر نتيجة عالية الدلالة. `git diff --check`: PASS.
- الخوادم التي شغلتها Phase 13 أوقفت؛ المنفذان 8080 و5175 بلا listeners عند الإغلاق.

## 14. الحكم النهائي

**PASS** — اكتمل Phase 13 محليًا من حيث التنظيف المثبت، سلامة الصور والمراجع، الاختبارات، والفحص المرئي. التطبيق بقي صالحًا للـlocal internal beta. المطلوب قبل النشر إلى GitHub هو مراجعة المالك لنطاق commit الانتقائي أعلاه، ثم تنفيذ commit/push وفحص GitHub Actions في مهمة منفصلة مصرح بها.
