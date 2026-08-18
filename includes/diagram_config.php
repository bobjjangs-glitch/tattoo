<?php
// includes/diagram_config.php
return [
    'none' => [
        'label'  => '도해 없음',
        'thumb'  => null,
        'panels' => [],
    ],
    'scalp' => [
        'label'  => '두피·모발',
        'thumb'  => '/tattoo/assets/images/body-diagram-scalp.jpg',
        'panels' => [
            ['image' => '/tattoo/assets/images/body-diagram-scalp.jpg', 'zones' => null],
        ],
    ],
    'face' => [
        'label'  => '얼굴',
        'thumb'  => '/tattoo/assets/images/body-diagram-face.jpg',
        'panels' => [
            ['image' => '/tattoo/assets/images/body-diagram-face.jpg', 'zones' => null],
        ],
    ],
    'hands_feet' => [
        'label'  => '손·발',
        'thumb'  => '/tattoo/assets/images/body-diagram-hands-feet.jpg',
        'panels' => [
            ['image' => '/tattoo/assets/images/body-diagram-hands-feet.jpg', 'zones' => null],
        ],
    ],
    'front_back' => [
        'label'  => '신체 앞면·뒷면',
        'thumb'  => '/tattoo/assets/images/body-diagram-front-back.jpg',
        'panels' => [
            ['image' => '/tattoo/assets/images/body-diagram-front-back.jpg', 'zones' => ['정면', '후면']],
        ],
    ],
    'face_body' => [
        'label'  => '얼굴+신체',
        'thumb'  => '/tattoo/assets/images/body-diagram-front-back.jpg',
        'panels' => [
            ['image' => '/tattoo/assets/images/body-diagram-front-back.jpg', 'zones' => ['정면', '후면']],
            ['image' => '/tattoo/assets/images/body-diagram-face.jpg', 'zones' => null],
        ],
    ],
];
