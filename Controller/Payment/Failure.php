<?php

namespace Natso\Piraeus\Controller\Payment;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;

class Failure extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    public $context;
    protected $_order;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Sales\Model\Order $_order
    ) {
        $this->context = $context;
        $this->_order = $_order;
        parent::__construct($context);
    }

    public function createCsrfValidationException(
    RequestInterface $request 
    ): ?InvalidRequestException {
    return null;
    }
   
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    public function execute()
    {
        try {
            $postData = $this->getRequest()->getPostValue();
            if (!empty($postData) && isset($postData['MerchantReference'])) {
                $this->_order->loadByIncrementId($postData['MerchantReference']);
                $this->_order->setState(\Magento\Sales\Model\Order::STATE_CANCELED, true);
                $this->_order->setStatus(\Magento\Sales\Model\Order::STATE_CANCELED);
                foreach ($this->_order->getAllItems() as $item) { // Cancel order items
                    $item->cancel();
                }
                $this->_order->addStatusToHistory($this->_order->getStatus(), 'Payment Failure. Transaction Id: ' . $postData['TransactionId']);
                $this->_order->save();
                $this->_redirect('checkout/onepage/failure');
            } else {
                $this->_redirect('/');
            }
        } catch (Exception $e) {
            echo $e;
        }
    }
}
