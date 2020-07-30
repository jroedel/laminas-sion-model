<?php
namespace SionModel\Validator;

use Zend\Validator\ValidatorChain;

class ValidatorOrChain extends ValidatorChain
{
    /**
     * Returns true if and only if $value passes any validations in the chain
     *
     * Validators are run in the order in which they were added to the chain (FIFO).
     *
     * @param  mixed $value
     * @param  mixed $context Extra "context" to provide the validator
     * @return bool
     */
    public function isValid($value, $context = null)
    {
        $this->messages = [];
        $result         = 0 === count($this->validators) ? true : false;
        foreach ($this->validators as $element) {
            $validator = $element['instance'];
            if ($validator->isValid($value, $context)) {
                $result = true;
                continue;
            }
            $messages       = $validator->getMessages();
            $this->messages = array_replace_recursive($this->messages, $messages);
            if ($element['breakChainOnFailure']) {
                break;
            }
        }
        return $result;
    }
}
