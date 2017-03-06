<?php
namespace SionModel;

return [
    'view_helpers' => [
        'factories' => [
            'address'				=> 'SionModel\Service\AddressFactory',
            'editPencil'			=> 'SionModel\Service\EditPencilFactory',
            'formatEntity'		    => 'SionModel\Service\FormatEntityFactory',
        ],
        'invokables' => [
            'formRow'		        => 'SionModel\Form\View\Helper\SionFormRow',
            'dayFormat'             => 'SionModel\I18n\View\Helper\DayFormat',
            'debugEncoding'			=> 'SionModel\View\Helper\DebugEncoding',
            'diffForHumans'         => 'SionModel\View\Helper\DiffForHumans',
            'email'					=> 'SionModel\View\Helper\Email',
            'jshrink'		        => 'SionModel\View\Helper\Jshrink',
            'shortDateRange'		=> 'SionModel\View\Helper\ShortDateRange',
            'telephone'				=> 'SionModel\View\Helper\Telephone',
            'telephoneList'			=> 'SionModel\View\Helper\TelephoneList',
            'tooltip'               => 'SionModel\View\Helper\Tooltip',
		],
	],
    'validators' => [
        'invokables' => [
            'Skype'     => 'SionModel\Validator\Skype',
            'Twitter'   => 'SionModel\Validator\Twitter',
            'Instagram' => 'SionModel\Validator\Instagram',
            'Phone'     => 'SionModel\Validator\Phone',
            'Slack'     => 'SionModel\Validator\Slack',
         ],
    ],
    'form_elements' => [
        'invokables' => [
            'Phone' => 'SionModel\Form\Element\Phone',
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            'sion-model' => __DIR__ . '/../view',
        ],
    ],
    'service_manager' => [
        'factories' => [
            'CountryValueOptions'                   => 'SionModel\Service\CountryValueOptionsFactory',
            'SionModel\Config'                      => 'SionModel\Service\ConfigServiceFactory',
            'SionModel\Service\EntitiesService'     => 'SionModel\Service\EntitiesServiceFactory',
            'SionModel\Problem\ProblemTable'        => 'SionModel\Service\ProblemTableFactory',
            'SionModel\Service\ProblemService'      => 'SionModel\Service\ProblemServiceFactory',
            'SionModel\Form\SuggestForm'            => 'SionModel\Service\SuggestFormFactory',
        ],
    ],
    'sion_model' => [
        'post_place_line_format' => ':zip :cityState',
        'post_place_line_format_by_country' => [
            'US' => ':cityState :zip',
            'CL' => ':cityState :zip',
        ],
        'entities' => [
            'problem' => [
//                 'table_name' => 'event',
                'table_key' => 'event_id',
                'scope' => 'problem',
                'get_object_function' => 'getProblem',
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
    'controllers' => [
        'invokables' => [
            'SionModel\Controller\Sion'    => 'SionModel\Controller\SionModelController',
        ],
    ],
    'router' => [
        'routes' => [
            'sion-model' => [
                'type'    => 'Literal',
                'options' => [
                    'route'    => '/sm',
                    'defaults' => [
                        'controller' => 'SionModel\Controller\Sion',
                        'action'     => 'index',
                    ],
                ],
                'may_terminate' => false,
                'child_routes' => [
                    'data-problems' => [
                        'type'    => 'Literal',
                        'options' => [
                            'route'    => '/data-problems',
                            'defaults' => [
                                'action'     => 'dataProblems',
                            ],
                        ],
                    ],
                    'auto-fix-data-problems' => [
                        'type'    => 'Literal',
                        'options' => [
                            'route'    => '/auto-fix-data-problems',
                            'defaults' => [
                                'action'     => 'autoFixDataProblems',
                            ],
                        ],
                    ],
                    'view-changes' => [
                        'type'    => 'Literal',
                        'options' => [
                            'route'    => '/view-changes',
                            'defaults' => [
                                'action'     => 'viewChanges',
                            ],
                        ],
                    ],
                    'delete-entity' => [
                        'type'    => 'Segment',
                        'options' => [
                            'route'    => '/delete/:entity/:entity_id',
                            'defaults' => [
                                'action' => 'deleteEntity',
                            ],
                            'constraints' => [
                                'entity_id' => '[0-9]{1,5}',
                                'entity' => '[a-zA-Z_-]{1,25}',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
