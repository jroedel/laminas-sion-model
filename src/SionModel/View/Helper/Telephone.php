<?php
// SionModel/View/Helper/Telephone.php

namespace SionModel\View\Helper;

use Zend\View\Helper\AbstractHelper;
use Zend\Filter\StringTrim;
use Zend\Filter\FilterChain;
use Zend\Filter\PregReplace;
use libphonenumber\PhoneNumberFormat;

class Telephone extends AbstractHelper
{
	/**
	 * 
	 * @var StringTrim
	 */
	protected $filter;
	protected $phoneUtil;
	protected $geocoder;
	
	public function __construct()
	{
		$this->filter = new FilterChain();
		$this->filter->attach(new StringTrim())
		            ->attach(new PregReplace(array(
					    'pattern'     => '/[^-+0-9]+/',
					    'replacement' => '-',
					)));
    	$this->phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
    	$this->geocoder = \libphonenumber\geocoding\PhoneNumberOfflineGeocoder::getInstance();
	}
	
    public function __invoke($telephone, $whatsApp = false, $displayWarning = false)
    {
    	if (is_null($telephone) || '' == $telephone) {
    		return '';
    	}
    	$filteredTelephone = $this->filter->filter($telephone);
    	$numberProto = null;
    	if ('' == $filteredTelephone) {
    		return '';
    	}
    	try {
    	    $numberProto = $this->phoneUtil->parse($telephone, "DE");
//     	    var_dump($numberProto);
//     	    var_dump($this->phoneUtil->format($numberProto, \libphonenumber\PhoneNumberFormat::INTERNATIONAL));
//     	    var_dump($this->phoneUtil->getRegionCodeForNumber($numberProto));
//     	    var_dump($this->phoneUtil->getNumberType($numberProto));
//     	    var_dump($this->geocoder->getDescriptionForNumber($numberProto, "en_US"));
    	    //@todo add in the current user's locale
    	    if (!is_null($numberProto)) {
    	       $telephone = $this->phoneUtil->format($numberProto, \libphonenumber\PhoneNumberFormat::INTERNATIONAL);
    	       $tooltip = $this->geocoder->getDescriptionForNumber($numberProto, "en_US");
    	       $filteredTelephone = $this->phoneUtil->format($numberProto, PhoneNumberFormat::E164);
    	       $telUrl = $this->phoneUtil->format($numberProto, PhoneNumberFormat::RFC3966);
    	    }
    	} catch (\libphonenumber\NumberParseException $e) {
    	    $tooltip = null;
    	}
    	$return = '<a href="'; 
        if (isset($telUrl) && !is_null($telUrl)) { //if we have it, use the lib-formatted URL
            $return .= $telUrl;
        } else {
        	$return .= 'tel:'.$this->getView()->escapeHTML($filteredTelephone);
        }
        $return .= '" '. ($tooltip ? ('data-toggle="tooltip" data-placement="bottom" data-container="body" data-original-title="'.$tooltip.'"'):"").
    	   '>'. $this->getView()->escapeHTML($telephone). '</a>';
    	if ($whatsApp) {
    	    $return .= ' <i class="fa fa-whatsapp" aria-hidden="true" '.
    	    'data-toggle="tooltip" data-placement="bottom" data-container="body" data-original-title="'.
    	        $this->view->translate('WhatsApp number').'"></i>';
    	}
    	
    	if ((!is_object($numberProto) || !$this->phoneUtil->isValidNumber($numberProto)) && $displayWarning) {
    	    $return .= ' <i class="fa fa-exclamation-triangle" aria-hidden="true" '.
    	    'data-toggle="tooltip" data-placement="bottom" data-container="body" data-original-title="'.
    	        $this->view->translate('Unrecognized phone number').'"></i>';
    	}
    	return $return;
    }
}
