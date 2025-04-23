<?php

namespace Natso\Piraeus\Controller\Payment;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

class DoCancel extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    public $context;
    protected $orderFactory;
    protected $checkoutSession;
    protected $orderRepository;
    protected $messageManager;
    protected $logger;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        OrderRepositoryInterface $orderRepository,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->context   = $context;
        $this->orderFactory = $orderFactory;
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->messageManager = $messageManager;
        $this->logger = $logger;
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
            if ($this->checkoutSession->getLastRealOrderId()) {
                $order = $this->orderFactory->create();
                $order->loadByIncrementId($this->checkoutSession->getLastRealOrderId());
                $order->cancel();
                $order->setStatus(\Magento\Sales\Model\Order::STATE_CANCELED);
                $order->addCommentToStatusHistory('Payment canceled from client after redirect.');
                $this->orderRepository->save($order);
                $this->messageManager->addErrorMessage(__('Payment canceled by user.'));
                $this->checkoutSession->restoreQuote();
                $this->_redirect('checkout/cart');
            } else {
                $this->logger->error('Piraeus Bank Payment Cancel: No order found.');
                $this->_redirect('/');
            }
        } catch (\Exception $e) {
            $this->logger->error('Piraeus Bank Payment Cancelled: Exception: ' . $e->getMessage());
            $this->_redirect('/');
        }
    }
}
