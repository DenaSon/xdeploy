<div dir="rtl" class="mx-auto w-full max-w-3xl space-y-5">
    <header class="flex items-start justify-between gap-4">
        <div class="flex min-w-0 items-start gap-3.5">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary ring-1 ring-primary/10">
                <x-icon name="lucide.message-square-plus" class="!size-[18px] stroke-[1.8]" />
            </span>

            <div class="min-w-0">
                <h1 class="text-xl font-semibold tracking-tight text-base-content sm:text-2xl">
                    درخواست پشتیبانی جدید
                </h1>

                <p class="mt-1 max-w-xl text-xs leading-6 text-base-content/45 sm:text-sm">
                    موضوع و جزئیات را واضح بنویسید تا بررسی درخواست سریع‌تر و دقیق‌تر انجام شود.
                </p>
            </div>
        </div>

        <x-button
            icon="lucide.arrow-right"
            :link="route('panel.support.index')"
            wire:navigate
            aria-label="بازگشت به پشتیبانی"
            class="btn-ghost btn-square btn-sm shrink-0 rounded-xl text-base-content/45"
        />
    </header>

    <section class="overflow-hidden rounded-2xl border border-base-300/80 bg-base-100">
        <div class="flex items-start gap-3 bg-primary/[0.035] px-4 py-3.5 sm:px-5">
            <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <x-icon name="lucide.info" class="!size-3.5" />
            </span>

            <p class="text-xs leading-6 text-base-content/50">
                در صورت مرتبط‌بودن مشکل با یک VPS، همان سرور را انتخاب کنید. اطلاعات اتصال یا رمز عبور را داخل متن درخواست ارسال نکنید.
            </p>
        </div>

        <form wire:submit="submit" class="space-y-5 p-4 sm:p-6">
            <x-input
                label="موضوع درخواست"
                placeholder="مثلاً مشکل در نصب n8n"
                wire:model="subject"
                maxlength="160"
                icon="lucide.text"
            />

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="form-control w-full">
                    <div class="label pb-1.5">
                        <span class="label-text text-xs font-medium text-base-content/65">دسته‌بندی</span>
                    </div>

                    <select
                        wire:model="category"
                        class="select select-bordered h-11 min-h-11 w-full rounded-xl border-base-300 bg-base-100 text-sm focus:outline-none"
                    >
                        <option value="technical">فنی</option>
                        <option value="billing">مالی و پرداخت</option>
                        <option value="account">حساب کاربری</option>
                        <option value="other">سایر</option>
                    </select>

                    @error('category')
                        <div class="mt-1.5 text-xs text-error">{{ $message }}</div>
                    @enderror
                </label>

                <label class="form-control w-full">
                    <div class="label pb-1.5">
                        <span class="label-text text-xs font-medium text-base-content/65">سرور مرتبط</span>
                        <span class="label-text-alt text-[10px] text-base-content/35">اختیاری</span>
                    </div>

                    <select
                        wire:model="serverId"
                        class="select select-bordered h-11 min-h-11 w-full rounded-xl border-base-300 bg-base-100 text-sm focus:outline-none"
                    >
                        <option value="">بدون سرور مشخص</option>

                        @foreach($servers as $server)
                            <option value="{{ $server->id }}">
                                {{ $server->name ?: $server->host }}
                                @if($server->name)
                                    — {{ $server->host }}
                                @endif
                            </option>
                        @endforeach
                    </select>

                    @error('serverId')
                        <div class="mt-1.5 text-xs text-error">{{ $message }}</div>
                    @enderror
                </label>
            </div>

            <label class="form-control w-full">
                <div class="label pb-1.5">
                    <span class="label-text text-xs font-medium text-base-content/65">شرح درخواست</span>
                    <span class="label-text-alt text-[10px] text-base-content/35">حداکثر ۱۰٬۰۰۰ کاراکتر</span>
                </div>

                <textarea
                    wire:model="message"
                    rows="8"
                    maxlength="10000"
                    placeholder="چه اتفاقی افتاده، انتظار داشتید چه اتفاقی بیفتد و اگر پیام خطایی دیده‌اید آن را اینجا بنویسید."
                    class="textarea textarea-bordered w-full resize-y rounded-xl border-base-300 bg-base-100 text-sm leading-7 focus:outline-none"
                ></textarea>

                @error('message')
                    <div class="mt-1.5 text-xs text-error">{{ $message }}</div>
                @enderror
            </label>

            <x-support.image-uploader
                :files="$attachments"
                label="اسکرین‌شات مشکل"
            />

            <div class="flex flex-col-reverse gap-2 border-t border-base-300/70 pt-5 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-[11px] leading-5 text-base-content/35">
                    پس از ثبت، درخواست در بخش پشتیبانی قابل پیگیری و ادامه گفتگو خواهد بود.
                </p>

                <x-button
                    type="submit"
                    label="ثبت درخواست"
                    icon="lucide.send"
                    spinner="submit"
                    wire:loading.attr="disabled"
                    wire:target="attachments"
                    class="btn-primary rounded-xl px-5"
                />
            </div>
        </form>
    </section>
</div>
