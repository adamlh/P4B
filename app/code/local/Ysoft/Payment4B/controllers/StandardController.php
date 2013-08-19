<?php

/**
* 4B Standard Checkout Controller
*
*/
class Ysoft_Payment4B_StandardController extends Mage_Core_Controller_Front_Action 
{
  protected function _expireAjax() {
    if (!Mage::getSingleton('checkout/session')->getQuote()->hasItems()) {
      $this->getResponse()->setHeader('HTTP/1.1','403 Session Expired');
      exit;
    }
 }

  /**
   * Get singleton with 4b strandard order transaction information
   *
   * @return Ysoft_Payment4B_Model_Standard
   */
  public function getStandard() {
    return Mage::getSingleton('payment4b/standard');
  }
   
  public function visaAction(){
    $this->loadLayout();
    $this->getLayout()->getBlock('root')->setTemplate('page/1column.phtml');
    $this->getLayout()->getBlock('content')->append($this->getLayout()->createBlock('payment4b/standard_visa'));
    $this->renderLayout();
  }

  public function mastercardAction(){
    $this->loadLayout();
    $this->getLayout()->getBlock('root')->setTemplate('page/1column.phtml');
    $this->getLayout()->getBlock('content')->append($this->getLayout()->createBlock('payment4b/standard_mastercard'));
    $this->renderLayout();
  }                      

  /**
   * When a customer chooses 4B on Checkout/Payment page
   *
   */
  public function redirectAction() {
    $session = Mage::getSingleton('checkout/session');
    if($session->getLastOrderId()){

      $state	=	Mage::getModel('payment4b/standard')->getConfigData('redirect_status');
      $order	=	Mage::getModel('sales/order')->load($session->getLastOrderId());
      $order->setState($state,$state,Mage::helper('payment4b')->__('Entra en TPV'),false);
      $order->save();

      // guardamos en sesion la ip original
      $sufijo="payment4b/standard/redirect/";
      $longitud=strlen($sufijo);
      $direccion=$_SERVER["REQUEST_URI"];
      $sobrante=substr($direccion, 0, -$longitud);

      $final="http://".$_SERVER["HTTP_HOST"].$sobrante;
      $core_session = Mage::getSingleton('core/session');
      $core_session->setPayment4bOriginalUrl($final);

      $this->loadLayout();
//      $this->getLayout()->getBlock('content')->append($this->getLayout()->createBlock('payment4b/standard_redirect'));
      $this->renderLayout();
    }
  }

  // devolvemos el desglose de la cesta
  public function desglosecestaAction()
  {
    $s_output="";
    $params	=	$this->getRequest()->getParams();
    $order_number=$params["order"];

    $session = Mage::getSingleton('checkout/session');
    
    // cargamos los datos del pedido
    $order = Mage::getModel('sales/order');
    $order->loadByIncrementId($order_number);
    
    // enviamos una respuesta de texto con los datos
    
    // moneda
    $i_moneda=Mage::getModel('payment4b/standard')->convertToPayment4BCurrency($order->getOrderCurrencyCode());
    
    // importe
    $f_amount=$order->getTotalDue()*100;
    $s_output.="M".$i_moneda.$f_amount."\n";
    
    // items
    $st_items=$order->getAllItems();
    $s_output.=count($st_items)."\n";
    
    foreach($st_items as $key=>$st_item)
    {
      // descripcion de cada item
      $st_data=$st_item->getData();
      $s_output.=$st_data["sku"]."\n";
      $s_output.=$st_data["name"]."\n";
      $s_output.=intval($st_data["qty_ordered"])."\n";
      
      $f_amount=$st_data["row_total"]*100;
      $s_output.=$f_amount."\n";
    }
    echo $s_output;
  }
  
  private function isFromAllowedIP()
  {
    $ip=$_SERVER["REMOTE_ADDR"];
    $st_valids=array("194.224.159.47", "194.224.159.57");
    if (in_array($ip, $st_valids)) 
    {
      return true;
    }
    else return false;
  }
  
  public function callbackAction()
  {
    $comment="";
    // primero comprobamos la IP origen
    //if ($this->IsFromAllowedIP())
    //{
      $orderStatus = Mage::getModel('payment4b/standard')->getConfigData('callbackorder_status');
      $params	=	$this->getRequest()->getParams();
      $order_number=$params["pszPurchorderNum"];
      $result=$params["result"];
      
      $order = Mage::getModel('sales/order');
      $order->loadByIncrementId($order_number);
      if ($result=="0")
      {
        // enviamos mail
        if (((int)Mage::getModel('payment4b/standard')->getConfigData('sendmailorderconfirmation'))==1)
        {
          $order->sendNewOrderEmail();
        }
        
        // creamos factura
        if((bool)Mage::getModel('payment4b/standard')->getConfigData('autoinvoice'))
        {
          $invoice = $order->prepareInvoice();
          $invoice->register()->capture();
          Mage::getModel('core/resource_transaction')
            ->addObject($invoice)
              ->addObject($invoice->getOrder())
              ->save();
          $comment	.=	$this->__('<br />Factura #%s creada', $invoice->getIncrementId());
        }
        
        
        // datos de la transaccion
        $comment.="Fecha de la transaccion: ".$params["pszTxnDate"]."<br />".
          "Codigo de autorizacion: ".$params["pszApprovalCode"]."<br />".
          "Id de transaccion: ".$params["pszTxnID"];
        
        // grabamos estado del pedido
        $order->setState($orderStatus,$orderStatus,$comment,true);
        $order->save();
        
      }
      else
      {
        // resultado erroneo
        if ($result=="1")
        {
          $message=$this->__("Pago cancelado");
          break;
        }
        else
        {
          $message="Transacci&oacute;n denegada";
          $comment="Transacci&oacute;n denegada desde 4B con codigo de error #".$params["coderror"]." - ".$params["deserror"];
        }
        
        $orderStatus	=	Mage::getModel('payment4b/standard')->getConfigData('error_status');
            
        $order->setState($orderStatus, $orderStatus, $comment, true);
        $order->save();

        if (((int)Mage::getModel('payment4b/standard')->getConfigData('sendmailorderconfirmation')) == 1) {
          $order->sendOrderUpdateEmail(true,$message);
        }
      }
      
    //}
  }
  
  public function successAction()
  {
	$core_session = Mage::getSingleton('core/session');
	$final = $core_session->getPayment4bOriginalUrl();
	$url=$final."checkout/onepage/success";

	$this->_redirect($url);
  }
  
  public function failAction()
  {
	$core_session = Mage::getSingleton('core/session');
	$final = $core_session->getPayment4bOriginalUrl();
	$url=$final."checkout/cart";
        $this->_redirect($url);
  }
  
  public function finishAction()
  {
	$params	=	$this->getRequest()->getParams();
	$core_session = Mage::getSingleton('core/session');
	$final = $core_session->getPayment4bOriginalUrl();

	if ($params["result"]=="0") 
	{
		Mage::app()->getFrontController()
			   ->getResponse()
			   ->setRedirect($final.'checkout/onepage/success');	// ok
	}
	else 
	{
		Mage::app()->getFrontController()
			   ->getResponse()
			   ->setRedirect($final.'checkout/cart');		// fallo
	}
  }
}
?>
