<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Models\TelegramConnection;
use App\Models\TelegramLinkChallenge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TelegramConnectionFoundationTest extends TestCase
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

    public function test_connection_routes_require_authentication(): void
    {
        $this->post(
            route('panel.integrations.telegram.connect'),
        )->assertRedirect(route('login'));

        $this->delete(
            route('panel.integrations.telegram.disconnect'),
        )->assertRedirect(route('login'));
    }

    public function test_connect_is_not_available_as_a_get_mutation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('panel.integrations.telegram.connect'))
            ->assertMethodNotAllowed();

        self::assertDatabaseCount('telegram_link_challenges', 0);
    }

    public function test_connect_creates_only_a_hashed_short_lived_challenge(): void
    {
        $user = User::factory()->create();

        $token = $this->startLink($user);
        $challenge = TelegramLinkChallenge::query()->sole();

        self::assertSame($user->getKey(), $challenge->user_id);
        self::assertSame(hash('sha256', $token), $challenge->token_hash);
        self::assertNotSame($token, $challenge->token_hash);
        self::assertNull($challenge->consumed_at);
        self::assertGreaterThan(
            now()->timestamp,
            $challenge->expires_at->timestamp,
        );
        self::assertLessThanOrEqual(
            now()->addMinutes(10)->timestamp,
            $challenge->expires_at->timestamp,
        );
    }

    public function test_valid_private_start_links_telegram_and_consumes_challenge(): void
    {
        $user = User::factory()->create();
        $token = $this->startLink($user);

        $this->telegramWebhook(
            $this->startUpdate(
                token: $token,
                telegramId: 123456789,
                username: 'coreflare_user',
                firstName: 'Mohammad',
            ),
        )->assertOk()->assertJson(['ok' => true]);

        $connection = TelegramConnection::query()->sole();
        self::assertSame($user->getKey(), $connection->user_id);
        self::assertSame('123456789', $connection->chat_id);
        self::assertSame('123456789', $connection->telegram_user_id);
        self::assertSame('coreflare_user', $connection->username);
        self::assertSame('Mohammad', $connection->first_name);
        self::assertNotNull($connection->connected_at);
        self::assertNotNull(
            TelegramLinkChallenge::query()->sole()->consumed_at,
        );
    }

    public function test_consumed_challenge_cannot_be_replayed(): void
    {
        $user = User::factory()->create();
        $token = $this->startLink($user);

        $this->telegramWebhook(
            $this->startUpdate(
                token: $token,
                telegramId: 123456789,
                username: 'initial_user',
                firstName: 'Initial',
            ),
        )->assertOk();

        $this->telegramWebhook(
            $this->startUpdate(
                token: $token,
                telegramId: 123456789,
                username: 'replayed_user',
                firstName: 'Replayed',
            ),
        )->assertOk();

        $connection = TelegramConnection::query()->sole();
        self::assertSame('initial_user', $connection->username);
        self::assertSame('Initial', $connection->first_name);
    }

    public function test_expired_challenge_never_links_account(): void
    {
        $user = User::factory()->create();
        $token = $this->startLink($user);

        $this->travel(11)->minutes();

        $this->telegramWebhook(
            $this->startUpdate(
                token: $token,
                telegramId: 123456789,
            ),
        )->assertOk();

        self::assertDatabaseCount('telegram_connections', 0);
        self::assertNull(
            TelegramLinkChallenge::query()->sole()->consumed_at,
        );
    }

    public function test_webhook_requires_matching_secret_header(): void
    {
        $user = User::factory()->create();
        $token = $this->startLink($user);
        $update = $this->startUpdate(
            token: $token,
            telegramId: 123456789,
        );

        $this->postJson(
            route('integrations.telegram.webhook'),
            $update,
        )->assertForbidden();

        $this->postJson(
            route('integrations.telegram.webhook'),
            $update,
            [
                'X-Telegram-Bot-Api-Secret-Token' => 'wrong_secret_value',
            ],
        )->assertForbidden();

        self::assertDatabaseCount('telegram_connections', 0);
    }

    public function test_unrelated_and_non_private_updates_are_acknowledged_without_linking(): void
    {
        $user = User::factory()->create();
        $token = $this->startLink($user);

        $this->telegramWebhook([
            'update_id' => 100,
        ])->assertOk();

        $this->telegramWebhook([
            'update_id' => 101,
            'message' => [
                'text' => "/start {$token}",
                'chat' => [
                    'id' => -100123456789,
                    'type' => 'group',
                ],
                'from' => [
                    'id' => 123456789,
                    'first_name' => 'Group User',
                ],
            ],
        ])->assertOk();

        self::assertDatabaseCount('telegram_connections', 0);
    }

    public function test_one_telegram_identity_cannot_be_claimed_by_another_user(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $firstToken = $this->startLink($firstUser);
        $this->telegramWebhook(
            $this->startUpdate(
                token: $firstToken,
                telegramId: 123456789,
            ),
        )->assertOk();

        $secondToken = $this->startLink($secondUser);
        $this->telegramWebhook(
            $this->startUpdate(
                token: $secondToken,
                telegramId: 123456789,
            ),
        )->assertOk();

        self::assertDatabaseCount('telegram_connections', 1);
        self::assertDatabaseHas('telegram_connections', [
            'user_id' => $firstUser->getKey(),
            'chat_id' => '123456789',
        ]);
        self::assertDatabaseMissing('telegram_connections', [
            'user_id' => $secondUser->getKey(),
        ]);

        $secondChallenge = TelegramLinkChallenge::query()
            ->where('user_id', $secondUser->getKey())
            ->sole();
        self::assertNotNull($secondChallenge->consumed_at);
    }

    public function test_same_user_can_relink_to_another_unused_private_chat(): void
    {
        $user = User::factory()->create();

        $firstToken = $this->startLink($user);
        $this->telegramWebhook(
            $this->startUpdate(
                token: $firstToken,
                telegramId: 123456789,
            ),
        )->assertOk();

        $secondToken = $this->startLink($user);
        $this->telegramWebhook(
            $this->startUpdate(
                token: $secondToken,
                telegramId: 987654321,
                username: 'new_telegram_user',
            ),
        )->assertOk();

        self::assertDatabaseCount('telegram_connections', 1);
        self::assertDatabaseHas('telegram_connections', [
            'user_id' => $user->getKey(),
            'chat_id' => '987654321',
            'telegram_user_id' => '987654321',
            'username' => 'new_telegram_user',
        ]);
    }

    public function test_disconnect_removes_connection_and_pending_link_challenges(): void
    {
        $user = User::factory()->create();

        $token = $this->startLink($user);
        $this->telegramWebhook(
            $this->startUpdate(
                token: $token,
                telegramId: 123456789,
            ),
        )->assertOk();

        $this->startLink($user);

        $this->actingAs($user)
            ->delete(
                route('panel.integrations.telegram.disconnect'),
            )
            ->assertRedirect(route('panel.integrations.index'))
            ->assertSessionHas(
                'integration_status',
                'اتصال Telegram با موفقیت قطع شد.',
            );

        self::assertDatabaseCount('telegram_connections', 0);
        self::assertSame(
            0,
            TelegramLinkChallenge::query()
                ->where('user_id', $user->getKey())
                ->whereNull('consumed_at')
                ->count(),
        );
    }

    private function startLink(User $user): string
    {
        $response = $this->actingAs($user)
            ->post(
                route('panel.integrations.telegram.connect'),
            )
            ->assertRedirect();

        $location = $response->headers->get('Location');
        self::assertIsString($location);
        self::assertSame('https', parse_url($location, PHP_URL_SCHEME));
        self::assertSame('t.me', parse_url($location, PHP_URL_HOST));
        self::assertSame(
            '/CoreflareTestBot',
            parse_url($location, PHP_URL_PATH),
        );

        parse_str(
            (string) parse_url($location, PHP_URL_QUERY),
            $query,
        );

        $token = $query['start'] ?? null;
        self::assertIsString($token);
        self::assertMatchesRegularExpression(
            '/\A[A-Za-z0-9_-]{43}\z/D',
            $token,
        );

        return $token;
    }

    /**
     * @return array<string, mixed>
     */
    private function startUpdate(
        string $token,
        int $telegramId,
        ?string $username = null,
        string $firstName = 'Telegram User',
    ): array {
        $from = [
            'id' => $telegramId,
            'first_name' => $firstName,
        ];

        if ($username !== null) {
            $from['username'] = $username;
        }

        return [
            'update_id' => 1000,
            'message' => [
                'text' => "/start {$token}",
                'chat' => [
                    'id' => $telegramId,
                    'type' => 'private',
                ],
                'from' => $from,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function telegramWebhook(array $payload)
    {
        return $this->postJson(
            route('integrations.telegram.webhook'),
            $payload,
            [
                'X-Telegram-Bot-Api-Secret-Token'
                    => 'ci_telegram_webhook_secret_123',
            ],
        );
    }
}
