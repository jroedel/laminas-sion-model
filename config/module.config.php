<?php
namespace SionModel;

return [
    'view_helpers' => [
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
    ],
    'service_manager' => [
        'factories' => [
            'SionModel\Config'                      => 'SionModel\Service\ConfigServiceFactory',
            'SionModel\Service\EntitiesService'     => 'SionModel\Service\EntitiesServiceFactory',
            'SionModel\Problem\ProblemTable'        => 'SionModel\Service\ProblemTableFactory',
            'SionModel\Service\ProblemService'      => 'SionModel\Service\ProblemServiceFactory',
        ],
    ],
    'sion_model' => [
        'entities' => [
            'problem' => [
//                 'table_name' => 'event',
                'table_key' => 'event_id',
                'scope' => 'problem',
                'update_reference_data_function' => 'getProblem',
                'required_columns_for_creation' => [
        	        'project',
                    'entity',
                    'entityId',
                    'problem',
        	    ],
                'name_column' => 'problemName',
                'date_columns' => [
                    'ignoredOn',
                    'resolvedOn',
                    'updatedOn',
                    'createdOn',
                ],
                'update_columns' => [
                    'problemId' => 'ProblemId',
                    'project' => 'Project',
                    'entity' => 'Entity',
                    'entityId' => 'EntityId',
                    'problem' => 'Problem',
                    'severity' => 'Severity',
                    'ignoredOn' => 'IgnoredOn',
                    'ignoredBy' => 'IgnoredBy',
                    'resolvedOn' => 'ResolvedOn',
                    'resolvedBy' => 'ResolvedBy',
                    'updatedOn' => 'UpdatedOn',
                    'updatedBy' => 'UpdatedBy',
                    'createdOn' => 'CreatedOn',
                    'createdBy' => 'CreatedBy',
        	    ],
            ],
        ],
    ],
];
