<?php

// SionModel/View/Helper/Email.php

namespace SionModel\View\Helper;

use Laminas\Validator\EmailAddress;
use Laminas\Filter\StringTrim;
use SionModel\View\Escape;

class Email
{
    public function __invoke($email, $onlyGlyph = false)
    {
        $trimFilter = new StringTrim();
        $emailValidator = new EmailAddress();
        $email = $trimFilter->filter($email);
        if ($emailValidator->isValid($email)) {
            $return = '<a href="mailto:' . Escape::html($email) . '">';
            if ($onlyGlyph) {
                $return .= '<span class="glyphicon glyphicon-envelope"></span>';
            } else {
                $return .= Escape::html($email);
            }
            return $return . '</a>';
        } else {
            return '';
        }
    }
}
