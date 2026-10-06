<?php

use App\Services\TaxCertificatePdfService;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

/** @return array<int, array{x1: float, y1: float, x2: float, y2: float, dashed: bool}> */
function taxCertificatePdfLines(string $pdfContent, string $svgPath): array
{
    $pdfPath = tempnam(sys_get_temp_dir(), 'tax-vector-');
    file_put_contents($pdfPath, $pdfContent);

    try {
        (new Process(['pdftocairo', '-svg', '-f', '1', '-l', '1', $pdfPath, $svgPath]))->mustRun();
    } finally {
        unlink($pdfPath);
    }

    $svg = new DOMDocument;
    $svg->load($svgPath);
    $xpath = new DOMXPath($svg);
    $lines = [];

    foreach ($xpath->query('//*[local-name()="path" and @transform]') as $path) {
        $number = '(-?\d+(?:\.\d+)?)';
        if (! preg_match('/^M\s+'.$number.'\s+'.$number.'\s+L\s+'.$number.'\s+'.$number.'\s*$/', $path->getAttribute('d'), $points)) {
            continue;
        }
        if (! preg_match('/matrix\(\s*'.$number.'[,\s]+'.$number.'[,\s]+'.$number.'[,\s]+'.$number.'[,\s]+'.$number.'[,\s]+'.$number.'\s*\)/', $path->getAttribute('transform'), $matrix)) {
            continue;
        }

        [$a, $b, $c, $d, $e, $f] = array_map('floatval', array_slice($matrix, 1));
        [$x1, $y1, $x2, $y2] = array_map('floatval', array_slice($points, 1));
        $lines[] = [
            'x1' => $a * $x1 + $c * $y1 + $e,
            'y1' => $b * $x1 + $d * $y1 + $f,
            'x2' => $a * $x2 + $c * $y2 + $e,
            'y2' => $b * $x2 + $d * $y2 + $f,
            'dashed' => $path->hasAttribute('stroke-dasharray'),
        ];
    }

    return $lines;
}

// Production break caught: the PDF service keeps hard-coded parties and checkmarks instead of the supplied certificate payload.
test('the PDF renders supplied imported document values and selected form boxes', function () {
    $result = TaxCertificatePdfService::generatePdf([
        'taxYear' => 2567,
        'certificateNo' => 'TAX-2567-01',
        'totalIncome' => 987654.32,
        'taxWithheld' => 12345.67,
        'taxText' => 'TAX-TEXT-12345',
        'payerName' => 'PAYER-A',
        'payerTaxId' => 'PAYER-TAX-ID',
        'payerAddress' => 'PAYER-ADDR',
        'recipientName' => 'RECIPIENT-A',
        'recipientCid' => 'RECIPIENT-CID',
        'recipientTaxId' => 'RECIPIENT-TAX-ID',
        'recipientAddress' => 'RECIPIENT-ADDR',
        'incomeTypeId' => '3',
        'incomeTypeName' => 'FORM-3',
        'fundType' => '2',
        'fundName' => 'FUND-2',
        'fundAmount' => 4567.89,
        'paymentTypeId' => '3',
        'paymentTypeName' => 'PAYMENT-3',
        'formattedIssueDate' => '29 มกราคม 2568',
    ]);
    $pdfPath = tempnam(sys_get_temp_dir(), 'imported-tax-pdf-');
    $svgPath = tempnam(sys_get_temp_dir(), 'imported-tax-pdf-');
    file_put_contents($pdfPath, $result['content']);

    try {
        $text = new Process(['pdftotext', '-layout', $pdfPath, '-']);
        $text->mustRun();
        $lines = taxCertificatePdfLines($result['content'], $svgPath);
        $hasCrossAt = static function (float $x, float $y) use ($lines): bool {
            $expectedX = ($x + 4) / 1225 * 595.275590551;
            $expectedY = ($y + 4) / 1566 * 841.88976378;

            return collect($lines)->contains(static fn (array $line): bool =>
                abs(min($line['x1'], $line['x2']) - $expectedX) < 1
                && abs(min($line['y1'], $line['y2']) - $expectedY) < 1
                && abs($line['x1'] - $line['x2']) > 2
                && abs($line['y1'] - $line['y2']) > 2
            );
        };

        expect($text->getOutput())
            ->toContain('PAYER-A')
            ->toContain('PAYER-TAX-ID')
            ->toContain('PAYER-ADDR')
            ->toContain('RECIPIENT-A')
            ->toContain('RECIPIENT-CID')
            ->toContain('RECIPIENT-TAX-ID')
            ->toContain('RECIPIENT-ADDR')
            ->toContain('FORM-3')
            ->toContain('FUND-2')
            ->toContain('PAYMENT-3')
            ->toContain('TAX-TEXT-12345')
            ->toMatch('/987,654\s+32/')
            ->toMatch('/12,345\s+67/');

        expect($hasCrossAt(846, 576))->toBeTrue()
            ->and($hasCrossAt(792, 1125))->toBeTrue()
            ->and($hasCrossAt(622, 1197))->toBeTrue();
    } finally {
        unlink($pdfPath);
        unlink($svgPath);
    }
});

test('dotted volume and document lines meet the bottoms of their labels', function () {
    $result = TaxCertificatePdfService::generatePdf(['certificateNo' => '151302']);
    $svgPath = tempnam(sys_get_temp_dir(), 'tax-dotted-');
    $pdfPath = tempnam(sys_get_temp_dir(), 'tax-dotted-');
    $bboxPath = $pdfPath.'.html';
    file_put_contents($pdfPath, $result['content']);

    try {
        $lines = taxCertificatePdfLines($result['content'], $svgPath);
        (new Process(['pdftotext', '-bbox', $pdfPath, $bboxPath]))->mustRun();
        $bbox = new DOMDocument;
        $bbox->load($bboxPath, LIBXML_NONET);
        $xpath = new DOMXPath($bbox);
        $labelBottoms = [];
        foreach ($xpath->query('//*[local-name()="word"]') as $word) {
            $x = (float) $word->getAttribute('xMin');
            $y = (float) $word->getAttribute('yMax');
            if ($x > 480 && $x < 510 && $y > 60 && $y < 105) {
                $labelBottoms[] = $y;
            }
        }
        sort($labelBottoms);

        $dottedLineYs = [];
        foreach ($lines as $line) {
            if ($line['dashed'] && abs($line['x1'] - 507.2) < 1 && abs($line['x2'] - 554.8) < 1 && $line['y1'] < 110) {
                $dottedLineYs[] = $line['y1'];
            }
        }
        sort($dottedLineYs);

        expect($labelBottoms)->toHaveCount(2)
            ->and($dottedLineYs)->toHaveCount(2)
            ->and(abs($dottedLineYs[0] - $labelBottoms[0]))->toBeLessThan(1.0)
            ->and(abs($dottedLineYs[1] - $labelBottoms[1]))->toBeLessThan(1.0);
    } finally {
        unlink($pdfPath);
        unlink($bboxPath);
        unlink($svgPath);
    }
});

test('the PDF total row keeps the marked income and tax column dividers', function () {
    $result = TaxCertificatePdfService::generatePdf([
        'totalIncome' => 315500,
        'taxWithheld' => 0,
    ]);
    $svgPath = tempnam(sys_get_temp_dir(), 'tax-total-');

    try {
        $lines = taxCertificatePdfLines($result['content'], $svgPath);
        $totalDividers = [];
        foreach ($lines as $line) {
            if ($line['dashed'] || abs($line['x1'] - $line['x2']) > 0.5) {
                continue;
            }
            if (abs(min($line['y1'], $line['y2']) - 527.8) < 1 && abs(max($line['y1'], $line['y2']) - 547.2) < 1) {
                $totalDividers[] = $line['x1'];
            }
        }

        foreach ([393.6, 454.8, 476.7, 533.6] as $expectedX) {
            expect(collect($totalDividers)->contains(fn (float $x): bool => abs($x - $expectedX) < 1))->toBeTrue();
        }
    } finally {
        unlink($svgPath);
    }
});

test('it creates a one-page tax certificate PDF with a stable document filename', function () {
    $result = TaxCertificatePdfService::generatePdf([
        'taxYear' => 2567,
        'certificateNo' => '151302',
        'totalIncome' => 315500,
        'taxWithheld' => 0,
        'governmentPensionFund' => 18930,
        'providentFund' => 0,
        'formattedIssueDate' => '29 มกราคม 2568',
        'employeeInfo' => [
            'name' => 'ภาณุพงษ์ ดงเย็น',
            'pid' => '1571200051606',
            'department' => 'สำนักงานสาธารณสุขจังหวัดพิษณุโลก',
        ],
    ]);

    $path = tempnam(sys_get_temp_dir(), 'tax-certificate-');
    file_put_contents($path, $result['content']);

    try {
        $pdfInfo = new Process(['pdfinfo', $path]);
        $pdfInfo->run();

        expect($result['filename'])->toBe('tax_certificate_2567_151302.pdf')
            ->and($result['content'])->toStartWith('%PDF')
            ->and($pdfInfo->getExitCode())->toBe(0)
            ->and($pdfInfo->getOutput())->toMatch('/^Pages:\s+1$/m');

        $text = new Process(['pdftotext', '-layout', $path, '-']);
        $text->run();

        expect($text->getExitCode())->toBe(0)
            ->and($text->getOutput())->not->toContain('\\n')
            ->and($text->getOutput())->toMatch('/เลขที่\s+151302/')
            ->and($text->getOutput())->toMatch('/315,500\s+00/')
            ->and($text->getOutput())->toContain('1-5712-00051-60-6');

        $images = new Process(['pdfimages', '-list', $path]);
        $images->run();

        expect($images->getExitCode())->toBe(0)
            ->and($images->getOutput())->not->toMatch('/^\s*\d+\s+\d+\s+image\s+/m');
    } finally {
        unlink($path);
    }
});

test('the tax certificate signature keeps its original image proportions', function () {
    $result = TaxCertificatePdfService::generatePdf([
        'taxYear' => 2567,
        'certificateNo' => '151302',
        'signatureImage' => file_get_contents(public_path('img/sign.png')),
    ]);

    $path = tempnam(sys_get_temp_dir(), 'tax-signature-ratio-');
    file_put_contents($path, $result['content']);

    try {
        $images = new Process(['pdfimages', '-list', $path]);
        $images->mustRun();
        preg_match('/^\s*1\s+\d+\s+image\s+150\s+132\s+\S+\s+\d+\s+\d+\s+\S+\s+\S+\s+\d+\s+\d+\s+(\d+)\s+(\d+)/m', $images->getOutput(), $match);

        expect($match)->not->toBeEmpty()
            ->and(abs((int) $match[1] - (int) $match[2]))->toBeLessThanOrEqual(2);
    } finally {
        unlink($path);
    }
});
