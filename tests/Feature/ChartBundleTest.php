<?php

use Symfony\Component\Process\Process;

test('the dashboard chart module registers all chart controllers it renders', function () {
    $script = <<<'JS'
import Chart from './resources/js/charts.js';
for (const type of ['line', 'pie', 'bar']) {
    Chart.registry.getController(type);
}
console.log('dashboard-charts-ready');
JS;

    $process = new Process(['node', '--input-type=module', '--eval', $script], base_path());
    $process->run();

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('dashboard-charts-ready');
});
