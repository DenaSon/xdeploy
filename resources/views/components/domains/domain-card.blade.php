@props([
    'domain' => null,
    'application' => 'Marzban',
    'state' => 'unknown',
    'openUrl' => null,
    'applicationUrl' => null,
    'manageEndpointId' => null,
    'removing' => false,
])

@php
    use App\Support\PublicEndpoint\PublicEndpointStatusPresentation;

    $domain = is_string($domain) && trim($domain) !== ''
        ? trim($domain)
        : null;

    $openUrl = is_string($openUrl) && trim($openUrl) !== ''
        ? trim($openUrl)
        : null;

    $presentation = PublicEndpointStatusPresentation::for(
        $removing ? 'removing' : $state,
    );

    $tone = match ($presentation['tone']) {
        'success' => [
            'icon' => 'bg-success/10 text-success',
            'badge' => 'border-success/20 bg-success/10 text-success',
            'dot' => 'bg-success',
            'footer' => 'border-success/15 bg-success/[0.035]',
            'footerIcon' => 'lucide.circle-check',
            'footerIconClass' => 'text-success',
        ],
        'error' => [
            'icon' => 'bg-error/10 text-error',
            'badge' => 'border-error/20 bg-error/10 text-error',
            'dot' => 'bg-error',
            'footer' => 'border-error/15 bg-error/[0.035]',
            'footerIcon' => 'lucide.triangle-alert',
            'footerIconClass' => 'text-error',
        ],
        'info' => [
            'icon' => 'bg-info/10 text-info',
            'badge' => 'border-info/20 bg-info/10 text-info',
            'dot' => 'bg-info',
            'footer' => 'border-info/15 bg-info/[0.035]',
            'footerIcon' => 'lucide.info',
            'footerIconClass' => 'text-info',
        ],
        default => [
            'icon' => 'bg-warning/10 text-warning',
            'badge' => 'border-warning/20 bg-warning/10 text-warning',
            'dot' => 'bg-warning',
            'footer' => 'border-warning/15 bg-warning/[0.035]',
            'footerIcon' => 'lucide.info',
            'footerIconClass' => 'text-warning',
        ],
    };
@endphp

<article
    {{ $attributes->class([
        'overflow-hidden rounded-2xl',
        'border border-base-300',
        'bg-base-100',
    ]) }}
>
    <div class="p-5 sm:p-6">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex min-w-0 items-start gap-3.5">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-xl {{ $tone['icon'] }}">
                    @if ($presentation['state'] === 'removing' || $presentation['state'] === 'checking')
                        <span class="loading loading-spinner loading-xs"></span>
                    @else
                        <x-icon
                            :name="$presentation['icon']"
                            class="!size-4.5 stroke-[1.8]"
                        />
                    @endif
                </div>

                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2
                            dir="ltr"
                            class="technical-value truncate text-base font-semibold text-base-content"
                        >
                            {{ $domain ?? 'دامنه شناسایی نشد' }}
                        </h2>

                        <span class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[11px] font-medium {{ $tone['badge'] }}">
                            <span class="size-1.5 rounded-full {{ $tone['dot'] }}"></span>
                            {{ $presentation['label'] }}
                        </span>
                    </div>

                    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1.5 text-xs text-base-content/45">
                        <span class="inline-flex items-center gap-1.5">
                            <x-icon name="lucide.package" class="!size-3.5" />
                            {{ $application }}
                        </span>

                        @if ($presentation['state'] === 'enabled')
                            <span class="inline-flex items-center gap-1.5 text-success">
                                <x-icon name="lucide.shield-check" class="!size-3.5" />
                                HTTPS فعال
                            </span>
                        @endif
                    </div>

                    <p class="mt-3 max-w-2xl text-sm leading-7 text-base-content/50">
                        {{ $presentation['description'] }}
                    </p>
                </div>
            </div>

            <div class="flex shrink-0 flex-wrap items-center gap-2">
                @if ($presentation['primary_action'] === 'manage' && $manageEndpointId !== null)
                    <x-button
                        :label="$presentation['primary_label']"
                        :icon="$presentation['primary_icon']"
                        wire:click="manageEndpoint({{ (int) $manageEndpointId }})"
                        class="btn-primary btn-sm rounded-xl"
                    />
                @elseif ($presentation['primary_action'] === 'refresh')
                    <x-button
                        :label="$presentation['primary_label']"
                        :icon="$presentation['primary_icon']"
                        wire:click="refreshDomains"
                        spinner="refreshDomains"
                        wire:loading.attr="disabled"
                        wire:target="refreshDomains"
                        class="btn-primary btn-sm rounded-xl"
                    />
                @elseif ($presentation['primary_action'] === 'open' && $openUrl !== null)
                    <x-button
                        :label="$presentation['primary_label']"
                        :icon="$presentation['primary_icon']"
                        :link="$openUrl"
                        external
                        class="btn-primary btn-sm rounded-xl"
                    />
                @endif

                @if ($presentation['state'] === 'enabled' && $openUrl !== null)
                    <button
                        type="button"
                        x-data="{ copied: false }"
                        x-on:click="navigator.clipboard.writeText(@js($openUrl)).then(() => { copied = true; setTimeout(() => copied = false, 1800) })"
                        class="btn btn-ghost btn-sm rounded-xl"
                        aria-label="کپی آدرس دامنه"
                    >
                        <x-icon name="lucide.copy" class="!size-4" />
                        <span x-text="copied ? 'کپی شد' : 'کپی آدرس'">کپی آدرس</span>
                    </button>
                @endif

                @if ($applicationUrl !== null)
                    <x-button
                        label="مشاهده برنامه"
                        icon="lucide.package-open"
                        :link="$applicationUrl"
                        wire:navigate
                        class="btn-ghost btn-sm rounded-xl"
                    />
                @endif

                @if (
                    $manageEndpointId !== null
                    && ! in_array($presentation['state'], ['pending', 'removing'], true)
                )
                    <x-button
                        label="حذف دامنه"
                        icon="lucide.unlink"
                        wire:click="removeEndpoint({{ (int) $manageEndpointId }})"
                        spinner="removeEndpoint"
                        wire:confirm="دامنه {{ $domain }} از {{ $application }} حذف شود؟ برنامه روی سرور باقی می‌ماند اما دسترسی عمومی این دامنه و HTTPS آن غیرفعال می‌شود."
                        class="btn-error btn-outline btn-sm rounded-xl"
                    />
                @endif
            </div>
        </div>
    </div>

    @if ($presentation['state'] === 'enabled')
        <div class="grid grid-cols-2 border-t border-base-300 bg-base-200/20">
            <div class="flex items-center gap-2.5 border-l border-base-300 px-5 py-3.5 sm:px-6">
                <span class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-success/10 text-success">
                    <x-icon name="lucide.network" class="!size-3.5" />
                </span>

                <div>
                    <div class="text-[10px] text-base-content/35">DNS</div>
                    <div class="mt-0.5 text-xs font-medium text-success">متصل</div>
                </div>
            </div>

            <div class="flex items-center gap-2.5 px-5 py-3.5 sm:px-6">
                <span class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-success/10 text-success">
                    <x-icon name="lucide.lock-keyhole" class="!size-3.5" />
                </span>

                <div>
                    <div class="text-[10px] text-base-content/35">HTTPS</div>
                    <div class="mt-0.5 text-xs font-medium text-success">فعال</div>
                </div>
            </div>
        </div>
    @elseif ($presentation['footer'] !== null)
        <div class="flex items-center gap-2.5 border-t px-5 py-3.5 sm:px-6 {{ $tone['footer'] }}">
            <x-icon
                :name="$tone['footerIcon']"
                class="!size-3.5 shrink-0 {{ $tone['footerIconClass'] }}"
            />

            <p class="text-xs leading-6 text-base-content/55">
                {{ $presentation['footer'] }}
            </p>
        </div>
    @endif
</article>
