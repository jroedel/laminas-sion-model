<?php

declare(strict_types=1);

namespace SionModel\Problem;

interface ProblemProviderInterface
{
    /**
     * @return EntityProblem[]
     */
    public function getProblems(string $minimumSeverity = EntityProblem::SEVERITY_INFO): array;

    /**
     * Fixes any auto-fixable problems with the user's confirmation.
     * When $simulate is true, no changes to the database should be made.
     * Returns an array of EntityProblems. If there's no functionality,
     * simply return an empty array.
     *
     * @return EntityProblem[]
     */
    public function autoFixProblems(bool $simulate = true): array;
}
