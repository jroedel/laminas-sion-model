<?php

declare(strict_types=1);

// SionModel/View/Helper/TelephoneList.php

namespace SionModel\View\Helper;

use Laminas\View\Helper\AbstractHelper;

use function implode;
use function is_array;
use function is_string;
use function sprintf;
use function strlen;

class TelephoneList extends AbstractHelper
{
    protected string $patternWithLabel    = '<span>%s (%s)</span>';
    protected string $patternWithoutLabel = '<span>%s</span>';

    public function __invoke(array $telephoneList, bool $hasLabels = false)
    {
        $results = [];
        if ($hasLabels) {
            foreach ($telephoneList as $value) {
                if (! isset($value) || ! is_array($value) || ! isset($value['number']) || ! $value['number']) {
                    continue;
                }
                if (isset($value['label']) && $value['label'] && 0 !== strlen($value['label'])) {
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
                if (! isset($value) || ! $value || ! is_string($value)) {
                    continue;
                }
                $results[] = sprintf($this->patternWithoutLabel, $this->view->telephone($value));
            }
        }
        return implode(', ', $results);
    }
}
