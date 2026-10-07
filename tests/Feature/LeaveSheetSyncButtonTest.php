<?php

use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

test('the leave sheet sync button remains clickable so empty URLs can show validation', function () {
    $html = view('livewire.admin.leave-sheet-sync', [
        'syncRuns' => collect(),
        'syncResult' => null,
        'sheetUrl' => '',
        'errors' => (new ViewErrorBag)->put('default', new MessageBag),
    ])->render();

    preg_match('/<button\b(?=[^>]*wire:click="syncFromGoogleSheet")([^>]*)>/s', $html, $matches);

    expect($matches)->not->toBeEmpty()
        ->and((bool) preg_match('/(?:^|\s)disabled(?:\s|=|$)/', trim($matches[1])))->toBeFalse();
});

test('the leave sheet sync confirmation is rendered as an application modal', function () {
    $html = view('livewire.admin.leave-sheet-sync', [
        'syncRuns' => collect(),
        'syncResult' => null,
        'sheetUrl' => 'https://docs.google.com/spreadsheets/d/example/edit',
        'errors' => (new ViewErrorBag)->put('default', new MessageBag),
    ])->render();

    expect($html)
        ->toContain('id="leaveSheetSyncConfirmModal"')
        ->toContain('window.portalModal.show(\'leaveSheetSyncConfirmModal\')')
        ->toContain('ยืนยันซิงค์ประวัติการลาจาก Google Sheet หรือไม่? รายการเดิมที่ตรงกันจะถูกปรับปรุง')
        ->toContain('data-modal-close')
        ->not->toContain('wire:confirm');
});

test('the leave sheet sync page shows a streamed progress bar while syncing', function () {
    $html = view('livewire.admin.leave-sheet-sync', [
        'syncRuns' => collect(),
        'syncResult' => null,
        'sheetUrl' => '',
        'errors' => (new ViewErrorBag)->put('default', new MessageBag),
    ])->render();

    expect($html)
        ->toContain('wire:loading.block')
        ->toContain('wire:stream="leave-sync-progress"')
        ->toContain('role="progressbar"')
        ->toContain('กำลังเริ่มซิงค์ข้อมูล');
});

test('the leave sync progress partial renders row counts and percent while importing', function () {
    $html = view('livewire.admin.partials.leave-sync-progress', [
        'stage' => 'processing',
        'processed' => 1250,
        'total' => 5000,
        'percent' => 25,
    ])->render();

    expect($html)
        ->toContain('กำลังนำเข้ารายการลา')
        ->toContain('1,250 / 5,000 แถว')
        ->toContain('aria-valuenow="25"')
        ->toContain('width: 25%');
});

test('the leave sync progress partial is indeterminate while downloading', function () {
    $html = view('livewire.admin.partials.leave-sync-progress', ['stage' => 'downloading'])->render();

    expect($html)
        ->toContain('กำลังดาวน์โหลดข้อมูลจาก Google Sheet')
        ->toContain('animate-progress-indeterminate')
        ->not->toContain('aria-valuenow');
});

test('the leave sheet sync page links back to the leave register', function () {
    $html = view('livewire.admin.leave-sheet-sync', [
        'syncRuns' => collect(),
        'syncResult' => null,
        'sheetUrl' => '',
        'errors' => (new ViewErrorBag)->put('default', new MessageBag),
    ])->render();

    expect($html)
        ->toContain('href="' . route('admin.leaves.index') . '"')
        ->toContain('กลับไปหน้าจัดการข้อมูลการลา');
});
