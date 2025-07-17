<div>
    <button 
        wire:click="redirectToMyMOPH" 
        class="btn btn-primary btn-user btn-block d-flex align-items-center justify-content-center"
        style="background-color: #F0F0F1; border-color: #02757D;color: #02757D;"
        wire:loading.attr="disabled"
    >
        <span wire:loading.remove>
            <img src="{{ asset('img/mymoph-app-icon-radius.png') }}" height="40">&nbsp; เข้าสู่ระบบด้วย MyMOPH
        </span>
        <span wire:loading>
            <i class="fas fa-spinner fa-spin"></i>&nbsp; กำลังเชื่อมต่อ MyMOPH...
        </span>
    </button>
</div>
