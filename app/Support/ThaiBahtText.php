<?php

namespace App\Support;

/** Reads a baht amount in Thai words, e.g. 7117.44 → เจ็ดพันหนึ่งร้อยสิบเจ็ดบาทสี่สิบสี่สตางค์. */
final class ThaiBahtText
{
    private const DIGITS = ['', 'หนึ่ง', 'สอง', 'สาม', 'สี่', 'ห้า', 'หก', 'เจ็ด', 'แปด', 'เก้า'];
    private const PLACES = ['', 'สิบ', 'ร้อย', 'พัน', 'หมื่น', 'แสน'];

    public static function format(float|string $amount): string
    {
        $cents = (int) round((float) $amount * 100);
        if ($cents === 0) {
            return 'ศูนย์บาทถ้วน';
        }

        $baht = intdiv($cents, 100);
        $satang = $cents % 100;
        $text = $baht > 0 ? self::number($baht).'บาท' : '';

        return $text.($satang > 0 ? self::number($satang).'สตางค์' : 'ถ้วน');
    }

    private static function number(int $value, bool $afterMillion = false): string
    {
        if ($value >= 1000000) {
            return self::number(intdiv($value, 1000000)).'ล้าน'.($value % 1000000 > 0 ? self::number($value % 1000000, true) : '');
        }

        $text = '';
        $digits = array_reverse(str_split((string) $value));
        foreach ($digits as $place => $digit) {
            $digit = (int) $digit;
            if ($digit === 0) {
                continue;
            }
            $word = match (true) {
                $place === 0 && $digit === 1 && ($value > 9 || $afterMillion) => 'เอ็ด',
                $place === 1 && $digit === 1 => '',
                $place === 1 && $digit === 2 => 'ยี่',
                default => self::DIGITS[$digit],
            };
            $text = $word.self::PLACES[$place].$text;
        }

        return $text;
    }
}
