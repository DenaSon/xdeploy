<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Livewire\Integrations\Telegram\Overview;
use App\Models\NotificationPreference;
use App\Models\TelegramConnection;
use App\Models\TelegramLinkChallenge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class TelegramPreferencesUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.enabled' => true,
            'services.telegram.bot_token' => '123456:ci-telegram-bot-token',
            'services.telegram.bot_username' => 'CoreflareTestBot',
            'services.telegram.webhook_secret' => 'ci_telegram_webhook_secret_123',
            'services.telegram.link_ttl_seconds' => 600,
            'services.telegram.api_base_url' => 'https://api.telegram.test',
            'services.telegram.connect_timeout' => 5,
            'services.telegram.timeout' => 10,
        ]);
    }

    public function test_disconnected_user_sees_simple_connection_state(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Overview::class)
            ->assertSee('اتصال Telegram')
            ->assertSee('متصل نیست')
            ->assertSee('پس از اتصال Telegram، انتخاب نوع اعلان‌ها از همین بخش فعال می‌شود.');
    }

    public function test_pending_link_state_is_visible_without_exposing_token_hash(): void
    {
        $user = User::factory()->create();

        TelegramLinkChallenge::query()->create([
            'user_id' => $user->getKey(),
            'token_hash' => str_repeat('a', 64),
            'expires_at' => now()->addMinutes(10),
            'consumed_at' => null,
        ]);

        Livewire::actingAs($user)
            ->test(Overview::class)
            ->assertSee('در انتظار تأیید')
            ->assertSee('پس از زدن Start، این صفحه خودکار به‌روزرسانی می‌شود.')
            ->assertDontSee(str_repeat('a', 64));
    }

    public function test_connected_user_can_toggle_each_telegram_topic(): void
    {
        $user = User::factory()->create();
        $this->connectTelegram($user);

        Livewire::actingAs($user)
            ->test(Overview::class)
            ->assertSee('@coreflare_test')
            ->assertSee('سرورها و سرویس‌ها')
            ->assertSee('پشتیبانی')
            ->assertSee('حساب کاربری')
            ->call('togglePreference', 'servers')
            ->assertSet('statusMessage', 'تنظیم اعلان‌های Telegram ذخیره شد.');

        self::assertDatabaseHas('notification_preferences', [
            'user_id' => $user->getKey(),
            'channel' => 'telegram',
            'topic' => 'servers',
            'enabled' => false,
        ]);

        Livewire::actingAs($user)
            ->test(Overview::class)
            ->call('togglePreference', 'servers');

        self::assertTrue(
            (bool) NotificationPreference::query()
                ->where('user_id', $user->getKey())
                ->where('channel', 'telegram')
                ->where('topic', 'servers')
                ->value('enabled'),
        );
    }

    public function test_disconnect_removes_connection_and_stops_preference_controls(): void
    {
        $user = User::factory()->create();
        $this->connectTelegram($user);

        TelegramLinkChallenge::query()->create([
            'user_id' => $user->getKey(),
            'token_hash' => str_repeat('b', 64),
            'expires_at' => now()->addMinutes(10),
            'consumed_at' => null,
        ]);

        Livewire::actingAs($user)
            ->test(Overview::class)
            ->call('disconnect')
            ->assertSet('statusMessage', 'اتصال Telegram با موفقیت قطع شد.')
            ->assertSee('متصل نیست');

        self::assertDatabaseMissing('telegram_connections', [
            'user_id' => $user->getKey(),
        ]);
        self::assertDatabaseMissing('telegram_link_challenges', [
            'user_id' => $user->getKey(),
        ]);
    }

    private function connectTelegram(User $user): TelegramConnection
    {
        return TelegramConnection::query()->create([
            'user_id' => $user->getKey(),
            'chat_id' => '123456789',
            'telegram_user_id' => '123456789',
            'username' => 'coreflare_test',
            'first_name' => 'Test',
            'connected_at' => now(),
        ]);
    }
}
