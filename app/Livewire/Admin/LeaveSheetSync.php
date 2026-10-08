<?php

namespace App\Livewire\Admin;

use Livewire\Attributes\Title;
use App\Support\AdminAccess;
use App\Models\LeaveSyncRun;
use App\Services\LeaveSheetSyncService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

class LeaveSheetSync extends Component
{
    public function boot(): void
    {
        $this->ensureAllowed();
    }

    public string $sheetUrl = '';

    #[Locked]
    public ?array $syncResult = null;

    public function syncFromGoogleSheet(LeaveSheetSyncService $syncService): void
    {
        $this->ensureAllowed();
        $this->validate([
            'sheetUrl' => ['required', 'string', 'max:2048', 'url'],
        ], [
            'sheetUrl.required' => 'กรุณาวาง URL ของ Google Sheets ก่อนเริ่มซิงค์',
            'sheetUrl.url' => 'กรุณาระบุ URL ที่ถูกต้อง',
        ]);
        $this->resetErrorBag('sync');
        $this->syncResult = null;

        try {
            $this->syncResult = $syncService->sync(
                (int) Auth::id(),
                $this->sheetUrl,
                $this->progressReporter(),
            );
        } catch (\RuntimeException $exception) {
            $this->addError('sync', $exception->getMessage());
        }
    }

    /**
     * Streams progress to the wire:stream target while the sync request runs.
     * Updates are throttled to whole-percent changes to keep the stream small.
     */
    private function progressReporter(): callable
    {
        $lastKey = null;

        return function (string $stage, int $processed, int $total) use (&$lastKey): void {
            $percent = $total > 0 ? intdiv($processed * 100, $total) : null;
            $key = $stage . ':' . ($percent ?? '-');
            if ($key === $lastKey) {
                return;
            }
            $lastKey = $key;

            $this->stream(
                to: 'leave-sync-progress',
                content: view('livewire.admin.partials.leave-sync-progress', [
                    'stage' => $stage,
                    'processed' => $processed,
                    'total' => $total,
                    'percent' => $percent,
                ])->render(),
                replace: true,
            );
        };
    }

    #[Title('ดึงประวัติการลา')]
    public function render()
    {
        $this->ensureAllowed();

        return view('livewire.admin.leave-sheet-sync', [
            'syncRuns' => LeaveSyncRun::with('admin:id,name')
                ->orderByDesc('id')
                ->limit(10)
                ->get(),
        ]);
    }

    private function ensureAllowed(): void
    {
        abort_unless(AdminAccess::allows(Auth::user(), 'leave-sync'), 403);
    }
}
