<?php

declare(strict_types=1);

namespace SionModel\View\Helper;

use Exception;
use Laminas\View\Helper\AbstractHelper;

use function sprintf;

class EditPencilNew extends AbstractHelper
{
    public function __invoke(string $editRoute, array $params, bool $openInNewTab = false)
    {
        //if there's not enough info we won't do anything
        if (! $params || ! $editRoute) {
            return '';
        }

        $isAllowed = true; //if there is an exception, we'll assume there's no route permissions configured
        try {
            $isAllowed = $this->view->isAllowed("route/$editRoute");
        } catch (Exception) {
        }
        if (! $isAllowed) {
            return '';
        }
        $otherAttributes = $openInNewTab ? 'target="_blank"' : '';
        $pattern         = ' <a href="%s" %s><span class="glyphicon glyphicon-pencil" aria-hidden="true"></span></a>';
        return sprintf(
            $pattern,
            $this->view->url($editRoute, $params),
            $otherAttributes
        );
    }
}
