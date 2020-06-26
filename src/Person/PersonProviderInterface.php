<?php

namespace SionModel\Person;

/**
 * Person provider interface
 *
 * @author Jeff Ro
 */
interface PersonProviderInterface
{
    /**
     * @return mixed[]
     */
    public function getPersonValueOptions();
}
