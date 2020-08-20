<?php

namespace SionModel\View\Helper;

use Zend\View\Helper\AbstractHelper;

class EditPencilNew extends AbstractHelper
{
    /**
     *
     * @param string $entityType
     * @param int $id
     */
    public function __invoke($editRoute, $params, $openInNewTab = false)
    {
        //if there's not enough info we won't do anything
        if (! $params || ! $editRoute) {
            return '';
        }

        $isAllowed = true; //if there is an exception, we'll assume there's no route permissions configured
        try {
            $isAllowed = $this->view->isAllowed("route/{$editRoute}");
        } catch (\Exception $e) {
        }
        if (! $isAllowed) {
            return '';
        }
        $otherAttributes = $openInNewTab ? 'target="_blank"' : '';
        $pattern = ' <a href="%s" %s><span class="glyphicon glyphicon-pencil" aria-hidden="true"></span></a>';
        $finalMarkup = sprintf(
            $pattern,
            $this->view->url($editRoute, $params),
            $otherAttributes
        );
        return $finalMarkup;
    }
}
