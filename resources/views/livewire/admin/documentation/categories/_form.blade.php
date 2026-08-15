<form wire:submit="save" class="space-y-5">
    <section class="rounded-2xl border border-base-300 bg-base-100 p-5 sm:p-6">
        <div class="grid gap-5 lg:grid-cols-2">
            <x-input
                label="عنوان"
                placeholder="مثلاً شروع کار"
                wire:model.blur="title"
                required
            />

            <x-input
                label="Slug"
                placeholder="getting-started"
                hint="فقط حروف انگلیسی کوچک، عدد و خط تیره"
                wire:model.blur="slug"
                dir="ltr"
                required
            />
        </div>

        <div class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1fr)_12rem]">
            <label class="form-control">
                <span class="mb-2 text-sm font-medium">توضیح کوتاه</span>
                <textarea
                    rows="4"
                    wire:model.blur="description"
                    class="textarea textarea-bordered w-full resize-y text-sm leading-7"
                    placeholder="این دسته شامل چه موضوعاتی است؟"
                ></textarea>
                @error('description')
                    <span class="mt-2 text-xs text-error">{{ $message }}</span>
                @enderror
            </label>

            <x-input
                label="ترتیب نمایش"
                type="number"
                min="0"
                wire:model.blur="sortOrder"
                required
            />
        </div>
    </section>

    <section class="rounded-2xl border border-base-300 bg-base-100 p-5 sm:p-6">
        <label class="flex cursor-pointer items-start justify-between gap-5">
            <div>
                <div class="font-medium">انتشار دسته</div>
                <p class="mt-1 text-sm leading-6 text-base-content/50">
                    فقط دسته‌های منتشرشده در بخش عمومی مستندات نمایش داده می‌شوند. مقاله‌های داخل دسته همچنان وضعیت انتشار مستقل دارند.
                </p>
            </div>

            <input type="checkbox" wire:model="isPublished" class="toggle toggle-primary mt-1">
        </label>
    </section>

    <div class="flex flex-wrap items-center justify-end gap-2">
        <x-button
            label="انصراف"
            :link="route('admin.documentation.categories.index')"
            wire:navigate
            class="btn-ghost"
        />
        <x-button
            type="submit"
            label="ذخیره دسته"
            icon="lucide.save"
            class="btn-primary"
            spinner="save"
        />
    </div>
</form>
