<?php

// SionModel/I18n/View/Helper/DayFormat.php

namespace SionModel\I18n\View\Helper;

use Laminas\Translator\TranslatorInterface;
use SionModel\View\Escape;

class DayFormat
{
    protected $englishStrings;
    protected $cardinalEndings;

    public function __construct(private readonly ?TranslatorInterface $translator = null)
    {
        $this->englishStrings = [
            1 => 'January %s',
            2 => 'February %s',
            3 => 'March %s',
            4 => 'April %s',
            5 => 'May %s',
            6 => 'June %s',
            7 => 'July %s',
            8 => 'August %s',
            9 => 'September %s',
            10 => 'October %s',
            11 => 'November %s',
            12 => 'December %s',
        ];
        $this->cardinalEndings = [
            0 => 'th',
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            4 => 'th',
            5 => 'th',
            6 => 'th',
            7 => 'th',
            8 => 'th',
            9 => 'th',
            10 => 'th',
            11 => 'th',
            12 => 'th',
            13 => 'th',
            14 => 'th',
            15 => 'th',
            16 => 'th',
            17 => 'th',
            18 => 'th',
            19 => 'th',
            20 => 'th',
            21 => 'st',
            22 => 'nd',
            23 => 'rd',
            24 => 'th',
            25 => 'th',
            26 => 'th',
            27 => 'th',
            28 => 'th',
            29 => 'th',
            30 => 'th',
            31 => 'st',
        ];
    }

    /**
     *
     * @param \DateTime $date
     */
    public function __invoke($date)
    {
        if (! is_object($date)) {
            throw new \Exception('Invalid date.');
        }
        $month = (int)$date->format('m');
        $day = (int)$date->format('j');
        $pattern = $this->englishStrings[$month];
        //'Patres' is this string set's own text domain, and was passed here before the
        //translator was injected; keeping it is what stops these month names landing in
        //`default` and filing a second phrase row for every one of them.
        $pattern = null === $this->translator
            ? $pattern
            : $this->translator->translate($pattern, 'Patres');
        $return  = Escape::html(sprintf($pattern, $day));
        if (\Locale::getPrimaryLanguage(\Locale::getDefault()) == 'en') {
            $return .= $this->cardinalEndings[$day];
        }
        return $return;
    }
}
