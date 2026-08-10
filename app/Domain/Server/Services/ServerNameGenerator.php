<?php

declare(strict_types=1);

namespace App\Domain\Server\Services;

final class ServerNameGenerator
{
    /**
     * @var list<string>
     */
    private const array ADJECTIVES = [
        'calm',
        'swift',
        'bright',
        'silent',
        'steady',
        'silver',
        'rapid',
        'noble',
        'crisp',
        'solid',

        'bold',
        'clear',
        'cool',
        'deep',
        'fast',
        'fresh',
        'grand',
        'green',
        'keen',
        'light',

        'lunar',
        'modern',
        'prime',
        'pure',
        'quiet',
        'sharp',
        'smooth',
        'solar',
        'stable',
        'strong',

        'vivid',
        'wise',
        'wild',
        'blue',
        'golden',
        'hidden',
        'polar',
        'stellar',
        'atomic',
        'cosmic',

        'dynamic',
        'elastic',
        'nimble',
        'robust',
        'secure',
        'trusted',
        'vital',
        'agile',
        'frozen',
        'gentle',

        'loyal',
        'lucid',
        'mighty',
        'neat',
        'open',
        'proud',
        'ready',
        'smart',
        'brisk',
        'clean',
    ];

    /**
     * @var list<string>
     */
    private const array NOUNS = [
        'falcon',
        'cedar',
        'orbit',
        'atlas',
        'comet',
        'pine',
        'nova',
        'harbor',
        'summit',
        'river',

        'eagle',
        'hawk',
        'wolf',
        'fox',
        'raven',
        'lynx',
        'tiger',
        'bison',
        'orca',
        'panda',

        'aster',
        'aurora',
        'cosmos',
        'galaxy',
        'meteor',
        'nebula',
        'pulsar',
        'quasar',
        'saturn',
        'zenith',

        'cloud',
        'cluster',
        'core',
        'node',
        'relay',
        'signal',
        'stack',
        'vertex',
        'matrix',
        'kernel',

        'bridge',
        'fort',
        'gateway',
        'haven',
        'island',
        'peak',
        'ridge',
        'rock',
        'shore',
        'valley',

        'ember',
        'frost',
        'glacier',
        'storm',
        'thunder',
        'wave',
        'wind',
        'ocean',
        'forest',
        'stone',
    ];

    public function generate(): string
    {
        $adjective = self::ADJECTIVES[
        array_rand(self::ADJECTIVES)
        ];

        $noun = self::NOUNS[
        array_rand(self::NOUNS)
        ];

        $number = random_int(
            1000,
            9999,
        );

        return sprintf(
            '%s-%s-%d',
            $adjective,
            $noun,
            $number,
        );
    }
}
