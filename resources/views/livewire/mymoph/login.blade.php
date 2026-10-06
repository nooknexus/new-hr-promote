<div class="w-full">
    <button
        type="button"
        wire:click="redirectToMyMOPH"
        class="flex min-h-16 w-full items-center justify-center gap-3 rounded-2xl border border-teal-200 bg-white px-4 py-3 text-sm font-semibold text-teal-800 shadow-sm transition hover:border-teal-300 hover:bg-teal-50 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-600 focus-visible:ring-offset-2"
        wire:loading.attr="disabled"
    >
        <span wire:loading.remove class="inline-flex items-center gap-3 whitespace-nowrap">
            <img src="{{ asset('img/mymoph-app-icon-radius.png') }}" alt="" class="h-10 w-10 shrink-0 object-contain">
            <span>เข้าสู่ระบบด้วย MyMOPH</span>
        </span>
        <span wire:loading class="inline-flex items-center gap-3 whitespace-nowrap">
            <i class="fas fa-spinner fa-spin" aria-hidden="true"></i>
            <span>กำลังเชื่อมต่อ MyMOPH...</span>
        </span>
    </button>
</div>
