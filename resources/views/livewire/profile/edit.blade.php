<div class="mx-auto w-full max-w-2xl space-y-5">
    <header>
        <div class="flex items-start gap-3">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <x-icon
                    name="lucide.user-round"
                    class="!size-5 stroke-[1.8]"
                />
            </span>

            <div class="min-w-0">
                <h1 class="text-2xl font-semibold tracking-tight text-base-content">
                    پروفایل
                </h1>

                <p class="mt-1.5 text-sm leading-7 text-base-content/50">
                    اطلاعات شخصی حساب خود را تکمیل یا ویرایش کنید. تکمیل پروفایل اختیاری است.
                </p>
            </div>
        </div>
    </header>

    <section class="rounded-2xl border border-base-300 bg-base-100 p-5 sm:p-6">
        <div class="mb-6 flex items-center gap-3 rounded-xl bg-base-200/60 p-4">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-base-100 text-base-content/50">
                <x-icon
                    name="lucide.smartphone"
                    class="!size-4 stroke-[1.7]"
                />
            </span>

            <div>
                <div class="text-[11px] text-base-content/40">
                    شماره موبایل حساب
                </div>

                <div
                    dir="ltr"
                    class="mt-1 font-mono text-sm font-medium text-base-content/70"
                >
                    {{ $user->phone }}
                </div>
            </div>
        </div>

        @if($statusMessage)
            <div
                role="status"
                class="mb-5 flex items-center gap-2 rounded-xl border border-success/20 bg-success/[0.06] px-3.5 py-3 text-sm text-success"
            >
                <x-icon
                    name="lucide.circle-check"
                    class="!size-4 shrink-0 stroke-[1.8]"
                />

                {{ $statusMessage }}
            </div>
        @endif

        <form
            wire:submit="save"
            class="space-y-5"
        >
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input
                    label="نام"
                    wire:model="firstName"
                    maxlength="80"
                    autocomplete="given-name"
                />

                <x-input
                    label="نام خانوادگی"
                    wire:model="lastName"
                    maxlength="80"
                    autocomplete="family-name"
                />
            </div>

            <div class="flex items-center justify-end border-t border-base-300 pt-5">
                <x-button
                    type="submit"
                    label="ذخیره تغییرات"
                    icon="lucide.check"
                    spinner="save"
                    class="btn-primary rounded-xl"
                />
            </div>
        </form>
    </section>
</div>
