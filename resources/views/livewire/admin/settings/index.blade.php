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
                <form wire:submit="saveSeo" class="space-y-6">
                    <div>
                        <h2 class="font-semibold">SEO پیش‌فرض</h2>
                        <p class="mt-1 text-sm leading-6 text-base-content/50">
                            مقادیر پایه برای صفحات عمومی. اتصال این تنظیمات به Meta و Schema در فاز SEO انجام می‌شود.
                        </p>
                    </div>

                    @if($savedSection === 'seo')
                        <div role="status" class="rounded-xl border border-success/20 bg-success/5 px-4 py-3 text-sm text-success">
                            تنظیمات SEO ذخیره شد.
                        </div>
                    @endif

                    <div class="grid gap-5 lg:grid-cols-2">
                        <x-input
                            label="عنوان پیش‌فرض"
                            hint="حداکثر ۷۰ کاراکتر"
                            wire:model.blur="seoDefaultTitle"
                            required
                        />

                        <x-input
                            label="تصویر Open Graph"
                            placeholder="/images/og/coreflare.png"
                            hint="مسیر داخلی یا URL تصویر"
                            wire:model.blur="seoDefaultOgImage"
                            dir="ltr"
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

                    <div class="rounded-xl border border-base-300 bg-base-200/35 p-4">
                        <label class="flex cursor-pointer items-start justify-between gap-5">
                            <div>
                                <div class="font-medium">اجازه ایندکس عمومی</div>
                                <p class="mt-1 text-sm leading-6 text-base-content/50">
                                    این مقدار در فاز SEO برای کنترل رفتار robots صفحات عمومی استفاده خواهد شد.
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
