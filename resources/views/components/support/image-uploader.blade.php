@props([
    'files' => [],
    'model' => 'attachments',
    'removeMethod' => 'removeAttachment',
    'label' => 'تصاویر',
])

<div
    x-data="{ uploading: false, progress: 0 }"
    x-on:livewire-upload-start="uploading = true; progress = 0"
    x-on:livewire-upload-finish="uploading = false; progress = 0"
    x-on:livewire-upload-cancel="uploading = false; progress = 0"
    x-on:livewire-upload-error="uploading = false; progress = 0"
    x-on:livewire-upload-progress="progress = $event.detail.progress"
    data-support-image-uploader
    class="space-y-2.5"
>
    <div class="flex items-center justify-between gap-3">
        <span class="text-xs font-medium text-base-content/60">{{ $label }}</span>
        <span class="text-[10px] text-base-content/35">اختیاری · حداکثر ۲ تصویر</span>
    </div>

    @if(count($files) < 2)
        <label
            class="group flex min-h-20 cursor-pointer items-center gap-3 rounded-xl border border-dashed border-base-300 bg-base-200/20 px-3.5 py-3 transition hover:border-primary/30 hover:bg-primary/[0.025]"
        >
            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-base-100 text-base-content/40 ring-1 ring-base-300/70 transition group-hover:text-primary">
                <x-icon name="lucide.image-plus" class="!size-4 stroke-[1.8]" />
            </span>

            <span class="min-w-0 flex-1">
                <span class="block text-xs font-medium text-base-content/65">
                    افزودن اسکرین‌شات
                </span>
                <span class="mt-1 block text-[10px] leading-5 text-base-content/35">
                    JPG، PNG یا WebP · حداکثر ۲ مگابایت برای هر تصویر
                </span>
            </span>

            <input
                type="file"
                wire:model="{{ $model }}"
                accept="image/jpeg,image/png,image/webp"
                multiple
                class="sr-only"
            />
        </label>
    @endif

    <div
        x-cloak
        x-show="uploading"
        class="rounded-xl border border-primary/10 bg-primary/[0.035] px-3 py-2.5"
    >
        <div class="flex items-center justify-between gap-3 text-[10px] text-base-content/45">
            <span>در حال بارگذاری تصویر...</span>
            <span x-text="`${progress}%`" dir="ltr"></span>
        </div>

        <progress
            class="progress progress-primary mt-2 h-1.5 w-full"
            max="100"
            x-bind:value="progress"
        ></progress>
    </div>

    @if($files !== [])
        <div class="grid grid-cols-2 gap-2 sm:max-w-md">
            @foreach($files as $index => $file)
                @php
                    $mimeType = method_exists($file, 'getMimeType')
                        ? $file->getMimeType()
                        : null;
                    $previewable = in_array(
                        $mimeType,
                        ['image/jpeg', 'image/png', 'image/webp'],
                        true,
                    );
                @endphp

                <div
                    wire:key="support-upload-preview-{{ $index }}"
                    class="relative overflow-hidden rounded-xl border border-base-300 bg-base-200/30"
                >
                    @if($previewable)
                        <img
                            src="{{ $file->temporaryUrl() }}"
                            alt="پیش‌نمایش تصویر انتخاب‌شده"
                            class="aspect-video w-full object-cover"
                        />
                    @else
                        <div class="flex aspect-video items-center justify-center text-base-content/25">
                            <x-icon name="lucide.file-warning" class="!size-5" />
                        </div>
                    @endif

                    <button
                        type="button"
                        wire:click="{{ $removeMethod }}({{ $index }})"
                        aria-label="حذف تصویر"
                        class="btn btn-circle btn-xs absolute left-1.5 top-1.5 border-base-300/70 bg-base-100/90 text-base-content/55 shadow-sm backdrop-blur hover:text-error"
                    >
                        <x-icon name="lucide.x" class="!size-3" />
                    </button>

                    <div class="truncate px-2.5 py-2 text-[10px] text-base-content/40" dir="ltr">
                        {{ method_exists($file, 'getClientOriginalName') ? $file->getClientOriginalName() : 'image' }}
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if($errors->has($model) || $errors->has($model.'.*'))
        <div class="flex items-start gap-1.5 text-xs leading-5 text-error">
            <x-icon name="lucide.circle-alert" class="mt-0.5 !size-3.5 shrink-0" />
            <span>
                {{ $errors->first($model) ?: $errors->first($model.'.*') }}
            </span>
        </div>
    @endif
</div>
