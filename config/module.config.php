<?php
namespace SionModel;

use SionModel\Service\AddressFactory;
use SionModel\Service\EditPencilFactory;
use SionModel\Service\FormatEntityFactory;
use SionModel\Service\TouchButtonFactory;
use SionModel\Form\View\Helper\SionFormRow;
use SionModel\I18n\View\Helper\DayFormat;
use SionModel\View\Helper\DebugEncoding;
use SionModel\View\Helper\DiffForHumans;
use SionModel\View\Helper\Email;
use SionModel\View\Helper\FormatUrlObject;
use SionModel\View\Helper\Jshrink;
use SionModel\View\Helper\ShortDateRange;
use SionModel\View\Helper\Telephone;
use SionModel\View\Helper\TelephoneList;
use SionModel\View\Helper\Tooltip;
use SionModel\Validator\Skype;
use SionModel\Validator\Twitter;
use SionModel\Validator\Instagram;
use SionModel\Validator\Phone;
use SionModel\Validator\Slack;
use SionModel\Service\CountryValueOptionsFactory;
use SionModel\Service\ConfigServiceFactory;
use SionModel\Service\FilesTableFactory;
use SionModel\Db\Model\FilesTable;
use SionModel\Form\SuggestForm;
use SionModel\Service\SuggestFormFactory;
use SionModel\Service\PersistentCacheFactory;
use SionModel\Service\ProblemTableFactory;
use SionModel\Problem\ProblemTable;
use SionModel\Service\EntitiesServiceFactory;
use SionModel\Service\EntitiesService;
use SionModel\Service\ProblemServiceFactory;
use SionModel\Service\AllChangesServiceFactory;
use SionModel\Service\MailerFactory;
use SionModel\Mailing\Mailer;
use SionModel\Service\ProblemService;
use SionModel\Controller\SionModelController;
use Zend\Router\Http\Literal;
use Zend\Router\Http\Segment;
use SionModel\Service\ControllerNameFactory;
use SionModel\Service\RouteNameFactory;
use SionModel\Controller\SionControllerFactory;
use Zend\ServiceManager\Proxy\LazyServiceFactory;

return [
    'view_helpers' => [
        'factories' => [
            'address'				=> AddressFactory::class,
            'editPencil'			=> EditPencilFactory::class,
            'formatEntity'		    => FormatEntityFactory::class,
            'touchButton'			=> TouchButtonFactory::class,
            'controllerName'        => ControllerNameFactory::class,
            'routeName'             => RouteNameFactory::class,
        ],
        'invokables' => [
            'formRow'		        => SionFormRow::class,
            'dayFormat'             => DayFormat::class,
            'debugEncoding'			=> DebugEncoding::class,
            'diffForHumans'         => DiffForHumans::class,
            'email'					=> Email::class,
            'formatUrlObject'       => FormatUrlObject::class,
            'jshrink'		        => Jshrink::class,
            'shortDateRange'		=> ShortDateRange::class,
            'telephone'				=> Telephone::class,
            'telephoneList'			=> TelephoneList::class,
            'tooltip'               => Tooltip::class,
		],
	],
    'validators' => [
        'invokables' => [
            'Skype'     => Skype::class,
            'Twitter'   => Twitter::class,
            'Instagram' => Instagram::class,
            'Phone'     => Phone::class,
            'Slack'     => Slack::class,
         ],
    ],
    'form_elements' => [
        'invokables' => [
            'Phone' => \SionModel\Form\Element\Phone::class,
        ],
    ],
    'view_manager' => [
        'template_map' => include __DIR__ . '/template_map.config.php',
        'template_path_stack' => [
            'sion-model' => __DIR__ . '/../view',
        ],
    ],
    'controllers' => [
        'invokables' => [
            //SionModelController::class => SionModelController::class,
        ],
        'abstract_factories' => [
            SionControllerFactory::class,
            \SionModel\Controller\LazyControllerFactory::class,
        ],
    ],
    'service_manager' => [
        'factories' => [
            'CountryValueOptions'           => CountryValueOptionsFactory::class,
            'SionModel\Config'              => ConfigServiceFactory::class,
            FilesTable::class               => FilesTableFactory::class,
            SuggestForm::class              => SuggestFormFactory::class,
            'SionModel\PersistentCache'     => PersistentCacheFactory::class,
            ProblemTable::class             => ProblemTableFactory::class,
            EntitiesService::class          => EntitiesServiceFactory::class,
            ProblemService::class           => ProblemServiceFactory::class,
            'SionModel\Service\AllChanges'  => AllChangesServiceFactory::class,
            Mailer::class                   => MailerFactory::class,
        ],
        'lazy_services' => [
            // Mapping services to their class names is required
            // since the ServiceManager is not a declarative DIC.
            'class_map' => [
                FilesTable::class => FilesTable::class,
                Mailer::class => Mailer::class,
                ProblemService::class => Mailer::class,
                ProblemTable::class => Mailer::class,
                SuggestForm::class => Mailer::class,
            ],
        ],
        'delegators' => [
            FilesTable::class => [
                LazyServiceFactory::class,
            ],
            Mailer::class => [
                LazyServiceFactory::class,
            ],
            ProblemService::class => [
                LazyServiceFactory::class,
            ],
            ProblemTable::class => [
                LazyServiceFactory::class,
            ],
            SuggestForm::class => [
                LazyServiceFactory::class,
            ],
        ],
    ],
    'sion_model' => [
        'file_directory'            => 'data/files',
        'public_file_directory'     => 'public/files',
        'max_items_to_cache'        => 2,
        'changes_max_rows'          => 500,
        'changes_show_all'          => true,
        'api_keys'                  => [], //users should specify long, random authentication keys here
        'post_place_line_format'    => ':zip :cityState',
        'post_place_line_format_by_country' => [
            'US' => ':cityState :zip',
            'CL' => ':cityState :zip',
        ],
        'url_map'                   => [ //@todo clarify this, for general users
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
            'mailing' => [
                'table_name' => 'mailings',
                'table_key' => 'MailingId',
                'entity_key_field' => 'mailingId',
                'has_dedicated_suggest_form' => false,
                'get_object_function' => 'getMailing',
                'required_columns_for_creation' => [
                    'toAddresses',
                    'status',
                ],
                'name_field' => 'mailingName',
                'name_field_is_translateable' => false,
                //                 'moderate_route' => 'courses/course/moderate',
                //                 'moderate_route_entity_key' => 'course_id',
                'text_columns' => [
                ],
                'date_columns' => [
                    'mailingOn',
                    'openedOn',
                    'queueUntil',
                ],
                'update_columns' => [
                    'mailingId'             => 'MailingId',
                    'toAddresses'           => 'ToAddresses',
                    'mailingOn'             => 'MailingOn',
                    'mailingBy'             => 'MailingBy',
                    'subject'               => 'Subject',
                    'body'                  => 'Body',
                    'sender'                => 'Sender',
                    'text'                  => 'MailingText',
                    'tags'                  => 'MailingTags',
                    'trackingToken'         => 'TrackingToken',
                    'openedFromIpAddress'   => 'OpenedFromIpAddress',
                    'openedFromHeaders'     => 'OpenedFromHeaders',
                    'openedOn'              => 'OpenedOn',
                    'emailTemplate'         => 'EmailTemplate',
                    'emailLocale'           => 'EmailLocale',
                    'status'                => 'Status',
                    'attempt'               => 'Attempt',
                    'maxAttempts'           => 'MaxAttempts',
                    'queueUntil'            => 'QueueUntil',
                    'errorMessage'          => 'ErrorMessage',
                    'stackTrace'            => 'StackTrace',
                ],
            ],
            /**
             * For more information on entity config:
             * @see \SionModel\Entity\Entity
             */
            'file' => [
                'name'									=> 'file',
                'table_name' 							=> 'files',
                'table_key' 							=> 'FileId',
                'entity_key_field'               		=> 'fileId',
                'sion_model_class'               		=> FilesTable::class,
                'get_object_function' 					=> 'getFile',
                'get_objects_function'               	=> 'getFiles',
//                 'format_view_helper'                    => 'formatEvent',
                'required_columns_for_creation' 		=> [
                    'originalFileName',
                    'mimeType',
                    'size',
                    'sha1',
                ],
                'name_field'               				=> 'originalFileName',
                'name_field_is_translateable'           => false,
//                 'country_field'               			=> 'country',
                'text_columns'               			=> [],
                'many_to_one_update_columns'     		=> [
//                     'email'	=> 'contactInfo',
//                     'cell'	=> 'contactInfo',
                ],
                'report_changes'               			=> true,
                'index_route'               			=> 'files',
//                 'index_template'               			=> 'project/events/index',
//                 'default_route_key'                     => 'file_id',
//                 'show_action_template'               	=> 'project/events/show',
//                 'show_route' 							=> 'files/files',
//                 'show_route_key' 						=> 'file_id',
//                 'show_route_key_field' 					=> 'fileId',
//                 'edit_action_form'               		=> 'SionModel\Form\EditFileForm',
//                 'edit_action_template'               	=> 'project/events/edit',
//                 'edit_route'               				=> 'events/event/edit',
//                 'edit_route_key'               			=> 'file_id',
//                 'edit_route_key_field'           		=> 'fileId',
//                 'create_action_form'              		=> 'SionModel\Form\UploadFileForm',
//                 'create_action_valid_data_handler'		=> 'createEvent',
//                 'create_action_redirect_route'         	=> 'files/file',
//                 'create_action_redirect_route_key'    	=> 'file_id',
//                 'create_action_redirect_route_key_field'=> 'fileId',
//                 'create_action_template'           		=> 'project/events/create',
//                 'touch_default_field'               	=> 'fileId',
//                 'touch_field_route_key'           		=> 'event_id',
//                 'touch_json_route'               		=> 'events/event/touch',
//                 'touch_json_route_key'            		=> 'file_id',
                'database_bound_data_preprocessor' 		=> 'preprocessFile',
//                 'database_bound_data_postprocessor' 	=> 'postprocessEvent',
//                 'moderate_route' 						=> 'events/event/moderate',
//                 'moderate_route_entity_key' 			=> 'file_id',
//                 'has_dedicated_suggest_form' 			=> false,
//                 'suggest_form'               			=> 'Project\Form\SuggestEventForm',
                'enable_delete_action' 					=> true,
//                 'delete_action_acl_resource' 			=> 'event_:id',
//                 'delete_action_acl_permission' 			=> 'delete_event',
//                 'delete_action_redirect_route' 			=> 'events',
                'update_columns' 						=> [
                    'fileId'                => 'FileId',
                    'storeFileName'         => 'StoreFileName',
                    'originalFileName'      => 'OriginalFileName',
                    'fileKind'              => 'FileKind',
                    'description'           => 'Description',
                    'size'                  => 'Size',
                    'sha1'                  => 'Sha1',
                    'contentTags'           => 'ContentTags',
                    'structureTags'         => 'StructureTags',
                    'mimeType'              => 'MimeType',
                    'isPublic'              => 'IsPublic',
                    'isEncrypted'           => 'IsEncrypted',
                    'encryptedEncryptionKey'=> 'EncryptedEncryptionKey',
                    'createdOn'             => 'CreatedOn',
                    'createdBy'             => 'CreatedBy',
                    'updatedOn'             => 'UpdatedOn',
                    'createdBy'             => 'UpdatedBy',
                ],
            ],
            'file-entity' => [
                'name'									=> 'file-entity',
                'table_name' 							=> 'files_entities',
                'table_key' 							=> 'FileEntityId',
                'entity_key_field'               		=> 'fileEntityId',
                'sion_model_class'               		=> FilesTable::class,
                'get_object_function' 					=> 'getFileEntity',
                'get_objects_function'               	=> 'getFileEntities',
                //                 'format_view_helper'                    => 'formatEvent',
                'required_columns_for_creation' 		=> [
                    'title'
                ],
                'name_field'               				=> 'fileName',
                'name_field_is_translateable'           => false,
//                 'country_field'               			=> 'country',
                'text_columns'               			=> [],
                'many_to_one_update_columns'     		=> [
//                     'email'	=> 'contactInfo',
//                     'cell'	=> 'contactInfo',
                ],
                'report_changes'               			=> true,
                'index_route'               			=> 'files',
//                 'index_template'               			=> 'project/events/index',
                'default_route_key'                     => 'file_id',
//                 'show_action_template'               	=> 'project/events/show',
                'show_route' 							=> 'files/files',
                'show_route_key' 						=> 'file_id',
                'show_route_key_field' 					=> 'fileId',
                'edit_action_form'               		=> 'SionModel\Form\EditFileForm',
                'edit_action_template'               	=> 'project/events/edit',
                'edit_route'               				=> 'files/file/edit',
                'edit_route_key'               			=> 'file_id',
                'edit_route_key_field'           		=> 'fileId',
                'create_action_form'              		=> 'SionModel\Form\UploadFileForm',
//                 'create_action_valid_data_handler'		=> 'createEvent',
                'create_action_redirect_route'         	=> 'files/file',
                'create_action_redirect_route_key'    	=> 'file_id',
                'create_action_redirect_route_key_field'=> 'fileId',
//                 'create_action_template'           		=> 'project/events/create',
//                 'touch_default_field'               	=> 'fileId',
//                 'touch_field_route_key'           		=> 'event_id',
//                 'touch_json_route'               		=> 'events/event/touch',
//                 'touch_json_route_key'            		=> 'file_id',
//                 'database_bound_data_preprocessor' 		=> 'preprocessEvent',
//                 'database_bound_data_postprocessor' 	=> 'postprocessEvent',
//                 'moderate_route' 						=> 'events/event/moderate',
//                 'moderate_route_entity_key' 			=> 'file_id',
//                 'has_dedicated_suggest_form' 			=> false,
//                 'suggest_form'               			=> 'Project\Form\SuggestEventForm',
                'enable_delete_action' 					=> true,
                'delete_action_acl_resource' 			=> 'event_:id',
                'delete_action_acl_permission' 			=> 'delete_event',
                'delete_action_redirect_route' 			=> 'events',
                'update_columns' 						=> [
                    'fileId'        => 'FileId',
                    'fileName'      => 'FileName',
                    'mimeType'      => 'MimeType',
                    'extension'     => 'Extension',
                    'description'   => 'Description',
                    'fullText'      => 'FullText',
                    'size'          => 'Size',
                    'md5'           => 'MD5',
                    'contentTags'   => 'ContentTags',
                    'structureTags' => 'StructureTags',
                    'createdOn'     => 'CreatedOn',
                    'createdBy'     => 'CreatedBy',
                    'updatedOn'     => 'UpdatedOn',
                    'createdBy'     => 'UpdatedBy',
                ],
            ],
        ],
    ],
    'router' => [
        'routes' => [
            'sion-model' => [
                'type'    => Literal::class,
                'options' => [
                    'route'    => '/sm',
                    'defaults' => [
                        'controller' => SionModelController::class,
                        'action'     => 'index',
                    ],
                ],
                'may_terminate' => false,
                'child_routes' => [
                    'clear-persistent-cache' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/clear-persistent-cache',
                            'defaults' => [
                                'action'     => 'clearPersistentCache',
                            ],
                        ],
                    ],
                    'data-problems' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/data-problems',
                            'defaults' => [
                                'action'     => 'dataProblems',
                            ],
                        ],
                    ],
                    'auto-fix-data-problems' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/auto-fix-data-problems',
                            'defaults' => [
                                'action'     => 'autoFixDataProblems',
                            ],
                        ],
                    ],
                    'view-changes' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/view-changes',
                            'defaults' => [
                                'action'     => 'viewChanges',
                            ],
                        ],
                    ],
                    'delete-entity' => [
                        'type'    => Segment::class,
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
