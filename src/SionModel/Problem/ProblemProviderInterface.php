<?php
namespace SionModel\Problem;

/**
 * Problem provider interface
 *
 * @author Jeff Ro
 */
interface ProblemProviderInterface
{
    /**
     * @return \Patres\Problem\EntityProblem[]
     */
    public function getProblems();
}
