<div dir="rtl" class="mx-auto w-full max-w-6xl space-y-5">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex min-w-0 items-start gap-3.5">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary ring-1 ring-primary/10">
                <x-icon name="lucide.messages-square" class="!size-[18px] stroke-[1.8]" />
            </span>

            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-mono text-[10px] text-base-content/35" dir="ltr">
                        #SUP-{{ str_pad((string) $supportRequest->id, 6, '0', STR_PAD_LEFT) }}
                    </span>
                    <x-support.status-badge :status="$supportRequest->status" />
                    <x-support.category-badge :category="$supportRequest->category" />
                </div>

                <h1 class="mt-2 text-xl font-semibold tracking-tight text-base-content sm:text-2xl">
                    {{ $supportRequest->subject }}
                </h1>

                <p class="mt-1 text-xs text-base-content/40">
                    آخرین فعالیت: {{ $supportRequest->last_message_at?->format('Y-m-d H:i') }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2 self-start">
            @if(! $supportRequest->isClosed())
                <x-button
                    label="بستن درخواست"
                    icon="lucide.circle-x"
                    wire:click="close"
                    wire:confirm="پس از بستن این درخواست امکان ارسال پیام جدید وجود ندارد. ادامه می‌دهید؟"
                    spinner="close"
                    class="btn-ghost btn-sm rounded-xl text-base-content/50 hover:text-error"
                />
            @endif

            <x-button
                icon="lucide.arrow-right"
                :link="route('panel.support.index')"
                wire:navigate
                aria-label="بازگشت به پشتیبانی"
                class="btn-ghost btn-square btn-sm rounded-xl text-base-content/45"
            />
        </div>
    </header>

    @if($statusMessage)
        <div role="status" class="flex items-center gap-2 rounded-xl bg-success/[0.07] px-3.5 py-3 text-sm text-success">
            <x-icon name="lucide.circle-check" class="!size-4 shrink-0 stroke-[1.8]" />
            {{ $statusMessage }}
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_280px] lg:items-start">
        <section
            x-data
            x-on:support-message-sent.window="$nextTick(() => $refs.threadEnd?.scrollIntoView({ behavior: 'smooth', block: 'end' }))"
            class="overflow-hidden rounded-2xl border border-base-300/80 bg-base-100"
        >
            <div class="flex items-center justify-between border-b border-base-300/60 px-4 py-3.5 sm:px-5">
                <div>
                    <h2 class="text-sm font-semibold text-base-content">گفتگو</h2>
                    <p class="mt-0.5 text-[11px] text-base-content/40">
                        {{ number_format($supportRequest->messages->count()) }} پیام در این درخواست
                    </p>
                </div>

                <span class="flex size-8 items-center justify-center rounded-lg bg-base-200/70 text-base-content/35">
                    <x-icon name="lucide.message-circle-more" class="!size-4" />
                </span>
            </div>

            <div class="dashboard-scroll max-h-[560px] space-y-3 overflow-y-auto bg-base-200/20 p-4 sm:p-5">
                @foreach($supportRequest->messages as $message)
                    <x-support.message
                        :message="$message"
                        wire:key="support-message-{{ $message->id }}"
                    />
                @endforeach

                <div x-ref="threadEnd"></div>
            </div>

            @if($supportRequest->isClosed())
                <div class="border-t border-base-300/60 px-4 py-4 sm:px-5">
                    <div class="flex items-start gap-3 rounded-xl bg-base-200/60 px-3.5 py-3">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-base-100 text-base-content/35">
                            <x-icon name="lucide.lock" class="!size-3.5" />
                        </span>

                        <div>
                            <div class="text-xs font-semibold text-base-content/65">این درخواست بسته شده است</div>
                            <p class="mt-1 text-[11px] leading-5 text-base-content/40">
                                برای موضوع جدید می‌توانید یک درخواست پشتیبانی تازه ثبت کنید.
                            </p>
                        </div>
                    </div>
                </div>
            @else
                <form wire:submit="sendReply" class="border-t border-base-300/60 p-4 sm:p-5">
                    <label class="form-control w-full">
                        <div class="label pb-1.5">
                            <span class="label-text text-xs font-medium text-base-content/60">پاسخ شما</span>
                        </div>

                        <textarea
                            wire:model="reply"
                            rows="4"
                            maxlength="10000"
                            placeholder="پیام خود را بنویسید..."
                            class="textarea textarea-bordered w-full resize-y rounded-xl border-base-300 bg-base-100 text-sm leading-7 focus:outline-none"
                        ></textarea>

                        @error('reply')
                            <div class="mt-1.5 text-xs text-error">{{ $message }}</div>
                        @enderror
                    </label>

                    <div class="mt-3">
                        <x-support.image-uploader
                            :files="$attachments"
                            label="اسکرین‌شات تکمیلی"
                        />
                    </div>

                    <div class="mt-3 flex justify-end">
                        <x-button
                            type="submit"
                            label="ارسال پاسخ"
                            icon="lucide.send"
                            spinner="sendReply"
                            wire:loading.attr="disabled"
                            wire:target="attachments"
                            class="btn-primary btn-sm rounded-xl px-4"
                        />
                    </div>
                </form>
            @endif
        </section>

        <aside class="space-y-3 lg:sticky lg:top-4">
            <section class="rounded-2xl border border-base-300/80 bg-base-100 p-4">
                <h2 class="text-xs font-semibold text-base-content/70">جزئیات درخواست</h2>

                <dl class="mt-4 space-y-3.5 text-xs">
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-base-content/40">شماره</dt>
                        <dd class="font-mono text-[11px] text-base-content/65" dir="ltr">
                            #SUP-{{ str_pad((string) $supportRequest->id, 6, '0', STR_PAD_LEFT) }}
                        </dd>
                    </div>

                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-base-content/40">وضعیت</dt>
                        <dd><x-support.status-badge :status="$supportRequest->status" /></dd>
                    </div>

                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-base-content/40">دسته‌بندی</dt>
                        <dd><x-support.category-badge :category="$supportRequest->category" /></dd>
                    </div>

                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-base-content/40">ایجاد</dt>
                        <dd class="text-left text-base-content/60" dir="ltr">{{ $supportRequest->created_at?->format('Y-m-d H:i') }}</dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-2xl border border-base-300/80 bg-base-100 p-4">
                <div class="flex items-center gap-2">
                    <span class="flex size-8 items-center justify-center rounded-lg bg-base-200/70 text-base-content/40">
                        <x-icon name="lucide.server" class="!size-3.5" />
                    </span>
                    <h2 class="text-xs font-semibold text-base-content/70">سرور مرتبط</h2>
                </div>

                @if($supportRequest->server)
                    <div class="mt-3">
                        <div class="text-sm font-medium text-base-content/75">
                            {{ $supportRequest->server->name ?: 'سرور بدون نام' }}
                        </div>
                        <div class="mt-1 font-mono text-[11px] text-base-content/40" dir="ltr">
                            {{ $supportRequest->server->host }}
                        </div>
                    </div>
                @else
                    <p class="mt-3 text-xs leading-5 text-base-content/40">
                        این درخواست به سرور مشخصی متصل نشده است.
                    </p>
                @endif
            </section>
        </aside>
    </div>
</div>
