<form
    wire:submit="save"
    class="space-y-5"
>
    <section class="rounded-2xl border border-base-300 bg-base-100 p-5 sm:p-6">
        <div class="grid gap-5 lg:grid-cols-2">
            <x-input
                label="عنوان"
                placeholder="مثلاً حریم خصوصی"
                wire:model.blur="title"
                required
            />

            <x-input
                label="Slug"
                placeholder="privacy-policy"
                hint="فقط حروف انگلیسی کوچک، عدد و خط تیره"
                wire:model.blur="slug"
                dir="ltr"
                required
            />
        </div>

        <div class="mt-5">
            <div class="mb-2 flex items-center justify-between gap-3">
                <label
                    for="page-content"
                    class="text-sm font-medium"
                >
                    محتوا
                </label>

                <span class="text-xs text-base-content/45">
                    Markdown پشتیبانی می‌شود
                </span>
            </div>

            <textarea
                id="page-content"
                rows="20"
                wire:model.blur="content"
                class="textarea textarea-bordered min-h-96 w-full resize-y text-sm leading-8"
                placeholder="محتوای صفحه را بنویسید..."
            ></textarea>

            @error('content')
                <p class="mt-2 text-xs text-error">{{ $message }}</p>
            @enderror
        </div>
    </section>

    <section class="rounded-2xl border border-base-300 bg-base-100 p-5 sm:p-6">
        <label class="flex cursor-pointer items-start justify-between gap-5">
            <div>
                <div class="font-medium">انتشار عمومی</div>
                <p class="mt-1 text-sm leading-6 text-base-content/50">
                    با فعال‌کردن این گزینه، صفحه برای کاربران عمومی قابل مشاهده می‌شود. صفحه منتشرشده باید محتوا داشته باشد.
                </p>
            </div>

            <input
                type="checkbox"
                wire:model="isPublished"
                class="toggle toggle-primary mt-1"
            >
        </label>
    </section>

    <div class="flex flex-wrap items-center justify-end gap-2">
        <x-button
            label="انصراف"
            :link="route('admin.pages.index')"
            wire:navigate
            class="btn-ghost"
        />

        <x-button
            type="submit"
            label="ذخیره صفحه"
            icon="lucide.save"
            class="btn-primary"
            spinner="save"
        />
    </div>
</form>
