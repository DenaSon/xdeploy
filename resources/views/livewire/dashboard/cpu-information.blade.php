<div class="min-w-0">
    @if ($errorMessage !== null)
        <x-dashboard.widget-error
            :title="$errorTitle ?? 'دریافت اطلاعات CPU ناموفق بود'"
            :message="$errorMessage"
            retry-action="reload"
        />
    @else
        <x-dashboard.cpu-info
            :cpu="$cpu"
        />
    @endif
</div>
