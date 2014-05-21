<?php
/**
 * abstract class to auto set number
 *
 * @package     Sales
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Alexander Stintzing <a.stintzing@metaways.de>
 * @copyright   Copyright (c) 2013 Metaways Infosystems GmbH (http://www.metaways.de)
 * 
 * @todo: make this more general (tinebase)
 *
 */

abstract class Sales_Controller_NumberableAbstract extends Tinebase_Controller_Record_Abstract {
    /**
     * the number gets prefixed zeros until this amount of chars is reached
     *
     * @var integer
     */
    protected $_numberZerofill = NULL;
    
    /**
     * the prefix for the invoice
     *
     * @var string
     */
    protected $_numberPrefix = NULL;
    
    /**
     * the property which holds the number of the record
     * 
     * @var string
     */
    protected $_numberProperty = 'number';
    
    /**
     * Checks if number is unique if manual generated
     * 
     * @param Tinebase_Record_Interface $record
     * @param Boolean $update true if called un update
     * @throws Tinebase_Exception_Duplicate
     * @return boolean
     */
    protected function _checkNumberUniquity($record, $update = false)
    {
        $filterArray = array(
            array('field' => 'number', 'operator' => 'equals', 'value' => $record->{$this->_numberProperty})
        );
        
        if ($update) {
            $filterArray[] = array('field' => 'id', 'operator' => 'notin', 'value' => $record->getId());
        }
        
        $filterName = $this->_modelName . 'Filter';
        $filter = new $filterName($filterArray);
        $existing = $this->search($filter);
    
        if (count($existing->toArray()) > 0) {
            $e = new Tinebase_Exception_Duplicate(_('The number you have tried to set is already in use!'));
            $e->setData($existing);
            $e->setClientRecord($record);
            throw $e;
        }
        
        return true;
    }
    
    /**
     * sets the number of the record
     * 
     * @param Tinebase_Record_Interface $record
     * @throws Sales_Exception_DuplicateNumber
     */
    protected function _setNextNumber($record)
    {
        if (empty($record->number)) {
            // create number
            $this->_addNextNumber($record);
        } else {
            // check uniquity if not autogenerated
            try {
                $this->_checkNumberUniquity($record, false);
                $this->_setLastNumber($record);
            } catch (Tinebase_Exception_Duplicate $e) {
                throw new Sales_Exception_DuplicateNumber();
            }
        }
    }
    
    /**
     * adds the next available number to the record
     * 
     * @param Tinebase_Record_Interface $record
     */
    protected function _addNextNumber($record)
    {
        $numberBackend = new Sales_Backend_Number();
        $number = $numberBackend->getNext($this->_modelName, Tinebase_Core::getUser()->getId());
        $record->{$this->_numberProperty} = intval($number->number);
        
        $this->_formatNumber($record);
    }
    
    /**
     * sets the last number by a given record, if the number has been manually set
     * 
     * @param Tinebase_Record_Interface $record
     */
    protected function _setLastNumber($record)
    {
        $numberBackend = new Sales_Backend_Number();
        $number = $numberBackend->getCurrent($this->_modelName);
        
        $this->_removePrefix($record);
        
        if (intval($record->{$this->_numberProperty}) > $number) {
            $numberBackend->setCurrent($this->_modelName, $record->{$this->_numberProperty});
        }
        
        $this->_formatNumber($record);
    }
    
    /**
     * removes the prefix from the number
     * 
     * @param Tinebase_Record_Interface $record
     */
    protected function _removePrefix($record)
    {
        if (strpos($record->{$this->_numberProperty}, $this->_numberPrefix) > -1) {
            $record->{$this->_numberProperty} = substr($this->_numberPrefix, strlen($record->{$this->_numberProperty}));
        }
    }
    
    /**
     * returns the formatted invoice number, if $_numberPrefix and/or $_numberZerofill is set.
     *
     * @param Tinebase_Record_Interface $record
     */
    protected function _formatNumber($record)
    {
        $this->_removePrefix($record);
        
        $record->{$this->_numberProperty} = ($this->_numberPrefix ? $this->_numberPrefix : '') . ($this->_numberZerofill 
            ? str_pad((string) $record->{$this->_numberProperty}, $this->_numberZerofill, '0', STR_PAD_LEFT) 
            : $record->{$this->_numberProperty});
        
    }
}
