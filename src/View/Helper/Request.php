<?php

declare(strict_types=1);

namespace SionModel\View\Helper;

use Laminas\Stdlib\RequestInterface;
use Laminas\View\Helper\AbstractHelper;

class Request extends AbstractHelper
{
    public function __construct(
        private RequestInterface $request
    ) {
    }

    public function __invoke(): RequestInterface
    {
        return $this->request;
    }
}
