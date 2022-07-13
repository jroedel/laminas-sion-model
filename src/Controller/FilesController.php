<?php

namespace SionModel\Controller;

use Laminas\Form\FormInterface;
use Laminas\Log\LoggerInterface;
use SionModel\Db\Model\PredicatesTable;
use SionModel\Db\Model\SionTable;
use SionModel\Service\EntitiesService;

class FilesController extends SionController
{
    public function __construct(
        EntitiesService $entitiesService,
        SionTable $sionTable,
        PredicatesTable $predicatesTable,
        ?FormInterface $createActionForm,
        ?FormInterface $editActionForm,
        ?FormInterface $suggestForm,
        array $config,
        LoggerInterface $logger
    ) {
        parent::__construct(
            entity: 'file',
            entitiesService: $entitiesService,
            sionTable: $sionTable,
            predicatesTable: $predicatesTable,
            createActionForm: $createActionForm,
            editActionForm: $editActionForm,
            suggestForm: $suggestForm,
            config: $config,
            logger: $logger
        );
    }
}
