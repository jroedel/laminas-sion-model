<?php

declare(strict_types=1);

namespace Project;

use Laminas\Log\Logger;
use Laminas\Mvc\MvcEvent;

return [
    'sion_model' => [
        'logger_to_file_minimum_level'   => Logger::DEBUG,
        'logger_to_email_minimum_level'  => Logger::WARN,
        'logger_to_email_to_address'     => 'test@example.com',
        'logger_to_email_sender_address' => 'test@example.com',
        'logger_to_email_subject'        => 'Logger message',
        'application_log_path'           => 'data/logs/application_{monthString}.log',
        'exceptions_log_path'            => 'data/logs/exceptions_{monthString}.log',
        /**
         * This PersonProvider will be used by the SuggestFormFactory if we have a multi-person user
         */
        'multi_person_user_person_provider' => 'Project\Model\MyPersonProvider',
        //change values to modify hashing of ip addresses and user agents
        'privacy_hash_algorithm' => 'sha256',
        'privacy_hash_salt'      => 'O3!k5Uvv@',
        //config for Content Security Policy
        'csp_config' => [
            //https://csp-evaluator.withgoogle.com
            'csp_string' => "script-src 'strict-dynamic' 'nonce-{:nonce}' 'unsafe-inline' https:; object-src 'none'; "
                . "base-uri 'none'; report-uri https://csp.example.com;",
            //if this header isn't set, no Content-Security-Policy header will be set
            'inject_headers_event' => MvcEvent::EVENT_FINISH,
        ],
        /**
         * Database table name of where to store change records
         */
        'changes_table' => 'project_changes',
        /**
         * Max number of rows to display in
         */
        'changes_max_rows' => 500,
        /**
         * Database table name of where to store visit records
         */
        'visits_table' => 'project_visits',
        /**
         * Used for auto-generating navigation pages for breadcrumbs
         */
        'navigation_key' => 'default',

//        'post_place_line_format' => ':zip :cityState',
//        'post_place_line_format_by_country' => [
//            'US' => ':cityState :zip',
//            'CL' => ':cityState :zip',
//        ],
        'default_redirect_route' => 'home',

        /**
        * If this key is set, it will be used to prime the SionForm with persons for suggestions.
        * The class must implement the SionModel\Person\PersonProviderInterface
        */
        'person_provider' => 'Project\Model\EventTable',
        'entities'        => [
            /**
             * For more information on entity config:
             *
             * @see \SionModel\Entity\Entity
             */
            'event' => [
                'name'                 => 'event',
                'table_name'           => 'event',
                'table_key'            => 'event_id',
                'entity_key_field'     => 'eventId',
                'sion_model_class'     => 'Project\Model\EventTable',
                'get_object_function'  => 'getEvent',
                'get_objects_function' => 'getEvents',
//                 'format_view_helper'                        => 'formatEvent',
                'required_columns_for_creation' => [
                    'title',
                ],
                'name_field'                    => 'title',
                'name_field_is_translatable'    => false,
                'country_field'                 => 'country',
//                 'text_columns'                              => [],
//                 'many_to_one_update_columns'                => [
//                     'email'    => 'contactInfo',
//                     'cell'    => 'contactInfo',
//                 ],
                'report_changes' => true,
                'index_route'    => 'events',
                'index_template' => 'project/events/index',
//                 'show_action_template'                      => 'project/events/show',
                'show_route' => 'events/event',
//                 'edit_action_form'                          => Form\EditEventForm::class,
//                 'edit_action_template'                      => 'project/events/edit',
                'edit_route' => 'events/event/edit',
//                 'create_action_form'                        => Form\CreateEventForm::class,
                'create_action_redirect_route' => 'events/event',
//                 'create_action_template'                    => 'project/events/create',
//                 'touch_json_route'                          => 'events/event/touch',
//                 'database_bound_data_preprocessor'          => 'preprocessEvent',
//                 'database_bound_data_postprocessor'         => 'postprocessEvent',
//                 'moderate_route'                            => 'events/event/moderate',
//                 'suggest_form'                              => Form\SuggestEventForm::class,
                'enable_delete_action'         => true,
                'delete_action_redirect_route' => 'events',
                'acl_resource_id_field'        => 'resourceId',
                'acl_show_permission'          => 'show',
                'acl_edit_permission'          => 'edit',
                'acl_suggest_permission'       => 'suggest',
                'acl_moderate_permission'      => 'moderate',
                'acl_delete_permission'        => 'delete',
                'update_columns'               => [
                    'eventId'          => 'event_id',
                    'file'             => 'event_file',
                    'fileDateModified' => 'event_file_date_modified',
                    'duration'         => 'event_duration',
                    'accuracy'         => 'event_accuracy',
                    'source'           => 'event_source',
                    'startDate'        => 'event_start_date',
                    'endDate'          => 'event_end_date',
                    'textQuality'      => 'event_text_quality',
                    'adminKeywords'    => 'event_admin_keywords',
                    'createdOn'        => 'event_create_datetime',
                    'updatedOn'        => 'event_update_datetime',
                ],
            ],
        ],
    ],
];
