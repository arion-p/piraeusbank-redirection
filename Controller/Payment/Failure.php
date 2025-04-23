<?php

namespace Natso\Piraeus\Controller\Payment;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;

class Failure extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface, HttpPostActionInterface
{
    public $context;
    protected $orderFactory;
    protected $orderRepository;
    protected $checkoutSession;
    protected $winbankLogger;
    protected $logger;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Psr\Log\LoggerInterface $winbankLogger,
        \Psr\Log\LoggerInterface $logger,
    ) {
        $this->context = $context;
        $this->orderFactory = $orderFactory;
        $this->orderRepository = $orderRepository;
        $this->checkoutSession = $checkoutSession;
        $this->winbankLogger = $winbankLogger;
        $this->logger = $logger;
        parent::__construct($context);
    }

    public function createCsrfValidationException(RequestInterface $request ): ?InvalidRequestException {
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
            $this->winbankLogger->info('Piraeus Bank Payment Failure: Post Data: ' . print_r($postData, true));

            if (isset($postData['MerchantReference'])) {
                $order = $this->orderFactory->create();
                $order->loadByIncrementId($postData['MerchantReference']);
                $order->cancel();
                $order->setStatus(\Magento\Sales\Model\Order::STATE_CANCELED);
                $result = '';
                if ($postData['ResultCode'] != 0) $result .= $postData['ResultCode'] . ' - ' . $postData['ResultDescription'];
                if ($postData['ResponseCode'] != 0) $result .= $postData['ResponseCode'] . ' - ' . $postData['ResponseDescription'];
                $order->addCommentToStatusHistory('Payment Failure:' . $result. '. Transaction Id: ' . $postData['TransactionId']);
                $this->orderRepository->save($order);
                $this->messageManager->addErrorMessage(__('Payment Failed') );
                $this->checkoutSession->setLastRealOrderId($postData['MerchantReference']);
                $this->checkoutSession->restoreQuote();
                $this->_redirect('checkout/onepage/failure');
            } else {
                $this->_redirect('/');
            }
        } catch (\Exception $e) {
            $this->logger->error('Piraeus Bank Payment Failure: Exception: ' . $e->getMessage());
            $this->_redirect('/');
        }
    }
}
