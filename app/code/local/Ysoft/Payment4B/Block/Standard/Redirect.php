<?php
class Ysoft_Payment4B_Block_Standard_Redirect extends Mage_Core_Block_Template 
{
  protected function _construct() 
  {
    parent::_construct();
    $standard = Mage::getModel('payment4b/standard');
    $form = new Varien_Data_Form();
    $form->setAction($standard->getPayment4BUrl())
      ->setId('payment4b_standard_checkout')
      ->setName('4B')
      ->setMethod('POST')
      ->setUseContainer(true);
      
      foreach ($standard->getStandardCheckoutFormFields() as $field=>$value) {
        $form->addField($field, 'hidden', array('name'=>$field, 'value'=>$value));
      }
      
      $html = '';
      if ($useContainer = $form->getUseContainer()) {
        $html .= '<form '.$form->serialize(array('id', 'name', 'method', 'action', 'enctype', 'class', 'onsubmit')).'>';
        $html .= '<div>';
        $html .= '</div>';
     }
     foreach ($form->getElements() as $element) {
       $html.= $element->toHtml();
     }
     if (strtolower($form->getData('method')) == 'post') {
          $html .= '<input name="form_key" type="hidden" value="'.Mage::getSingleton('core/session')->getFormKey().'" />';
     }
     if ($useContainer) {
       $html.= '</form>';
     }
                                                                                                                                                                                          
      $this->setFormRedirect($html);
      $this->setTemplate('payment4b/redirect.phtml');
    }
}
?>