<?php

use App\Application\Server\Actions\ConnectServerAction;
use App\Application\Server\Actions\GetServerOverviewAction;
use App\Domain\Server\Enums\AuthenticationType;
use App\Models\Server;
use App\Support\Formatters\ByteFormatter;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::panel')]
class extends Component {
    public string $title = 'داشبورد';

    public string $description = 'نمای کلی وضعیت سرور و ماژول‌های نصب‌شده';

    public array $overview = [];

    public function mount(
        ConnectServerAction     $connect,
        GetServerOverviewAction $overview,
    ): void
    {
        // TODO: Read the selected server from the authenticated user.

        $server = new Server([
            'host' => '127.0.0.1',
            'port' => 2222,
            'username' => 'root',
            'authentication_type' => AuthenticationType::Password,
            'credential' => 'xdeploy',
        ]);

        $connect->handle($server);

        $this->overview = $overview
            ->handle()
            ->toArray();
    }
};

?>

<div class="space-y-6">

    <x-panel.page-header
        :title="$title"
        :description="$description"
    />

    <section class="rounded-xl border bg-white p-6">

        <h2 class="mb-4 text-lg font-semibold">
            وضعیت سرور
        </h2>

        <dl class="grid gap-4 sm:grid-cols-2">

            <div>
                <dt class="text-sm text-gray-500">Hostname</dt>
                <dd>{{ $overview['hostname'] }}</dd>
            </div>

            <div>
                <dt class="text-sm text-gray-500">Operating System</dt>
                <dd>{{ $overview['operatingSystem'] }}</dd>
            </div>

            <div>
                <dt class="text-sm text-gray-500">Kernel</dt>
                <dd>{{ $overview['kernel'] }}</dd>
            </div>

            <div>
                <dt class="text-sm text-gray-500">User</dt>
                <dd>{{ $overview['user'] }}</dd>
            </div>

            <div>
                <dt class="text-sm text-gray-500">Uptime</dt>
                <dd>{{ $overview['uptime'] }}</dd>
            </div>

            <div>
                <dt class="text-sm text-gray-500">Private IP</dt>
                <dd>{{ $overview['privateIp'] }}</dd>
            </div>

            <div>
                <dt class="text-sm text-gray-500">Public IP</dt>
                <dd>-</dd>
            </div>

        </dl>

    </section>

    <section class="rounded-xl border bg-white p-6">

        <h2 class="mb-4 text-lg font-semibold">
            سرویس‌ها
        </h2>

        <div class="overflow-hidden rounded-lg border">

            <table class="min-w-full divide-y divide-gray-200">

                <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-right text-sm font-medium">
                        سرویس
                    </th>

                    <th class="px-4 py-3 text-right text-sm font-medium">
                        وضعیت
                    </th>
                </tr>
                </thead>

                <tbody class="divide-y divide-gray-100 bg-white">

                @foreach($overview['services'] as $service)

                    <tr>

                        <td class="px-4 py-3">
                            {{ $service['name'] }}
                        </td>

                        <td class="px-4 py-3">
                            {{ $service['status'] }}
                        </td>

                    </tr>

                @endforeach

                </tbody>

            </table>

        </div>

    </section>

    <section class="rounded-xl border bg-white p-6">

        <h2 class="mb-4 text-lg font-semibold">
            اطلاعات سیستم
        </h2>

        <div class="space-y-4">

            <div>
                <h3 class="font-medium">CPU</h3>

                <pre>
{{ print_r($overview['cpu'], true) }}
</pre>
            </div>

            <div>
                <h3 class="font-medium">Memory</h3>

                <pre class="mt-2 overflow-auto rounded bg-gray-100 p-4 text-xs">
Total     : {{ ByteFormatter::format($overview['memory']['total']) }}
Used      : {{ ByteFormatter::format($overview['memory']['used']) }}
Free      : {{ ByteFormatter::format($overview['memory']['free']) }}
Available : {{ ByteFormatter::format($overview['memory']['available']) }}
Usage     : {{ $overview['memory']['usage_percent'] }}%
    </pre>
            </div>

            <div>
                <h3 class="font-medium">Disk</h3>

                <pre>
{{ print_r($overview['disk'], true) }}
</pre>
            </div>

            <div>
                <h3 class="font-medium">Load Average</h3>

                <pre>
{{ print_r($overview['load_average'], true) }}
</pre>
            </div>

        </div>

    </section>

</div>
