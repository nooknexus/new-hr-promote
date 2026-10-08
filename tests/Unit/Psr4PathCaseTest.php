<?php

/*
 * macOS file systems ignore letter case, so a class whose directory casing
 * differs from its namespace still loads locally but fails on Linux
 * production (e.g. ThaID/ vs namespace Thaid broke ThaiD login).
 */
test('every application class lives at the exact-case PSR-4 path of its namespace', function () {
    $mismatches = [];
    $appPath = app_path();

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appPath, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $source = file_get_contents($file->getPathname());
        if (! preg_match('/^namespace\s+([^;]+);/m', $source, $namespace)
            || ! preg_match('/^(?:final\s+|abstract\s+|readonly\s+)*(?:class|interface|trait|enum)\s+(\w+)/m', $source, $class)) {
            continue;
        }

        $relative = str_replace('\\', '/', preg_replace('/^App\\\\?/', '', $namespace[1]));
        $expected = $appPath.'/'.($relative === '' ? '' : $relative.'/').$class[1].'.php';

        if ($expected !== $file->getPathname()) {
            $mismatches[] = substr($file->getPathname(), strlen(base_path()) + 1);
        }
    }

    expect($mismatches)->toBe([]);
});
