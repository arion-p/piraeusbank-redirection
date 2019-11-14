<?php

namespace Natso\Piraeus\Controller\Payment;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;

class Success extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    public $context;
    protected $_invoiceService;
    protected $_order;
    protected $_transaction;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Sales\Model\Service\InvoiceService $_invoiceService,
        \Magento\Sales\Model\Order $_order,
        \Magento\Framework\DB\Transaction $_transaction
    ) {
        $this->_invoiceService = $_invoiceService;
        $this->_transaction    = $_transaction;
        $this->_order          = $_order;
        $this->context         = $context;
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
            if (!empty($postData) && isset($postData['MerchantReference']) && isset($postData['TransactionId'])) {
                $this->_order->loadByIncrementId($postData['MerchantReference']);
                $this->_order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING, true);
                $this->_order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
                $this->_order->addStatusToHistory($this->_order->getStatus(), 'Success Payment. Transaction Id: ' . $postData['TransactionId']);
                $this->_order->save();

                if ($this->_order->canInvoice()) {
                    $invoice = $this->_invoiceService->prepareInvoice($this->_order);
                    $invoice->register();
                    $invoice->save();
                    $transactionSave = $this->_transaction->addObject($invoice)->addObject($invoice->getOrder());
                    $transactionSave->save();
                    $this->_order->addStatusHistoryComment(__('Invoiced', $invoice->getId()))->setIsCustomerNotified(false)->save();
                }
                $this->_redirect('checkout/onepage/success');
            } else {
                $this->_redirect('/');
            }
        } catch (Exception $e) {
            echo $e;
        }
    }
}
