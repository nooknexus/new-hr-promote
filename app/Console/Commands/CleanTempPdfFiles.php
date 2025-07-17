<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CleanTempPdfFiles extends Command
{
    protected $signature = 'temp:clean-pdf {--days=1 : จำนวนวันที่จะเก็บไฟล์ไว้}';
    protected $description = 'ลบไฟล์ PDF ชั่วคราวที่เก่ากว่าจำนวนวันที่กำหนด';

    public function handle()
    {
        $days = $this->option('days');
        $cutoffDate = Carbon::now()->subDays($days);
        $path = public_path('temp');
        $count = 0;
        $totalSize = 0;

        $this->info("กำลังลบไฟล์ PDF ที่เก่ากว่า {$days} วัน...");

        if (!File::exists($path)) {
            $this->warn("ไม่พบโฟลเดอร์ {$path}");
            return 0;
        }

        // ดึงเฉพาะไฟล์ PDF
        $files = File::files($path);
        foreach ($files as $file) {
            // ตรวจสอบว่าเป็นไฟล์ PDF หรือไม่
            if (strtolower($file->getExtension()) === 'pdf') {
                $fileModified = Carbon::createFromTimestamp($file->getMTime());
                
                // ลบไฟล์ที่เก่ากว่าวันที่กำหนด
                if ($fileModified->lt($cutoffDate)) {
                    $fileSize = $file->getSize();
                    $fileName = $file->getFilename();
                    
                    File::delete($file->getPathname());
                    
                    $count++;
                    $totalSize += $fileSize;
                    $this->line("ลบไฟล์: {$fileName} (อายุ: {$fileModified->diffForHumans()})");
                }
            }
        }

        $readableSize = $this->formatBytes($totalSize);
        $this->info("ลบไฟล์ PDF ทั้งหมด {$count} ไฟล์ (ขนาด {$readableSize})");
        
        Log::info("ระบบลบไฟล์ PDF ชั่วคราวอัตโนมัติ", [
            'files_removed' => $count,
            'total_size' => $readableSize,
            'older_than_days' => $days
        ]);

        return 0;
    }

    protected function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
