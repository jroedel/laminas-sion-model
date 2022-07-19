<?php

declare(strict_types=1);

namespace SionModel\View\Helper;

use Laminas\View\Helper\Placeholder\Container\AbstractContainer;

class InlineScript extends \Laminas\View\Helper\InlineScript
{
    public function __construct(private string $nonce)
    {
        parent::__construct();
        $this->setAllowArbitraryAttributes(true);
    }

    /**
     * {@inheritDoc}
     *
     * @see \Laminas\View\Helper\InlineScript::__invoke()
     */
    public function __invoke(
        $mode = self::FILE,
        $spec = null,
        $placement = 'APPEND',
        array $attrs = [],
        $type = 'text/javascript'
    ) {
        return parent::__invoke($mode, $spec, $placement, $attrs, $type);
    }

    /**
     * {@inheritDoc}
     *
     * @see \Laminas\View\Helper\HeadScript::captureStart()
     */
    public function captureStart(
        $captureType = AbstractContainer::APPEND,
        $type = 'text/javascript',
        $attrs = []
    ) {
        if (isset($this->nonce) && ! isset($attrs['nonce'])) {
            $attrs['nonce'] = $this->nonce;
        }

        parent::captureStart($captureType, $type, $attrs);
    }

    /**
     * {@inheritDoc}
     *
     * @see \Laminas\View\Helper\HeadScript::captureEnd()
     */
    public function captureEnd()
    {
        //@todo calculate sums
        parent::captureEnd();
    }
}
