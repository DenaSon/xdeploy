<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Application\Marzban\Parsers;

use App\Domain\Application\Marzban\Exceptions\MarzbanSetupInspectionException;
use App\Domain\Application\Marzban\Setup\Enums\MarzbanSetupState;
use App\Infrastructure\Application\Marzban\Parsers\MarzbanAdminListParser;
use PHPUnit\Framework\TestCase;

final class MarzbanAdminListParserTest extends TestCase
{
    public function test_it_parses_admin_usernames_from_the_cli_table(): void
    {
        $output = <<<'OUTPUT'
┏━━━━━━━━━━┳━━━━━━━┳━━━━━━━━━━━━━━━┳━━━━━━━━━━━━━┳━━━━━━━━━┳━━━━━━━━━━━━━━━━━━━━━┳━━━━━━━━━━━━━┳━━━━━━━━━━━━━━━━━┓
┃ Username ┃ Usage ┃ Reseted usage ┃ Users Usage ┃ Is sudo ┃ Created at          ┃ Telegram ID ┃ Discord Webhook ┃
┡━━━━━━━━━━╇━━━━━━━╇━━━━━━━━━━━━━━━╇━━━━━━━━━━━━━╇━━━━━━━━━╇━━━━━━━━━━━━━━━━━━━━━╇━━━━━━━━━━━━━╇━━━━━━━━━━━━━━━━━┩
│ admin    │ 0 B   │ 0 B           │ 0 B         │ √       │ 10 August 2026      │ X           │ X               │
│ operator │ 0 B   │ 0 B           │ 0 B         │ √       │ 10 August 2026      │ X           │ X               │
└──────────┴───────┴───────────────┴─────────────┴─────────┴─────────────────────┴─────────────┴─────────────────┘
OUTPUT;

        $overview = (new MarzbanAdminListParser)
            ->parse(
                $output,
            );

        self::assertSame(
            MarzbanSetupState::Complete,
            $overview->state,
        );

        self::assertSame(
            [
                'admin',
                'operator',
            ],
            array_map(
                static fn ($admin): string => $admin->username,
                $overview->admins,
            ),
        );
    }

    public function test_empty_cli_table_is_pending(): void
    {
        $output = <<<'OUTPUT'
┏━━━━━━━━━━┳━━━━━━━┳━━━━━━━━━━━━━━━┳━━━━━━━━━━━━━┳━━━━━━━━━┳━━━━━━━━━━━━┳━━━━━━━━━━━━━┳━━━━━━━━━━━━━━━━━┓
┃ Username ┃ Usage ┃ Reseted usage ┃ Users Usage ┃ Is sudo ┃ Created at ┃ Telegram ID ┃ Discord Webhook ┃
┡━━━━━━━━━━╇━━━━━━━╇━━━━━━━━━━━━━━━╇━━━━━━━━━━━━━╇━━━━━━━━━╇━━━━━━━━━━━━╇━━━━━━━━━━━━━╇━━━━━━━━━━━━━━━━━┩
└──────────┴───────┴───────────────┴─────────────┴─────────┴────────────┴─────────────┴─────────────────┘
OUTPUT;

        $overview = (new MarzbanAdminListParser)
            ->parse(
                $output,
            );

        self::assertSame(
            MarzbanSetupState::Pending,
            $overview->state,
        );

        self::assertSame(
            [],
            $overview->admins,
        );
    }

    public function test_it_parses_real_wrapped_rich_output_from_marzban(): void
    {
        $output = <<<'OUTPUT'
┏━━━━━━━━━┳━━━━━━━┳━━━━━━━━━┳━━━━━━━━━┳━━━━━━━━━┳━━━━━━━━━┳━━━━━━━━━━┳━━━━━━━━━┓
┃         ┃       ┃ Reseted ┃ Users   ┃         ┃ Created ┃ Telegram ┃ Discord ┃
┃ Userna… ┃ Usage ┃ usage   ┃ Usage   ┃ Is sudo ┃ at      ┃ ID       ┃ Webhook ┃
┡━━━━━━━━━╇━━━━━━━╇━━━━━━━━━╇━━━━━━━━━╇━━━━━━━━━╇━━━━━━━━━╇━━━━━━━━━━╇━━━━━━━━━┩
│ admin   │ 0 B   │ 0 B     │ 0 B     │ ✔️       │ 10      │ ✖️        │ ✖️       │
│         │       │         │         │         │ August  │          │         │
│         │       │         │         │         │ 2026,   │          │         │
│         │       │         │         │         │ 12:02:… │          │         │
└─────────┴───────┴─────────┴─────────┴─────────┴─────────┴──────────┴─────────┘
OUTPUT;

        $overview = (new MarzbanAdminListParser)
            ->parse(
                $output,
            );

        self::assertSame(
            MarzbanSetupState::Complete,
            $overview->state,
        );

        self::assertCount(
            1,
            $overview->admins,
        );

        self::assertSame(
            'admin',
            $overview->admins[0]->username,
        );
    }

    public function test_unknown_cli_format_is_not_treated_as_an_empty_list(): void
    {
        $this->expectException(
            MarzbanSetupInspectionException::class,
        );

        (new MarzbanAdminListParser)
            ->parse(
                'Marzban CLI output changed.',
            );
    }

    public function test_ansi_sequences_do_not_break_table_parsing(): void
    {
        $output = "\033[32m│ Username │ Usage │\033[0m\n"
            ."\033[36m│ admin    │ 0 B   │\033[0m";

        $overview = (new MarzbanAdminListParser)
            ->parse(
                $output,
            );

        self::assertSame(
            MarzbanSetupState::Complete,
            $overview->state,
        );

        self::assertSame(
            'admin',
            $overview->admins[0]->username,
        );
    }
}
