<?php

// SionModel/View/Helper/Email.php

namespace SionModel\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Laminas\Validator\EmailAddress;
use Laminas\Filter\StringTrim;

class Email extends AbstractHelper
{
    public function __invoke($email, $onlyGlyph = false)
    {
        $trimFilter = new StringTrim();
        $emailValidator = new EmailAddress();
        $email = $trimFilter->filter($email);
        if ($emailValidator->isValid($email)) {
            $return = '<a href="mailto:' . $this->getView()->escapeHtml($email) . '">';
            if ($onlyGlyph) {
                $return .= '<span class="glyphicon glyphicon-envelope"></span>';
            } else {
                $return .= $this->getView()->escapeHtml($email);
            }
            return $return . '</a>';
        } else {
            return '';
        }
    }
}
