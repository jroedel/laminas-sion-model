<?php
namespace SionModel\Problem;

use SionModel\Problem\EntityProblem;

/**
 * Problem provider interface
 *
 * @author Jeff Ro
 */
interface ProblemProviderInterface
{
    /**
     * @param string $minimumSeverity
     * @return \SionModel\Problem\EntityProblem[]
     */
    public function getProblems($minimumSeverity = EntityProblem::SEVERITY_INFO);
    
    /**
     * Fixes any auto-fixable problems with the user's confirmation.
     * When $simulate is true, no changes to the database should be made. 
     * Returns an array of EntityProblems. If there's no funcitonality, 
     * simply return an empty array.
     * @param bool $simulate
     * @return \SionModel\Problem\EntityProblem[]
     */
    public function autoFixProblems($simulate = true);
}
