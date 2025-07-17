<?php

return [
    'page_format'           => 'A4',
    'page_orientation'      => 'P',
    'page_units'            => 'mm',
    'unicode'               => true,
    'encoding'              => 'UTF-8',
    'font_directory'        => storage_path('fonts/'),
    'image_directory'       => '',
    'tcpdf_throw_exception' => true,
    'use_fpdi'              => false,
    'use_original_header'   => false,
    'use_original_footer'   => false,
    'pdfa'                  => false, // Set to true to enable PDF/A mode
    'font_data' => [
        'thsarabun' => [
            'R'  => 'THSarabunNew.ttf',
            'B'  => 'THSarabunNew-Bold.ttf',
            'I'  => 'THSarabunNew-Italic.ttf',
            'BI' => 'THSarabunNew-BoldItalic.ttf',
        ],
    ]
];
