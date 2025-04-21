<?php

namespace Natso\Piraeus\Block\Payment;

class Redirect extends \Magento\Framework\View\Element\Template
{
    public      $customerSession;
    public      $logger;
    protected   $_helper;

	public function __construct(
        \Magento\Framework\View\Element\Template\Context    $context,
        \Magento\Customer\Model\Session                     $customerSession,
        \Natso\Piraeus\Helper\Data                          $_helper,
        \Psr\Log\LoggerInterface                            $logger,
        array                                               $data = []
        )
	{
        parent::__construct($context, $data);
        $this->customerSession  = $customerSession;
        $this->_helper          = $_helper;
        $this->logger           = $logger;
	}

    public function getPostData(){
        return $this->_helper->getPostData();
    }

    public function getPostUrl(){
        return $this->_helper->getPostUrl();
    }
}
