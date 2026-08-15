<div class="space-y-5">
    <x-admin.page-header
        :title="$supportRequest->subject"
        description="گفتگوی پشتیبانی و زمینه مرتبط با حساب کاربر را از همین صفحه بررسی کنید."
        icon="lucide.headset"
    >
        <x-slot:actions>
            <div class="flex items-center gap-2">
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
                    label="بازگشت"
                    icon="lucide.arrow-right"
                    :link="route('admin.support.index')"
                    wire:navigate
                    class="btn-ghost btn-sm rounded-xl"
                />
            </div>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="flex flex-wrap items-center gap-2 px-1">
        <span class="font-mono text-[10px] text-base-content/35" dir="ltr">
            #SUP-{{ str_pad((string) $supportRequest->id, 6, '0', STR_PAD_LEFT) }}
        </span>
        <x-support.status-badge :status="$supportRequest->status" />
        <x-support.category-badge :category="$supportRequest->category" />
        <span class="text-[10px] text-base-content/35">
            آخرین فعالیت {{ $supportRequest->last_message_at?->format('Y-m-d H:i') }}
        </span>
    </div>

    @if($statusMessage)
        <div role="status" class="flex items-center gap-2 rounded-xl bg-success/[0.07] px-3.5 py-3 text-sm text-success">
            <x-icon name="lucide.circle-check" class="!size-4 shrink-0 stroke-[1.8]" />
            {{ $statusMessage }}
        </div>
    @endif

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_320px] xl:items-start">
        <section
            x-data
            x-on:support-message-sent.window="$nextTick(() => $refs.threadEnd?.scrollIntoView({ behavior: 'smooth', block: 'end' }))"
            class="overflow-hidden rounded-2xl border border-base-300 bg-base-100"
        >
            <div class="flex items-center justify-between border-b border-base-300/60 px-4 py-3.5 sm:px-5">
                <div>
                    <h2 class="text-sm font-semibold text-base-content">گفتگو با کاربر</h2>
                    <p class="mt-0.5 text-[11px] text-base-content/40">
                        {{ number_format($supportRequest->messages->count()) }} پیام
                    </p>
                </div>

                <span class="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <x-icon name="lucide.messages-square" class="!size-4" />
                </span>
            </div>

            <div class="dashboard-scroll max-h-[620px] space-y-3 overflow-y-auto bg-base-200/20 p-4 sm:p-5">
                @foreach($supportRequest->messages as $message)
                    <x-support.message
                        :message="$message"
                        :admin-view="true"
                        wire:key="admin-support-message-{{ $message->id }}"
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
                            <div class="text-xs font-semibold text-base-content/65">درخواست بسته شده است</div>
                            <p class="mt-1 text-[11px] leading-5 text-base-content/40">
                                Conversation در وضعیت نهایی است و پیام جدید پذیرفته نمی‌شود.
                            </p>
                        </div>
                    </div>
                </div>
            @else
                <form wire:submit="sendReply" class="border-t border-base-300/60 p-4 sm:p-5">
                    <label class="form-control w-full">
                        <div class="label pb-1.5">
                            <span class="label-text text-xs font-medium text-base-content/60">پاسخ مدیریت</span>
                            <span class="label-text-alt text-[10px] text-base-content/35">برای کاربر اعلان داخلی ارسال می‌شود</span>
                        </div>

                        <textarea
                            wire:model="reply"
                            rows="5"
                            maxlength="10000"
                            placeholder="پاسخ روشن، دقیق و قابل اقدام برای کاربر بنویسید..."
                            class="textarea textarea-bordered w-full resize-y rounded-xl border-base-300 bg-base-100 text-sm leading-7 focus:outline-none"
                        ></textarea>

                        @error('reply')
                            <div class="mt-1.5 text-xs text-error">{{ $message }}</div>
                        @enderror
                    </label>

                    <div class="mt-3 flex justify-end">
                        <x-button
                            type="submit"
                            label="ارسال پاسخ"
                            icon="lucide.send"
                            spinner="sendReply"
                            class="btn-primary btn-sm rounded-xl px-4"
                        />
                    </div>
                </form>
            @endif
        </section>

        <aside class="space-y-3 xl:sticky xl:top-4">
            <section class="rounded-2xl border border-base-300 bg-base-100 p-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span class="flex size-8 items-center justify-center rounded-lg bg-base-200/70 text-base-content/40">
                            <x-icon name="lucide.user-round" class="!size-3.5" />
                        </span>
                        <h2 class="text-xs font-semibold text-base-content/70">کاربر</h2>
                    </div>

                    <x-button
                        icon="lucide.external-link"
                        :link="route('admin.users.show', $supportRequest->user)"
                        wire:navigate
                        aria-label="مشاهده کاربر"
                        class="btn-ghost btn-square btn-xs rounded-lg"
                    />
                </div>

                <div class="mt-3">
                    <div class="text-sm font-medium text-base-content/75">
                        {{ $supportRequest->user->displayName() ?? 'بدون نام' }}
                    </div>
                    <div class="mt-1 font-mono text-[11px] text-base-content/40" dir="ltr">
                        {{ $supportRequest->user->phone }}
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-base-300 bg-base-100 p-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span class="flex size-8 items-center justify-center rounded-lg bg-base-200/70 text-base-content/40">
                            <x-icon name="lucide.server" class="!size-3.5" />
                        </span>
                        <h2 class="text-xs font-semibold text-base-content/70">سرور مرتبط</h2>
                    </div>

                    @if($supportRequest->server)
                        <x-button
                            icon="lucide.external-link"
                            :link="route('admin.servers.show', ['adminServer' => $supportRequest->server])"
                            wire:navigate
                            aria-label="مشاهده سرور"
                            class="btn-ghost btn-square btn-xs rounded-lg"
                        />
                    @endif
                </div>

                @if($supportRequest->server)
                    <div class="mt-3">
                        <div class="text-sm font-medium text-base-content/75">
                            {{ $supportRequest->server->name ?: 'سرور بدون نام' }}
                        </div>
                        <div class="mt-1 font-mono text-[11px] text-base-content/40" dir="ltr">
                            {{ $supportRequest->server->host }}:{{ $supportRequest->server->port }}
                        </div>
                    </div>
                @else
                    <p class="mt-3 text-xs leading-5 text-base-content/40">
                        کاربر این درخواست را به سرور مشخصی متصل نکرده است.
                    </p>
                @endif
            </section>

            <section class="rounded-2xl border border-base-300 bg-base-100 p-4">
                <h2 class="text-xs font-semibold text-base-content/70">اطلاعات درخواست</h2>

                <dl class="mt-4 space-y-3.5 text-xs">
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-base-content/40">وضعیت</dt>
                        <dd><x-support.status-badge :status="$supportRequest->status" /></dd>
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-base-content/40">دسته‌بندی</dt>
                        <dd><x-support.category-badge :category="$supportRequest->category" /></dd>
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-base-content/40">ایجاد</dt>
                        <dd class="text-base-content/60" dir="ltr">{{ $supportRequest->created_at?->format('Y-m-d H:i') }}</dd>
                    </div>

                    @if($supportRequest->closed_at)
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-base-content/40">بسته‌شدن</dt>
                            <dd class="text-base-content/60" dir="ltr">{{ $supportRequest->closed_at->format('Y-m-d H:i') }}</dd>
                        </div>
                    @endif
                </dl>
            </section>
        </aside>
    </div>
</div>
