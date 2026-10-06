<div>
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">ผู้ลงนามในใบรับรองการจ่ายเงินเดือน</h1>
        <button type="button" class="btn btn-primary" wire:click="openCreateModal">
            <i class="fas fa-plus"></i> เพิ่มผู้ลงนาม
        </button>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">รายชื่อผู้ลงนามตามปีงบประมาณ</h6>
        </div>
        <div class="card-body">
            @if($signatories->isEmpty())
                <div class="alert alert-info">
                    <i class="fas fa-info-circle mr-1"></i> ยังไม่มีข้อมูลผู้ลงนาม — ใบรับรองจะใช้ข้อมูลเริ่มต้นของระบบไปก่อน
                </div>
            @else
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ปีงบประมาณ</th>
                                <th>ลายเซ็น</th>
                                <th>ชื่อ-นามสกุล</th>
                                <th>ตำแหน่ง</th>
                                <th class="text-right">การดำเนินการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($signatories as $signatory)
                                <tr>
                                    <td class="whitespace-nowrap">{{ $this->fiscalYearLabel($signatory->fiscal_year) }}</td>
                                    <td>
                                        @if($signatory->signature_data_uri)
                                            <img src="{{ $signatory->signature_data_uri }}" alt="ลายเซ็น" class="h-10 w-auto object-contain">
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>({{ $signatory->name }})</td>
                                    <td>
                                        <div>{{ $signatory->position_line1 }}</div>
                                        @if($signatory->position_line2)
                                            <div class="text-muted small">{{ $signatory->position_line2 }}</div>
                                        @endif
                                    </td>
                                    <td class="text-right whitespace-nowrap">
                                        <button type="button" class="btn btn-sm btn-info" wire:click="edit({{ $signatory->id }})">
                                            <i class="fas fa-edit"></i> แก้ไข
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger"
                                            wire:click="delete({{ $signatory->id }})"
                                            wire:confirm="ยืนยันการลบข้อมูลผู้ลงนามของปีงบประมาณ {{ $signatory->fiscal_year }}?">
                                            <i class="fas fa-trash"></i> ลบ
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <!-- Signatory Form Modal -->
    <div class="modal @if($showFormModal) show @endif" id="signatoryFormModal" tabindex="-1" role="dialog" aria-hidden="{{ $showFormModal ? 'false' : 'true' }}">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $editingId ? 'แก้ไขผู้ลงนาม' : 'เพิ่มผู้ลงนาม' }}</h5>
                    <button type="button" class="close" wire:click="closeModal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form wire:submit="save">
                    <div class="modal-body">
                        <div class="form-group">
                            <label>ปีงบประมาณ (พ.ศ.)</label>
                            <input type="number" class="form-control" wire:model="fiscal_year" placeholder="เช่น 2568">
                            @if($fiscal_year)
                                <p class="text-muted small mt-1">{{ $this->fiscalYearLabel((int) $fiscal_year) }}</p>
                            @endif
                            @error('fiscal_year') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="form-group">
                            <label>ชื่อ-นามสกุล (ไม่ต้องใส่วงเล็บ)</label>
                            <input type="text" class="form-control" wire:model="name" placeholder="เช่น นายมนัสศักต์ มากบุญ">
                            @error('name') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="form-group">
                            <label>ตำแหน่ง บรรทัดที่ 1</label>
                            <input type="text" class="form-control" wire:model="position_line1" placeholder="เช่น นักวิชาการสาธารณสุขชำนาญการพิเศษ (ด้านบริการทางวิชาการ)">
                            @error('position_line1') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="form-group">
                            <label>ตำแหน่ง บรรทัดที่ 2 (ถ้ามี)</label>
                            <input type="text" class="form-control" wire:model="position_line2" placeholder="เช่น รองนายแพทย์สาธารณสุขจังหวัดพิษณุโลก (ด้านบริหาร)">
                            @error('position_line2') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="form-group">
                            <label>รูปลายเซ็น (PNG/JPG ไม่เกิน 2MB)</label>
                            <input type="file" class="form-control" wire:model="signatureUpload" accept="image/*">
                            @error('signatureUpload') <p class="text-danger small mt-1">{{ $message }}</p> @enderror

                            <div wire:loading wire:target="signatureUpload" class="text-muted small mt-1">กำลังอัปโหลด...</div>

                            @if($signatureUpload)
                                <img src="{{ $signatureUpload->temporaryUrl() }}" alt="ตัวอย่างลายเซ็นใหม่" class="h-16 w-auto object-contain mt-2">
                            @elseif($hasExistingSignature)
                                <p class="text-muted small mt-1"><i class="fas fa-check-circle text-success"></i> มีรูปลายเซ็นอยู่แล้ว — อัปโหลดไฟล์ใหม่เพื่อแทนที่ ไม่อัปโหลดจะคงรูปเดิมไว้</p>
                            @endif
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="closeModal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
                            <span wire:loading.remove wire:target="save">บันทึก</span>
                            <span wire:loading wire:target="save">กำลังบันทึก...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
