//cart controller class
/**
 * Shopping cart controller
 */
include_once("Mage/Checkout/controllers/CartController.php");

class MG1Override_OverrideCart_CartController extends Mage_Checkout_CartController
{

    protected $customImage = ''; 

    /**
     * Add product to shopping cart action
     *
     * @return Mage_Core_Controller_Varien_Action
     * @throws Exception
     */
    public function addAction()
    {

	if (!$this->_validateFormKey()) {
            $this->_goBack();
            return;
        }
        $cart   = $this->_getCart();
        $params = $this->getRequest()->getParams();
        try {
            if (isset($params['qty'])) {
                $filter = new Zend_Filter_LocalizedToNormalized(
                    array('locale' => Mage::app()->getLocale()->getLocaleCode())
                );
                $params['qty'] = $filter->filter($params['qty']);
            }

            $product = $this->_initProduct();  
            $related = $this->getRequest()->getParam('related_product');

            /**
             * Check product availability
             */
            if (!$product) {
                $this->_goBack();
                return;
            }

            $cart->addProduct($product, $params);
            if (!empty($related)) {
                $cart->addProductsByIds(explode(',', $related));
            }
            $cart->save();

	    //-------------------------------------
	    //modify the image for the quote item
	    //-------------------------------------
	    $cItems = $cart->getQuote()->getAllItems(); 
	    $filename = $this->_getSession()->getCustomImage(); //echo $filename; exit;
	    //change image of the matching items
	    foreach($cItems as $quoteItem){
		if($quoteItem->getProductId() == $this->getRequest()->getParam('product')){
		  //var_dump( $quoteItem->getProduct()->getData() );
		  //$setProductImage = Mage::getModel('catalog/product_media_config')->getMediaUrl('cproducts/'.$filename); 
		  //$quoteItem->getProduct()->setThumbnail('/cproducts/'.$filename);
		  //$quoteItem->getProduct()->setSelectImage('/cproducts/'.$filename);
		  $quoteItem->setAdditionalData('customImage:'.'/cproducts/'.$filename);
		  $quoteItem->save(); //var_dump($quoteItem->getProduct()->getData()); exit;
		}
	    }

            $this->_getSession()->setCartWasUpdated(true);

            /**
             * @todo remove wishlist observer processAddToCart
             */
            Mage::dispatchEvent('checkout_cart_add_product_complete',
                array('product' => $product, 'request' => $this->getRequest(), 'response' => $this->getResponse())
            );

            if (!$this->_getSession()->getNoCartRedirect(true)) {
                if (!$cart->getQuote()->getHasError()) {
                    $message = $this->__('%s was added to your shopping cart.', Mage::helper('core')->escapeHtml($product->getName()));
                    $this->_getSession()->addSuccess($message);
                }
                $this->_goBack();
            }

        } catch (Mage_Core_Exception $e) {

            if ($this->_getSession()->getUseNotice(true)) {
                $this->_getSession()->addNotice(Mage::helper('core')->escapeHtml($e->getMessage()));
            } else {
                $messages = array_unique(explode("\n", $e->getMessage()));
                foreach ($messages as $message) {
                    $this->_getSession()->addError(Mage::helper('core')->escapeHtml($message));
                }
            }

            $url = $this->_getSession()->getRedirectUrl(true);

            if ($url) {
                $this->getResponse()->setRedirect($url);
            } else {
                $this->_redirectReferer(Mage::helper('checkout/cart')->getCartUrl());
            }

        } catch (Exception $e) {
            $this->_getSession()->addException($e, $this->__('Cannot add the item to shopping cart.'));
            Mage::logException($e);
            $this->_goBack();
        }
    }


    /**
     * Action to upload customized image from the item
     */
    public function ajaxUploadImageAction()
    {
	$result = array();
	$dirpath = Mage::getBaseDir('media').DS.'catalog'.DS.'cproducts'; 

	//------------------------------------------
	// create directory if not exists
	//------------------------------------------
	$iowriter = new Varien_Io_File();
	if(!$iowriter->fileExists($dirpath, false)){ $iowriter->mkdir($dirpath); }

	//-----------------------------------------
        //upload customized image for the product
	//-----------------------------------------
	if( null !== $this->getRequest()->getParam('img')  && 
			null !== $this->getRequest()->getParam('id') ){
	    try{
		//get image data and product id
		$imageData = $this->getRequest()->getParam('img'); 
		$id = (int) $this->getRequest()->getParam('id');

		//image to be modified and uploaded
		$imageData = str_replace('data:image/png;base64,', '', $imageData);
		$imageData = str_replace(' ', '+', $imageData);
		$data = base64_decode($imageData);
		$filename = $id."-".mktime().".png";
		$file = $dirpath.DS.$filename; //echo $file;
		$success = file_put_contents($file, $data); //var_dump($success); exit; 

		//add the filename to session
		$this->_getSession()->setCustomImage($filename); 
		//$this->setCustomImage($filename);		

		//filepath to be returned
		$result['success'] = $success;
		$result['file'] = $filename;
	   } 
	   catch (Exception $e) {
                $result['success'] = 0;
                $result['error'] = $this->__('Can not save item.');
           }

	}else{
	  $result['success'] = 0;
          $result['error'] = $this->__('Request Parameters "img" and "id" for product not found');
	}

	$this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }

}
