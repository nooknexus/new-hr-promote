<?php

use App\Models\User;

test('about page explains the personnel portal and displays the configured application version', function () {
    config(['app.version' => '2.3.4']);

    $this->actingAs(new User(['name' => 'Test User']))
        ->get(route('about'))
        ->assertOk()
        ->assertSee('เกี่ยวกับ MyPPH')
        ->assertSee('สำนักงานสาธารณสุขจังหวัดพิษณุโลก')
        ->assertSee('ระบบข้อมูลบุคลากรและเอกสารสำหรับบุคลากร')
        ->assertSee('สลิปเงินเดือน')
        ->assertSee('ใบรับรองการหักภาษี')
        ->assertSee('ประวัติการลา')
        ->assertSee('เวอร์ชัน 2.3.4');
});
