<?php
class Ysoft_Payment4B_Block_Standard_Mastercard extends Mage_Core_Block_Template 
{
  protected function _construct()
  {
    parent::_construct();
    $this->setTemplate('payment4b/mastercard.phtml');
  }
}
?>