<?php

namespace Natso\Piraeus\Helper;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    const EURO_CURRENCY_CODE = '978';
    const CONFIG_PATH_ACQUIRER_ID = 'payment/piraeus/acquirer_id';
    const CONFIG_PATH_MERCHANT_ID = 'payment/piraeus/merchant_id';
    const CONFIG_PATH_POS_ID = 'payment/piraeus/pos_id';
    const CONFIG_PATH_USERNAME = 'payment/piraeus/username';
    const CONFIG_PATH_PASSWORD = 'payment/piraeus/password';
    const CONFIG_PATH_REQUEST_TYPE = 'payment/piraeus/request_type';
    const CONFIG_PATH_EXPIRE_PREAUTH = 'payment/piraeus/expire_preauth';
    const CONFIG_PATH_INSTALLMENTS = 'payment/piraeus/installments';
    const CONFIG_PATH_TICKET_URL = 'payment/piraeus/ticket_url';
    const CONFIG_PATH_POST_URL = 'payment/piraeus/post_url';

    public $scopeConfig;
    public $checkoutSession;
    public $orderRepository;
    public $order;
    public $cart;
    protected $_objectManager;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Checkout\Model\Session $checkoutSession,
        //\Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Api\Data\OrderInterface $order,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Framework\ObjectManagerInterface $_objectManager
    ) {
        $this->checkoutSession = $checkoutSession;
        //$this->orderRepository  = $orderRepository;
        $this->order            = $order;
        $this->cart             = $cart;
        $this->scopeConfig      = $scopeConfig;
        $this->_objectManager   = $_objectManager;
    }

    public function getTicketData()
    {
        //$checkout = $this->_objectManager->get('Magento\Checkout\Model\Type\Onepage')->getCheckout();
        //$this->order->loadByIncrementId($checkout->getLastRealOrderId());
        
        //$this->order->loadByIncrementId($this->checkoutSession->getLastRealOrderId());
        //$order = $this->order;
        $order = $this->checkoutSession->getLastRealOrder();

        $acquirerId = $this->scopeConfig->getValue(
            self::CONFIG_PATH_ACQUIRER_ID,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        $merchantId = $this->scopeConfig->getValue(
            self::CONFIG_PATH_MERCHANT_ID,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        $posId = $this->scopeConfig->getValue(
            self::CONFIG_PATH_POS_ID,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        $username = $this->scopeConfig->getValue(
            self::CONFIG_PATH_USERNAME,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        $password = $this->scopeConfig->getValue(
            self::CONFIG_PATH_PASSWORD,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        $requestType = $this->scopeConfig->getValue(
            self::CONFIG_PATH_REQUEST_TYPE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        $expirePreauth = $this->scopeConfig->getValue(
            self::CONFIG_PATH_EXPIRE_PREAUTH,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        $ai = $order->getPayment()->getAdditionalInformation();

        if ( isset($ai['installments']) ) {
            $orderInstallments = $ai['installments'];
        } else {
            $orderInstallments = '';
        }

        $billAddress = $order->getBillingAddress();
        $shipAddress = $order->getShippingAddress();

        $ticketRequest = array(
            'AcquirerId'        => $acquirerId,
            'MerchantId'        => $merchantId,
            'PosId'             => $posId,
            'Username'          => $username,
            'Password'          => hash('md5', $password),
            'RequestType'       => $requestType,
            'CurrencyCode'      => self::EURO_CURRENCY_CODE,
            'MerchantReference' => $this->checkoutSession->getLastRealOrderId(),
            'Amount'            => round($order->getData('base_grand_total'), 2),
            'Installments'      => $orderInstallments,
            'ExpirePreauth'     => $expirePreauth,
            'Bnpl'              => '',
            'Parameters'        => '',
            'BillAddrCity' => $this->cleanString($billAddress->getCity()),
            'BillAddrCountry' => $billAddress->getCountryId(),
            'BillAddrLine1' => $this->cleanString($billAddress->getStreet()[0] ?? ''),
            'BillAddrLine2' => $this->cleanString($billAddress->getStreet()[1] ?? ''),
            'BillAddrLine3' => $this->cleanString($billAddress->getStreet()[2] ?? ''),
            'BillAddrPostCode' => $billAddress->getPostcode(),

            'ShipAddrCity' => $this->cleanString($shipAddress->getCity()),
            'ShipAddrCountry' => $shipAddress->getCountryId(),
            'ShipAddrLine1' => $this->cleanString($shipAddress->getStreet()[0] ?? ''),
            'ShipAddrLine2' => $this->cleanString($shipAddress->getStreet()[1] ?? ''),
            'ShipAddrLine3' => $this->cleanString($shipAddress->getStreet()[2] ?? ''),
            'ShipAddrPostCode' => $shipAddress->getPostcode(),
            'CardHolderName' => $this->cleanStringStrict($billAddress->getFirstname() . ' ' . $billAddress->getLastname()),
            'Email' => $billAddress->getEmail(),
            'HomePhone' => $billAddress->getTelephone(),
        );

        return $ticketRequest;
    }

    public function getPostData()
    {
        //$checkout = $this->_objectManager->get('Magento\Checkout\Model\Type\Onepage')->getCheckout();

        $acquirerId = $this->scopeConfig->getValue(
            self::CONFIG_PATH_ACQUIRER_ID,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        $merchantId = $this->scopeConfig->getValue(
            self::CONFIG_PATH_MERCHANT_ID,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        $posId = $this->scopeConfig->getValue(
            self::CONFIG_PATH_POS_ID,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        $username = $this->scopeConfig->getValue(
            self::CONFIG_PATH_USERNAME,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        $postData = array(
            'AcquirerId'        => $acquirerId,
            'MerchantId'        => $merchantId,
            'PosId'             => $posId,
            'User'              => $username,
            'MerchantReference' => $this->checkoutSession->getLastRealOrderId(),
            'LanguageCode'      => 'el-GR',
            'Parameters'        => ''
        );

        return $postData;
    }

    public function getInstallments()
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_PATH_INSTALLMENTS,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getAvailableInstallments() {
        $available = array();
        $installments = $this->getInstallments();
        $bgt = $this->cart->getQuote()->getData('base_grand_total');
        $installments = $installments? explode(";", $installments) : [];
        foreach ($installments as $inst) {
            $inst = explode(":",$inst);
            if ($inst[0] <= $bgt) {
                array_push($available, $inst[1]);
            }
        }
        return $available;
    }

    public function getTicketUrl()
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_PATH_TICKET_URL,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getPostUrl()
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_PATH_POST_URL,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    private function cleanString($string)
    {
        return preg_replace('/[^\pL\pN \/\:_().,+\-]/u', '', $string);
    }
    private function cleanStringStrict($string)
    {
        return preg_replace('/[^a-zA-Z\-.,:]/u', '', $string);
    }
}
