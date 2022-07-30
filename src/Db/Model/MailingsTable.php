<?php

declare(strict_types=1);

namespace SionModel\Db\Model;

use JUser\Model\UserTable;
use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Log\LoggerInterface;
use SionModel\I18n\LanguageSupport;
use SionModel\Problem\EntityProblem;
use SionModel\Service\SionCacheService;

class MailingsTable extends SionTable
{
    public function __construct(
        AdapterInterface $adapter,
        array $entitySpecifications,
        SionCacheService $sionCacheService,
        EntityProblem $entityProblemPrototype,
        ?UserTable $userTable,
        LanguageSupport $languageSupport,
        LoggerInterface $logger,
        ?int $actingUserId,
        array $generalConfig
    ) {
        parent::__construct(
            adapter: $adapter,
            entitySpecifications: $entitySpecifications,
            sionCacheService: $sionCacheService,
            entityProblemPrototype: $entityProblemPrototype,
            userTable: $userTable,
            languageSupport: $languageSupport,
            logger: $logger,
            actingUserId: $actingUserId,
            generalConfig: $generalConfig
        );
    }
}
