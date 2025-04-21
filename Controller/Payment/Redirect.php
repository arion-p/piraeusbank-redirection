<?php

namespace Natso\Piraeus\Controller\Payment;

class Redirect extends \Magento\Framework\App\Action\Action
{
    protected $resultPageFactory;
    protected $checkoutSession;
    protected $soapClientFactory;
    protected $messageManager;
    protected $_helper;
    protected $logger;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Webapi\Soap\ClientFactory $soapClientFactory,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Natso\Piraeus\Helper\Data $_helper,
        \Psr\Log\LoggerInterface $logger,
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->checkoutSession = $checkoutSession;
        $this->soapClientFactory = $soapClientFactory;
        $this->messageManager = $messageManager;
        $this->_helper = $_helper;
        $this->logger = $logger;
        parent::__construct($context);
    }

    public function execute()
    {
        if($this->generateTicket()) {
            return $this->resultPageFactory->create();
        } else {
            $this->_redirect('/checkout/#payment');
        }
    }

	public function generateTicket()
	{
        try {
            $soap       = $this->soapClientFactory->create($this->_helper->getTicketUrl(), ['features' => SOAP_SINGLE_ELEMENT_ARRAYS]);
            $xml        = array('Request' => $this->_helper->getTicketData());
            $response   = $soap->IssueNewTicket($xml)->IssueNewTicketResult;
            $this->logger->debug(print_r($xml, true));
            $this->logger->debug(print_r($response, true));

            if ($response->ResultCode == 0) {
                $this->checkoutSession->setPiraeusBankTicket($response);
            } else {
                $this->checkoutSession->unsPiraeusBankTicket();
                $this->messageManager->addErrorMessage(__('Error: ') . $response->ResultCode . ' - ' . $response->ResultDecription);
                return false;
            }
            return true;
        }
        catch(\Exception $e) {
            $this->logger->error('Piraeus Bank generate ticket: Error: ' . $e->getMessage());
            return false;
        }
	}

}
