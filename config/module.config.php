<?php

namespace SionModel;

use SionModel\Controller\LazyControllerFactory;
use SionModel\Form\Element\Phone;
use Laminas\Router\Http\Literal;
use Laminas\Router\Http\Segment;
use Laminas\View\Helper\InlineScript;

return [
    'view_helpers' => [
        'factories' => [
            'inlineScript'          => Service\InlineScriptFactory::class,
            InlineScript::class => Service\InlineScriptFactory::class,
            'address'               => Service\AddressFactory::class,
            'editPencil'            => Service\EditPencilFactory::class,
            'formatEntity'          => Service\FormatEntityFactory::class,
            'touchButton'           => Service\TouchButtonFactory::class,
            'controllerName'        => Service\ControllerNameFactory::class,
            'routeName'             => Service\RouteNameFactory::class,
        ],
        'invokables' => [
            'editPencilNew'         => View\Helper\EditPencilNew::class,
            'formRow'               => Form\View\Helper\SionFormRow::class,
            'dayFormat'             => I18n\View\Helper\DayFormat::class,
            'debugEncoding'         => View\Helper\DebugEncoding::class,
            'diffForHumans'         => View\Helper\DiffForHumans::class,
            'email'                 => View\Helper\Email::class,
            'formatUrlObject'       => View\Helper\FormatUrlObject::class,
            'helpBlock'             => View\Helper\HelpBlock::class,
            'jshrink'               => View\Helper\Jshrink::class,
            'shortDateRange'        => View\Helper\ShortDateRange::class,
            'telephone'             => View\Helper\Telephone::class,
            'telephoneList'         => View\Helper\TelephoneList::class,
            'tooltip'               => View\Helper\Tooltip::class,
        ],
    ],
    'validators' => [
        'invokables' => [
            'Skype'     => Validator\Skype::class,
            'Twitter'   => Validator\Twitter::class,
            'Instagram' => Validator\Instagram::class,
            'Phone'     => Validator\Phone::class,
            'Slack'     => Validator\Slack::class,
         ],
    ],
    'form_elements' => [
        'invokables' => [
            'Phone' => Phone::class,
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
        'factories' => [
            Controller\SionModelController::class => Service\SionModelControllerFactory::class,
        ],
        'abstract_factories' => [
            Controller\SionControllerFactory::class,
            LazyControllerFactory::class,
        ],
    ],
    'service_manager' => [
        'invokables' => [
            I18n\LanguageSupport::class     => I18n\LanguageSupport::class
        ],
        'factories' => [
            'CountryValueOptions'           => Service\CountryValueOptionsFactory::class,
            'SionModel\Config'              => Service\ConfigServiceFactory::class,
            Db\Model\FilesTable::class      => Service\FilesTableFactory::class,
            Form\SuggestForm::class         => Service\SuggestFormFactory::class,
            'SionModel\PersistentCache'     => Service\PersistentCacheFactory::class,
            Problem\ProblemTable::class     => Service\ProblemTableFactory::class,
            Service\EntitiesService::class  => Service\EntitiesServiceFactory::class,
            Service\ProblemService::class   => Service\ProblemServiceFactory::class,
            Service\ChangesCollector::class => Service\ChangesCollectorFactory::class,
            Mailing\Mailer::class           => Service\MailerFactory::class,
            Db\Model\PredicatesTable::class => Service\PredicatesTableFactory::class,
            Mvc\CspListener::class          => Service\CspListenerFactory::class,
            Service\ErrorHandling::class    => Service\ErrorHandlingFactory::class,
            'ExceptionsLogger'              => Service\ExceptionsLoggerFactory::class,
            'SionModel\Logger'              => Service\LoggerFactory::class,
            //exception reporting: recorder, notifier and the dispatch/render
            //error listener Module::onBootstrap() attaches
            Error\Fingerprinter::class      => Service\FingerprinterFactory::class,
            Error\ExceptionStore::class     => Service\ExceptionStoreFactory::class,
            Error\RequestContext::class     => Service\RequestContextFactory::class,
            Error\ExceptionNotifier::class  => Service\ExceptionNotifierFactory::class,
            Error\ErrorListener::class      => Service\ErrorListenerFactory::class,
            //a literal, not ExceptionNotifierFactory::TRANSPORT_SERVICE: a
            //class constant here makes this config file unloadable without an
            //autoloader, which breaks any tooling that just includes it
            'SionModel\ExceptionMailTransport' => Service\ExceptionMailTransportFactory::class,
        ],
    ],
    'sion_model' => [
        'application_log_path'      => 'data/logs/application_{monthString}.log',
        'exceptions_log_path'       => 'data/logs/exceptions_{monthString}.log',
        /**
         * Exception reporting. Every exception that reaches dispatch.error or
         * render.error is logged as before and additionally recorded in a
         * per-failure directory under store_path; the first occurrence of each
         * distinct failure is emailed.
         *
         * A "distinct failure" is keyed on the exception class chain, the
         * matched route name and the enclosing function of the root cause —
         * never the request URI, which would make every request to a variable
         * URL look like a brand new bug and mail accordingly.
         *
         * Recipients are intentionally empty here: a project that has not
         * configured them records without mailing.
         */
        'exception_notifications'   => [
            'enabled'    => true,
            'store_path' => 'data/exceptions',
            /** Email recipients. Set these per project, e.g. in a *.global.php. */
            'to'         => [],
            /** Envelope sender; defaults to the SMTP account when left null. */
            'from'       => null,
            'from_name'  => null,
            /** Subject prefix; defaults to the request's host in brackets. */
            'subject_prefix' => null,
            /**
             * Exception classes that are recorded and logged but never mailed.
             * Exact class names, or a namespace prefix ending in `*`.
             *
             * dispatch.error is not a bug channel: an unauthenticated visitor
             * touching a guarded route raises UnAuthorizedException through the
             * very same event. That is ordinary traffic, and mailing it would
             * bury every real failure. Matched as strings so nothing is
             * autoloaded while the application is mid-failure.
             */
            'ignore_classes' => [
                'BjyAuthorize\Exception\UnAuthorizedException',
            ],
            /**
             * Occurrence counts that earn a second look after the first email:
             * a rare annoyance becoming an outage is worth hearing about.
             */
            'spike_counts' => [10, 100, 1000],
            /** Ceiling on distinct fingerprints, so a storm cannot fill the disk. */
            'max_fingerprints' => 500,
            /** Ceiling on notifications per hour, so a storm cannot flood the inbox. */
            'max_emails_per_hour' => 20,
            /** Truncation ceiling for a single write-up. */
            'max_write_up_bytes' => 262144,
            /** How many recent write-ups to keep besides first and last. */
            'ring_size' => 3,
            /** How long to stop trying after the mail transport fails. */
            'breaker_seconds' => 900,
            /**
             * What request state to keep. The store gets copied off the server
             * and its contents get mailed, so these default to the least data
             * that still lets you reproduce a failure:
             *   ip:       truncate (IPv4 /24, IPv6 /48) | full | none
             *   identity: id (user id only, never the address) | none
             *   params:   keys (names kept, values redacted) | full | none
             */
            'capture' => [
                'ip'       => 'truncate',
                'identity' => 'id',
                'params'   => 'keys',
            ],
        ],
        'file_directory'            => 'data/files',
        'public_file_directory'     => 'public/files',
        'max_items_to_cache'        => 2,
        /**
         * Bytes. A single persistent cache item bigger than this is skipped
         * rather than written. APCu clears its whole segment when an allocation
         * fails (apc.ttl is 0), so one oversized write costs every other cached
         * item site-wide — refusing it locally is cheaper. 0 disables the check.
         *
         * 4 MiB was picked from the measured size distribution rather than by
         * feel. Warming the main routes against production-scale data gives a
         * long tail of legitimate items topping out at ~2.5 MiB
         * (query-objects-association 2.44, publication-navigation-data 1.84,
         * unlinked-persons 0.88) and then one outlier at 29.21 MiB
         * (query-objects-publication, the full 10k-row 80-field table). The gap
         * between those two groups is where this belongs: everything real keeps
         * caching with headroom to grow, and only the table-sized blob is
         * refused. /sm/cache-status reports largestEntries so the number can be
         * re-checked against production instead of assumed.
         */
        'max_cached_item_size'      => 4194304, //4 MiB
        'changes_max_rows'          => 500,
        'changes_show_all'          => true,
        'api_keys'                  => [], //users should specify long, random authentication keys here
        'post_place_line_format'    => ':zip :cityState',
        'post_place_line_format_by_country' => [
            'US' => ':cityState :zip',
            'CL' => ':cityState :zip',
        ],
        'sion_controller_services' => [
            'SionModel\Config',
            Service\ProblemService::class,
            'SionModel\PersistentCache',
            Service\ChangesCollector::class,
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
                'get_object_function' => 'getMailing',
                'required_columns_for_creation' => [
                    'toAddresses',
                    'status',
                ],
                'name_field' => 'mailingName',
                'name_field_is_translatable' => false,
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
                'name'                                  => 'file',
                'table_name'                            => 'files',
                'table_key'                             => 'FileId',
                'entity_key_field'                      => 'fileId',
//                 'sion_model_class'                       => FilesTable::class,
                'get_object_function'                   => 'getFile',
                'get_objects_function'                  => 'getFiles',
//                 'format_view_helper'                    => 'formatEvent',
                'required_columns_for_creation'         => [
                    'originalFileName',
                    'mimeType',
                    'size',
                    'sha1',
                ],
                'name_field'                            => 'originalFileName',
                'name_field_is_translatable'           => false,
//                 'country_field'                          => 'country',
                'text_columns'                          => [],
                'many_to_one_update_columns'            => [
//                     'email'  => 'contactInfo',
//                     'cell'   => 'contactInfo',
                ],
                'report_changes'                        => true,
                'index_route'                           => 'files',
//                 'index_template'                         => 'project/events/index',
//                 'default_route_key'                     => 'file_id',
//                 'show_action_template'                   => 'project/events/show',
//                 'show_route'                             => 'files/files',
//                 'show_route_key'                         => 'file_id',
//                 'show_route_key_field'                   => 'fileId',
//                 'edit_action_form'                       => 'SionModel\Form\EditFileForm',
//                 'edit_action_template'                   => 'project/events/edit',
//                 'edit_route'                             => 'events/event/edit',
//                 'edit_route_key'                         => 'file_id',
//                 'edit_route_key_field'                   => 'fileId',
//                 'create_action_form'                     => 'SionModel\Form\UploadFileForm',
//                 'create_action_valid_data_handler'       => 'createEvent',
//                 'create_action_redirect_route'           => 'files/file',
//                 'create_action_redirect_route_key'       => 'file_id',
//                 'create_action_redirect_route_key_field'=> 'fileId',
//                 'create_action_template'                 => 'project/events/create',
//                 'touch_default_field'                => 'fileId',
//                 'touch_field_route_key'                  => 'event_id',
//                 'touch_json_route'                       => 'events/event/touch',
//                 'touch_json_route_key'                   => 'file_id',
                'database_bound_data_preprocessor'      => 'preprocessFile',
//                 'database_bound_data_postprocessor'  => 'postprocessEvent',
//                 'moderate_route'                         => 'events/event/moderate',
//                 'moderate_route_entity_key'          => 'file_id',
//                 'suggest_form'                           => 'Project\Form\SuggestEventForm',
                'enable_delete_action'                  => true,
//                 'delete_action_acl_resource'             => 'event_:id',
//                 'delete_action_acl_permission'           => 'delete_event',
//                 'delete_action_redirect_route'           => 'events',
                'update_columns'                        => [
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
                    'encryptedEncryptionKey' => 'EncryptedEncryptionKey',
                    'createdOn'             => 'CreatedOn',
                    'createdBy'             => 'CreatedBy',
                    'updatedOn'             => 'UpdatedOn',
                    'updatedBy'             => 'UpdatedBy',
                ],
            ],
            'file-entity' => [
                'name'                                  => 'file-entity',
                'table_name'                            => 'files_entities',
                'table_key'                             => 'FileEntityId',
                'entity_key_field'                      => 'fileEntityId',
//                 'sion_model_class'                       => FilesTable::class,
                'get_object_function'                   => 'getFileEntity',
                'get_objects_function'                  => 'getFileEntities',
                //                 'format_view_helper'                    => 'formatEvent',
                'required_columns_for_creation'         => [
                    'title'
                ],
                'name_field'                            => 'fileName',
                'name_field_is_translatable'           => false,
//                 'country_field'                          => 'country',
                'text_columns'                          => [],
                'many_to_one_update_columns'            => [
//                     'email'  => 'contactInfo',
//                     'cell'   => 'contactInfo',
                ],
                'report_changes'                        => true,
                'index_route'                           => 'files',
//                 'index_template'                         => 'project/events/index',
                'default_route_key'                     => 'file_id',
//                 'show_action_template'                   => 'project/events/show',
                'show_route'                            => 'files/files',
                'show_route_key'                        => 'file_id',
                'show_route_key_field'                  => 'fileId',
                'edit_action_form'                      => 'SionModel\Form\EditFileForm',
                'edit_action_template'                  => 'project/events/edit',
                'edit_route'                            => 'files/file/edit',
                'edit_route_key'                        => 'file_id',
                'edit_route_key_field'                  => 'fileId',
                'create_action_form'                    => 'SionModel\Form\UploadFileForm',
//                 'create_action_valid_data_handler'       => 'createEvent',
                'create_action_redirect_route'          => 'files/file',
                'create_action_redirect_route_key'      => 'file_id',
                'create_action_redirect_route_key_field' => 'fileId',
//                 'create_action_template'                 => 'project/events/create',
//                 'touch_default_field'                => 'fileId',
//                 'touch_field_route_key'                  => 'event_id',
//                 'touch_json_route'                       => 'events/event/touch',
//                 'touch_json_route_key'                   => 'file_id',
//                 'database_bound_data_preprocessor'       => 'preprocessEvent',
//                 'database_bound_data_postprocessor'  => 'postprocessEvent',
//                 'moderate_route'                         => 'events/event/moderate',
//                 'moderate_route_entity_key'          => 'file_id',
//                 'suggest_form'                           => 'Project\Form\SuggestEventForm',
                'enable_delete_action'                  => true,
                'delete_action_acl_resource'            => 'event_:id',
                'delete_action_acl_permission'          => 'delete_event',
                'delete_action_redirect_route'          => 'events',
                'update_columns'                        => [
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
                    'updatedBy'     => 'UpdatedBy',
                ],
            ],
            'comment' => [
                'name'                                  => 'comment',
                'table_name'                            => 'comments',
                'table_key'                             => 'CommentId',
                'entity_key_field'                      => 'commentId',
                'sion_model_class'                      => Db\Model\PredicatesTable::class,
                'get_object_function'                   => 'getComment',
                'get_objects_function'                  => 'getComments',
                'sion_controllers'                          => [Controller\CommentController::class],//BorrowersController::class],
                'controller_services'                       => [
                ],
//                 'format_view_helper'                    => 'formatEvent',
                'required_columns_for_creation'         => [
                    'comment',
                    'kind',
                    'status',
                ],
                'name_field'                            => 'comment',
                'name_field_is_translatable'           => false,
                //                 'country_field'                          => 'country',
                'text_columns'                          => [],
                'many_to_one_update_columns'            => [
//                     'email'  => 'contactInfo',
//                     'cell'   => 'contactInfo',
                ],
                'report_changes'                        => false,
//                 'index_route'                        => 'files',
//                 'index_template'                         => 'project/events/index',
//                 'default_route_key'                     => 'file_id',
//                 'show_action_template'                   => 'project/events/show',
//                 'show_route'                             => 'files/files',
//                 'show_route_key'                         => 'file_id',
//                 'show_route_key_field'                   => 'fileId',
//                 'edit_action_form'                       => 'SionModel\Form\EditFileForm',
//                 'edit_action_template'                   => 'project/events/edit',
//                 'edit_route'                             => 'events/event/edit',
//                 'edit_route_key'                         => 'file_id',
//                 'edit_route_key_field'                   => 'fileId',
                'create_action_form'                     => Form\CommentForm::class,
//                 'create_action_valid_data_handler'       => 'createEvent',
//                 'create_action_redirect_route'           => 'files/file',
//                 'create_action_redirect_route_key'       => 'file_id',
//                 'create_action_redirect_route_key_field'=> 'fileId',
//                 'create_action_template'                 => 'project/events/create',
//                 'touch_default_field'                => 'fileId',
//                 'touch_field_route_key'                  => 'event_id',
//                 'touch_json_route'                       => 'events/event/touch',
//                 'touch_json_route_key'                   => 'file_id',
//                 'database_bound_data_preprocessor'        => 'preprocessFile',
                'database_bound_data_postprocessor'  => 'postprocessComment',
//                 'moderate_route'                         => 'events/event/moderate',
//                 'moderate_route_entity_key'          => 'file_id',
//                 'suggest_form'                           => 'Project\Form\SuggestEventForm',
                'enable_delete_action'                  => true,
                //                 'delete_action_acl_resource'             => 'event_:id',
                //                 'delete_action_acl_permission'           => 'delete_event',
                //                 'delete_action_redirect_route'           => 'events',
                'update_columns' => [
                    'commentId'         => 'CommentId',
                    'rating'            => 'Rating',
                    'kind'              => 'CommentKind',
                    'comment'           => 'Comment',
                    'status'            => 'Status',
                    'reviewedBy'        => 'ReviewedBy',
                    'reviewedOn'        => 'ReviewedOn',
                    'createdOn'         => 'CreatedOn',
                    'createdBy'         => 'CreatedBy',
                ],
            ],
            'predicate' => [
                'name'                                  => 'predicate',
                'table_name'                            => 'predicates',
                'table_key'                             => 'PredicateKind',
                'entity_key_field'                      => 'predicateKind',
                'row_processor_function'                => 'processPredicateRow',
                'sion_model_class'                      => Db\Model\PredicatesTable::class,
//                 'get_object_function'                   => 'getRelationship',
//                 'get_objects_function'                  => 'getRelationships',
                'sion_controllers'                          => [],//BorrowersController::class],
                'controller_services'                       => [
                ],
                //                 'format_view_helper'                    => 'formatEvent',
                'required_columns_for_creation'         => [
                    'predicateKind',
                    'subjectEntityKind',
                    'objectEntityKind',
                    'text',
                ],
                'name_field'                            => 'text',
                'name_field_is_translatable'           => true,
//                 'country_field'                          => 'country',
                'text_columns'                          => [],
                'many_to_one_update_columns'            => [
//                     'email'  => 'contactInfo',
//                     'cell'   => 'contactInfo',
                ],
                'report_changes'                        => false,
//                 'index_route'                        => 'files',
//                 'index_template'                         => 'project/events/index',
//                 'default_route_key'                     => 'file_id',
//                 'show_action_template'                   => 'project/events/show',
//                 'show_route'                             => 'files/files',
//                 'show_route_key'                         => 'file_id',
//                 'show_route_key_field'                   => 'fileId',
//                 'edit_action_form'                       => 'SionModel\Form\EditFileForm',
//                 'edit_action_template'                   => 'project/events/edit',
//                 'edit_route'                             => 'events/event/edit',
//                 'edit_route_key'                         => 'file_id',
//                 'edit_route_key_field'                   => 'fileId',
//                 'create_action_form'                     => 'SionModel\Form\UploadFileForm',
//                 'create_action_valid_data_handler'       => 'createEvent',
//                 'create_action_redirect_route'           => 'files/file',
//                 'create_action_redirect_route_key'       => 'file_id',
//                 'create_action_redirect_route_key_field'=> 'fileId',
//                 'create_action_template'                 => 'project/events/create',
//                 'touch_default_field'                => 'fileId',
//                 'touch_field_route_key'                  => 'event_id',
//                 'touch_json_route'                       => 'events/event/touch',
//                 'touch_json_route_key'                   => 'file_id',
//                 'database_bound_data_preprocessor'        => 'preprocessFile',
//                 'database_bound_data_postprocessor'  => 'postprocessEvent',
//                 'moderate_route'                         => 'events/event/moderate',
//                 'moderate_route_entity_key'          => 'file_id',
//                 'suggest_form'                           => 'Project\Form\SuggestEventForm',
                'enable_delete_action'                  => false,
//                 'delete_action_acl_resource'             => 'event_:id',
//                 'delete_action_acl_permission'           => 'delete_event',
//                 'delete_action_redirect_route'           => 'events',
                'update_columns' => [
                    'predicateKind' => 'PredicateKind',
                    'subjectEntityKind' => 'SubjectEntityKind',
                    'objectEntityKind' => 'ObjectEntityKind',
                    'text' => 'PredicateText',
                    'description' => 'DescriptionEn',
                ],
            ],
            'relationship' => [
                'name'                                  => 'relationship',
                'table_name'                            => 'relationships',
                'table_key'                             => 'RelationshipId',
                'entity_key_field'                      => 'relationshipId',
                'sion_model_class'                      => Db\Model\PredicatesTable::class,
                'row_processor_function'                => 'processRelationshipRow',
//                 'get_object_function'                   => 'getRelationship',
//                 'get_objects_function'                  => 'getRelationships',
                'sion_controllers'                          => [],//BorrowersController::class],
                'controller_services'                       => [
                ],
//                 'format_view_helper'                    => 'formatEvent',
                'required_columns_for_creation'         => [
                    'subjectEntityId',
                    'objectEntityId',
                    'predicateKind'
                ],
                'name_field'                            => 'comment',
                'name_field_is_translatable'           => false,
//                 'country_field'                          => 'country',
                'text_columns'                          => [],
                'many_to_one_update_columns'            => [
//                     'email'  => 'contactInfo',
//                     'cell'   => 'contactInfo',
                ],
                'report_changes'                        => false,
//                 'index_route'                        => 'files',
//                 'index_template'                         => 'project/events/index',
//                 'default_route_key'                     => 'file_id',
//                 'show_action_template'                   => 'project/events/show',
//                 'show_route'                             => 'files/files',
//                 'show_route_key'                         => 'file_id',
//                 'show_route_key_field'                   => 'fileId',
//                 'edit_action_form'                       => 'SionModel\Form\EditFileForm',
//                 'edit_action_template'                   => 'project/events/edit',
//                 'edit_route'                             => 'events/event/edit',
//                 'edit_route_key'                         => 'file_id',
//                 'edit_route_key_field'                   => 'fileId',
//                 'create_action_form'                     => 'SionModel\Form\UploadFileForm',
//                 'create_action_valid_data_handler'       => 'createEvent',
//                 'create_action_redirect_route'           => 'files/file',
//                 'create_action_redirect_route_key'       => 'file_id',
//                 'create_action_redirect_route_key_field'=> 'fileId',
//                 'create_action_template'                 => 'project/events/create',
//                 'touch_default_field'                => 'fileId',
//                 'touch_field_route_key'                  => 'event_id',
//                 'touch_json_route'                       => 'events/event/touch',
//                 'touch_json_route_key'                   => 'file_id',
//                 'database_bound_data_preprocessor'        => 'preprocessFile',
//                 'database_bound_data_postprocessor'  => 'postprocessEvent',
//                 'moderate_route'                         => 'events/event/moderate',
//                 'moderate_route_entity_key'          => 'file_id',
//                 'suggest_form'                           => 'Project\Form\SuggestEventForm',
                'enable_delete_action'                  => true,
//                 'delete_action_acl_resource'             => 'event_:id',
//                 'delete_action_acl_permission'           => 'delete_event',
//                 'delete_action_redirect_route'           => 'events',
                'update_columns' => [
                    'relationshipId' => 'RelationshipId',
                    'subjectEntityId' => 'SubjectEntityId',
                    'objectEntityId' => 'ObjectEntityId',
                    'predicateKind' => 'PredicateKind',
                    'priority' => 'Priority',
                    'publicNotes' => 'PublicNotes',
                    'publicNotesUpdatedOn' => 'PublicNotesUpdatedOn',
                    'publicNotesUpdatedBy' => 'PublicNotesUpdatedBy',
                    'adminNotes' => 'AdminNotes',
                    'adminNotesUpdatedOn' => 'AdminNotesUpdatedOn',
                    'adminNotesUpdatedBy' => 'AdminNotesUpdatedBy',
                    'updatedOn' => 'UpdatedOn',
                    'updatedBy' => 'UpdatedBy',
                ],
            ],
        ],
    ],
    'router' => [
        'routes' => [
            'comments' => [
                'type'    => Literal::class,
                'options' => [
                    'route'    => '/comments',
                    'defaults' => [
                        'controller' => Controller\CommentController::class,
                    ],
                ],
                'may_terminate' => false,
                'child_routes' => [
                    'create' => [
                        'type'    => Segment::class,
                        'options' => [
                            'route'    => '/create/:entity/:entity_id[/:kind]',
                            'defaults' => [
                                'action' => 'create',
                                'kind' => 'comment',
                            ],
                            'constraints' => [
                                'entity_id' => '[0-9]{1,5}',
                                'entity' => '[a-zA-Z_-]{1,25}',
                                'kind' => '(comment|review|rating)',
                            ],
                        ],
                    ],
                ],
            ],
            'sion-model' => [
                'type'    => Literal::class,
                'options' => [
                    'route'    => '/sm',
                    'defaults' => [
                        'controller' => Controller\SionModelController::class,
                        'action'     => 'index',
                    ],
                ],
                'may_terminate' => false,
                'child_routes' => [
                    'phpinfo' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/phpinfo',
                            'defaults' => [
                                'action'     => 'phpinfo',
                            ],
                        ],
                    ],
                    'clear-persistent-cache' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/clear-persistent-cache',
                            'defaults' => [
                                'action'     => 'clearPersistentCache',
                            ],
                        ],
                    ],
                    'cache-status' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/cache-status',
                            'defaults' => [
                                'action'     => 'cacheStatus',
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
