<div class="min-w-0">
    @if ($errorMessage !== null)
        <x-dashboard.widget-error
            :title="$errorTitle ?? 'دریافت اطلاعات ناموفق بود'"
            :message="$errorMessage"
            retry-action="reload"
        />
    @else
        <x-dashboard.server-overview
            :overview="$identity"
        />
    @endif
</div>
