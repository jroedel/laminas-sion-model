<?php
// SionModel/View/Helper/Email.php

namespace SionModel\View\Helper;

use Zend\View\Helper\AbstractHelper;
use Zend\Validator\EmailAddress;
use Zend\Filter\StringTrim;

class Email extends AbstractHelper
{
    public function __invoke($email, $onlyGlyph = false)
    {
    	$trimFilter = new StringTrim();
    	$emailValidator = new EmailAddress();
    	$email = $trimFilter->filter($email);
    	if ($emailValidator->isValid($email)) {
    		$return = '<a href="mailto:'.$this->getView()->escapeHTML($email).'">';
    		if ($onlyGlyph) {
                $return .= '<span class="glyphicon glyphicon-envelope"></span>';
    		} else {
    		    $return .= $this->getView()->escapeHTML($email);
    		}
    		return $return.'</a>';
    	} else {
    		return '';
    	}
    }
}
