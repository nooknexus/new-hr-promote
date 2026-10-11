<?php

use Symfony\Component\Finder\Finder;

// Livewire 3 runs only the code inside <script> tags of an @script block;
// anything else is silently dropped, so modal and toast handlers never run.
test('every @script block in a view wraps its code in a script tag', function () {
    $missing = [];
    foreach ((new Finder)->files()->in(dirname(__DIR__, 2).'/resources/views')->name('*.blade.php') as $file) {
        preg_match_all('/@script\b(.*?)@endscript/s', $file->getContents(), $blocks);
        foreach ($blocks[1] as $block) {
            if (! preg_match('/<script\b[^>]*>.*<\/script>/s', $block)) {
                $missing[] = $file->getRelativePathname();
            }
        }
    }

    expect($missing)->toBe([]);
});
