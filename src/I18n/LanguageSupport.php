<?php
namespace SionModel\I18n;

use Matriphe\ISO639\ISO639;

class LanguageSupport extends ISO639
{ 
    /**
     * Fetch value options for a Select form element
     * @param string $labelsInOwnLanguage
     * @return string[]
     */
    public function getLanguageValueOptions($labelsInOwnLanguage = false)
    {
        $valueOptions = [];
        $labelField = $labelsInOwnLanguage ? 5 : 4;
        foreach ($this->languages as $languageRow) {
            $valueOptions[$languageRow[0]] = $languageRow[$labelField];
        }
        return $valueOptions;
    }
}
