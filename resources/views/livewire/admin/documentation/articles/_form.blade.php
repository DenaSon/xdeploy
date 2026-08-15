<form wire:submit="save" class="space-y-5">
    <section class="rounded-2xl border border-base-300 bg-base-100 p-5 sm:p-6">
        <div class="grid gap-5 lg:grid-cols-2">
            <label class="form-control">
                <span class="mb-2 text-sm font-medium">دسته</span>
                <select wire:model="categoryId" class="select select-bordered w-full" required>
                    <option value="">انتخاب دسته</option>
                    @foreach($categories as $categoryItem)
                        <option value="{{ $categoryItem->id }}">
                            {{ $categoryItem->title }}{{ $categoryItem->is_published ? '' : ' — پیش‌نویس' }}
                        </option>
                    @endforeach
                </select>
                @error('categoryId')
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

            <x-input
                label="عنوان"
                placeholder="مثلاً اتصال اولین سرور"
                wire:model.blur="title"
                required
            />

            <x-input
                label="Slug"
                placeholder="connect-first-server"
                hint="داخل هر دسته باید یکتا باشد"
                wire:model.blur="slug"
                dir="ltr"
                required
            />
        </div>

        <label class="form-control mt-5">
            <span class="mb-2 text-sm font-medium">خلاصه</span>
            <textarea
                rows="3"
                wire:model.blur="excerpt"
                class="textarea textarea-bordered w-full resize-y text-sm leading-7"
                placeholder="توضیح کوتاهی که در فهرست مستندات نمایش داده می‌شود."
            ></textarea>
            @error('excerpt')
                <span class="mt-2 text-xs text-error">{{ $message }}</span>
            @enderror
        </label>

        <div class="mt-5">
            <div class="mb-2 flex items-center justify-between gap-3">
                <label for="documentation-content" class="text-sm font-medium">محتوا</label>
                <span class="text-xs text-base-content/45">Markdown پشتیبانی می‌شود</span>
            </div>

            <textarea
                id="documentation-content"
                rows="22"
                wire:model.blur="content"
                class="textarea textarea-bordered min-h-96 w-full resize-y text-sm leading-8"
                placeholder="محتوای راهنما را بنویسید..."
            ></textarea>
            @error('content')
                <p class="mt-2 text-xs text-error">{{ $message }}</p>
            @enderror
        </div>
    </section>

    <section class="rounded-2xl border border-base-300 bg-base-100 p-5 sm:p-6">
        <label class="flex cursor-pointer items-start justify-between gap-5">
            <div>
                <div class="font-medium">انتشار مقاله</div>
                <p class="mt-1 text-sm leading-6 text-base-content/50">
                    مقاله منتشرشده باید محتوا داشته باشد. نمایش عمومی آن علاوه بر این گزینه، به منتشر بودن دسته نیز وابسته است.
                </p>
            </div>

            <input type="checkbox" wire:model="isPublished" class="toggle toggle-primary mt-1">
        </label>
    </section>

    <div class="flex flex-wrap items-center justify-end gap-2">
        <x-button
            label="انصراف"
            :link="route('admin.documentation.articles.index')"
            wire:navigate
            class="btn-ghost"
        />
        <x-button
            type="submit"
            label="ذخیره مقاله"
            icon="lucide.save"
            class="btn-primary"
            spinner="save"
        />
    </div>
</form>
