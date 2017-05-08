<?php
namespace SionModel;

return [
    'view_helpers' => [
        'factories' => [
            'address'				=> 'SionModel\Service\AddressFactory',
            'editPencil'			=> 'SionModel\Service\EditPencilFactory',
            'formatEntity'		    => 'SionModel\Service\FormatEntityFactory',
            'touchButton'			=> 'SionModel\Service\TouchButtonFactory',
        ],
        'invokables' => [
            'formRow'		        => 'SionModel\Form\View\Helper\SionFormRow',
            'dayFormat'             => 'SionModel\I18n\View\Helper\DayFormat',
            'debugEncoding'			=> 'SionModel\View\Helper\DebugEncoding',
            'diffForHumans'         => 'SionModel\View\Helper\DiffForHumans',
            'email'					=> 'SionModel\View\Helper\Email',
            'formatUrlObject'       => 'SionModel\View\Helper\FormatUrlObject',
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
            'SionModel\Form\SuggestForm'            => 'SionModel\Service\SuggestFormFactory',
            'SionModel\PersistentCache'             => 'SionModel\Service\PersistentCacheFactory',
            'SionModel\Problem\ProblemTable'        => 'SionModel\Service\ProblemTableFactory',
            'SionModel\Service\EntitiesService'     => 'SionModel\Service\EntitiesServiceFactory',
            'SionModel\Service\ProblemService'      => 'SionModel\Service\ProblemServiceFactory',
        ],
    ],
    'sion_model' => [
        'max_items_to_cache' => 2,
        'api_keys' => [], //users should specify long, random authentication keys here
        'post_place_line_format' => ':zip :cityState',
        'post_place_line_format_by_country' => [
            'US' => ':cityState :zip',
            'CL' => ':cityState :zip',
        ],
        'url_map' => [ //@todo clarify this, for general users
            'g+' => [
                'android'   => '%s',
                'ios'       => '%s',
                'default'   => '%s',
                'logo'      => 'img/g+.png',
                'label'     => 'G+',
            ],
            'skype' => [
                'android'   => 'skype:%s?call',
                'ios'       => 'skype:%s?call',
                'default'   => 'skype:%s?call',
                'logo'      => 'img/skype.png',
                'userKey'   => 'skypeUser',
                'label'     => 'Skype',
            ],
            'instagram' => [
                'android'   => 'https://www.instagram.com/%s',
                'ios'       => 'instagram://user?username=%s',
                'default'   => 'https://www.instagram.com/%s',
                'logo'      => 'img/instagram.png',
                'userKey'   => 'instagramUser',
                'label'     => 'Instagram',
            ],
            'slack' => [
                'android'   => 'https://schoenstatt-fathers.slack.com/messages/%s/',
                'ios'       => 'https://schoenstatt-fathers.slack.com/messages/%s/',
                'default'   => 'https://schoenstatt-fathers.slack.com/messages/%s/',
                'logo'      => 'img/slack.png',
                'userKey'   => 'slackUser',
                'label'     => 'Slack',
            ],
            'twitter' => [
                'android'   => 'https://twitter.com/%s',
                'ios'       => 'twitter://user?screen_name=%s',
                'default'   => 'https://twitter.com/%s',
                'logo'      => 'img/twitter.png',
                'userKey'   => 'twitterUser',
                'label'     => 'Twitter',
            ],
            'facebook' => [
                'android'   => '%s',
                'ios'       => '%s',
                'default'   => '%s',
                'logo'      => 'img/facebook.png',
                'userKey'   => 'facebookUrl',
                'label'     => 'Facebook',
            ],
            'wikipedia' => [
                'android'   => '%s',
                'ios'       => '%s',
                'default'   => '%s',
                'logo'      => 'img/wikipedia.png',
                'label'     => 'Wikipedia',
            ],
            'blog' => [
                'logo'      => 'img/blogger.png',
                'label'     => 'Blog',
            ],
        ],
//         'persistent_cache_config' => [
//             'adapter' => [
//                 'name' => 'filesystem',
//                 'options' => [
//                     'dirLevel' => 2,
//                     'cacheDir' => 'data/cache',
//                     'dirPermission' => 0755,
//                     'filePermission' => 0666,
//                     'namespaceSeparator' => '-db-'
//                 ],
//             ],
//             'plugins' => ['serializer'],//   - See more at: https://arjunphp.com/zend-framework-2-cache-example/#sthash.1P0kgSma.dpuf
//         ],
        'entities' => [
        ],
    ],
    'controllers' => [
        'invokables' => [
            'SionModel\Controller\Sion' => 'SionModel\Controller\SionModelController',
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
                    'clear-persistent-cache' => [
                        'type'    => 'Literal',
                        'options' => [
                            'route'    => '/clear-persistent-cache',
                            'defaults' => [
                                'action'     => 'clearPersistentCache',
                            ],
                        ],
                    ],
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
