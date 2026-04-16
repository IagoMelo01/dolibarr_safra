<?php
declare(strict_types=1);

$tests = array(
    __DIR__ . '/ActivityDomainTest.php',
    __DIR__ . '/ActivityStockServiceTest.php',
    __DIR__ . '/MigrationAndSchemaTest.php',
    __DIR__ . '/ApiSfactivitiesTest.php',
);

$failed = 0;

foreach ($tests as $testFile) {
    $name = basename($testFile);
    try {
        $result = require $testFile;
        if ($result === false) {
            $failed++;
            echo '[FAIL] ' . $name . " returned false\n";
            continue;
        }
        echo '[PASS] ' . $name . "\n";
    } catch (Throwable $e) {
        $failed++;
        echo '[FAIL] ' . $name . ': ' . $e->getMessage() . "\n";
    }
}

if ($failed > 0) {
    echo "Tests failed: {$failed}\n";
    exit(1);
}

echo "All tests passed.\n";
exit(0);
