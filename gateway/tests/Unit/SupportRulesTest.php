<?php

use App\Support\Digits;
use App\Support\MarkdownText;
use App\Support\PublicId;

test('public ids are random, prefixed and the requested length', function () {
    $first = PublicId::make('pay_', 24);
    $second = PublicId::make('pay_', 24);

    expect($first)->toStartWith('pay_')->not->toBe($second)
        ->and(strlen($first))->toBe(28)
        ->and(PublicId::numeric(6))->toMatch('/^\d+$/');
});

test('arabic and persian digits become latin digits', function () {
    expect(Digits::toLatin('٤٢٤٢ ۴۲۴۲ ١٢٬٥٠٠'))->toBe('4242 4242 12,500');
});

test('markdown syntax in names is neutralised', function () {
    expect(MarkdownText::escape('[Pay](https://evil.example)'))->toBe('\[Pay\]\(https://evil\.example\)');
});
