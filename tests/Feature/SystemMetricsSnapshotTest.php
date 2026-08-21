<?php

use App\Support\SystemMetricsSnapshot;

test('metrics snapshot keeps only a bounded tail of the application log', function () {
    $logPath = tempnam(sys_get_temp_dir(), 'metrics-log-');
    expect($logPath)->toBeString();

    $oldLines = array_fill(0, 3000, str_repeat('discarded log content ', 8));
    $recentLines = array_map(
        fn (int $line): string => "recent line {$line} ".str_repeat('x', 500),
        range(1, 20),
    );

    file_put_contents($logPath, implode(PHP_EOL, [...$oldLines, ...$recentLines]));

    try {
        $lines = (new SystemMetricsSnapshot)->recentLogLines($logPath);
    } finally {
        unlink($logPath);
    }

    expect($lines)
        ->toHaveCount(15)
        ->and($lines[0])->toStartWith('recent line 6 ')
        ->and($lines[14])->toStartWith('recent line 20 ')
        ->and(collect($lines)->every(fn (string $line): bool => mb_strlen($line) <= 323))
        ->toBeTrue();
});
