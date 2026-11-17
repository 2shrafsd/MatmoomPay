<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Mail lines are rendered as Markdown, so a merchant or user name such as "[Verify](https://evil.example)"
 * would become a real link in an email sent from our domain. Names go through here first.
 */
final class MarkdownText
{
    public static function escape(?string $text): string
    {
        // HTML is already escaped by the mail template; only Markdown syntax needs neutralising here.
        return (string) preg_replace('/([\\\\`*_{}\[\]()#+\-.!|~])/', '\\\\$1', (string) $text);
    }
}
