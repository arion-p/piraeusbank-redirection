<?php

namespace Natso\Piraeus\Controller\Payment;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;

class Success extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    public $context;
    protected $_invoiceService;
    protected $orderFactory;
    protected $_transaction;
    protected $logger;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Sales\Model\Service\InvoiceService $_invoiceService,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\DB\Transaction $_transaction,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->_invoiceService = $_invoiceService;
        $this->_transaction = $_transaction;
        $this->orderFactory = $orderFactory;
        $this->logger = $logger;
        $this->context = $context;
        parent::__construct($context);
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException 
    {
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
                $order = $this->orderFactory->create();
                $order->loadByIncrementId($postData['MerchantReference']);
                $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING, true);
                $order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
                $order->addStatusToHistory($order->getStatus(), 'Success Payment. Transaction Id: ' . $postData['TransactionId']);
                $order->save();

                if ($order->canInvoice()) {
                    $invoice = $this->_invoiceService->prepareInvoice($order);
                    $invoice->register();
                    $invoice->save();
                    $transactionSave = $this->_transaction->addObject($invoice)->addObject($invoice->getOrder());
                    $transactionSave->save();
                    $order->addStatusHistoryComment(__('Invoiced', $invoice->getId()))->setIsCustomerNotified(false)->save();
                }
                $this->_redirect('checkout/onepage/success');
            } else {
                $this->logger->error('Piraeus Bank Payment Success: Invalid Post Data');
                $this->_redirect('/');
            }
        } catch (\Exception $e) {
            $this->logger->error('Piraeus Bank Payment Success: Exception: ' . $e->getMessage());
            $this->_redirect('/');
        }
    }
}
