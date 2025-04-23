<?php

namespace Natso\Piraeus\Controller\Payment;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;

class Success extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface, HttpPostActionInterface
{
    public $context;
    protected $_checkoutSession;
    protected $_invoiceService;
    protected $orderFactory;
    protected $orderRepository;
    protected $_transaction;
    protected $_helper;
    protected $winbankLogger;
    protected $logger;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\Service\InvoiceService $_invoiceService,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Framework\DB\Transaction $_transaction,
        \Natso\Piraeus\Helper\Data $_helper,
        \Psr\Log\LoggerInterface $winbankLogger,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->_checkoutSession = $checkoutSession;
        $this->_invoiceService = $_invoiceService;
        $this->_transaction = $_transaction;
        $this->orderFactory = $orderFactory;
        $this->orderRepository = $orderRepository;
        $this->_helper = $_helper;
        $this->winbankLogger = $winbankLogger;
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
            $postData = $this->getRequest()->getParams();
            $this->winbankLogger->info('Piraeus Bank Payment Success: Post Data: ' . print_r($postData, true));

            if($postData['ResultCode'] == 0 && $postData['StatusFlag'] == 'Success') {
                if (isset($postData['MerchantReference']) && isset($postData['TransactionId'])) {
                    $order = $this->orderFactory->create();
                    $order->loadByIncrementId($postData['MerchantReference']);
                    if ($this->_helper->isValidResponse($order, $postData)) {

                        $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING, true);
                        $order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
                        $order->addCommentToStatusHistory('Success Payment. Transaction Id: ' . $postData['TransactionId']);
                        $this->orderRepository->save($order);

                        if ($order->canInvoice()) {
                            $invoice = $this->_invoiceService->prepareInvoice($order);
                            $invoice->register();
                            $this->_transaction
                                    ->addObject($invoice)
                                    ->addObject($order)
                                    ->save();
                            $order->addCommentToStatusHistory(__('Invoiced', $invoice->getId()))->setIsCustomerNotified(false);
                            $this->orderRepository->save($order);
                        }
                        // add order information to the session
                        $this->_checkoutSession
                            ->setLastOrderId($order->getId())             
                            ->setLastRealOrderId($order->getIncrementId())
                            ->setLastOrderStatus($order->getStatus());
                        $this->_checkoutSession->setLastQuoteId($order->getQuoteId());
                        $this->_checkoutSession->setLastSuccessQuoteId($order->getQuoteId());
                        
                        $this->_redirect('checkout/onepage/success', ['_secure' => true]);
                        return;
                    }
                    else {
                        $order->addCommentToStatusHistory('Invalid response from Piraeus Bank. Transaction Id: ' . $postData['TransactionId']);
                        $this->orderRepository->save($order);
                    }
                }
                $this->logger->error('Piraeus Bank Payment Success: Invalid Post Data');
            } else {
                $this->logger->debug('Piraeus Bank Payment Success: Got failure response in success url', $postData);
                $this->_redirect('winbank/payment/failure');
                $this->getResponse()->setHttpResponseCode(307);
                return;
            }
        } catch (\Exception $e) {
            $this->logger->error('Piraeus Bank Payment Success: Exception: ' . $e->getMessage());
        }
        $this->_redirect('/');
    }
}
