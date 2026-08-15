<div class="space-y-5">
    <section
        class="
            overflow-hidden rounded-2xl
            border border-base-300
            bg-base-100
        "
    >
        <div class="p-5 sm:p-6">
            <div class="flex items-start gap-4">
                <div
                    class="
                        flex size-11 shrink-0
                        items-center justify-center
                        rounded-2xl
                        bg-primary/10
                        text-primary
                    "
                >
                    <x-icon
                        name="lucide.shield-check"
                        class="!size-5 stroke-[1.8]"
                    />
                </div>

                <div class="min-w-0">
                    <h1
                        class="
                            text-lg font-semibold
                            tracking-tight
                            sm:text-xl
                        "
                    >
                        داشبورد مدیریت
                    </h1>

                    <p
                        class="
                            mt-1.5 max-w-2xl
                            text-sm leading-7
                            text-base-content/55
                        "
                    >
                        زیرساخت مدیریت {{ config('app.name') }} آماده است. بخش‌های عملیاتی در فازهای بعدی روی همین ساختار اضافه می‌شوند.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <div class="grid gap-4 md:grid-cols-3">
        <section
            class="
                rounded-2xl border border-base-300
                bg-base-100 p-5
            "
        >
            <div
                class="
                    flex size-9 items-center justify-center
                    rounded-xl bg-success/10 text-success
                "
            >
                <x-icon
                    name="lucide.lock-keyhole"
                    class="!size-4 stroke-[1.8]"
                />
            </div>

            <h2 class="mt-4 text-sm font-semibold">
                دسترسی محافظت‌شده
            </h2>

            <p class="mt-1.5 text-xs leading-6 text-base-content/50">
                این بخش فقط برای کاربران دارای دسترسی مدیریت قابل مشاهده است.
            </p>
        </section>

        <section
            class="
                rounded-2xl border border-base-300
                bg-base-100 p-5
            "
        >
            <div
                class="
                    flex size-9 items-center justify-center
                    rounded-xl bg-primary/10 text-primary
                "
            >
                <x-icon
                    name="lucide.blocks"
                    class="!size-4 stroke-[1.8]"
                />
            </div>

            <h2 class="mt-4 text-sm font-semibold">
                بدون فریمورک اضافه
            </h2>

            <p class="mt-1.5 text-xs leading-6 text-base-content/50">
                پنل مدیریت با همان Livewire، Mary UI و Tailwind پروژه ساخته شده است.
            </p>
        </section>

        <section
            class="
                rounded-2xl border border-base-300
                bg-base-100 p-5
            "
        >
            <div
                class="
                    flex size-9 items-center justify-center
                    rounded-xl bg-info/10 text-info
                "
            >
                <x-icon
                    name="lucide.arrow-left"
                    class="!size-4 stroke-[1.8]"
                />
            </div>

            <h2 class="mt-4 text-sm font-semibold">
                آماده فاز بعد
            </h2>

            <p class="mt-1.5 text-xs leading-6 text-base-content/50">
                کاربران، سرورها، سفارش‌ها و پرداخت‌ها در فاز دوم به این سطح مدیریت متصل می‌شوند.
            </p>
        </section>
    </div>
</div>
