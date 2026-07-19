<?php

declare(strict_types=1);

namespace App\Domain\Module\Registry;

use App\Domain\Module\Contracts\Module;
use App\Domain\Module\Modules\Docker\DockerModule;
use App\Domain\Module\Modules\Fail2Ban\Fail2BanModule;
use App\Domain\Module\Modules\Marzban\MarzbanModule;
use App\Domain\Module\Modules\Nginx\NginxModule;
use App\Domain\Module\Modules\Xray\XrayModule;

final readonly class ModuleRegistry
{
    /**
     * @return array<int, Module>
     */
    public function all(): array
    {
        return [
            app(DockerModule::class),
            app(NginxModule::class),
            app(MarzbanModule::class),
            app(XrayModule::class),
            app(Fail2BanModule::class),
        ];
    }
}
