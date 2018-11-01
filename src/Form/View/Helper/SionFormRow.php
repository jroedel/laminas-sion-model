<?php

namespace SionModel\Form\View\Helper;

use Zend\Form\ElementInterface;
use TwbBundle\Form\View\Helper\TwbBundleFormRow;

class SionFormRow extends TwbBundleFormRow
{
    /**
     * Render element's help block, especially for moderation-mode form rendering
     * This makes sure that JTranslate doesn't try translating the content
     * @param ElementInterface $oElement
     * @return string
     */
    protected function renderHelpBlock(ElementInterface $oElement)
    {
        $this->setTranslatorEnabled(false);
        $return = parent::renderHelpBlock($oElement);
        $this->setTranslatorEnabled(true);
        return $return;
    }
}
