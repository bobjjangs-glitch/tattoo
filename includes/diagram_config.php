<?php
// includes/diagram_config.php
// consent-edit.php(관리자 도해 선택 화면)는 thumb / panels를 사용하고
// diagram_render.php(고객 서명 화면)는 images / zones를 사용하므로
// 두 화면 모두를 위해 모든 키를 함께 정의한다.
return [
    'none' => [
        'label'  => '도해 없음',
        'thumb'  => null,
        'images' => [],
        'zones'  => null,
        'panels' => [],
    ],
    'scalp' => [
        'label'  => '두피·모발',
        'thumb'  => '/tattoo/assets/images/body-diagram-scalp.jpg',
        'images' => ['/tattoo/assets/images/body-diagram-scalp.jpg'],
        'zones'  => null,
        'panels' => [
            ['image' => '/tattoo/assets/images/body-diagram-scalp.jpg', 'zones' => null],
        ],
    ],
    'face' => [
        'label'  => '얼굴',
        'thumb'  => '/tattoo/assets/images/body-diagram-face.jpg',
        'images' => ['/tattoo/assets/images/body-diagram-face.jpg'],
        'zones'  => null,
        'panels' => [
            ['image' => '/tattoo/assets/images/body-diagram-face.jpg', 'zones' => null],
        ],
    ],
    'hands_feet' => [
        'label'  => '손·발',
        'thumb'  => '/tattoo/assets/images/body-diagram-hands-feet.jpg',
        'images' => ['/tattoo/assets/images/body-diagram-hands-feet.jpg'],
        'zones'  => null,
        'panels' => [
            ['image' => '/tattoo/assets/images/body-diagram-hands-feet.jpg', 'zones' => null],
        ],
    ],
    'front_back' => [
        'label'  => '신체 앞면·뒷면',
        'thumb'  => '/tattoo/assets/images/body-diagram-front-back.jpg',
        'images' => ['/tattoo/assets/images/body-diagram-front-back.jpg'],
        'zones'  => ['정면', '후면'],
        'panels' => [
            ['image' => '/tattoo/assets/images/body-diagram-front-back.jpg', 'zones' => ['정면', '후면']],
        ],
    ],
    'face_body' => [
        'label'  => '얼굴+신체',
        'thumb'  => '/tattoo/assets/images/body-diagram-front-back.jpg',
        'images' => [
            '/tattoo/assets/images/body-diagram-front-back.jpg',
            '/tattoo/assets/images/body-diagram-face.jpg',
        ],
        'zones'  => ['정면', '후면'],
        'panels' => [
            ['image' => '/tattoo/assets/images/body-diagram-front-back.jpg', 'zones' => ['정면', '후면']],
            ['image' => '/tattoo/assets/images/body-diagram-face.jpg', 'zones' => null],
        ],
    ],
];
