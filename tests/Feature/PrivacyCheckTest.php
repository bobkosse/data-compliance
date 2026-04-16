<?php

namespace Tests\Feature;

it('scans the directory and finds models', function () {
    $modelsDir = __DIR__ . '/../MockModels';

    $this->artisan('privacy:audit', [
        '--scan' => $modelsDir,
    ])
        ->expectsTable(['Model', 'Has Privacy Trait', 'Privacy Fields'], [
            ['Tests\MockModels\ProtectedModel', 'Yes', 'email, phone'],
            ['Tests\MockModels\UnprotectedModel', 'No', '-'],
        ])
        ->assertExitCode(0);
});
