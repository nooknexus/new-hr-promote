<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use TCPDF;

/**
 * Renders the salary promotion notice (หนังสือแจ้งผลการเลื่อนเงินเดือน) shown
 * on /profile/salary-promotion as an A4 PDF.
 */
class SalaryPromotionPdfService
{
    private const THAI_MONTHS = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
    ];

    /** April and October are the regular rounds; any other month is a special round. */
    public static function roundLabel(CarbonInterface $evaluationDate): string
    {
        return in_array($evaluationDate->month, [4, 10], true)
            ? self::THAI_MONTHS[$evaluationDate->month].' '.($evaluationDate->year + 543)
            : 'รอบพิเศษ';
    }

    /** "8 ตุลาคม 2569 เวลา 14:05น." */
    public static function thaiDateTime(CarbonInterface $date): string
    {
        return $date->day.' '.self::THAI_MONTHS[$date->month].' '.($date->year + 543).' เวลา '.$date->format('H:i').'น.';
    }

    public static function promoteLabel(?string $promote, ?string $remark): string
    {
        return match ($promote) {
            'Y' => 'ได้เลื่อนเงินเดือน',
            'N' => trim('ไม่ได้เลื่อนเงินเดือน '.($remark ?? '')),
            null, '' => '-',
            default => $promote,
        };
    }

    /**
     * @param  object  $promotion  data_promote row
     * @param  object|null  $person  person row supplying position and level
     */
    public static function fromPromotion(object $promotion, string $name, ?object $person): array
    {
        $evaluationDate = Carbon::parse($promotion->evaluation_date);

        return [
            'id' => (int) $promotion->id,
            'round' => self::roundLabel($evaluationDate),
            'name' => $name,
            'position' => self::text($person->position ?? null),
            'level' => self::text($person->position_level ?? null),
            'office' => self::text($promotion->office ?? null),
            'oldSalary' => $promotion->before_salary ?? null,
            'result' => self::promoteLabel($promotion->promote ?? null, $promotion->ps ?? null),
            'base' => $promotion->base ?? null,
            'percent' => $promotion->percent ?? null,
            'increase' => $promotion->sum ?? null,
            'extra' => $promotion->extra ?? null,
            'newSalary' => $promotion->new_salary ?? null,
            'dataAsOf' => empty($promotion->update_date) ? '-' : self::thaiDateTime(Carbon::parse($promotion->update_date)),
            'printedAt' => self::thaiDateTime(Carbon::now()),
            'evaluationDate' => $evaluationDate->format('Y-m-d'),
        ];
    }

    /** @return array{content: string, filename: string} */
    public static function generatePdf(array $notice): array
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('MyPPH');
        $pdf->SetAuthor('สำนักงานปลัดกระทรวงสาธารณสุข');
        $pdf->SetTitle('หนังสือแจ้งผลการเลื่อนเงินเดือน');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(20, 15, 20);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->AddPage();

        self::watermark($pdf);
        self::header($pdf);
        self::details($pdf, $notice);
        self::footer($pdf, $notice);

        return [
            'content' => $pdf->Output('', 'S'),
            'filename' => self::filename($notice),
        ];
    }

    public static function filename(array $notice): string
    {
        $date = preg_replace('/[^0-9-]/', '', (string) ($notice['evaluationDate'] ?? '')) ?: 'document';

        return "salary_promotion_{$date}_".(int) ($notice['id'] ?? 0).'.pdf';
    }

    private static function watermark(TCPDF $pdf): void
    {
        $image = public_path('img/head_logo.png');
        if (! is_file($image)) {
            return;
        }

        $pdf->SetAlpha(0.07);
        $pdf->Image($image, 25, 75, 160, 0, '', '', '', false, 300, '', false, false, 0, 'CM');
        $pdf->SetAlpha(1);
    }

    private static function header(TCPDF $pdf): void
    {
        self::font($pdf, 22, true);
        $pdf->SetTextColor(220, 38, 38);
        $pdf->SetY(15);
        $pdf->Cell(0, 10, 'ลับ', 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);

        $logo = public_path('img/logoMOPH.png');
        if (is_file($logo)) {
            $pdf->Image($logo, 92.5, 27, 25, 0, '', '', '', false, 300);
        }

        $pdf->SetY(55);
        self::font($pdf, 24, true);
        $pdf->Cell(0, 10, 'หนังสือแจ้งผลการเลื่อนเงินเดือน', 0, 1, 'C');
    }

    private static function details(TCPDF $pdf, array $notice): void
    {
        $rows = [
            'รอบการเลื่อนขั้น' => $notice['round'],
            'ชื่อ-สกุล' => $notice['name'],
            'ตำแหน่ง' => $notice['position'],
            'ระดับ' => $notice['level'],
            'ส่วนราชการ' => $notice['office'],
            'เงินเดือนก่อนเลื่อน' => self::money($notice['oldSalary']),
            'ผลการเลื่อน' => $notice['result'],
        ];

        $html = '<table cellpadding="4" style="font-size:16pt;">';
        foreach ($rows as $label => $value) {
            $html .= '<tr><td width="40%"><b>'.self::e($label).':</b></td><td width="60%">'.self::e((string) $value).'</td></tr>';
        }
        $html .= '</table><br><br>';

        $headers = ['ฐานในการคำนวณ', 'ร้อยละที่ได้เลื่อน', 'เงินเดือนที่ได้เลื่อน', 'ค่าตอบแทนพิเศษ', 'เงินเดือนที่ได้รับ'];
        $values = [
            self::money($notice['base']),
            self::money($notice['percent']),
            self::money($notice['increase']),
            self::money($notice['extra']),
            self::money($notice['newSalary']),
        ];
        $html .= '<table border="1" cellpadding="5" style="font-size:15pt; border-color:#999999;"><tr>';
        foreach ($headers as $header) {
            $html .= '<th align="center" width="20%"><b>'.self::e($header).'</b></th>';
        }
        $html .= '</tr><tr>';
        foreach ($values as $value) {
            $html .= '<td align="center" width="20%">'.self::e($value).'</td>';
        }
        $html .= '</tr></table>';

        $pdf->SetY(75);
        self::font($pdf, 16);
        $pdf->writeHTML($html, true, false, true, false, '');
    }

    private static function footer(TCPDF $pdf, array $notice): void
    {
        $top = 297 - 25;
        $pdf->SetDrawColor(210, 210, 210);
        $pdf->Line(20, $top, 190, $top);

        self::font($pdf, 13);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->SetXY(20, $top + 2);
        $pdf->Cell(170, 6, 'ข้อมูล ณ: '.$notice['dataAsOf'], 0, 1, 'L');
        $pdf->SetX(20);
        $pdf->Cell(85, 6, 'วันที่พิมพ์: '.$notice['printedAt'], 0, 0, 'L');
        $pdf->Cell(85, 6, 'เอกสารนี้จัดทำโดยระบบอิเล็กทรอนิกส์', 0, 1, 'R');
        $pdf->SetTextColor(0, 0, 0);
    }

    private static function money(mixed $value): string
    {
        return is_numeric($value) ? number_format((float) $value, 2) : '-';
    }

    private static function text(mixed $value): string
    {
        $text = trim((string) $value);

        return $text === '' ? '-' : $text;
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function font(TCPDF $pdf, float $size, bool $bold = false): void
    {
        $fontFile = base_path('vendor/tecnickcom/tcpdf/fonts/thsarabunnew.php');
        $pdf->SetFont(is_file($fontFile) ? 'thsarabunnew' : 'freeserif', $bold ? 'B' : '', $size);
    }
}
