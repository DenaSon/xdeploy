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
            <x-admin.markdown-editor
                wire:model="content"
                label="محتوا"
                hint="Markdown پشتیبانی می‌شود؛ پیش‌نمایش را قبل از انتشار بررسی کنید."
                placeholder="محتوای صفحه را بنویسید..."
            />
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
