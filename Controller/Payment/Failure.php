<?php

namespace Natso\Piraeus\Controller\Payment;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

class Failure extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    public $context;
    protected $orderFactory;
    protected $orderRepository;
    protected $logger;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        OrderRepositoryInterface $orderRepository,
        \Psr\Log\LoggerInterface $logger,
    ) {
        $this->context = $context;
        $this->orderFactory = $orderFactory;
        $this->orderRepository = $orderRepository;
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
            $postData = $this->getRequest()->getPostValue();
            if (!empty($postData) && isset($postData['MerchantReference'])) {
                $order = $this->orderFactory->create();
                $order->loadByIncrementId($postData['MerchantReference']);
                $order->setState(\Magento\Sales\Model\Order::STATE_CANCELED, true);
                $order->setStatus(\Magento\Sales\Model\Order::STATE_CANCELED);
                foreach ($order->getAllItems() as $item) { // Cancel order items
                    $item->cancel();
                }
                $order->addStatusToHistory($order->getStatus(), 'Payment Failure. Transaction Id: ' . $postData['TransactionId']);
                $this->orderRepository->save($order);
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
