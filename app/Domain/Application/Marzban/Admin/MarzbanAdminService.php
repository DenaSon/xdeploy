<?php

declare(strict_types=1);

namespace App\Domain\Application\Marzban\Admin;

use App\Domain\Application\Marzban\Exceptions\MarzbanAdminAlreadyConfiguredException;
use App\Domain\Application\Marzban\Exceptions\MarzbanAdminProvisioningException;
use App\Domain\Application\Marzban\Exceptions\MarzbanSetupInspectionException;
use App\Domain\Application\Marzban\Setup\Enums\MarzbanSetupState;

final readonly class MarzbanAdminService
{
    public function __construct(
        private MarzbanAdminGateway $gateway,
    ) {}

    public function create(
        string $username,
        string $password,
    ): void {
        $currentState = $this->inspectSetup();

        if ($currentState === MarzbanSetupState::Complete) {
            throw MarzbanAdminAlreadyConfiguredException::make();
        }

        if ($currentState !== MarzbanSetupState::Pending) {
            throw MarzbanAdminProvisioningException::inspectionFailed();
        }

        try {
            $this->gateway->create(
                username: $username,
                password: $password,
            );
        } catch (MarzbanAdminProvisioningException $exception) {
            /*
             * The remote command may have succeeded even when the SSH
             * response was interrupted. Inspect before allowing a retry.
             */
            if ($this->isVerified($username)) {
                return;
            }

            throw $exception;
        }

        if (! $this->isVerified($username)) {
            throw MarzbanAdminProvisioningException::verificationFailed();
        }
    }

    private function inspectSetup(): MarzbanSetupState
    {
        try {
            return $this->gateway->inspect();
        } catch (MarzbanSetupInspectionException) {
            throw MarzbanAdminProvisioningException::inspectionFailed();
        }
    }

    private function isVerified(string $username): bool
    {
        try {
            return $this->gateway->inspect($username)
                === MarzbanSetupState::Complete;
        } catch (MarzbanSetupInspectionException) {
            return false;
        }
    }
}
