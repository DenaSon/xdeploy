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
                name="lucide.workflow"
                class="size-4"
            />
        </div>

        <div>
            <h2
                class="text-base font-semibold
                       text-base-content"
            >
                وضعیت دسترسی n8n
            </h2>

            <p
                class="mt-0.5 text-sm leading-6
                       text-base-content/50"
            >
                سرویس در حال اجرا است و رابط وب آن فعلاً فقط
                از داخل سرور در دسترس است.
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
                    127.0.0.1:5678
                </p>
            </div>
        </div>

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

            دسترسی عمومی غیرفعال
        </span>
    </div>
</section>
