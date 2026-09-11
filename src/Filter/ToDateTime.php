<?php

/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace SionModel\Filter;

class ToDateTime extends AbstractFilter
{
    /**
     * Defined by SionModel\Filter\FilterInterface
     *
     * Converts a date string to a \DateTime in UTC.
     *
     * If the value provided is non-scalar, or is a string \DateTime cannot
     * parse, the value will remain unfiltered. That is deliberate and this
     * method must never throw: a filter runs inside InputFilter::isValid(),
     * so anything thrown here escapes isValid() as an uncaught exception —
     * a 500 with the user's whole submission lost. 'asdf', '2020-02-30' and
     * a POSTed array ('name[]=x') all used to do exactly that.
     *
     * Returning the original value rather than null is what lets a validator
     * downstream tell "unparseable" from "empty": validators see the
     * *filtered* value, so a null here would be indistinguishable from a
     * field the user left blank, and the bad input would be discarded
     * silently instead of reported. SionModel\Validator\ParseableDate is the
     * other half of this and belongs on every input using this filter.
     *
     * @param  mixed $value
     * @return \DateTimeInterface|mixed
     */
    public function filter($value)
    {
        $parsed = DateTimeParser::parse($value);

        // false means "not a storable date". Hand the value back rather than
        // nulling it, so SionModel\Validator\ParseableDate can see it and
        // report it — a null here would be indistinguishable from a blank field
        // and would discard bad input in silence. sanitizeRejected() strips the
        // one thing that is not safe to pass on; see its docblock.
        return false === $parsed ? DateTimeParser::sanitizeRejected($value) : $parsed;
    }
}
