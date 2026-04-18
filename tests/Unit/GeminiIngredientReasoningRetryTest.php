<?php

test('gemini ingredient reasoning service is removed (local-only mode)', function () {
    expect(class_exists('App\\Services\\GeminiIngredientReasoningService'))->toBeFalse();
});
