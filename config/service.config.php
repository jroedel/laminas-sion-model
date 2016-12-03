<?php
namespace SionModel;

use SionModel\Entity\Book;
use SionModel\Db\ResultSet\KeyedHydratingResultSet;
use SionModel\Stdlib\Hydrator\ObjectPropertyMapper;
use SionModel\Entity\Event;
use SionModel\Db\Model\SionTable;
use SionModel\Entity\Text;
use SionModel\Entity\EventTransl;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\TableGateway\TableGateway;
use SionModel\Db\TableGateway\HydratingTableGateway;
use Zend\Stdlib\Hydrator\Strategy\ClosureStrategy;
use Zend\Db\Sql\Select;

return array(
    'factories' => array(
//             'Event\Form\EditForm' => function($sm) {
//                 $form = new Form\EditEventForm();
//                 return $form;
//             },
//             'Event\Model\EventTable' =>  function($sm) {
//                 $eventGateway = $sm->get('EventTableGateway');
//                 $table = new SionTable($eventGateway);
//                 return $table;
//             },
//             'Event\Model\EventHydrator' => function($sm) {
//                 $hydrator = new ObjectPropertyMapper(
//                         array(
//                                 'event_id' => 'id',
//                                 'event_file' => 'file',
//                                 'event_file_date_modified' => 'fileDateModified',
//                                 'event_duration' => 'duration',
//                                 'event_accuracy' => 'accuracy',
//                                 'event_source' => 'source',
//                                 'event_start_date' => 'startDate',
//                                 'event_end_date' => 'endDate',
//                                 'event_text_quality' => 'textQuality',
//                                 'event_admin_keywords' => 'adminKeywords',
//                                 'event_resource_parent' => 'resourceParent',
//                                 'event_update_datetime' => 'updateDateTime',
//                                 'event_create_datetime' => 'createDateTime'
//                         ));
//                 $dateTimeStrat = new ClosureStrategy(
//                         function ($value) {
//                             if (is_object($value))
//                                 return date_format($value, 'Y-m-d H:i:s');
//                             else
//                                 return $value;
//                         },
//                         function ($value) {
//                             return new \DateTime($value, new \DateTimeZone('UTC'));
//                         });
//                         $hydrator->addStrategy('startDate', $dateTimeStrat);
//                         $hydrator->addStrategy('endDate', $dateTimeStrat);
//                         $hydrator->addStrategy('updateDateTime', $dateTimeStrat);
//                         return $hydrator;
//             },
//             'EventTableGateway' => function ($sm) {
//                 $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
//                 $hydrator = $sm->get('Event\Model\EventHydrator');
//                 $rowObjectPrototype = new Event();

//                 $resultSet = new KeyedHydratingResultSet(
//                         $hydrator, $rowObjectPrototype, array('id') //create array using 'id' as key
//                 );
//                 return new HydratingTableGateway('jk_event', $dbAdapter, null, $resultSet);
//             },
//             'Event\Model\TextTable' =>  function($sm) {
//                 $tableGateway = $sm->get('TextTableGateway');
//                 $where = function (Select $select) {
//                     $select->columns(array(
//                             'txt_id',
//                             'txt_file',
//                             'txt_date_path',
//                             'txt_file_date_modified',
//                             'txt_media_id',
//                             'txt_event_part',
//                             'txt_order_nr',
//                             'txt_title',
//                             'txt_lang',
//                             'txt_source',
//                             'txt_text_short',
//                             'txt_keywords',
//                             'txt_admin_keywords',
//                             'txt_parent_file_version',
//                             'txt_create_datetime',
//                             'txt_update_datetime',
//                             'txt_event_id',
//                     ));
//                     $select->where('txt_selected', true);
//                 };
//                 $table = new SionTable($tableGateway, $where);
//                 return $table;
//             },
//             'TextTableGateway' => function ($sm) {
//                 $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
//                 $hydrator = new ObjectPropertyMapper(
//                         array(
//                                 'txt_id' => 'id',
//                                 'txt_file' => 'file',
//                                 'txt_date_path' => 'datePath',
//                                 'txt_file_date_modified' => 'fileDateModified',
//                                 'txt_media_id' => 'mediaId',
//                                 'txt_event_part' => 'eventPart',
//                                 'txt_order_nr' => 'orderNr',
//                                 'txt_title' => 'title',
//                                 'txt_lang' => 'lang',
//                                 'txt_source' => 'source',
//                                 'txt_text_short' => 'textShort',
//                                 'txt_keywords' => 'keywords',
//                                 'txt_admin_keywords' => 'adminKeywords',
//                                 'txt_parent_file_version' => 'parentFileVersion',
//                                 'txt_create_datetime' => 'createDateTime',
//                                 'txt_update_datetime' => 'updateDateTime',
//                                 'txt_event_id' => 'eventId',
//                                 'txt_text' => 'text'
//                         ));
//                 $dateTimeStrat = new ClosureStrategy(
//                         function ($value) {
//                             if (is_object($value))
//                                 return date_format($value, 'Y-m-d H:i:s');
//                             else
//                                 return $value;
//                         },
//                         function ($value) {
//                             return new \DateTime($value, new \DateTimeZone('UTC'));
//                         });
//                         $hydrator->addStrategy('createDateTime', $dateTimeStrat);
//                         $hydrator->addStrategy('updateDateTime', $dateTimeStrat);

//                         $rowObjectPrototype = new Text();

//                         $resultSet = new KeyedHydratingResultSet(
//                                 $hydrator, $rowObjectPrototype, array('id') //create array using 'id' as key
//                         );
//                         return new HydratingTableGateway('jk_text', $dbAdapter, null, $resultSet);
//             },
//             'Event\Model\EventTranslTable' =>  function($sm) {
//                 $tableGateway = $sm->get('EventTranslTableGateway');
//                 $table = new SionTable($tableGateway);
//                 return $table;
//             },
//             'Event\Model\EventTranslHydrator' => function($sm) {
//                 $hydrator = new ObjectPropertyMapper(
//                         array(
//                                 'event_transl_id' => 'id',
//                                 'event_transl_event_id' => 'eventId',
//                                 'event_transl_lang' => 'lang',
//                                 'event_transl_title' => 'title',
//                                 'event_transl_subtitle' => 'subtitle',
//                                 'event_transl_abbrev' => 'abbrev',
//                                 'event_transl_notes' => 'notes',
//                                 'event_transl_create_datetime' => 'createDateTime',
//                                 'event_transl_update_datetime' => 'updateDateTime',
//                         ));
//                 $dateTimeStrat = new ClosureStrategy(
//                         function ($value) {
//                             if (is_object($value))
//                                 return date_format($value, 'Y-m-d H:i:s');
//                             else
//                                 return $value;
//                         },
//                         function ($value) {
//                             return new \DateTime($value, new \DateTimeZone('UTC'));
//                         });
//                         $hydrator->addStrategy('createDateTime', $dateTimeStrat);
//                         $hydrator->addStrategy('updateDateTime', $dateTimeStrat);
//                         return $hydrator;
//             },
//             'EventTranslTableGateway' => function ($sm) {
//                 $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
//                 $hydrator = $sm->get('Event\Model\EventTranslHydrator');

//                 $rowObjectPrototype = new EventTransl();

//                 $resultSet = new KeyedHydratingResultSet(
//                         $hydrator, $rowObjectPrototype, array('eventId', 'lang') //create array using 'id' as key
//                 );
//                 return new HydratingTableGateway('jk_event_transl', $dbAdapter, null, $resultSet);
//             },
    ),

);
