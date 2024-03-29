<?php

declare(strict_types=1);

namespace SionModel;

use GeoIp2\Database\Reader;
use Laminas\Log\Logger;
use Laminas\Mail\Transport\Smtp;
use Laminas\Mail\Transport\TransportInterface;
use Laminas\Router\Http\Literal;
use Laminas\Router\Http\Segment;
use Laminas\ServiceManager\Proxy\LazyServiceFactory;
use Laminas\View\Helper\InlineScript;
use SionModel\Controller\LazyControllerFactory;
use SionModel\Form\Element\Phone;

return [
    'view_helpers'    => [
        'factories'  => [
            InlineScript::class        => Service\InlineScriptFactory::class,
            View\Helper\Address::class => Service\AddressFactory::class,
            View\Helper\Request::class => Service\RequestFactory::class,
            'editPencil'               => Service\EditPencilFactory::class,
            'formatEntity'             => Service\FormatEntityFactory::class,
            'touchButton'              => Service\TouchButtonFactory::class,
            'routeName'                => Service\RouteNameFactory::class,
            'geoIp2City'               => Service\GeoIp2ViewFactory::class,
        ],
        'invokables' => [
            'editPencilNew'   => View\Helper\EditPencilNew::class,
            'formRow'         => Form\View\Helper\SionFormRow::class,
            'dayFormat'       => I18n\View\Helper\DayFormat::class,
            'debugEncoding'   => View\Helper\DebugEncoding::class,
            'diffForHumans'   => View\Helper\DiffForHumans::class,
            'email'           => View\Helper\Email::class,
            'formatUrlObject' => View\Helper\FormatUrlObject::class,
            'helpBlock'       => View\Helper\HelpBlock::class,
            'jshrink'         => View\Helper\Jshrink::class,
            'shortDateRange'  => View\Helper\ShortDateRange::class,
            'telephone'       => View\Helper\Telephone::class,
            'telephoneList'   => View\Helper\TelephoneList::class,
            'tooltip'         => View\Helper\Tooltip::class,
            'ipPlace'         => View\Helper\IpPlace::class,
        ],
        'aliases'    => [
            'address' => View\Helper\Address::class,
            'request' => View\Helper\Request::class,
        ],
    ],
    'validators'      => [
        'invokables' => [
            'Skype'     => Validator\Skype::class,
            'Twitter'   => Validator\Twitter::class,
            'Instagram' => Validator\Instagram::class,
            'Phone'     => Validator\Phone::class,
            'Slack'     => Validator\Slack::class,
        ],
    ],
    'form_elements'   => [
        'invokables' => [
            'Phone' => Phone::class,
        ],
    ],
    'view_manager'    => [
        'template_map'        => include __DIR__ . '/template_map.config.php',
        'template_path_stack' => [
            'sion-model' => __DIR__ . '/../view',
        ],
    ],
    'controllers'     => [
        'abstract_factories' => [
            LazyControllerFactory::class,
        ],
    ],
    'service_manager' => [
        'invokables'    => [
            I18n\LanguageSupport::class => I18n\LanguageSupport::class,
        ],
        'factories'     => [
            'CountryValueOptions'           => Service\CountryValueOptionsFactory::class,
            'ExceptionsLogger'              => Service\ExceptionsLoggerFactory::class,
            'SionModel\Config'              => Service\ConfigServiceFactory::class,
            'SionModel\PersistentCache'     => Service\PersistentCacheFactory::class,
            'SionModel\Logger'              => Service\LoggerFactory::class,
            Db\Model\FilesTable::class      => Service\FilesTableFactory::class,
            Db\Model\PredicatesTable::class => Service\PredicatesTableFactory::class,
            Db\Model\MailingsTable::class   => Service\MailingsTableFactory::class,
            Form\SuggestForm::class         => Service\SuggestFormFactory::class,
            Problem\EntityProblem::class    => Service\EntityProblemFactory::class,
            Service\EntitiesService::class  => Service\EntitiesServiceFactory::class,
            Service\ProblemService::class   => Service\ProblemServiceFactory::class,
            Service\ChangesCollector::class => Service\ChangesCollectorFactory::class,
            Service\SionCacheService::class => Service\SionCacheServiceFactory::class,
            View\Helper\Address::class      => Service\AddressFactory::class,
            Mailing\Mailer::class           => Service\MailerFactory::class,
            Mvc\CspListener::class          => Service\CspListenerFactory::class,
            Service\ErrorHandling::class    => Service\ErrorHandlingFactory::class,
            Reader::class                   => Service\GeoIp2Factory::class,
            TransportInterface::class       => Service\MailTransportFactory::class,
        ],
        'aliases'       => [
            Smtp::class => TransportInterface::class,
        ],
        'lazy_services' => [
            // Mapping services to their class names is required
            // since the ServiceManager is not a declarative DIC.
            'class_map' => [
                Db\Model\FilesTable::class      => Db\Model\FilesTable::class,
                Db\Model\MailingsTable::class   => Db\Model\MailingsTable::class,
                Db\Model\PredicatesTable::class => Db\Model\PredicatesTable::class,
                Form\SuggestForm::class         => Form\SuggestForm::class,
                Mailing\Mailer::class           => Mailing\Mailer::class,
                Service\ChangesCollector::class => Service\ChangesCollector::class,
                Service\ProblemService::class   => Service\ProblemService::class,
            ],
        ],
        'delegators'    => [
            Db\Model\FilesTable::class      => [LazyServiceFactory::class],
            Db\Model\MailingsTable::class   => [LazyServiceFactory::class],
            Db\Model\PredicatesTable::class => [LazyServiceFactory::class],
            Form\SuggestForm::class         => [LazyServiceFactory::class],
            Mailing\Mailer::class           => [LazyServiceFactory::class],
            Service\ChangesCollector::class => [LazyServiceFactory::class],
            Service\ProblemService::class   => [LazyServiceFactory::class],
        ],
    ],
    'sion_model'      => [
        'logger_to_file_minimum_level'      => Logger::DEBUG,
        'logger_to_email_minimum_level'     => Logger::WARN,
        'logger_to_email_to_address'        => null,
        'logger_to_email_sender_address'    => null,
        'logger_to_email_subject'           => 'Logger message',
        'geoip2_database_file'              => 'data/GeoIP/GeoLite2-City.mmdb',
        'application_log_path'              => 'data/logs/application_{monthString}.log',
        'exceptions_log_path'               => 'data/logs/exceptions_{monthString}.log',
        'file_directory'                    => 'data/files',
        'public_file_directory'             => 'public/files',
        'max_items_to_cache'                => 2,
        'changes_max_rows'                  => 500,
        'changes_show_all'                  => true,
        'api_keys'                          => [], //users should specify long, random authentication keys here
        'post_place_line_format'            => ':zip :cityState',
        'post_place_line_format_by_country' => [
            'US' => ':cityState :zip',
            'CL' => ':cityState :zip',
        ],
        'sion_controller_services'          => [
            'SionModel\Config',
            Service\ProblemService::class,
            'SionModel\PersistentCache',
            Service\ChangesCollector::class,
        ],
        'url_map'                           => [ //@todo clarify this, for general users
            'g+'        => [
                'android' => '%s',
                'ios'     => '%s',
                'default' => '%s',
                'logo'    => 'img/g+.png',
                'label'   => 'G+',
            ],
            'skype'     => [
                'android' => 'skype:%s?call',
                'ios'     => 'skype:%s?call',
                'default' => 'skype:%s?call',
                'logo'    => 'img/skype.png',
                'userKey' => 'skypeUser',
                'label'   => 'Skype',
            ],
            'instagram' => [
                'android' => 'https://www.instagram.com/%s',
                'ios'     => 'instagram://user?username=%s',
                'default' => 'https://www.instagram.com/%s',
                'logo'    => 'img/instagram.png',
                'userKey' => 'instagramUser',
                'label'   => 'Instagram',
            ],
            'slack'     => [
                'android' => 'https://schoenstatt-fathers.slack.com/messages/%s/',
                'ios'     => 'https://schoenstatt-fathers.slack.com/messages/%s/',
                'default' => 'https://schoenstatt-fathers.slack.com/messages/%s/',
                'logo'    => 'img/slack.png',
                'userKey' => 'slackUser',
                'label'   => 'Slack',
            ],
            'twitter'   => [
                'android' => 'https://twitter.com/%s',
                'ios'     => 'twitter://user?screen_name=%s',
                'default' => 'https://twitter.com/%s',
                'logo'    => 'img/twitter.png',
                'userKey' => 'twitterUser',
                'label'   => 'Twitter',
            ],
            'facebook'  => [
                'android' => '%s',
                'ios'     => '%s',
                'default' => '%s',
                'logo'    => 'img/facebook.png',
                'userKey' => 'facebookUrl',
                'label'   => 'Facebook',
            ],
            'wikipedia' => [
                'android' => '%s',
                'ios'     => '%s',
                'default' => '%s',
                'logo'    => 'img/wikipedia.png',
                'label'   => 'Wikipedia',
            ],
            'blog'      => [
                'logo'  => 'img/blogger.png',
                'label' => 'Blog',
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
//   - See more at: https://arjunphp.com/zend-framework-2-cache-example/#sthash.1P0kgSma.dpuf
//             'plugins' => ['serializer'],
//         ],
        'entities' => [
            'mailing' => [
                'table_name'                    => 'a_data_mailing',
                'table_key'                     => 'MailingId',
                'entity_key_field'              => 'mailingId',
                'sion_model_class'              => Db\Model\MailingsTable::class,
                'get_object_function'           => 'getMailing',
                'required_columns_for_creation' => [
                    'toAddresses',
                ],
                'name_field'                    => 'mailingName',
                'name_field_is_translatable'    => false,
                'text_columns'                  => [],
                'date_columns'                  => [
                    'mailingOn',
                    'openedOn',
                    'queueUntil',
                ],
                'update_columns'                => [
                    //@todo add fromAddresses
                    'mailingId'           => 'MailingId',
                    'toAddresses'         => 'ToAddresses',
                    'ccAddresses'         => 'CcAddresses',
                    'bccAddresses'        => 'BccAddresses',
                    'replyToAddresses'    => 'ReplyToAddresses',
                    'mailingOn'           => 'MailingOn',
                    'mailingBy'           => 'MailingSentWithinRequestUser',
                    'subject'             => 'Subject',
                    'body'                => 'Body',
                    'sender'              => 'Sender',
                    'text'                => 'MailingText',
                    'tags'                => 'MailingTags',
                    'trackingToken'       => 'TrackingToken',
                    'openedFromIpAddress' => 'FirstOpenedFromIpAddress',
                    'openedFromHeaders'   => 'FirstOpenedFromHeaders',
                    'openedOn'            => 'FirstOpenedOn',
                    'emailTemplate'       => 'EmailTemplate',
                    'emailLocale'         => 'EmailLocale',
                ],
            ],
            /**
             * For more information on entity config:
             *
             * @see \SionModel\Entity\Entity
             */
//            'file'         => [
//                'name'             => 'file',
//                'table_name'       => 'files',
//                'table_key'        => 'FileId',
//                'entity_key_field' => 'fileId',
////                 'sion_model_class'                       => FilesTable::class,
//                'get_object_function'  => 'getFile',
//                'get_objects_function' => 'getFiles',
////                 'format_view_helper'                    => 'formatEvent',
//                'required_columns_for_creation' => [
//                    'originalFileName',
//                    'mimeType',
//                    'size',
//                    'sha1',
//                ],
//                'name_field'                    => 'originalFileName',
//                'name_field_is_translatable'    => false,
////                 'country_field'                          => 'country',
//                'text_columns'               => [],
//                'many_to_one_update_columns' => [
////                     'email'  => 'contactInfo',
////                     'cell'   => 'contactInfo',
//                ],
//                'report_changes'             => true,
//                'index_route'                => 'files',
////                 'index_template'                         => 'project/events/index',
////                 'show_action_template'                   => 'project/events/show',
////                 'show_route'                             => 'files/files',
////                 'edit_action_form'                       => 'SionModel\Form\EditFileForm',
////                 'edit_action_template'                   => 'project/events/edit',
////                 'edit_route'                             => 'events/event/edit',
////                 'create_action_form'                     => 'SionModel\Form\UploadFileForm',
////                 'create_action_valid_data_handler'       => 'createEvent',
////                 'create_action_redirect_route'           => 'files/file',
////                 'create_action_template'                 => 'project/events/create',
////                 'touch_json_route'                       => 'events/event/touch',
//                'database_bound_data_preprocessor' => 'preprocessFile',
////                 'database_bound_data_postprocessor'  => 'postprocessEvent',
////                 'moderate_route'                         => 'events/event/moderate',
////                 'suggest_form'                           => 'Project\Form\SuggestEventForm',
//                'enable_delete_action' => true,
////                 'delete_action_acl_resource'             => 'event_:id',
////                 'delete_action_acl_permission'           => 'delete_event',
////                 'delete_action_redirect_route'           => 'events',
//                'update_columns' => [
//                    'fileId'                 => 'FileId',
//                    'storeFileName'          => 'StoreFileName',
//                    'originalFileName'       => 'OriginalFileName',
//                    'fileKind'               => 'FileKind',
//                    'description'            => 'Description',
//                    'size'                   => 'Size',
//                    'sha1'                   => 'Sha1',
//                    'contentTags'            => 'ContentTags',
//                    'structureTags'          => 'StructureTags',
//                    'mimeType'               => 'MimeType',
//                    'isPublic'               => 'IsPublic',
//                    'isEncrypted'            => 'IsEncrypted',
//                    'encryptedEncryptionKey' => 'EncryptedEncryptionKey',
//                    'createdOn'              => 'CreatedOn',
//                    'createdBy'              => 'CreatedBy',
//                    'updatedOn'              => 'UpdatedOn',
//                    'updatedBy'              => 'UpdatedBy',
//                ],
//            ],
//            'file-entity'  => [
//                'name'             => 'file-entity',
//                'table_name'       => 'files_entities',
//                'table_key'        => 'FileEntityId',
//                'entity_key_field' => 'fileEntityId',
////                 'sion_model_class'                       => FilesTable::class,
//                'get_object_function'  => 'getFileEntity',
//                'get_objects_function' => 'getFileEntities',
//                'required_columns_for_creation' => [
//                    'title',
//                ],
//                'name_field'                    => 'fileName',
//                'name_field_is_translatable'    => false,
//                'report_changes'               => true,
//                'index_route'                  => 'files',
//                'default_route_params'         => ['file_id' => 'fileId'],
//                'show_route'                   => 'files/files',
//                'edit_action_form'             => 'SionModel\Form\EditFileForm',
//                'edit_action_template'         => 'project/events/edit',
//                'edit_route'                   => 'files/file/edit',
//                'create_action_form'           => 'SionModel\Form\UploadFileForm',
//                'create_action_redirect_route' => 'files/file',
//                'enable_delete_action'         => true,
//                'delete_action_acl_resource'   => 'event_:id',
//                'delete_action_acl_permission' => 'delete_event',
//                'delete_action_redirect_route' => 'events',
//                'update_columns'               => [
//                    'fileId'        => 'FileId',
//                    'fileName'      => 'FileName',
//                    'mimeType'      => 'MimeType',
//                    'extension'     => 'Extension',
//                    'description'   => 'Description',
//                    'fullText'      => 'FullText',
//                    'size'          => 'Size',
//                    'md5'           => 'MD5',
//                    'contentTags'   => 'ContentTags',
//                    'structureTags' => 'StructureTags',
//                    'createdOn'     => 'CreatedOn',
//                    'createdBy'     => 'CreatedBy',
//                    'updatedOn'     => 'UpdatedOn',
//                    'updatedBy'     => 'UpdatedBy',
//                ],
//            ],
            'comment'      => [
                'name'                              => 'comment',
                'table_name'                        => 'comments',
                'table_key'                         => 'CommentId',
                'entity_key_field'                  => 'commentId',
                'sion_model_class'                  => Db\Model\PredicatesTable::class,
                'get_object_function'               => 'getComment',
                'get_objects_function'              => 'getComments',
                'row_processor_function'            => 'processCommentRow',
                'required_columns_for_creation'     => [
                    'comment',
                    'kind',
                    'status',
                ],
                'name_field'                        => 'comment',
                'name_field_is_translatable'        => false,
                'report_changes'                    => false,
                'create_action_form'                => Form\CommentForm::class,
                'database_bound_data_postprocessor' => 'postprocessComment',
                'enable_delete_action'              => true,
                'update_columns'                    => [
                    'commentId'  => 'CommentId',
                    'rating'     => 'Rating',
                    'kind'       => 'CommentKind',
                    'comment'    => 'Comment',
                    'status'     => 'Status',
                    'reviewedBy' => 'ReviewedBy',
                    'reviewedOn' => 'ReviewedOn',
                    'createdOn'  => 'CreatedOn',
                    'createdBy'  => 'CreatedBy',
                ],
            ],
            'predicate'    => [
                'name'                          => 'predicate',
                'table_name'                    => 'predicates',
                'table_key'                     => 'PredicateKind',
                'entity_key_field'              => 'predicateKind',
                'row_processor_function'        => 'processPredicateRow',
                'sion_model_class'              => Db\Model\PredicatesTable::class,
                'required_columns_for_creation' => [
                    'predicateKind',
                    'subjectEntityKind',
                    'objectEntityKind',
                    'text',
                ],
                'name_field'                    => 'text',
                'name_field_is_translatable'    => true,
                'report_changes'                => false,
                'enable_delete_action'          => false,
                'update_columns'                => [
                    'predicateKind'     => 'PredicateKind',
                    'subjectEntityKind' => 'SubjectEntityKind',
                    'objectEntityKind'  => 'ObjectEntityKind',
                    'text'              => 'PredicateText',
                    'description'       => 'DescriptionEn',
                ],
            ],
            'relationship' => [
                'name'                          => 'relationship',
                'table_name'                    => 'relationships',
                'table_key'                     => 'RelationshipId',
                'entity_key_field'              => 'relationshipId',
                'sion_model_class'              => Db\Model\PredicatesTable::class,
                'row_processor_function'        => 'processRelationshipRow',
                'required_columns_for_creation' => [
                    'subjectEntityId',
                    'objectEntityId',
                    'predicateKind',
                ],
                'name_field'                    => 'comment',
                'name_field_is_translatable'    => false,
                'report_changes'                => false,
                'enable_delete_action'          => true,
                'update_columns'                => [
                    'relationshipId'       => 'RelationshipId',
                    'subjectEntityId'      => 'SubjectEntityId',
                    'objectEntityId'       => 'ObjectEntityId',
                    'predicateKind'        => 'PredicateKind',
                    'priority'             => 'Priority',
                    'publicNotes'          => 'PublicNotes',
                    'publicNotesUpdatedOn' => 'PublicNotesUpdatedOn',
                    'publicNotesUpdatedBy' => 'PublicNotesUpdatedBy',
                    'adminNotes'           => 'AdminNotes',
                    'adminNotesUpdatedOn'  => 'AdminNotesUpdatedOn',
                    'adminNotesUpdatedBy'  => 'AdminNotesUpdatedBy',
                    'updatedOn'            => 'UpdatedOn',
                    'updatedBy'            => 'UpdatedBy',
                ],
            ],
        ],
    ],
    'router'          => [
        'routes' => [
            'comments'   => [
                'type'          => Literal::class,
                'options'       => [
                    'route'    => '/comments',
                    'defaults' => [
                        'controller' => Controller\CommentController::class,
                    ],
                ],
                'may_terminate' => false,
                'child_routes'  => [
                    'create' => [
                        'type'    => Segment::class,
                        'options' => [
                            'route'       => '/create/:entity/:entity_id[/:kind]',
                            'defaults'    => [
                                'action' => 'create',
                                'kind'   => 'comment',
                            ],
                            'constraints' => [
                                'entity_id' => '[0-9]{1,5}',
                                'entity'    => '[a-zA-Z_-]{1,25}',
                                'kind'      => '(comment|review|rating)',
                            ],
                        ],
                    ],
                ],
            ],
            'sion-model' => [
                'type'          => Literal::class,
                'options'       => [
                    'route'    => '/sm',
                    'defaults' => [
                        'controller' => Controller\SionModelController::class,
                        'action'     => 'index',
                    ],
                ],
                'may_terminate' => false,
                'child_routes'  => [
                    'phpinfo'                => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/phpinfo',
                            'defaults' => [
                                'action' => 'phpinfo',
                            ],
                        ],
                    ],
                    'clear-persistent-cache' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/clear-persistent-cache',
                            'defaults' => [
                                'action' => 'clearPersistentCache',
                            ],
                        ],
                    ],
                    'data-problems'          => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/data-problems',
                            'defaults' => [
                                'action' => 'dataProblems',
                            ],
                        ],
                    ],
                    'auto-fix-data-problems' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/auto-fix-data-problems',
                            'defaults' => [
                                'action' => 'autoFixDataProblems',
                            ],
                        ],
                    ],
                    'view-changes'           => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/view-changes',
                            'defaults' => [
                                'action' => 'viewChanges',
                            ],
                        ],
                    ],
                    'delete-entity'          => [
                        'type'    => Segment::class,
                        'options' => [
                            'route'       => '/delete/:entity/:entity_id',
                            'defaults'    => [
                                'action' => 'deleteEntity',
                            ],
                            'constraints' => [
                                'entity_id' => '[0-9]{1,5}',
                                'entity'    => '[a-zA-Z_-]{1,25}',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
