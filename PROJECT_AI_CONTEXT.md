# Automotive SaaS - Project AI Context

## 1) Core Project Identity
- Project name: Automotive SaaS
- Tech stack:
  - Laravel 10
  - PHP 8.2
  - stancl/tenancy v3
  - multi-database tenancy
  - central domain + tenant domains
  - Stripe billing
  - Vite / Node tooling موجودة داخل المشروع
- Hosting:
  - Server: Vultr
  - Project path on server: `/var/www/automotive`
  - Standard push command: `git push origin main`
- Required workflow:
  - نعمل محليًا أولًا
  - ثم `git push origin main`
  - ثم deploy على السيرفر
  - ثم test محليًا وعلى السيرفر

## 2) Non-Negotiable Rules
- ممنوع أي تخمين عن حالة المشروع أو افتراض أن شيء غير موجود.
- لازم الاعتماد فقط على الملفات الحالية الفعلية داخل الريبو.
- أي تعديل لازم يكون مبني على الحالة الحالية الفعلية للكود.
- أي ملف يتم تعديله لازم يُرسل كاملًا، وليس snippets ناقصة.
- لازم دائمًا ذكر المسار الكامل لكل ملف.
- لا يتم اقتراح إنشاء ملف جديد إلا بعد التأكد أنه غير موجود فعليًا.
- بعد كل تعديل يجب توفير:
  - أوامر `git add`
  - أمر `git commit`
  - أمر `git push origin main`
  - أوامر deploy على السيرفر
  - ما يجب اختباره محليًا
  - ما يجب اختباره على السيرفر
- ممنوع نهائيًا استخدام:
  - `php artisan route:cache`
- سبب المنع:
  - routes ديناميكية
  - product routes و tenant routes تتأثر
  - route caching يكسر tenant/product route resolution

## 3) Architecture Decisions That Must Remain True
- يوجد فصل واضح بين:
  - `Front Customer Portal`
  - `The Actual Tenant System`
- العميل لا يدخل إلى الـ tenant system كأول محطة.
- القرار المعماري المعتمد حاليًا:
  - التسجيل لا يبدأ trial تلقائيًا
  - المستخدم يسجل أولًا فقط
  - بعد التسجيل يدخل على `Front Customer Portal`
  - من الـ portal يختار:
    - `Start Free Trial`
    - أو `View Paid Plans / Subscribe`
- عند بدء free trial:
  - نستخدم البيانات التي تم حفظها أثناء التسجيل
  - يتم إنشاء trial tenant/workspace
  - بعدها يظهر:
    - `Open My Trial System`
    - `Upgrade to Paid Plan`
- خيار Free Trial يجب أن يبقى قابلًا للإظهار/الإخفاء من admin settings.
- لا يتم خلط front onboarding routes مع tenant admin routes.

## 4) Important Historical Warnings
- حصل سابقًا أن جزءًا من الشات كان محفوظًا كمرفقات `Pasted text / Pasted markdown` وليس ظاهرًا عاديًا.
- حصل لبس سابق بسبب وجود commits على GitHub لم تكن ظاهرة كنص داخل نفس الشات.
- لا تعتمد أبدًا على الذاكرة فقط، بل راجع الملفات الحالية دائمًا.
- حصلت أخطاء سابقة بسبب افتراض وجود أعمدة مثل `currency_code` في أماكن غير مضمونة.
- أي قراءة أو تعامل مع DB لازم يكون مرن مع الـ schema الحالية.
- حصلت مشكلة سابقًا لأن onboarding entry استخدمت route خاطئًا:
  - `route('automotive.admin.login')` داخل سياق غير صحيح
- لا نخلط بين:
  - front onboarding auth
  - tenant admin auth
- حصلت مشاكل سابقة في permissions/deploy:
  - أوامر deploy يجب أن تبقى بسيطة وآمنة وواضحة

## 5) Major Existing Modules Already Present Before Current Work
- admin notifications مقفولة
- activity log foundation + activity log UI تم العمل عليهم
- coupons module CRUD + eligibility + trial signup coupon integration موجودين على `main`
- SaaS setting للتحكم في free trial visibility موجودة
- onboarding entry page موجودة
- theme-based auth screens موجودة لبعض شاشات auth
- customer portal front page بدأ وتم اعتماده بدل الدخول المباشر للنظام

## 6) Existing Important Files For This Phase
- `app/Http/Controllers/Automotive/Front/Auth/RegisterController.php`
- `app/Http/Controllers/Automotive/Front/EntryController.php`
- `app/Http/Controllers/Automotive/Front/CustomerPortalController.php`
- `app/Services/Automotive/StartTrialService.php`
- `app/Services/Billing/TrialSignupCouponService.php`
- `app/Services/Billing/CouponEligibilityService.php`
- `app/Services/Admin/AppSettingsService.php`
- `app/Models/AppSetting.php`
- `resources/views/automotive/front/auth/register.blade.php`
- `resources/views/automotive/front/auth/login.blade.php`
- `resources/views/automotive/front/entry.blade.php`
- `resources/views/automotive/front/portal.blade.php`
- `routes/products/automotive/front.php`
- `resources/views/layout/partials/header.blade.php`

## 7) What Was Reviewed And Discovered In This Session

### 7.1 Initial Review Result
تمت مراجعة flow الحالي فعليًا، واتضح الآتي:
- التسجيل كان ينشئ:
  - `User`
  - `CustomerOnboardingProfile`
- ثم يوجه المستخدم إلى:
  - `automotive.portal`
- بدء الـ trial الفعلي ما زال منفصلًا داخل:
  - `StartTrialService`

### 7.2 Critical Bug Found In Portal Subscription Resolution
داخل:
- `app/Http/Controllers/Automotive/Front/CustomerPortalController.php`

كان يوجد منطق `latestSubscriptionForUser()` يبحث عن subscription باستخدام أعمدة مثل:
- `user_id`
- `user_email`
- `email`

لكن الـ schema الحالية لجدول `subscriptions` لا تحتوي هذه الأعمدة.

الربط الصحيح في هذا المشروع موجود عبر:
- `tenant_users`

هذا كان يؤدي إلى أن الـ portal قد لا يرى الاشتراك الحالي فعليًا حتى بعد إنشاء trial أو paid workspace.

### 7.3 Paid Flow Was Not Actually Connected
تمت مراجعة الراوتات والـ views، واتضح أن:
- زر `View Paid Plans` في `entry`
  - كان `href="#"` أو placeholder
- زر `View Paid Plans` و `Upgrade to Paid Plan` في `portal`
  - لم يكونا مرتبطين بمسار paid حقيقي
- لا يوجد front paid onboarding path مكتمل في الراوتات

### 7.4 Billing Foundation Already Existed
تمت مراجعة services وcontrollers الخاصة بالـ billing، واتضح أن:
- billing plan catalog موجود
- Stripe checkout موجود
- webhook sync موجود
- tenant admin billing screen موجودة

لكن هذا المسار كان مبنيًا على:
- tenant context موجود مسبقًا
- subscription row موجود مسبقًا

وبالتالي لا يمكن استخدامه مباشرة من front portal بدون bridge layer.

## 8) Actual Work Completed In This Session

### 8.1 Customer Portal Themed UI Was Preserved
تم الحفاظ على التصميم الثيمي الحالي للـ portal، مع الحفاظ على البيانات التالية:
- `name`
- `email`
- `company_name`
- `reserved subdomain`
- `primary domain`
- `current plan`
- `current status`
- `billing period`
- `trial ends at`
- `days remaining`
- `gateway`
- `system access`
- `domains list`
- `Start Free Trial button`
- `View Paid Plans button`
- `Go to My System button`
- `Upgrade to Paid Plan button`

### 8.2 Fixed Portal Subscription Lookup
تم تعديل:
- `app/Http/Controllers/Automotive/Front/CustomerPortalController.php`

بحيث أصبح lookup الاشتراك الحالي يعتمد على:
- `tenant_users.user_id`
- ثم استخراج `tenant_id`
- ثم جلب آخر subscription لهذا tenant

بدل الاعتماد على أعمدة غير موجودة داخل `subscriptions`.

### 8.3 Added Front Paid Plan Selection Inside Portal
تم تعديل:
- `resources/views/automotive/front/portal.blade.php`

بحيث أصبح الـ portal يحتوي فعليًا على:
- قسم `Paid Plan Selection`
- عرض paid plans من الكتالوج الحالي
- أزرار بدء checkout من داخل الـ portal نفسه

### 8.4 Added Front Paid Checkout Service
تم إنشاء:
- `app/Services/Automotive/StartPaidCheckoutService.php`

وظيفة هذه الخدمة:
- استخدام profile الحالي للمستخدم
- فحص وجود workspace/tenant حالي
- إذا لم يوجد tenant:
  - إنشاء tenant تمهيدي
  - إنشاء domain
  - إنشاء subscription row تمهيدي
  - إنشاء tenant_users link
  - تشغيل `tenants:migrate`
  - إنشاء user داخل tenant database
- إذا وجد tenant:
  - استخدامه
  - وإن لم توجد subscription model مناسبة يتم إنشاؤها
- بعد ذلك:
  - بدء Stripe checkout عبر billing gateway الحالي
  - حفظ `gateway_checkout_session_id`

### 8.5 Added Front Paid Portal Routes
تم تعديل:
- `routes/products/automotive/front.php`

وإضافة الراوتات التالية:
- `POST automotive/portal/subscribe`
- `GET automotive/portal/checkout/success`
- `GET automotive/portal/checkout/cancel`

مع الإبقاء على:
- `GET automotive/portal`
- `POST automotive/portal/start-trial`

### 8.6 Portal Success / Cancel Messaging
تمت إضافة رجوع من checkout إلى:
- `automotive.portal`

مع رسائل:
- success بعد completion
- error بعد cancel

### 8.7 Important Correction In Registration Logic
تم اكتشاف مشكلة معمارية مهمة:
- `RegisterController` كان يمنع التسجيل كاملًا إذا كان:
  - `free_trial_enabled = false`

وهذا كان يكسر paid onboarding بالكامل.

تم تعديل:
- `app/Http/Controllers/Automotive/Front/Auth/RegisterController.php`

بحيث:
- التسجيل يظل متاحًا دائمًا
- toggle الـ free trial يؤثر فقط على free trial behavior
- coupon preview للتسجيل التجريبي ما زال مقيدًا بإعداد free trial

### 8.8 Entry Page Was Aligned With Portal-First Flow
تم تعديل:
- `resources/views/automotive/front/entry.blade.php`

ليعكس flow الصحيح:
- `Create account first`
- ثم الدخول إلى customer portal
- ثم اختيار trial أو paid

بدل copy قديم يوحي بأن trial يبدأ مباشرة من صفحة التسجيل.

### 8.9 Register Page Was Aligned With Portal-First Flow
تم تعديل:
- `resources/views/automotive/front/auth/register.blade.php`

بحيث:
- العنوان والنصوص أصبحوا يعكسون:
  - إنشاء customer portal account أولًا
  - ثم متابعة trial أو paid من الـ portal
- تم تصحيح example النصي للـ subdomain:
  - `mido -> mido.automotive.seven-scapital.com`

### 8.10 Added Dedicated Customer Portal Login
تم إنشاء:
- `app/Http/Controllers/Automotive/Front/Auth/LoginController.php`
- `resources/views/automotive/front/auth/login.blade.php`

وتم تعديل:
- `routes/products/automotive/front.php`

لإضافة:
- `GET automotive/login`
- `POST automotive/login`
- `POST automotive/logout`

هذا المسار يستخدم:
- نفس guard `web`

لكن في سياق front automotive portal فقط، بدون أي خلط مع tenant admin auth.

### 8.11 Connected Entry/Register To Automotive Front Login
تم تعديل:
- `resources/views/automotive/front/entry.blade.php`
- `resources/views/automotive/front/auth/register.blade.php`

بحيث:
- رابط login يذهب إلى:
  - `automotive.login`
- بدل الاعتماد على `login` العام

### 8.12 Added Sign Out Action Inside Portal
تم تعديل:
- `resources/views/automotive/front/portal.blade.php`

لإضافة زر:
- `Sign Out`

يستخدم:
- `POST automotive/logout`

### 8.13 Root Cause Of Wrong Logout Redirect Was Found
المشكلة التي ظهرت:
- عند الضغط على `Sign Out` من `automotive/portal`
- كان المستخدم يتحول إلى `/home`

السبب الحقيقي:
- صفحة `automotive/portal` تستخدم:
  - `layout.mainlayout`
- هذا layout يحمّل:
  - `resources/views/layout/partials/header.blade.php`
- الهيدر فيه زري logout جاهزين مسبقًا داخل dropdown
- هذان الزران كانا يذهبان إلى:
  - `url('login')`

وبما أن المستخدم authenticated على guard `web`:
- الذهاب إلى `/login` كان يعيد التوجيه إلى `/home`

### 8.14 Fixed Shared Header Logout For Automotive Routes
تم تعديل:
- `resources/views/layout/partials/header.blade.php`

بحيث:
- إذا كان route الحالي يطابق:
  - `automotive.*`
- يتم استخدام:
  - `POST automotive/logout`

وإذا لم يكن كذلك:
- يظل السلوك القديم كما هو لباقي أجزاء الثيم

تم إصلاح:
- desktop dropdown logout
- mobile dropdown logout

## 9) Current Effective Front Automotive Flow After All Current Changes

### 9.1 Entry Flow
- المستخدم يفتح:
  - `/automotive/get-started`
- يرى أن عليه إنشاء حساب أولًا
- يمكنه:
  - `Create Account`
  - أو `Customer Portal Login`

### 9.2 Registration Flow
- المستخدم يفتح:
  - `/automotive/register`
- يقوم بإنشاء:
  - account
  - company name
  - reserved subdomain
  - optional coupon reservation
- بعد التسجيل:
  - يتم login تلقائيًا على guard `web`
  - ثم redirect إلى:
    - `/automotive/portal`

### 9.3 Portal Flow
- الـ portal يعرض بيانات المستخدم الحالية
- إذا لا يوجد subscription:
  - يمكن بدء free trial إذا كان enabled
  - أو اختيار paid plan
- إذا يوجد trial:
  - تظهر بيانات trial
  - ويمكن `Open My Trial System`
  - ويمكن `Upgrade to Paid Plan`
- إذا يوجد paid flow pending أو status مثل `past_due`:
  - يمكن استكمال paid checkout

### 9.4 Paid Checkout Flow
- يبدأ من داخل:
  - `/automotive/portal`
- إذا لم يوجد tenant:
  - يتم provisioning تمهيدي
- إذا وجد tenant:
  - يتم استخدامه
- ثم:
  - يتم إنشاء Stripe checkout session
  - redirect إلى Stripe
- بعد success/cancel:
  - الرجوع إلى `/automotive/portal`

### 9.5 Login / Logout Flow
- login الخاص بالـ portal:
  - `/automotive/login`
- logout الخاص بالـ portal:
  - `POST /automotive/logout`
- logout من shared header داخل portal تم إصلاحه ليمر على:
  - `automotive.logout`

## 10) Files Changed / Added During This Session

### Added
- `app/Services/Automotive/StartPaidCheckoutService.php`
- `app/Http/Controllers/Automotive/Front/Auth/LoginController.php`
- `resources/views/automotive/front/auth/login.blade.php`

### Modified
- `app/Http/Controllers/Automotive/Front/CustomerPortalController.php`
- `routes/products/automotive/front.php`
- `resources/views/automotive/front/portal.blade.php`
- `app/Http/Controllers/Automotive/Front/Auth/RegisterController.php`
- `resources/views/automotive/front/entry.blade.php`
- `resources/views/automotive/front/auth/register.blade.php`
- `resources/views/layout/partials/header.blade.php`

## 11) Validated In This Session
- `php -l app/Http/Controllers/Automotive/Front/CustomerPortalController.php`
- `php -l app/Services/Automotive/StartPaidCheckoutService.php`
- `php -l routes/products/automotive/front.php`
- `php -l app/Http/Controllers/Automotive/Front/Auth/RegisterController.php`
- `php -l app/Http/Controllers/Automotive/Front/Auth/LoginController.php`
- `php artisan route:list --name=automotive.portal`
- `php artisan route:list --name=automotive.login`
- `php artisan route:list --name=automotive.logout`

## 12) Deploy Notes
- على السيرفر استخدم:
  - `cd /var/www/automotive`
  - `git pull origin main`
  - `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction`
  - `php artisan optimize:clear`
  - `php artisan view:clear`
  - `php artisan config:clear`
- ممنوع:
  - `php artisan route:cache`

## 13) What Must Be Tested Now

### Front Automotive Auth
- `/automotive/login`
- `/automotive/register`
- login redirects to `/automotive/portal`
- logout from portal body works
- logout from shared header dropdown works
- logout from mobile dropdown works

### Portal Subscription Visibility
- user with existing trial should see trial subscription in portal
- portal should now resolve subscriptions correctly عبر `tenant_users`

### Trial Flow
- register -> portal -> start free trial
- trial tenant should be provisioned
- portal should show:
  - trial status
  - primary domain
  - system access
  - open system button

### Paid Flow
- register -> portal -> choose paid plan
- if no tenant exists:
  - tenant is provisioned before checkout
- if tenant exists:
  - current tenant reused
- redirect to Stripe should work
- success/cancel should return to portal
- webhook should complete local subscription sync

### Free Trial Toggle Behavior
- if free trial is disabled:
  - registration must still work
  - portal login must still work
  - paid onboarding must still work
  - start trial option should be hidden/unavailable

## 14) Important Current Open Follow-Up Items
- coupon reservation موجودة في registration/trial flow
- plan-specific coupon validation during paid selection still needs a dedicated follow-up pass if stricter plan application is required at checkout stage
- customer portal pages currently use a mix of standalone front auth views and shared theme layout in portal
- if needed later:
  - يمكن توحيد front automotive auth theming أكثر
  - ويمكن منع استخدام auth العام `/login` نهائيًا في automotive UX

## 15) Current Reference Summary
- لا تبدأ من الصفر
- لا تعيد بناء onboarding flow
- لا تعيد اقتراح customer portal أو paid selection كأفكار جديدة، لأنها نُفذت بالفعل
- لا تعيد إصلاح logout من portal، لأنه تم تحديد السبب وإصلاحه في `shared header`
- ابدأ من آخر نقطة فعلية منجزة:
  - `portal-first onboarding`
  - `front paid checkout bridge`
  - `front automotive login/logout`
  - `shared header logout fix for automotive routes`

## 16) Latest Session Continuation And Corrections

### 16.1 Admin Sidebar Was Updated With Recent SaaS Pages
تم تعديل:
- `resources/views/admin/layouts/centralLayout/partials/sidebar.blade.php`

بحيث يظهر داخل الـ admin sidebar الفعلي كل الصفحات التي تمت إضافتها مؤخرًا، وهي:
- `Tenants`
- `Coupons`
- `Notifications`
- `Activity Logs`
- `General Settings`

وتمت إضافتها في:
- mini sidebar icons
- main sidebar list
- footer icons

### 16.2 General Settings Page Was Restyled
تم تعديل:
- `resources/views/admin/settings/general.blade.php`

بحيث أصبح التصميم مستوحى من:
- `resources/views/customer-account-settings.blade.php`

مع الإبقاء على behavior الحالي الخاص بـ:
- `free_trial_enabled`

وتم أيضًا تصحيح:
- `$page = 'saas-settings-general'`

حتى تعمل active state في الـ admin sidebar بشكل صحيح.

### 16.3 Automotive Front Layouts Were Added
تم إنشاء layouts خاصة بالـ front داخل:
- `resources/views/automotive/front/layouts/public.blade.php`
- `resources/views/automotive/front/layouts/auth.blade.php`
- `resources/views/automotive/front/layouts/portal.blade.php`

ثم تم تعديل:
- `resources/views/automotive/front/entry.blade.php`
- `resources/views/automotive/front/auth/login.blade.php`
- `resources/views/automotive/front/auth/register.blade.php`
- `resources/views/automotive/front/portal.blade.php`

بحيث تعتمد على هذه layouts بدل الكتابة المباشرة أو الاعتماد المباشر على layout عام.

### 16.4 Automotive Guest Redirect Was Fixed
تم تعديل:
- `app/Http/Middleware/RedirectIfAuthenticated.php`

بحيث:
- لو المستخدم authenticated على guard `web`
- وفتح أي route داخل:
  - `/automotive/*`

فلا يتم تحويله إلى:
- `/home`

بل يتم تحويله إلى:
- `automotive.portal`

وهذا أصلح مشكلة:
- `/automotive/register`
- `/automotive/login`

حين كانا يعيدان التوجيه إلى `/home` غير الموجودة.

### 16.5 Portal Messaging Now Respects Free Trial Toggle
تم تعديل:
- `resources/views/automotive/front/portal.blade.php`

بحيث:
- إذا كانت `free trial` مفعلة:
  - تظهر رسالة تسمح بـ `start a free trial or subscribe`
- إذا كانت `free trial` معطلة:
  - تظهر رسالة paid-only

بدل رسالة ثابتة توحي دائمًا بأن trial متاح.

### 16.6 Portal No Longer Lies About Current Paid Plan Before Successful Payment
تم اكتشاف مشكلة مهمة:
- مجرد دخول المستخدم إلى Stripe checkout كان يؤدي سابقًا إلى ظهور الخطة المدفوعة داخل portal كأنها `current plan`
- رغم أن الدفع لم يكتمل بعد

تم تعديل:
- `app/Http/Controllers/Automotive/Front/CustomerPortalController.php`
- `resources/views/automotive/front/portal.blade.php`
- `app/Services/Automotive/StartPaidCheckoutService.php`

بحيث:
- لا يتم تغيير الخطة الحالية أو الحالة الحالية لمجرد بدء checkout
- الحساب يظل على حالته السابقة حتى نجاح الدفع فعليًا
- وإذا رجع المستخدم من Stripe بدون إتمام الدفع:
  - تظل الحالة السابقة كما هي
  - وتظهر فقط ملاحظة أن checkout لم يكتمل

### 16.7 Subdomain Availability Is Now Validated Earlier
تم تعديل:
- `app/Http/Controllers/Automotive/Front/Auth/RegisterController.php`

بحيث لم يعد التحقق يقتصر على:
- `customer_onboarding_profiles`

بل أصبح أيضًا يتحقق من:
- `tenants.id`
- `domains.domain`

وهذا يمنع سيناريو:
- نجاح التسجيل
- ثم فشل trial أو paid لاحقًا برسالة:
  - `This subdomain is not available.`

كما تم تطبيق نفس التحقق في:
- coupon preview أثناء التسجيل

### 16.8 Important Architectural Correction In Paid Checkout Flow
هذا هو أهم تصحيح في هذه الجلسة، وهو يصحح الوصف القديم الموجود في:
- `9.4 Paid Checkout Flow`

الوصف القديم لم يعد صحيحًا بالكامل.

الحالة الحالية الصحيحة الآن:
- عند بدء paid checkout من `portal`
- إذا لم يكن للمستخدم tenant فعلي بعد
- لا يتم إنشاء `Tenant` قبل الدفع
- لا يتم إنشاء `Domain` قبل الدفع
- لا يتم تشغيل `CreateDatabase` / `MigrateDatabase` قبل الدفع
- لا يتم عمل tenant DB provisioning قبل الدفع

السبب:
- `Tenant::create()` كان يطلق event `TenantCreated`
- وداخل:
  - `app/Providers/TenancyServiceProvider.php`

يوجد pipeline تلقائي يشغل:
- `Jobs\CreateDatabase`
- `Jobs\MigrateDatabase`

وبالتالي كان أي إنشاء tenant قبل Stripe يؤدي إلى provisioning مبكر وقد يفشل قبل الوصول إلى صفحة الدفع.

### 16.9 Final Paid Flow After Latest Fixes
تم تعديل:
- `app/Services/Automotive/StartPaidCheckoutService.php`
- `app/Services/Billing/StripeWebhookSyncService.php`
- `app/Services/Automotive/ProvisionTenantWorkspaceService.php` (ملف جديد)

وأصبح الـ paid flow كالتالي:

#### قبل الدفع
- المستخدم يختار خطة مدفوعة من `portal`
- إذا كان لديه subscription/tenant فعلي موجود، يتم استخدام subscription الحالي حسب الحالة
- إذا لم يكن لديه tenant فعلي:
  - لا يتم إنشاء tenant
  - لا يتم provisioning
  - لا يتم إنشاء subscription row تمهيدي جديد للحالة الفارغة
- يتم فقط بدء Stripe checkout session

#### بعد نجاح الدفع عبر webhook
داخل:
- `checkout.session.completed`

يتم:
- إنشاء subscription local row إذا لم تكن موجودة
- ربط `gateway_checkout_session_id`
- ربط `gateway_subscription_id`
- ثم تشغيل provisioning الحقيقي للـ workspace من خلال:
  - `app/Services/Automotive/ProvisionTenantWorkspaceService.php`

وهذه الخدمة مسؤولة عن:
- إنشاء `Tenant` الفعلي
- إنشاء `Domain`
- إنشاء `tenant_users`
- تشغيل:
  - `tenants:migrate`
- ثم إنشاء user داخل tenant database

### 16.10 Why The Old Paid Flow Description Is Now Outdated
الوصف السابق في:
- `8.4 Added Front Paid Checkout Service`
- `9.4 Paid Checkout Flow`

كان يقول إن:
- tenant provisioning قد يحدث قبل checkout

لكن بعد الإصلاحات الأخيرة هذا لم يعد صحيحًا.

الوصف الأحدث المعتمد الآن هو:
- paid checkout starts first
- provisioning happens only after successful Stripe completion/webhook

### 16.11 Latest Files Changed In This Continuation

#### Added
- `app/Services/Automotive/ProvisionTenantWorkspaceService.php`
- `resources/views/automotive/front/layouts/public.blade.php`
- `resources/views/automotive/front/layouts/auth.blade.php`
- `resources/views/automotive/front/layouts/portal.blade.php`

#### Modified
- `resources/views/admin/layouts/centralLayout/partials/sidebar.blade.php`
- `resources/views/admin/settings/general.blade.php`
- `resources/views/automotive/front/entry.blade.php`
- `resources/views/automotive/front/auth/login.blade.php`
- `resources/views/automotive/front/auth/register.blade.php`
- `resources/views/automotive/front/portal.blade.php`
- `app/Http/Middleware/RedirectIfAuthenticated.php`
- `app/Http/Controllers/Automotive/Front/Auth/RegisterController.php`
- `app/Http/Controllers/Automotive/Front/CustomerPortalController.php`
- `app/Services/Automotive/StartPaidCheckoutService.php`
- `app/Services/Billing/StripeWebhookSyncService.php`

### 16.12 Latest Validations Run
- `php -l app/Http/Middleware/RedirectIfAuthenticated.php`
- `php -l app/Http/Controllers/Automotive/Front/Auth/RegisterController.php`
- `php -l app/Http/Controllers/Automotive/Front/CustomerPortalController.php`
- `php -l app/Services/Automotive/StartPaidCheckoutService.php`
- `php -l app/Services/Automotive/ProvisionTenantWorkspaceService.php`
- `php -l app/Services/Billing/StripeWebhookSyncService.php`
- `php artisan view:cache`
- وتم تشغيل `php artisan optimize:clear` في بعض مراحل التحقق

### 16.13 Current Expected Behavior After Latest Fixes
- `/automotive/login` و `/automotive/register`
  - لو المستخدم authenticated يتم تحويله إلى `automotive.portal` وليس `/home`
- إذا كانت `free trial` معطلة:
  - portal لا يعرض رسالة misleading عن trial
- عند اختيار paid plan لمستأجر جديد:
  - يجب الوصول إلى Stripe مباشرة
  - بدون `Provisioning failed before checkout`
- إذا رجع المستخدم من Stripe بدون دفع:
  - لا تتغير الخطة الحالية
  - لا تتغير الحالة الحالية
- إذا نجح الدفع:
  - webhook ينشئ local subscription
  - ثم يتم provisioning الحقيقي للـ tenant workspace
