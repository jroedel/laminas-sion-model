<?php

declare(strict_types=1);

namespace SionModel\Filter;

use SionModel\Filter\Exception\InvalidArgumentException;

use function is_callable;

/**
 * A filter that is whatever a form said it was.
 *
 * Three specifications use one, each with a closure of the form's own. laminas also carried
 * `callback_params`, extra arguments appended after the value; none of the three passes
 * any, so it is not reproduced.
 */
final class Callback extends AbstractFilter
{
    /** @var array{callback: callable|null} */
    protected $options = ['callback' => null];

    /** @throws InvalidArgumentException */
    public function setCallback(mixed $callback): static
    {
        if (! is_callable($callback)) {
            throw new InvalidArgumentException('A Callback filter needs something callable');
        }

        $this->options['callback'] = $callback;

        return $this;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public function filter($value)
    {
        $callback = $this->options['callback'];

        if (null === $callback) {
            throw new InvalidArgumentException('A Callback filter was asked to filter before it was given a callback');
        }

        return $callback($value);
    }
}
