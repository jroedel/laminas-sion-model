<?php
namespace Project;

return [
    'sion_model' => [
        //change values to modify hashing of ip addresses and user agents
        'privacy_hash_algorithm' => 'sha256',
        'privacy_hash_salt' => 'O3!k5Uvv@',
        //config for Content Security Policy
        'csp_config' => [
            //https://csp-evaluator.withgoogle.com
            'csp_string' => "script-src 'strict-dynamic' 'nonce-{:nonce}' 'unsafe-inline' https:; object-src 'none'; base-uri 'none'; report-uri https://csp.example.com;",
            //if this header isn't set, no Content-Security-Policy header will be set
            'inject_headers_event' => \Zend\Mvc\MvcEvent::EVENT_FINISH,
        ],
        /**
         * Database table name of where to store change records
         */
        'changes_table' => 'project_changes',
        /**
         * This is the service name of a SionTable instance to call the getChanges() method if changes_show_all isn't set
         */
        'changes_model' => 'Project\Model\ProjectTable',
        /**
         * If true, the view-changes action won't restrict entities to one's that belong to the model
         */
        'changes_show_all' => true,
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

        /**
         * Route permission checking is designed to work with BjyAuthorize, calling the
         * isAllowed view helper and checking for route permissions with the 'route/' prefix as
         * a resource_id
         */
        'route_permission_checking_enabled' => false,

        'entities' => [
            /**
             * For more information on entity config:
             * @see \SionModel\Entity\Entity
             */
            'event' => [
                'name'                                      => 'event',
                'table_name'                                => 'event',
                'table_key'                                 => 'event_id',
                'sion_controllers'                          => [],
                'controller_services'                       => [],
                'entity_key_field'                          => 'eventId',
                'sion_model_class'                          => 'Project\Model\EventTable',
                'get_object_function'                       => 'getEvent',
                'get_objects_function'                      => 'getEvents',
//                 'format_view_helper'                        => 'formatEvent',
                'required_columns_for_creation'             => [
                    'title'
                ],
                'name_field'                                => 'title',
                'name_field_is_translateable'               => false,
                'country_field'                             => 'country',
//                 'text_columns'                              => [],
//                 'many_to_one_update_columns'                => [
//                     'email'    => 'contactInfo',
//                     'cell'    => 'contactInfo',
//                 ],
                'report_changes'                            => true,
                'index_route'                               => 'events',
                'index_template'                            => 'project/events/index',
                'default_route_key'                         => 'association_id',
//                 'show_action_template'                      => 'project/events/show',
                'show_route'                                => 'events/event',
                'show_route_key'                            => 'event_id',
                'show_route_key_field'                      => 'eventId',
//                 'edit_action_form'                          => Form\EditEventForm::class,
//                 'edit_action_template'                      => 'project/events/edit',
                'edit_route'                                => 'events/event/edit',
                'edit_route_key'                            => 'event_id',
                'edit_route_key_field'                      => 'eventId',
//                 'create_action_form'                        => Form\CreateEventForm::class,
                'create_action_valid_data_handler'          => 'createEvent',
                'create_action_redirect_route'              => 'events/event',
                'create_action_redirect_route_key'          => 'event_id',
                'create_action_redirect_route_key_field'    => 'eventId',
//                 'create_action_template'                    => 'project/events/create',
//                 'touch_default_field'                       => 'eventId',
//                 'touch_route_key'                           => 'event_id',
//                 'touch_field_route_key'                     => 'event_id',
//                 'touch_json_route'                          => 'events/event/touch',
//                 'touch_json_route_key'                      => 'event_id',
//                 'database_bound_data_preprocessor'          => 'preprocessEvent',
//                 'database_bound_data_postprocessor'         => 'postprocessEvent',
//                 'moderate_route'                            => 'events/event/moderate',
//                 'moderate_route_entity_key'                 => 'event_id',
//                 'suggest_form'                              => Form\SuggestEventForm::class,
                'enable_delete_action'                      => true,
                'delete_route_key'                          => 'event_id',
                'delete_action_redirect_route'              => 'events',

                'acl_resource_id_field'                     => 'resourceId',
                'acl_show_permission'                       => 'show',
                'acl_edit_permission'                       => 'edit',
                'acl_suggest_permission'                    => 'suggest',
                'acl_moderate_permission'                   => 'moderate',
                'acl_delete_permission'                     => 'delete',

                'update_columns'                            => [
                    'eventId' => 'event_id',
                    'file' => 'event_file',
                    'fileDateModified' => 'event_file_date_modified',
                    'duration' => 'event_duration',
                    'accuracy' => 'event_accuracy',
                    'source' => 'event_source',
                    'startDate' => 'event_start_date',
                    'endDate' => 'event_end_date',
                    'textQuality' => 'event_text_quality',
                    'adminKeywords' => 'event_admin_keywords',
                    'createdOn' => 'event_create_datetime',
                    'updatedOn' => 'event_update_datetime'
                ],
            ],
        ],
    ],
];
