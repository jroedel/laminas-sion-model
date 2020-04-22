<?php

// SionModel/View/Helper/TelephoneList.php

namespace SionModel\View\Helper;

use Zend\View\Helper\AbstractHelper;

class TelephoneList extends AbstractHelper
{
    protected $patternWithLabel = '<span>%s (%s)</span>';
    protected $patternWithoutLabel = '<span>%s</span>';
    public function __construct()
    {
    }

    public function __invoke(array $telephoneList, $hasLabels = false)
    {
        if (! is_array($telephoneList)) {
            throw new \InvalidArgumentException('Array expected, non-array passed.');
        }
        $results = [];
        if ($hasLabels) {
            foreach ($telephoneList as $value) {
                if (
                    is_null($value) || ! is_array($value) ||
                    ! isset($value['number']) || is_null($value['number']) ||
                    ! $value['number'] || $value['number'] === ''
                ) {
                    continue;
                }
                if (isset($value['label']) && $value['label'] && 0 != strlen($value['label'])) {
                    $results[] = sprintf(
                        $this->patternWithLabel,
                        $this->view->telephone($value['number']),
                        $this->view->translate($this->view->escapeHtml($value['label']))
                    );
                } else {
                    $results[] = sprintf($this->patternWithoutLabel, $this->view->telephone($value['number']));
                }
            }
        } else {
            foreach ($telephoneList as $value) {
                if (is_null($value) || ! $value || $value === '' || ! is_string($value)) {
                    continue;
                }
                $results[] = sprintf($this->patternWithoutLabel, $this->view->telephone($value));
            }
        }
        return implode(', ', $results);
    }
}
