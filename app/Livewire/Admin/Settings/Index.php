<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Settings;

use App\Application\Settings\GetSystemSettings;
use App\Application\Settings\SystemSettingsSnapshot;
use App\Application\Settings\UpdateBrandingSettings;
use App\Application\Settings\UpdateGeneralSettings;
use App\Application\Settings\UpdateSeoSettings;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('تنظیمات سامانه')]
final class Index extends Component
{
    public string $siteName = '';

    public string $tagline = '';

    public string $seoDefaultTitle = '';

    public string $seoDefaultDescription = '';

    public string $seoDefaultOgImage = '';

    public bool $seoIndexSite = true;

    public ?string $savedSection = null;

    public function mount(GetSystemSettings $settings): void
    {
        $this->hydrateFromSnapshot($settings->handle());
    }

    public function updated(string $property): void
    {
        if (in_array($property, [
            'siteName',
            'tagline',
            'seoDefaultTitle',
            'seoDefaultDescription',
            'seoDefaultOgImage',
            'seoIndexSite',
        ], true)) {
            $this->savedSection = null;
        }
    }

    public function saveGeneral(UpdateGeneralSettings $update): void
    {
        $validated = $this->validate([
            'siteName' => ['required', 'string', 'max:80'],
        ]);

        $this->siteName = trim($validated['siteName']);

        $update->handle($this->actor(), [
            'site_name' => $this->siteName,
        ]);

        $this->savedSection = 'general';
    }

    public function saveBranding(UpdateBrandingSettings $update): void
    {
        $validated = $this->validate([
            'tagline' => ['required', 'string', 'max:120'],
        ]);

        $this->tagline = trim($validated['tagline']);

        $update->handle($this->actor(), [
            'tagline' => $this->tagline,
        ]);

        $this->savedSection = 'branding';
    }

    public function saveSeo(UpdateSeoSettings $update): void
    {
        $validated = $this->validate([
            'seoDefaultTitle' => ['required', 'string', 'max:70'],
            'seoDefaultDescription' => ['required', 'string', 'max:160'],
            'seoDefaultOgImage' => ['nullable', 'string', 'max:2048'],
            'seoIndexSite' => ['required', 'boolean'],
        ]);

        $this->seoDefaultTitle = trim($validated['seoDefaultTitle']);
        $this->seoDefaultDescription = trim($validated['seoDefaultDescription']);
        $this->seoDefaultOgImage = trim($validated['seoDefaultOgImage'] ?? '');

        $update->handle($this->actor(), [
            'default_title' => $this->seoDefaultTitle,
            'default_description' => $this->seoDefaultDescription,
            'default_og_image' => $this->seoDefaultOgImage,
            'index_site' => $this->seoIndexSite,
        ]);

        $this->savedSection = 'seo';
    }

    public function render(): View
    {
        return view('livewire.admin.settings.index');
    }

    private function hydrateFromSnapshot(SystemSettingsSnapshot $snapshot): void
    {
        $this->siteName = $snapshot->siteName;
        $this->tagline = $snapshot->tagline;
        $this->seoDefaultTitle = $snapshot->seoDefaultTitle;
        $this->seoDefaultDescription = $snapshot->seoDefaultDescription;
        $this->seoDefaultOgImage = $snapshot->seoDefaultOgImage ?? '';
        $this->seoIndexSite = $snapshot->seoIndexSite;
    }

    /**
     * @throws AuthenticationException
     */
    private function actor(): User
    {
        $actor = Auth::user();

        if (! $actor instanceof User) {
            throw new AuthenticationException;
        }

        return $actor;
    }
}
