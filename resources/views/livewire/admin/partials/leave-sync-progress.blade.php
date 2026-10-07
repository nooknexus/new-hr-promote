@php
    $stage ??= 'starting';
    $processed ??= 0;
    $total ??= 0;
    $percent ??= null;
    $label = match ($stage) {
        'downloading' => 'กำลังดาวน์โหลดข้อมูลจาก Google Sheet',
        'processing' => 'กำลังนำเข้ารายการลา',
        default => 'กำลังเริ่มซิงค์ข้อมูล',
    };
    $determinate = $stage === 'processing' && $percent !== null;
@endphp
<div class="flex items-center justify-between gap-3 text-sm">
    <p class="font-semibold text-slate-800">{{ $label }}</p>
    @if($determinate)
        <p class="shrink-0 tabular-nums text-slate-600">
            {{ number_format($processed) }} / {{ number_format($total) }} แถว · <span class="font-semibold text-brand-700">{{ $percent }}%</span>
        </p>
    @endif
</div>
<div
    class="mt-3 h-2.5 w-full overflow-hidden rounded-full bg-slate-100"
    role="progressbar"
    aria-label="{{ $label }}"
    aria-valuemin="0"
    aria-valuemax="100"
    @if($determinate) aria-valuenow="{{ $percent }}" aria-valuetext="{{ $processed }} จาก {{ $total }} แถว" @endif
>
    @if($determinate)
        <div class="h-full rounded-full bg-brand-600 transition-[width] duration-300 ease-out" style="width: {{ $percent }}%"></div>
    @else
        <div class="h-full w-1/3 animate-progress-indeterminate rounded-full bg-brand-600 motion-reduce:w-full motion-reduce:animate-pulse"></div>
    @endif
</div>
