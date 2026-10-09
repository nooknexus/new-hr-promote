<?php

/*
| Salary ceilings (เพดานเงินเดือน) by personnel type (person.type_id) and
| position level (person.position_level / the import file's "ระดับ" column).
| A promotion that reaches the ceiling stops raising the salary and pays the
| rest as เงินค่าตอบแทนพิเศษ (data_promote.extra); see SalaryPromotionCalculator.
| Levels are written without the "ระดับ" prefix.
*/

return [
    // ข้าราชการ
    1 => [
        'ปฏิบัติงาน' => 38750,
        'ชำนาญงาน' => 38750,
        'อาวุโส' => 69040,
        'ทักษะพิเศษ' => 74320,
        'ปฏิบัติการ' => 43600,
        'ชำนาญการ' => 58390,
        'ชำนาญการพิเศษ' => 69040,
        'เชี่ยวชาญ' => 74320,
        'ทรงคุณวุฒิ' => 76800,
        'อำนวยการต้น' => 70360,
        'อำนวยการสูง' => 74320,
        'บริหารต้น' => 74320,
        'บริหารสูง' => 76800,
    ],
    // ลูกจ้างประจำ, พนักงานราชการ, พนักงานกระทรวง: add their ceilings when the
    // tables are available. Until then promotions are rounded without a ceiling.
    2 => [],
    3 => [],
    4 => [],
];
