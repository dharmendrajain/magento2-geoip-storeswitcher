<?php
namespace Sas\SwitchStore\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\ScopeInterface;

class SwitchStore implements ObserverInterface
{
	const XML_EXT_ENABLE = "switchstore/general/enable";
	const XML_CONFIG_SPECIFIC_COUNTRY = "switchstore/general/specificcountry";
	/**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
	protected $_scopeConfig;

	/**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
	protected $_storeManager;

	/**
     * @var \Magento\Framework\App\ResponseFactory
     */
	protected $responseFactory;


	protected $_coreSession;

	/**
     * SwitchStore constructor.
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
	public function __construct(
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		\Magento\Framework\App\ResponseFactory $responseFactory,
		\Magento\Framework\Session\SessionManagerInterface $coreSession
	)
	{
		$this->_scopeConfig = $scopeConfig;
		$this->_storeManager = $storeManager;
		$this->responseFactory = $responseFactory;
		$this->_coreSession = $coreSession;
		//we can add here dependency injection to get another class , if this observer need.
	}

	public function execute(\Magento\Framework\Event\Observer $observer)
	{
		$extensionEnabled = $this->_scopeConfig->getValue(self::XML_EXT_ENABLE);
		if(!$extensionEnabled){
			return;
		}
		$this->_coreSession->start();
		$storeSwitchFlag = $this->_coreSession->getStoreSwitchFlag();
		if($storeSwitchFlag){
			return;
		}
		$websites = $this->_storeManager->getWebsites();
		if(count($websites) > 1){
			$redirectUrl = "";
			$currentWebsuteId = $this->_storeManager->getWebsite()->getId();
			$countryCode = $this->ip_info();
			//echo $countryCode; exit;
			foreach ($websites as $key => $website) {
				$allowCountry = $this->_scopeConfig->getValue(self::XML_CONFIG_SPECIFIC_COUNTRY, ScopeInterface::SCOPE_WEBSITE, $website->getWebsiteId());
				$_allowCountries = explode(",", $allowCountry);
				if(in_array($countryCode , $_allowCountries) && $currentWebsuteId != $website->getWebsiteId()){
					$store = $website->getDefaultStore();
					$storeObj = $this->_storeManager->getStore($store);
					$redirectUrl = $storeObj->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
					continue;
				}
			}
			//echo $redirectUrl; exit;
			if($redirectUrl){
				$this->_coreSession->setStoreSwitchFlag(1);
				$this->responseFactory->create()->setRedirect($redirectUrl)->sendResponse();
				die();
			}
		}
	}

	public function ip_info($deep_detect = TRUE) {
	    $output = NULL;
	    $ip = NULL;
	    if (filter_var($ip, FILTER_VALIDATE_IP) === FALSE) {
	        $ip = $_SERVER["REMOTE_ADDR"];
	        if ($deep_detect) {
	            if (filter_var(@$_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP))
	                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
	            if (filter_var(@$_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP))
	                $ip = $_SERVER['HTTP_CLIENT_IP'];
	        }
	    }
	    //$ip = "5.28.128.0"; //IL
	    //$ip = "72.229.28.185"; //US
	    if (filter_var($ip, FILTER_VALIDATE_IP)) {
	        $ipdat = @json_decode(file_get_contents("http://www.geoplugin.net/json.gp?ip=" . $ip));
	        if (@strlen(trim($ipdat->geoplugin_countryCode)) == 2) {
	            $output = @$ipdat->geoplugin_countryCode;
	        }
	    }
	    return $output;
	}
}
?>