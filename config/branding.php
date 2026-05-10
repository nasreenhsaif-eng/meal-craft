<?php

$brandKitPaths = [
    resource_path('branding/brand-kit/brand-kit.json'),
    resource_path('Branding/brand-kit/brand-kit.json'),
];
$brandKit = [];

foreach ($brandKitPaths as $brandKitPath) {
    if (! is_file($brandKitPath)) {
        continue;
    }

    $decoded = json_decode((string) file_get_contents($brandKitPath), true);

    if (is_array($decoded)) {
        $brandKit = $decoded;

        break;
    }
}

return [
    'name' => $brandKit['name'] ?? config('app.name', 'Meal Craft'),
    'tagline' => $brandKit['tagline'] ?? 'ANTI-INFLAMMATORY SMART KITCHEN',
    'colors' => [
        'primary' => $brandKit['colors']['primary'] ?? '#D8A933',
        'secondary' => $brandKit['colors']['secondary'] ?? '#6E8C47',
        'accent' => $brandKit['colors']['accent'] ?? '#8F55A8',
        'danger' => $brandKit['colors']['danger'] ?? '#C44F5D',
        'info' => $brandKit['colors']['info'] ?? '#2F4C9B',
        'background' => $brandKit['colors']['background'] ?? '#FFFFFF',
        'surface' => $brandKit['colors']['surface'] ?? '#F8FAFC',
        'text' => $brandKit['colors']['text'] ?? '#0F172A',
    ],
    'fonts' => [
        'sans' => $brandKit['fonts']['sans'] ?? 'Montserrat',
        'heading' => $brandKit['fonts']['heading'] ?? 'Montserrat',
    ],
];
