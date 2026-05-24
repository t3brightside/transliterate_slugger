<?php
$EM_CONF[$_EXTKEY] = [
    'title' => 'Transliterate Slugger',
    'description' => 'Native PHP ICU transliteration for TYPO3 slug fields and file names.',
    'category' => 'be',
    'author' => 'Tanel Põld',
    'author_email' => 'tanel@brightside.ee',
    'author_company' => 'Brightside OÜ / t3brightside.com',
    'state' => 'stable',
    'version' => '0.3.1',
    'constraints' => [
        'depends' => [
            'typo3' => '14.0.0 - 14.9.99',
        ],
    ],
];