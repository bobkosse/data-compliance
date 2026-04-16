<?php

namespace Tests\Feature;

it('scans the directory and finds models and outputs the result', function () {
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

it('should give a clear message if the path is not correct ', function () {
    $modelsDir = __DIR__ . '/../NonModels';

    $this->artisan('privacy:audit', [
        '--scan' => $modelsDir,
    ])
        ->expectsOutput('Scan directory not found: ' . $modelsDir)
        ->assertExitCode(1);
});
