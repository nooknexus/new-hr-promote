<?php

namespace App\Services;

use App\Support\ThaiCitizenId;
use TCPDF;

class TaxCertificatePdfService
{
    private const TEMPLATE_WIDTH = 1225;
    private const TEMPLATE_HEIGHT = 1566;
    private const FONT_SCALE = 1.1;

    /** @var array<string, array{label: string, x: int, y: int, width: int, size: int, boxX: int, boxY: int}> */
    private const INCOME_TYPE_LAYOUT = [
        '1' => ['label' => 'ภ.ง.ด.1ก', 'x' => 530, 'y' => 577, 'width' => 115, 'size' => 11, 'boxX' => 495, 'boxY' => 576],
        '2' => ['label' => 'ภ.ง.ด.1ก พิเศษ', 'x' => 683, 'y' => 577, 'width' => 160, 'size' => 11, 'boxX' => 648, 'boxY' => 576],
        '3' => ['label' => 'ภ.ง.ด.2', 'x' => 881, 'y' => 577, 'width' => 144, 'size' => 11, 'boxX' => 846, 'boxY' => 576],
        '4' => ['label' => 'ภ.ง.ด.3', 'x' => 1062, 'y' => 579, 'width' => 80, 'size' => 9, 'boxX' => 1027, 'boxY' => 576],
        '5' => ['label' => 'ภ.ง.ด.2ก', 'x' => 530, 'y' => 622, 'width' => 115, 'size' => 11, 'boxX' => 495, 'boxY' => 621],
        '6' => ['label' => 'ภ.ง.ด.3ก', 'x' => 683, 'y' => 622, 'width' => 160, 'size' => 11, 'boxX' => 648, 'boxY' => 621],
        '7' => ['label' => 'ภ.ง.ด.53', 'x' => 881, 'y' => 622, 'width' => 144, 'size' => 11, 'boxX' => 846, 'boxY' => 621],
    ];

    /** @var array<string, string> */
    private const PAYMENT_TYPE_LABELS = [
        '1' => 'หัก ณ ที่จ่าย',
        '2' => 'ออกให้ตลอดไป',
        '3' => 'ออกให้ครั้งเดียว',
        '4' => 'อื่นๆ',
    ];

    /**
     * Map the complete, authorized data_tax row into the document payload shared by preview and PDF paths.
     *
     * @param  array<string, mixed>|object  $record
     * @return array<string, mixed>
     */
    public static function fromTaxRecord(array|object $record): array
    {
        $record = (array) $record;
        $fundType = (string) (self::recordValue($record, ['outcomeType']) ?? '');
        $fundAmount = (float) (self::recordValue($record, ['outcomeAmount']) ?? 0);
        $dateReport = (string) (self::recordValue($record, ['date_report']) ?? '');
        $certificate = [
            'taxYear' => self::recordValue($record, ['income_year']),
            'certificateNo' => self::recordValue($record, ['running_code1']),
            'payerName' => self::recordValue($record, ['department_name']),
            'payerTaxId' => self::recordValue($record, ['department_tax_id']),
            'payerAddress' => self::recordValue($record, ['department_addr']),
            'recipientName' => self::recordValue($record, ['fullname']),
            'recipientCid' => self::recordValue($record, ['CID', 'cid']),
            'recipientTaxId' => self::recordValue($record, ['tax_id']),
            'recipientAddress' => self::recordValue($record, ['addr']),
            'incomeTypeId' => self::recordValue($record, ['income_type_id']),
            'incomeTypeName' => self::recordValue($record, ['income_type_name']),
            'fundType' => $fundType,
            'fundName' => self::recordValue($record, ['outcomeName']),
            'fundAmount' => $fundAmount,
            'paymentTypeId' => self::recordValue($record, ['tax_type_id']),
            'paymentTypeName' => self::recordValue($record, ['tax_type_name']),
            'incomeMoney' => self::recordValue($record, ['income_money']),
            'totalIncome' => self::recordValue($record, ['total_money']),
            'taxWithheld' => self::recordValue($record, ['withholding_tax']),
            'sumTaxAmount' => self::recordValue($record, ['sumTaxAmount']),
            'taxText' => self::recordValue($record, ['sumTaxText']),
            'socialSecurity' => self::recordValue($record, ['socialSecurity']),
            'dateReport' => $dateReport,
            'formattedIssueDate' => self::formatThaiDate($dateReport),
            'governmentPensionFund' => $fundType === '1' ? $fundAmount : 0,
            'providentFund' => $fundType === '2' ? $fundAmount : 0,
            'employeeInfo' => [
                'name' => self::recordValue($record, ['fullname']),
                'pid' => self::recordValue($record, ['CID', 'cid']),
                'department' => self::recordValue($record, ['addr']),
            ],
        ];
        $certificate['incomeTypeOptions'] = self::incomeTypeOptions($certificate);
        $certificate['fundOptions'] = self::fundOptions($certificate);
        $certificate['paymentOptions'] = self::paymentOptions($certificate);

        return $certificate;
    }

    /** @return array<int, array{id: string, label: string, selected: bool}> */
    public static function incomeTypeOptions(array $certificate): array
    {
        $selected = array_key_exists('incomeTypeId', $certificate)
            ? (string) $certificate['incomeTypeId']
            : '2';
        $storedName = trim((string) ($certificate['incomeTypeName'] ?? ''));
        $options = [];

        foreach (self::INCOME_TYPE_LAYOUT as $id => $layout) {
            $id = (string) $id;
            $options[] = [
                'id' => $id,
                'label' => $id === $selected && $storedName !== '' ? $storedName : $layout['label'],
                'selected' => $id === $selected,
            ];
        }

        return $options;
    }

    /** @return array<int, array{id: string, label: string, amount: float, selected: bool}> */
    public static function fundOptions(array $certificate): array
    {
        $fundType = (string) ($certificate['fundType'] ?? '');
        $fundName = trim((string) ($certificate['fundName'] ?? ''));
        $fundAmount = (float) ($certificate['fundAmount'] ?? 0);
        $governmentPensionFund = (float) ($certificate['governmentPensionFund'] ?? 0);
        $providentFund = (float) ($certificate['providentFund'] ?? 0);

        return [
            [
                'id' => '1',
                'label' => $fundType === '1' && $fundName !== '' ? $fundName : 'กบข.',
                'amount' => $fundType === '1' ? $fundAmount : $governmentPensionFund,
                'selected' => $fundType === '1' || ($fundType === '' && $governmentPensionFund > 0),
            ],
            [
                'id' => '2',
                'label' => $fundType === '2' && $fundName !== '' ? $fundName : 'กสจ.',
                'amount' => $fundType === '2' ? $fundAmount : $providentFund,
                'selected' => $fundType === '2' || ($fundType === '' && $providentFund > 0),
            ],
        ];
    }

    /** @return array<int, array{id: string, label: string, selected: bool}> */
    public static function paymentOptions(array $certificate): array
    {
        $selected = array_key_exists('paymentTypeId', $certificate)
            ? (string) $certificate['paymentTypeId']
            : '1';
        $storedName = trim((string) ($certificate['paymentTypeName'] ?? ''));
        $options = [];

        foreach (self::PAYMENT_TYPE_LABELS as $id => $label) {
            $id = (string) $id;
            $options[] = [
                'id' => $id,
                'label' => $id === $selected && $storedName !== '' ? $storedName : $label,
                'selected' => $id === $selected,
            ];
        }

        return $options;
    }

    public static function formatThaiDate(?string $date): string
    {
        if (! $date || strlen($date) !== 8) {
            return $date ?? '';
        }

        $months = [
            '01' => 'มกราคม', '02' => 'กุมภาพันธ์', '03' => 'มีนาคม', '04' => 'เมษายน',
            '05' => 'พฤษภาคม', '06' => 'มิถุนายน', '07' => 'กรกฎาคม', '08' => 'สิงหาคม',
            '09' => 'กันยายน', '10' => 'ตุลาคม', '11' => 'พฤศจิกายน', '12' => 'ธันวาคม',
        ];

        return substr($date, 0, 2).' '.($months[substr($date, 2, 2)] ?? 'เดือนไม่ทราบ').' '.substr($date, 4, 4);
    }

    /** Create the certificate with vector form lines on a single A4 page. */
    public static function generatePdf(array $certificate): array
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('MyPPH');
        $pdf->SetAuthor('สำนักงานปลัดกระทรวงสาธารณสุข');
        $pdf->SetTitle('หนังสือรับรองการหักภาษี ณ ที่จ่าย');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->AddPage();

        self::drawForm($pdf);
        self::setThaiFont($pdf, 12);
        self::renderCertificate($pdf, $certificate);

        return [
            'content' => $pdf->Output('', 'S'),
            'filename' => self::filename($certificate),
        ];
    }

    public static function filename(array $certificate): string
    {
        $year = self::safeFilenamePart((string) ($certificate['taxYear'] ?? 'document'));
        $number = self::safeFilenamePart((string) ($certificate['certificateNo'] ?? 'certificate'));

        return "tax_certificate_{$year}_{$number}.pdf";
    }

    private static function drawForm(TCPDF $pdf): void
    {
        $pdf->SetDrawColor(75, 75, 75);
        $pdf->SetLineWidth(0.14);

        foreach ([
            [81, 117, 1080, 1306],
            [99, 198, 1044, 180],
            [99, 396, 1044, 307],
            [189, 576, 100, 36],
            [99, 720, 1044, 360],
            [99, 1107, 1044, 55],
            [99, 1179, 1044, 55],
            [99, 1251, 428, 153],
            [535, 1251, 608, 153],
        ] as [$x, $y, $width, $height]) {
            self::rectangle($pdf, $x, $y, $width, $height);
        }

        foreach ([
            [495, 576], [648, 576], [846, 576], [1027, 576],
            [495, 621], [648, 621], [846, 621],
            [324, 1125], [792, 1125],
            [252, 1197], [423, 1197], [622, 1197], [828, 1197],
        ] as [$x, $y]) {
            self::rectangle($pdf, $x, $y, 27, 27);
        }

        // Header, body, total and words bands use the same six columns as the preview.
        foreach ([792, 982, 1018] as $y) {
            self::line($pdf, 99, $y, 1143, $y);
        }
        foreach ([657, 810, 981] as $x) {
            self::line($pdf, $x, 720, $x, 982);
        }
        foreach ([936, 1098] as $x) {
            self::line($pdf, $x, 792, $x, 982);
        }
        foreach ([810, 936, 981, 1098] as $x) {
            self::line($pdf, $x, 982, $x, 1018);
        }

        $pdf->SetFillColor(198, 198, 198);
        $pdf->Rect(self::mmX(396), self::mmY(1035), self::mmX(747), self::mmY(36), 'F');

        $pdf->SetLineStyle(['width' => 0.12, 'dash' => '1,1', 'color' => [90, 90, 90]]);
        foreach ([
            [1045, 154, 1143, 154], [1045, 180, 1143, 180],
            [171, 270, 613, 270], [171, 332, 1106, 332],
            [163, 477, 604, 477], [163, 540, 1097, 540],
            [666, 1333, 1009, 1333],
        ] as [$x1, $y1, $x2, $y2]) {
            self::line($pdf, $x1, $y1, $x2, $y2);
        }
        $pdf->SetLineStyle(['width' => 0.14, 'dash' => 0, 'color' => [75, 75, 75]]);

    }

    private static function rectangle(TCPDF $pdf, float $x, float $y, float $width, float $height): void
    {
        $pdf->Rect(self::mmX($x), self::mmY($y), self::mmX($width), self::mmY($height));
    }

    private static function line(TCPDF $pdf, float $x1, float $y1, float $x2, float $y2): void
    {
        $pdf->Line(self::mmX($x1), self::mmY($y1), self::mmX($x2), self::mmY($y2));
    }

    private static function cross(TCPDF $pdf, float $x, float $y): void
    {
        $pdf->SetLineWidth(0.4);
        self::line($pdf, $x + 4, $y + 4, $x + 23, $y + 23);
        self::line($pdf, $x + 23, $y + 4, $x + 4, $y + 23);
        $pdf->SetLineWidth(0.14);
    }

    private static function renderCertificate(TCPDF $pdf, array $certificate): void
    {
        $employee = $certificate['employeeInfo'] ?? [];
        $payerName = (string) ($certificate['payerName'] ?? '');
        $payerTaxId = ThaiCitizenId::display((string) ($certificate['payerTaxId'] ?? ''));
        $payerAddress = (string) ($certificate['payerAddress'] ?? '');
        $recipientName = (string) ($certificate['recipientName'] ?? $employee['name'] ?? '');
        $recipientCid = ThaiCitizenId::display((string) ($certificate['recipientCid'] ?? $employee['pid'] ?? ''));
        $recipientTaxId = ThaiCitizenId::display((string) ($certificate['recipientTaxId'] ?? ''));
        $recipientAddress = (string) ($certificate['recipientAddress'] ?? $employee['department'] ?? '');
        $incomeParts = explode('.', number_format((float) ($certificate['totalIncome'] ?? 0), 2));
        $taxParts = explode('.', number_format((float) ($certificate['taxWithheld'] ?? 0), 2));
        $incomeWhole = $incomeParts[0];
        $incomeSatang = $incomeParts[1] ?? '00';
        $taxWhole = $taxParts[0];
        $taxSatang = $taxParts[1] ?? '00';
        $taxText = array_key_exists('taxText', $certificate)
            ? (string) ($certificate['taxText'] ?? '')
            : ((float) ($certificate['taxWithheld'] ?? 0) > 0 ? 'ใส่จำนวนเงินเป็นตัวอักษร' : 'ศูนย์บาทถ้วน');
        $incomeTypeOptions = self::incomeTypeOptions($certificate);
        [$governmentPensionFundOption, $providentFundOption] = self::fundOptions($certificate);
        $paymentOptions = self::paymentOptions($certificate);

        self::text($pdf, 108, 42, 700, 'ฉบับที่ 1 (สําหรับผู้ถูกหักภาษี ณ ที่จ่าย ใช้แนบพร้อมกับแบบแสดงรายการภาษี)', 10);
        self::text($pdf, 108, 60, 700, 'ฉบับที่ 2 (สําหรับผู้ถูกหักภาษี ณ ที่จ่าย เก็บไว้เป็นหลักฐาน)', 10);
        self::text($pdf, 400, 117, 425, 'หนังสือรับรองการหักภาษี ณ ที่จ่าย', 16, 'C', true);
        self::text($pdf, 400, 148, 425, 'ตามมาตรา 50 ทวิ แห่งประมวลรัษฎากร', 13, 'C');
        self::text($pdf, 975, 126, 65, 'เล่มที่', 11, 'R');
        self::text($pdf, 975, 152, 65, 'เลขที่', 11, 'R');
        self::text($pdf, 1045, 152, 98, (string) ($certificate['certificateNo'] ?? ''), 11, 'C');

        self::text($pdf, 114, 207, 360, 'ผู้มีหน้าที่หักภาษี ณ ที่จ่าย :-', 13);
        self::text($pdf, 621, 207, 220, 'เลขประจำตัวประชาชน', 12);
        self::text($pdf, 878, 207, 220, $payerTaxId, 12);
        self::text($pdf, 166, 238, 40, 'ชื่อ', 12);
        self::text($pdf, 244, 238, 450, $payerName, 12);
        self::text($pdf, 702, 238, 360, 'เลขประจำตัวผู้เสียภาษีอากร '.$payerTaxId, 12);
        self::text($pdf, 166, 274, 850, '( ให้ระบุว่าเป็น บุคคล นิติบุคคล บริษัท สมาคม หรือคณะบุคคล )', 9);
        self::text($pdf, 710, 274, 415, '( กรอกเฉพาะกรณีเป็นผู้ไม่มีเลขบัตรประจำตัวประชาชน )', 9);
        self::singleLineText($pdf, 166, 300, 70, 'ที่อยู่', 12);
        self::text($pdf, 244, 300, 680, $payerAddress, 12);
        self::text($pdf, 166, 338, 900, '( ให้ระบุ ชื่ออาคาร/หมู่บ้าน ห้องเลขที่ ชั้นที่ เลขที่ ตรอก/ซอย หมู่ที่ ถนน ตำบล/แขวง อำเภอ/เขต จังหวัด )', 9);

        self::text($pdf, 114, 406, 360, 'ผู้ถูกหักภาษี ณ ที่จ่าย :-', 13);
        self::text($pdf, 621, 406, 220, 'เลขประจำตัวประชาชน', 12);
        self::text($pdf, 878, 406, 220, $recipientCid, 12);
        self::text($pdf, 166, 437, 40, 'ชื่อ', 12);
        self::text($pdf, 244, 437, 450, $recipientName, 12);
        self::text($pdf, 702, 437, 360, 'เลขประจำตัวผู้เสียภาษีอากร '.$recipientTaxId, 12);
        self::text($pdf, 166, 481, 850, '( ให้ระบุว่าเป็น บุคคล นิติบุคคล บริษัท สมาคม หรือคณะบุคคล )', 9);
        self::text($pdf, 710, 481, 415, '( กรอกเฉพาะกรณีเป็นผู้ไม่มีเลขบัตรประจำตัวประชาชน )', 9);
        self::singleLineText($pdf, 166, 510, 70, 'ที่อยู่', 12);
        self::text($pdf, 244, 510, 680, $recipientAddress, 12);
        self::text($pdf, 166, 545, 900, '( ให้ระบุ ชื่ออาคาร/หมู่บ้าน ห้องเลขที่ ชั้นที่ เลขที่ ตรอก/ซอย หมู่ที่ ถนน ตำบล/แขวง อำเภอ/เขต จังหวัด )', 9);

        self::text($pdf, 113, 575, 90, 'ลำดับที่', 11);
        self::text($pdf, 188, 575, 105, (string) ($certificate['certificateNo'] ?? ''), 12, 'C');
        self::text($pdf, 302, 575, 80, 'ในแบบ', 11);
        foreach ($incomeTypeOptions as $option) {
            $layout = self::INCOME_TYPE_LAYOUT[$option['id']];
            self::text($pdf, $layout['x'], $layout['y'], $layout['width'], '('.$option['id'].') '.$option['label'], $layout['size']);

            if ($option['selected']) {
                self::cross($pdf, $layout['boxX'], $layout['boxY']);
            }
        }

        self::text($pdf, 116, 735, 530, 'ประเภทเงินได้พึงประเมินที่จ่าย', 12, 'C');
        self::text($pdf, 657, 728, 150, "วัน เดือน\nหรือปีภาษี ที่จ่าย", 11, 'C');
        self::text($pdf, 808, 735, 160, 'จำนวนเงินที่จ่าย', 12, 'C');
        self::text($pdf, 970, 728, 150, "ภาษีที่หัก\nและนำส่งไว้", 11, 'C');
        self::text($pdf, 120, 801, 510, "1. เงินเดือน ค่าจ้าง เบี้ยเลี้ยง โบนัส ฯลฯ ตามมาตรา 40(1)\n2. ค่าธรรมเนียม ค่านายหน้า ฯลฯ ตามมาตรา 40(2)", 11);
        self::text($pdf, 657, 801, 150, (string) ($certificate['taxYear'] ?? ''), 12, 'C');
        self::text($pdf, 813, 801, 115, $incomeWhole, 12, 'R');
        self::text($pdf, 936, 801, 45, $incomeSatang, 11, 'C');
        self::text($pdf, 984, 801, 106, $taxWhole, 12, 'R');
        self::text($pdf, 1098, 801, 45, $taxSatang, 11, 'C');
        self::text($pdf, 490, 983, 310, 'รวมเงินที่จ่ายและภาษีที่หักนำส่ง', 12, 'R');
        self::text($pdf, 813, 983, 115, $incomeWhole, 12, 'R');
        self::text($pdf, 936, 983, 45, $incomeSatang, 11, 'C');
        self::text($pdf, 984, 983, 106, $taxWhole, 12, 'R');
        self::text($pdf, 1098, 983, 45, $taxSatang, 11, 'C');
        self::text($pdf, 117, 1040, 285, 'รวมเงินภาษีที่หักนำส่ง (ตัวอักษร)', 11);
        self::text($pdf, 411, 1040, 700, $taxText, 11);

        self::text($pdf, 117, 1120, 180, 'เงินสะสมที่จ่ายเข้า', 11);
        if ($governmentPensionFundOption['selected']) {
            self::cross($pdf, 324, 1125);
        }
        self::text($pdf, 355, 1120, 250, $governmentPensionFundOption['label'].'   '.($governmentPensionFundOption['selected'] ? number_format($governmentPensionFundOption['amount'], 2) : '').' บาท', 11);
        if ($providentFundOption['selected']) {
            self::cross($pdf, 792, 1125);
        }
        self::text($pdf, 823, 1120, 250, $providentFundOption['label'].'   '.($providentFundOption['selected'] ? number_format($providentFundOption['amount'], 2) : '').' บาท', 11);
        self::text($pdf, 117, 1199, 85, 'ผู้จ่ายเงิน', 11);
        foreach ([
            ['x' => 287, 'width' => 130, 'boxX' => 252],
            ['x' => 455, 'width' => 160, 'boxX' => 423],
            ['x' => 655, 'width' => 170, 'boxX' => 622],
            ['x' => 862, 'width' => 240, 'boxX' => 828],
        ] as $index => $layout) {
            $option = $paymentOptions[$index];
            self::text($pdf, $layout['x'], 1199, $layout['width'], '('.$option['id'].') '.$option['label'].($option['id'] === '4' ? ' (ระบุ) ................' : ''), 11);

            if ($option['selected']) {
                self::cross($pdf, $layout['boxX'], 1197);
            }
        }
        self::text($pdf, 117, 1272, 390, "คำเตือน   ผู้ที่มีหน้าที่ออกหนังสือรับรองการหักภาษี ณ ที่จ่าย\nฝ่าฝืนไม่ปฏิบัติตามมาตรา 50 ทวิ แห่งประมวลรัษฎากร\nต้องรับโทษทางอาญาตามมาตรา 35", 10);
        self::text($pdf, 540, 1272, 570, 'ขอรับรองว่าข้อความและตัวเลขดังกล่าวข้างต้นถูกต้องตรงกับความจริงทุกประการ', 11, 'C');
        self::text($pdf, 600, 1312, 65, 'ลงชื่อ', 11, 'R');
        self::text($pdf, 1014, 1312, 110, 'ผู้จ่ายเงิน', 11);
        self::text($pdf, 550, 1347, 540, (string) ($certificate['formattedIssueDate'] ?? ''), 11, 'C');
        self::text($pdf, 550, 1377, 540, '(วัน เดือน ปี ที่ออกหนังสือรับรอง)', 10, 'C');

        $signatureImage = $certificate['signatureImage'] ?? null;
        $dimensions = is_string($signatureImage) && $signatureImage !== ''
            ? @getimagesizefromstring($signatureImage)
            : false;
        if ($dimensions && $dimensions[0] > 0 && $dimensions[1] > 0) {
            $scale = min(100 / $dimensions[0], 44 / $dimensions[1]);
            $width = $dimensions[0] * $scale;
            $height = $dimensions[1] * $scale;
            $pdf->Image('@'.$signatureImage, self::mmX(837.5 - $width / 2), self::mmY(1294 + (44 - $height) / 2), self::mmX($width), self::mmX($height));
        }
    }

    private static function text(TCPDF $pdf, float $x, float $y, float $width, string $text, float $size, string $align = 'L', bool $bold = false): void
    {
        self::setThaiFont($pdf, $size * self::FONT_SCALE, $bold);
        $pdf->SetXY(self::mmX($x), self::mmY($y));
        $pdf->MultiCell(self::mmX($width), 0, $text, 0, $align, false, 0, '', '', true, 0, false, true, 0, 'T', false);
    }

    private static function singleLineText(TCPDF $pdf, float $x, float $y, float $width, string $text, float $size, string $align = 'L'): void
    {
        self::setThaiFont($pdf, $size * self::FONT_SCALE);
        $pdf->SetXY(self::mmX($x), self::mmY($y));
        $pdf->Cell(self::mmX($width), 0, $text, 0, 0, $align);
    }

    private static function setThaiFont(TCPDF $pdf, float $size, bool $bold = false): void
    {
        $fontFile = base_path('vendor/tecnickcom/tcpdf/fonts/thsarabunnew.php');
        $pdf->SetFont(is_file($fontFile) ? 'thsarabunnew' : 'freeserif', $bold ? 'B' : '', $size);
    }

    private static function mmX(float $pixels): float
    {
        return $pixels / self::TEMPLATE_WIDTH * 210;
    }

    private static function mmY(float $pixels): float
    {
        return $pixels / self::TEMPLATE_HEIGHT * 297;
    }

    /** @param array<string, mixed> $record */
    private static function recordValue(array $record, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $record)) {
                return $record[$key];
            }
        }

        return null;
    }

    private static function safeFilenamePart(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9_-]+/', '-', trim($value)) ?? '';

        return trim($value, '-_') ?: 'document';
    }
}
