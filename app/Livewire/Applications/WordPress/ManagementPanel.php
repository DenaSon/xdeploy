<?php

declare(strict_types=1);

namespace App\Livewire\Applications\WordPress;

use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Domain\PublicEndpoint\Exceptions\InvalidPublicEndpointDomainException;
use App\Domain\PublicEndpoint\ValueObjects\PublicEndpointDomain;
use App\Models\PublicEndpoint;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class ManagementPanel extends Component
{
    #[Locked]
    public int $serverId;

    #[Locked]
    public ?string $publicUrl = null;

    public function mount(int $serverId): void
    {
        $server = $this->authenticatedUser()
            ->servers()
            ->whereKey($serverId)
            ->firstOrFail();

        $this->serverId = (int) $server->getKey();

        $endpoint = $server->publicEndpoints()
            ->where(
                'application_type',
                ApplicationType::WordPress->value,
            )
            ->whereNotNull('activated_at')
            ->first();

        if (! $endpoint instanceof PublicEndpoint) {
            return;
        }

        try {
            $domain = PublicEndpointDomain::from(
                $endpoint->domain,
            );

            $this->publicUrl = "https://{$domain->value}/";
        } catch (InvalidPublicEndpointDomainException) {
            // Invalid persisted state must never produce an unsafe public link.
        }
    }

    public function render(): View
    {
        return view(
            'livewire.applications.wordpress.management-panel',
        );
    }

    private function authenticatedUser(): User
    {
        $user = Auth::user();

        abort_unless(
            $user instanceof User,
            401,
        );

        return $user;
    }
}
