<?php

use Illuminate\Support\Facades\Blade;

test('viteReactRefresh blade directive is registered', function () {
    expect(app('blade.compiler')->getCustomDirectives())->toHaveKey('viteReactRefresh');
});

test('viteReactRefresh renders react preamble when hot file is present', function () {
    $hot = public_path('hot');

    file_put_contents($hot, 'http://127.0.0.1:5173');

    try {
        $html = Blade::render('@viteReactRefresh');
        expect($html)
            ->toContain('__vite_plugin_react_preamble_installed__')
            ->and($html)->toContain('http://127.0.0.1:5173/@react-refresh');
    } finally {
        if (file_exists($hot)) {
            unlink($hot);
        }
    }
});

test('viteReactRefresh renders nothing when hot file is absent', function () {
    $hot = public_path('hot');
    $backup = public_path('hot.vite-react-refresh-test.bak');
    $restored = false;

    if (file_exists($hot)) {
        rename($hot, $backup);
        $restored = true;
    }

    try {
        expect(trim(Blade::render('@viteReactRefresh')))->toBe('');
    } finally {
        if ($restored && file_exists($backup)) {
            rename($backup, $hot);
        }
    }
});
