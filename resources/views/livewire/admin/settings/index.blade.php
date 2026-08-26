<div
    class="space-y-5"
    x-data="{ tab: 'general' }"
>
    <x-admin.page-header
        title="تنظیمات سامانه"
        description="تنظیمات عمومی، هویت برند و مقادیر پایه SEO را از یک محل مدیریت کنید."
        icon="lucide.settings-2"
    />

    <section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100">
        <div
            role="tablist"
            aria-label="گروه تنظیمات"
            class="flex gap-1 overflow-x-auto border-b border-base-300 p-2"
        >
            <button
                type="button"
                role="tab"
                @click="tab = 'general'"
                :aria-selected="tab === 'general'"
                :class="tab === 'general' ? 'bg-primary/10 text-primary' : 'text-base-content/55 hover:bg-base-200 hover:text-base-content'"
                class="inline-flex shrink-0 items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium transition-colors duration-150"
            >
                <x-icon name="lucide.sliders-horizontal" class="!size-4 stroke-[1.8]" />
                عمومی
            </button>

            <button
                type="button"
                role="tab"
                @click="tab = 'branding'"
                :aria-selected="tab === 'branding'"
                :class="tab === 'branding' ? 'bg-primary/10 text-primary' : 'text-base-content/55 hover:bg-base-200 hover:text-base-content'"
                class="inline-flex shrink-0 items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium transition-colors duration-150"
            >
                <x-icon name="lucide.badge-check" class="!size-4 stroke-[1.8]" />
                برندینگ
            </button>

            <button
                type="button"
                role="tab"
                @click="tab = 'seo'"
                :aria-selected="tab === 'seo'"
                :class="tab === 'seo' ? 'bg-primary/10 text-primary' : 'text-base-content/55 hover:bg-base-200 hover:text-base-content'"
                class="inline-flex shrink-0 items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium transition-colors duration-150"
            >
                <x-icon name="lucide.search-check" class="!size-4 stroke-[1.8]" />
                SEO
            </button>
        </div>

        <div class="p-5 sm:p-6">
            <div x-show="tab === 'general'">
                <form wire:submit="saveGeneral" class="space-y-6">
                    <div>
                        <h2 class="font-semibold">تنظیمات عمومی</h2>
                        <p class="mt-1 text-sm leading-6 text-base-content/50">
                            نام عمومی سامانه در بخش‌هایی که از تنظیمات مرکزی استفاده می‌کنند.
                        </p>
                    </div>

                    @if($savedSection === 'general')
                        <div role="status" class="rounded-xl border border-success/20 bg-success/5 px-4 py-3 text-sm text-success">
                            تنظیمات عمومی ذخیره شد.
                        </div>
                    @endif

                    <div class="max-w-xl">
                        <x-input
                            label="نام سامانه"
                            hint="حداکثر ۸۰ کاراکتر"
                            wire:model.blur="siteName"
                            required
                        />
                    </div>

                    <div class="flex justify-end border-t border-base-300 pt-5">
                        <x-button
                            type="submit"
                            label="ذخیره تنظیمات عمومی"
                            icon="lucide.save"
                            class="btn-primary"
                            spinner="saveGeneral"
                        />
                    </div>
                </form>
            </div>

            <div x-show="tab === 'branding'">
                <form wire:submit="saveBranding" class="space-y-6">
                    <div>
                        <h2 class="font-semibold">برندینگ</h2>
                        <p class="mt-1 text-sm leading-6 text-base-content/50">
                            پیام کوتاه برند که در بخش‌های عمومی Coreflare قابل استفاده است.
                        </p>
                    </div>

                    @if($savedSection === 'branding')
                        <div role="status" class="rounded-xl border border-success/20 bg-success/5 px-4 py-3 text-sm text-success">
                            تنظیمات برندینگ ذخیره شد.
                        </div>
                    @endif

                    <div class="max-w-xl">
                        <x-input
                            label="Tagline"
                            hint="حداکثر ۱۲۰ کاراکتر"
                            wire:model.blur="tagline"
                            required
                        />
                    </div>

                    <div class="flex justify-end border-t border-base-300 pt-5">
                        <x-button
                            type="submit"
                            label="ذخیره برندینگ"
                            icon="lucide.save"
                            class="btn-primary"
                            spinner="saveBranding"
                        />
                    </div>
                </form>
            </div>

            <div x-show="tab === 'seo'">
                @php
                    $ogPreview = $seoDefaultOgImageUpload?->temporaryUrl()
                        ?? ($seoDefaultOgImage !== '' ? $seoDefaultOgImage : null);
                    $logoPreview = $seoOrganizationLogoUpload?->temporaryUrl()
                        ?? ($seoOrganizationLogo !== '' ? $seoOrganizationLogo : null);
                    $faviconPreview = $seoFaviconUpload?->temporaryUrl()
                        ?? ($seoFavicon !== '' ? $seoFavicon : '/favicon.svg');
                    $applePreview = $seoAppleTouchIconUpload?->temporaryUrl()
                        ?? ($seoAppleTouchIcon !== '' ? $seoAppleTouchIcon : null);
                @endphp

                <form wire:submit="saveSeo" class="space-y-7">
                    <div>
                        <h2 class="font-semibold">SEO و Search Appearance</h2>
                        <p class="mt-1 text-sm leading-6 text-base-content/50">
                            عنوان و توضیحات نتایج جستجو، هویت سایت، تصاویر اشتراک‌گذاری و فایل‌های برند را مدیریت کنید.
                        </p>
                    </div>

                    @if($savedSection === 'seo')
                        <div role="status" class="rounded-xl border border-success/20 bg-success/5 px-4 py-3 text-sm text-success">
                            تنظیمات SEO ذخیره شد.
                        </div>
                    @endif

                    <section class="space-y-5 rounded-2xl border border-base-300 bg-base-200/20 p-4 sm:p-5">
                        <div>
                            <h3 class="text-sm font-semibold">نمایش در نتایج جستجو</h3>
                            <p class="mt-1 text-xs leading-6 text-base-content/45">
                                متادیتای اصلی که برای عنوان و توضیح صفحات عمومی استفاده می‌شود.
                            </p>
                        </div>

                        <div class="grid gap-5 lg:grid-cols-2">
                            <x-input
                                label="عنوان پیش‌فرض"
                                hint="حداکثر ۷۰ کاراکتر"
                                wire:model.blur="seoDefaultTitle"
                                required
                            />

                            <x-input
                                label="نام جایگزین سایت"
                                hint="برای Site Name؛ مثال: کورفلر"
                                wire:model.blur="seoSiteAlternateName"
                                required
                            />
                        </div>

                        <div>
                            <label for="seo-default-description" class="mb-2 block text-sm font-medium">
                                Meta Description پیش‌فرض
                            </label>
                            <textarea
                                id="seo-default-description"
                                wire:model.blur="seoDefaultDescription"
                                rows="4"
                                maxlength="160"
                                class="textarea textarea-bordered w-full leading-7"
                                required
                            ></textarea>
                            <div class="mt-1.5 text-xs text-base-content/45">
                                حداکثر ۱۶۰ کاراکتر
                            </div>
                            @error('seoDefaultDescription')
                                <p class="mt-1.5 text-sm text-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </section>

                    <section class="space-y-5">
                        <div>
                            <h3 class="text-sm font-semibold">هویت بصری موتورهای جستجو</h3>
                            <p class="mt-1 text-xs leading-6 text-base-content/45">
                                تصاویر آپلودشده در فضای عمومی SEO نگهداری می‌شوند. فایل جدید، فایل مدیریت‌شده قبلی همان بخش را جایگزین می‌کند.
                            </p>
                        </div>

                        <div class="grid gap-4 lg:grid-cols-3">
                            <div class="rounded-2xl border border-base-300 p-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-base-300 bg-base-200/40">
                                        @if($logoPreview)
                                            <img src="{{ $logoPreview }}" alt="Organization logo preview" class="size-full object-contain p-1.5">
                                        @else
                                            <x-icon name="lucide.image" class="!size-5 text-base-content/30" />
                                        @endif
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium">Organization Logo</div>
                                        <div class="mt-1 text-xs text-base-content/45">PNG/JPG/WebP · حداقل 112px</div>
                                    </div>
                                </div>

                                <input
                                    type="file"
                                    wire:model="seoOrganizationLogoUpload"
                                    accept="image/png,image/jpeg,image/webp"
                                    class="file-input file-input-bordered file-input-sm mt-4 w-full"
                                >
                                @error('seoOrganizationLogoUpload')
                                    <p class="mt-2 text-xs text-error">{{ $message }}</p>
                                @enderror

                                <x-input
                                    class="mt-3"
                                    label="مسیر یا URL"
                                    wire:model.blur="seoOrganizationLogo"
                                    dir="ltr"
                                />
                            </div>

                            <div class="rounded-2xl border border-base-300 p-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-base-300 bg-base-200/40">
                                        <img src="{{ $faviconPreview }}" alt="Favicon preview" class="size-full object-contain p-2">
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium">Favicon</div>
                                        <div class="mt-1 text-xs text-base-content/45">PNG مربع · حداقل 48×48</div>
                                    </div>
                                </div>

                                <input
                                    type="file"
                                    wire:model="seoFaviconUpload"
                                    accept="image/png"
                                    class="file-input file-input-bordered file-input-sm mt-4 w-full"
                                >
                                @error('seoFaviconUpload')
                                    <p class="mt-2 text-xs text-error">{{ $message }}</p>
                                @enderror

                                <x-input
                                    class="mt-3"
                                    label="مسیر یا URL"
                                    placeholder="/favicon.svg"
                                    wire:model.blur="seoFavicon"
                                    dir="ltr"
                                />
                            </div>

                            <div class="rounded-2xl border border-base-300 p-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-base-300 bg-base-200/40">
                                        @if($applePreview)
                                            <img src="{{ $applePreview }}" alt="Apple touch icon preview" class="size-full object-contain p-1.5">
                                        @else
                                            <x-icon name="lucide.smartphone" class="!size-5 text-base-content/30" />
                                        @endif
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium">Apple Touch Icon</div>
                                        <div class="mt-1 text-xs text-base-content/45">PNG مربع · حداقل 180×180</div>
                                    </div>
                                </div>

                                <input
                                    type="file"
                                    wire:model="seoAppleTouchIconUpload"
                                    accept="image/png"
                                    class="file-input file-input-bordered file-input-sm mt-4 w-full"
                                >
                                @error('seoAppleTouchIconUpload')
                                    <p class="mt-2 text-xs text-error">{{ $message }}</p>
                                @enderror

                                <x-input
                                    class="mt-3"
                                    label="مسیر یا URL"
                                    wire:model.blur="seoAppleTouchIcon"
                                    dir="ltr"
                                />
                            </div>
                        </div>
                    </section>

                    <section class="space-y-4 rounded-2xl border border-base-300 bg-base-200/20 p-4 sm:p-5">
                        <div>
                            <h3 class="text-sm font-semibold">Open Graph</h3>
                            <p class="mt-1 text-xs leading-6 text-base-content/45">
                                تصویر پیش‌فرض لینک Coreflare در شبکه‌های اجتماعی و پیام‌رسان‌ها.
                            </p>
                        </div>

                        <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_18rem]">
                            <div class="space-y-3">
                                <input
                                    type="file"
                                    wire:model="seoDefaultOgImageUpload"
                                    accept="image/png,image/jpeg,image/webp"
                                    class="file-input file-input-bordered w-full"
                                >
                                @error('seoDefaultOgImageUpload')
                                    <p class="text-xs text-error">{{ $message }}</p>
                                @enderror

                                <x-input
                                    label="مسیر یا URL تصویر"
                                    placeholder="/storage/seo/open-graph.png"
                                    hint="حداقل 600×315؛ برای کیفیت بهتر 1200×630 پیشنهاد می‌شود."
                                    wire:model.blur="seoDefaultOgImage"
                                    dir="ltr"
                                />
                            </div>

                            <div class="aspect-[1.91/1] overflow-hidden rounded-xl border border-base-300 bg-base-200/40">
                                @if($ogPreview)
                                    <img src="{{ $ogPreview }}" alt="Open Graph preview" class="size-full object-cover">
                                @else
                                    <div class="flex size-full items-center justify-center text-xs text-base-content/35">
                                        بدون تصویر Open Graph
                                    </div>
                                @endif
                            </div>
                        </div>
                    </section>

                    <section class="space-y-5">
                        <div>
                            <h3 class="text-sm font-semibold">تأیید موتورهای جستجو</h3>
                        </div>

                        <div class="grid gap-5 lg:grid-cols-2">
                            <x-input
                                label="Google Site Verification"
                                hint="فقط مقدار content تگ verification"
                                wire:model.blur="seoGoogleSiteVerification"
                                dir="ltr"
                            />

                            <x-input
                                label="Bing Site Verification"
                                hint="فقط مقدار content تگ msvalidate.01"
                                wire:model.blur="seoBingSiteVerification"
                                dir="ltr"
                            />
                        </div>
                    </section>

                    <div class="rounded-xl border border-base-300 bg-base-200/35 p-4">
                        <label class="flex cursor-pointer items-start justify-between gap-5">
                            <div>
                                <div class="font-medium">اجازه ایندکس عمومی</div>
                                <p class="mt-1 text-sm leading-6 text-base-content/50">
                                    با غیرفعال‌کردن این گزینه، صفحات عمومی noindex می‌شوند؛ Crawl عمومی باز می‌ماند تا موتور جستجو این دستور را دریافت کند.
                                </p>
                            </div>

                            <input
                                type="checkbox"
                                wire:model="seoIndexSite"
                                class="toggle toggle-primary mt-1"
                            >
                        </label>
                    </div>

                    <div class="flex justify-end border-t border-base-300 pt-5">
                        <x-button
                            type="submit"
                            label="ذخیره تنظیمات SEO"
                            icon="lucide.save"
                            class="btn-primary"
                            spinner="saveSeo"
                        />
                    </div>
                </form>
            </div>
        </div>
    </section>

    <div class="rounded-2xl border border-base-300 bg-base-100 px-5 py-4 text-sm leading-7 text-base-content/50">
        کلیدهای زیرساختی، رمزها و credentialهای سرویس‌ها در این بخش نگهداری نمی‌شوند و همچنان از Environment/Config مدیریت می‌شوند.
    </div>
</div>
