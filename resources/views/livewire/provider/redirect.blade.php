<div class="w-full">
    <button
        type="button"
        wire:click="redirectToProvider"
        class="flex min-h-16 w-full items-center justify-center gap-3 rounded-2xl border border-emerald-200 bg-white px-4 py-3 text-sm font-semibold text-emerald-800 shadow-sm transition hover:border-emerald-300 hover:bg-emerald-50 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2"
    >
        <img src="{{ asset('img/providerid.png') }}" alt="" class="h-10 w-10 shrink-0 object-contain">
        <span class="whitespace-nowrap">เข้าสู่ระบบด้วย ProviderID</span>
    </button>
</div>
