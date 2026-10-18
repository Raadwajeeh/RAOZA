# RAOZA — Phase 6: CI Stabilization, Security Hardening & E2E Testing

تاريخ التحقق: 15 أكتوبر 2026

الفرع: `main`

Commit التنفيذ النهائي: `9d5d1d2ee563b781e41f8d736bd3081c7cb590f2`

تشغيل GitHub Actions النهائي للتنفيذ: [38664583761](https://github.com/Raadwajeeh/RAOZA/actions/runs/38664583761)

> ملاحظة SHA: هذا التقرير يُضاف في Commit توثيقي تالٍ؛ لا يمكن لملف داخل Commit أن يحتوي مسبقًا على SHA ذلك الـCommit نفسه. SHA أعلاه هو Commit التنفيذ الذي اختبره التشغيل المشار إليه، ويُذكر SHA الـCommit التوثيقي في تسليم المهمة.

## النتيجة التنفيذية

أصبحت جميع بوابات CI المطلوبة خضراء فعليًا على GitHub: `test` و`mariadb-commerce` و`e2e` و`secrets`. لا يوجد `continue-on-error` ولا تعطيل لاختبارات لإخفاء فشل. لم يتم الاتصال بـHostinger أو أي قاعدة بعيدة، ولم يُرسل بريد خارجي أو دفع/Refund حقيقي، وظلت بوابة إصدار الفواتير الرسمية مغلقة.

## السبب الجذري لفشل CI السابق

التشغيل الفاشل [38660350533](https://github.com/Raadwajeeh/RAOZA/actions/runs/38660350533) بدأ من checkout نظيف لا يحتوي مجلدات Laravel التشغيلية الفارغة. كانت قواعد `.gitignore` تسمح بملفات الحماية داخل `storage/framework/*` و`storage/logs`، لكن هذه الملفات لم تكن متتبعة فعليًا؛ لذلك لم تُنشأ مسارات Blade/cache في Runner، ونتج الخطأ المتكرر `Please provide a valid cache path.` و41 فشلًا.

تم الإصلاح بطبقتين:

- إضافة ملفات `.gitignore` متتبعة داخل `storage/framework/cache` و`sessions` و`testing` و`views` و`storage/logs`.
- إضافة خطوة صريحة في Jobs المعنية تنشئ `bootstrap/cache` ومسارات `storage` وتمنح مستخدم/مجموعة Runner صلاحية الكتابة قبل تشغيل Laravel.

التشغيلان التاليان أثبتا الإصلاح داخل GitHub Runner نفسه: [38664281287](https://github.com/Raadwajeeh/RAOZA/actions/runs/38664281287) و[38664583761](https://github.com/Raadwajeeh/RAOZA/actions/runs/38664583761)، وكلاهما نجح في Jobs الأربعة.

## CI وأمان التبعيات

عُدّل `.github/workflows/ci.yml` ليشمل:

- أقل صلاحية افتراضية: `contents: read`.
- `actions/checkout@v5` و`actions/setup-node@v5`، مع Node 22 وPHP 8.4.
- `composer validate --strict` و`composer audit`.
- تثبيت JavaScript الحتمي بـ`npm ci` ثم `npm audit --audit-level=high`.
- TypeScript وVite و`production:verify-build` وPHPUnit.
- Job Playwright مع SQLite معزولة، بريد `array`، Queue محلية، Demo payment محصور في `testing`، وإصدار الفواتير معطل.
- Job مستقل لـGitleaks 8.30.1 يفحص تاريخ Git كاملًا مع redaction.
- رفع Playwright trace/screenshot/report فقط عند الفشل.

نتائج GitHub الفعلية للتشغيل النهائي:

| Job | النتيجة | تحقق مهم |
|---|---|---|
| `test` | success | Composer/NPM audit، TypeScript، build، verify-build، PHPUnit |
| `mariadb-commerce` | success | MariaDB disposable، migrations، compatibility، invoice/shipping concurrency |
| `e2e` | success | Chromium حقيقي و6 سيناريوهات تجارة متسلسلة |
| `secrets` | success | Gitleaks لتاريخ المستودع |

## MariaDB والتزامن

كان اختبار تزامن الشحن موجودًا سابقًا في `tests/Feature`، بينما `phpunit.mariadb.xml` يشغّل `tests/MariaDbConcurrency` فقط؛ لذلك كان نجاح Job السابق لا يثبت تشغيل اختبار الشحن. نُقل الاختبار إلى suite الصحيحة.

الـJob الآن يطبع `--list-tests`، يحسب الاختبارات ويشترط العدد `19`، ويتحقق صراحة من وجود:

- `test_invoice_numbers_are_unique_and_sequential_under_concurrency`
- `test_order_row_lock_excludes_a_second_shipping_transaction_on_mariadb`

ثم يشغّل المجموعة مع `--testdox`. نجحت الخطوة في MariaDB disposable على GitHub في التشغيل النهائي. تشمل المجموعة 13 اختبار تجارة/دفع/خصومات/تزامن وفواتير، 5 اختبارات توافق ومخطط، واختبار تزامن الشحن. لم تُستخدم قاعدة إنتاج أو Hostinger.

## Playwright E2E

أضيف `@playwright/test` وإعداد Chromium مع قاعدة `database/e2e.sqlite` تُنشأ وتُحذف داخل الاختبار فقط. الـSeeder محمي بشرطي `APP_ENV=testing` وDemo mode، وكلمات مروره تأتي من بيئة الاختبار ولا تُخزن كأسرار تشغيلية.

السيناريوهات الستة الناجحة:

1. تسجيل عميل جديد وإجباره على مسار التحقق من البريد.
2. تسجيل دخول عميل موثق، تصفح الفئة والمنتج، اختيار المقاس واللون، تحديث الكمية، واستخدام عنوان محفوظ في Checkout ثم Demo payment.
3. إثبات عزل الحسابات ومنع عميل آخر من فتح الطلب.
4. دخول المالك للإدارة، تجهيز الطلب، إنشاء شحنة يدوية، تأكيد التسليم للناقل ثم تأكيد `Delivered` صراحة.
5. ظهور رقم التتبع للمالك الصحيح وتقديم طلب إرجاع مؤهل.
6. Guest Checkout ثم إثبات رفض token غير صالح وقبول رابط ضيف جديد وآمن ومحدود بالطلب.

لم تستدعِ الرحلة Mollie أو بريدًا خارجيًا أو Carrier API. أمر إصدار رابط الضيف المساعد غير متاح إلا في `testing` مع Demo mode.

## موثوقية الإشعارات

### المشكلة المؤكدة

كان `TransactionalNotificationService` يسجل dispatch key قبل ضمان قبول Queue للعمل. إذا فشل enqueue بعد التسجيل، تمنع idempotency المحاولة التالية ويبقى الإشعار مفقودًا نهائيًا.

### الإصلاح

حُوّل السجل إلى transactional outbox قابل للاسترداد:

- تخزين المستلم والـMailable مشفرين، مع الإبقاء على hash للبحث/التدقيق.
- حالات `pending / processing / sent`، وعدّاد محاولات، claim، backoff، وأوقات الفشل/الإرسال.
- Job بحد أقصى خمس محاولات وclaim تحت database lock.
- recovery مجدول كل دقيقة للرسائل المستحقة أو claims القديمة.
- عدم تخزين نص الاستثناء أو أسرار؛ يُحفظ نوع الاستثناء فقط.
- اختبارات لفشل النقل، بقاء pending عند عدم عمل العامل، الاسترداد، ومنع إعادة الإرسال بعد `sent`.

الضمان هو **at-least-once مع idempotency على مستوى outbox** وليس exactly-once. توجد نافذة نظرية صغيرة إذا قبل خادم البريد الرسالة ثم مات العامل قبل كتابة `sent_at`؛ قد تعيد المحاولة الإرسال. تقليل هذه النافذة مستقبلًا يحتاج idempotency مدعومة من مزود البريد أو معرف رسالة متفقًا عليه معه.

## فحوص الأمان

### ثغرات مؤكدة ومصلحة

- كان `provider_payment_id` الخام يظهر في سجلات أخطاء return/webhook. استُبدل بـSHA-256 hash مع الاحتفاظ بمعرفات داخلية غير سرية للتشخيص.
- عيب فقد الإشعار بعد تسجيل dispatch key أُصلح بالـoutbox الموضح أعلاه.
- غياب مسارات Laravel من checkout النظيف أُصلح دون تخفيف الاختبارات.
- اختبار تزامن الشحن الذي لم يكن داخل MariaDB suite أُدخل فعليًا إلى المجموعة.

### تغطية أمنية ناجحة

- IDOR لطلبات ووثائق وعناوين وإرجاعات العملاء وروابط الضيوف.
- فصل Customer عن Staff/Admin ومنع تصعيد الأدوار وتعطيل آخر Owner.
- CSRF على عملية تغيير حالة، وتجديد session ID بعد login.
- منع mass assignment لحقول owner/admin/role/active.
- رفض SVG والمحتوى التنفيذي في رفع صور المنتجات.
- replay/idempotency للمدفوعات وwebhooks وrefunds والشحن والبريد.
- معاملات وأقفال وتنافس المخزون والخصومات والفواتير والشحن.
- عدم إجراء HTTP خارجي لمزودي الشحن المستقبليين.
- روابط التتبع تقبل HTTPS خاليًا من credentials فقط حسب قواعد النظام الحالية.

### الأسرار

نجح Gitleaks محليًا بعد Commit التنفيذ: 30 commits، نحو 2.24 MB، `no leaks found`. أُضيفت `.gitleaksignore` ببصمات دقيقة فقط لإنذارات تاريخية كاذبة تخص كلمات مرور اختبارات وplaceholder موثق؛ لا توجد استثناءات مسار واسعة. نجح Job `secrets` كذلك على GitHub. لا توجد ملفات `.env` فعلية أو SQLite أو تقارير Playwright ضمن الملفات المتتبعة.

## نتائج الاختبارات المحلية

- `php artisan test`: **220 passed، 1266 assertions، 0 skipped، 0 failed**.
- `npm run typecheck`: success.
- `npm run build`: success، 831 modules transformed.
- `npm run test:e2e`: **6 passed** على Chromium.
- اختبارات outbox والمدفوعات والشحن والاسترداد المستهدفة: **43 passed، 219 assertions**.
- `composer validate --strict`: success.
- `composer audit`: لا advisories.
- `npm audit --audit-level=high`: 0 vulnerabilities.
- `git diff --check`: success.
- Gitleaks history scan: success.
- MariaDB لم تُشغّل على جهاز التطوير لعدم وجود instance disposable محلية؛ شُغّلت ونجحت بدلًا من ذلك داخل service container المعزول في GitHub Actions، وهو ليس قاعدة إنتاج.

## الملفات الأساسية المعدلة أو المضافة

- CI والأسرار: `.github/workflows/ci.yml`، `.gitleaksignore`، `.gitignore`، وملفات حماية مجلدات Laravel التشغيلية.
- E2E: `playwright.config.ts`، `tests/E2E/*`، `database/seeders/E2eSeeder.php`، وpackage manifests.
- Outbox: `TransactionalNotificationService`، `DeliverTransactionalNotification`، `NotificationDispatch`، migration الإضافية، والجدولة في `routes/console.php`.
- الأمان: `PaymentController` و`Phase6SecurityHardeningTest`.
- الاختبارات: `NotificationOutboxReliabilityTest` وتحديث assertions البريدية القائمة، ونقل `MariaDbShippingConcurrencyTest`.

## مخاطر نظرية وأعمال تشغيلية لاحقة

- نافذة at-least-once للبريد المذكورة أعلاه؛ لا يوجد ادعاء exactly-once.
- يجب تشغيل Queue worker والScheduler ومراقبتهما في البيئة الحقيقية قبل تفعيل البريد، مع إعداد مزود بريد حقيقي واختبار sandbox منفصل.
- روابط التتبع HTTPS يحددها موظف مخول. يمكن إضافة allowlist لكل carrier عندما تُعتمد النطاقات والعقود الرسمية؛ لا توجد الآن مفاتيح أو تكاملات Carrier.
- لم تُختبر خدمات Mollie أو البريد أو الشحن الحقيقية، ولم تكن ضمن النطاق المسموح.
- لا تزال الفواتير الرسمية مغلقة بقرار محاسبي قائم.
- يوصى بحماية `main` عبر Pull Requests، ومنع force push والحذف، واشتراط Jobs الأربعة (`test`, `mariadb-commerce`, `e2e`, `secrets`) ومراجعة واحدة على الأقل. لم تُغيّر إعدادات المستودع لأن ذلك يحتاج إذن المالك.

## ما لم يُختبر

- Hostinger والإنتاج وقواعدهما: ممنوع حسب القيود.
- APIs الحقيقية للدفع والبريد والشحن وRefund: ممنوعة حسب القيود.
- إصدار فاتورة رسمية: البوابة بقيت مغلقة.
- اختبار متصفح Safari/Firefox وأجهزة حقيقية: خارج معيار القبول الحالي؛ Chromium غطى الرحلة الأساسية.
