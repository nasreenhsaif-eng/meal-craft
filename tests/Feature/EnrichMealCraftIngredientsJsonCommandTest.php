<?php

use Symfony\Component\Console\Exception\CommandNotFoundException;

test('meal craft enrichment command is disabled (local-only mode)', function (): void {
    expect(fn () => $this->artisan('meal-craft:enrich-b-vitamins-export'))->toThrow(CommandNotFoundException::class);
});
