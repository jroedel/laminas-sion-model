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
}
