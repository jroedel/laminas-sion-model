<?php
namespace SionModel;

return [
    'view_helpers' => [  
        'factories' => [
        ],
        'invokables' => [
            'dayFormat'             => 'SionModel\I18n\View\Helper\DayFormat',
            'diffForHumans'         => 'SionModel\View\Helper\DiffForHumans',
            'email'					=> 'SionModel\View\Helper\Email',
            'jshrink'		        => 'SionModel\View\Helper\Jshrink',
            'shortDateRange'		=> 'SionModel\View\Helper\ShortDateRange',
            'telephone'				=> 'SionModel\View\Helper\Telephone',
            'telephoneList'			=> 'SionModel\View\Helper\TelephoneList',
            'tooltip'               => 'SionModel\View\Helper\Tooltip',
		],
	],
    'view_manager' => [
        'template_path_stack' => [
            'sionmodel' => __DIR__ . '/../view',
        ],
        'template_map' => [
        ],
    ],
];
