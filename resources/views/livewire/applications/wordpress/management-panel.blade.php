<section
    class="overflow-hidden rounded-2xl
           border border-base-300
           bg-base-100"
>
    <header
        class="flex items-start gap-3
               border-b border-base-300
               px-5 py-4
               sm:px-6"
    >
        <div
            class="flex size-9 shrink-0
                   items-center justify-center
                   rounded-xl
                   bg-primary/[0.07]
                   text-primary"
        >
            <x-icon
                name="lucide.newspaper"
                class="size-4"
            />
        </div>

        <div>
            <h2
                class="text-base font-semibold
                       text-base-content"
            >
                آمادگی WordPress
            </h2>

            <p
                class="mt-0.5 text-sm leading-6
                       text-base-content/50"
            >
                WordPress و پایگاه داده اختصاصی آن سالم و در حال اجرا هستند.
            </p>
        </div>
    </header>

    <div
        class="flex flex-col gap-4
               px-5 py-4
               sm:flex-row
               sm:items-center
               sm:justify-between
               sm:px-6"
    >
        <div
            class="flex items-center gap-3"
        >
            <div
                class="flex size-8 shrink-0
                       items-center justify-center
                       rounded-lg
                       bg-base-200/70"
            >
                <x-icon
                    name="lucide.lock"
                    class="size-4
                           text-base-content/50"
                />
            </div>

            <div>
                <p
                    class="text-xs
                           text-base-content/40"
                >
                    رابط داخلی
                </p>

                <p
                    dir="ltr"
                    class="technical-value mt-0.5
                           text-left text-sm font-medium"
                >
                    127.0.0.1:8080
                </p>
            </div>
        </div>

        @if ($publicUrl !== null)
            <span
                class="inline-flex w-fit items-center
                       gap-1.5 rounded-full
                       border border-success/20
                       bg-success/10
                       px-2.5 py-1
                       text-xs font-medium
                       text-success"
            >
                <span
                    class="size-1.5 rounded-full
                           bg-success"
                ></span>

                دسترسی عمومی فعال
            </span>
        @else
            <span
                class="inline-flex w-fit items-center
                       gap-1.5 rounded-full
                       border border-base-300
                       bg-base-200/60
                       px-2.5 py-1
                       text-xs font-medium
                       text-base-content/50"
            >
                <span
                    class="size-1.5 rounded-full
                           bg-base-content/30"
                ></span>

                دسترسی عمومی غیرفعال
            </span>
        @endif
    </div>

    @if ($publicUrl !== null)
        <div
            class="flex flex-col gap-3
                   border-t border-base-300
                   px-5 py-4
                   sm:flex-row
                   sm:items-center
                   sm:justify-between
                   sm:px-6"
        >
            <div
                class="flex min-w-0 items-center gap-3"
            >
                <div
                    class="flex size-8 shrink-0
                           items-center justify-center
                           rounded-lg
                           bg-success/10"
                >
                    <x-icon
                        name="lucide.globe-lock"
                        class="size-4 text-success"
                    />
                </div>

                <div class="min-w-0">
                    <p
                        class="text-xs
                               text-base-content/40"
                    >
                        آدرس عمومی امن
                    </p>

                    <a
                        href="{{ $publicUrl }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        dir="ltr"
                        class="technical-value mt-0.5 block
                               truncate text-left text-sm font-medium
                               text-primary hover:underline"
                    >
                        {{ $publicUrl }}
                    </a>
                </div>
            </div>

            <a
                href="{{ $publicUrl }}"
                target="_blank"
                rel="noopener noreferrer"
                class="btn btn-primary btn-sm
                       w-fit rounded-xl"
            >
                <x-icon
                    name="lucide.external-link"
                    class="size-4"
                />

                باز کردن سایت
            </a>
        </div>
    @endif
</section>
