<?php
/**
 * Astrio Co.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0).
 * It is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you are unable to obtain it through the world-wide-web, please send
 * an email to info@astrio.net so we can send you a copy immediately.
 *
 * @category  Astrio
 * @package   Astrio_Postcalc
 * @copyright Copyright (c) 2010-2017 Astrio Co. (http://astrio.net)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Postcalc shipping carrier model
 *
 * @category Astrio
 * @package  Astrio_Postcalc
 * @author   Vladimir Khalzov <v.khalzov@astrio.net>
 */
class Astrio_Postcalc_Model_Shipping_Carrier_Postcalc
    extends Mage_Shipping_Model_Carrier_Abstract
    implements Mage_Shipping_Model_Carrier_Interface
{
    /**
     * Code of the carrier
     */
    const CODE = 'astriopostcalc';

    /**
     * Insurance types
     */
    const INSURANCE_TYPE_FULL    = 'f';
    const INSURANCE_TYPE_PARTIAL = 'p';

    /**
     * Fee types
     */
    const FEE_TYPE_FIXED    = 'f';
    const FEE_TYPE_PERCENT  = 'p';
    const FEE_TYPE_DISABLED = '0';

    /**
     * Russia country ID
     */
    const RUSSIA_COUNTRY_ID = 'RU';

    /**
     * Russian rubles currency code
     */
    const CURRENCY_RUB = 'RUB';

    /**
     * Carrier API URLs
     */
    const CARRIER_TEST_API_URL = 'http://test.postcalc.ru';
    const CARRIER_LIVE_API_URL = 'http://api.postcalc.ru';

    /**
     * Response charset
     */
    const RESPONSE_CHARSET = 'UTF-8';

    /**
     * Response format
     */
    const RESPONSE_FORMAT = 'PHP';

    /**
     * Request timeout
     */
    const REQUEST_TIMEOUT = 60;

    /**
     * Cache settings
     */
    const CACHE_TTL = 3600;
    const CACHE_TAG = 'astrio_postcalc_response_cache_tag';

    /**
     * Carrier's code
     *
     * @var string
     */
    protected $_code = self::CODE;

    /**
     * Methods list cache
     *
     * @var array|null
     */
    protected $_methods = null;

    /**
     * Shipping rate request model
     *
     * @var Mage_Shipping_Model_Rate_Request|null
     */
    protected $_request = null;

    /**
     * Rate request result data
     *
     * @var Mage_Shipping_Model_Rate_Result|null
     */
    protected $_result = null;

    /**
     * Raw rate request data
     *
     * @var Varien_Object|null
     */
    protected $_rawRequest = null;

    /**
     * Base currency rates cache
     *
     * @var array()
     */
    protected $_baseCurrencyRate = array();

    /**
     * Returns result of request
     *
     * @return Mage_Shipping_Model_Rate_Result|null
     */
    public function getResult()
    {
        return $this->_result;
    }

    /**
     * Determine whether zip-code is required for the country of destination
     *
     * @param string $countryId Country ID (OPTIONAL)
     *
     * @return boolean
     */
    public function isZipCodeRequired($countryId = null)
    {
        // Zip code is required only if destination is national (within Russia)
        return (self::RUSSIA_COUNTRY_ID == $countryId);
    }

    /**
     * Returns allowed shipping methods
     *
     * @return array
     */
    public function getAllowedMethods()
    {
        $allowedMethods = explode(',', $this->getConfigData('allowed_methods'));

        $result = array();

        if (!empty($allowedMethods)) {

            $allMethods = $this->getAllMethods();

            foreach ($allowedMethods as $method) {
                if (isset($allMethods[$method])) {
                    $result[$method] = $allMethods[$method]['title'];
                }
            }
        }

        return $result;
    }

    /**
     * Collects rates
     *
     * @param Mage_Shipping_Model_Rate_Request $request Shipping rate request model
     *
     * @return Mage_Shipping_Model_Rate_Result|boolean
     */
    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {
        $result = false;

        if ($this->isActive()) {

            $this->setRequest($request);

            $this->_result = $this->_getQuotes();

            $this->_updateFreeMethodQuote($request);

            $result = $this->getResult();
        }

        return $result;
    }

    /**
     * Set free method request
     *
     * @param string $freeMethod Free method code
     *
     * @return void
     */
    protected function _setFreeMethodRequest($freeMethod)
    {
        $r = $this->_rawRequest;

        $r->setWeight($this->getTotalNumOfBoxes($r->getFreeMethodWeight()));
    }

    /**
     * Prepares and sets request to this instance
     *
     * @param Mage_Shipping_Model_Rate_Request $request Shipping rate request model
     *
     * @return $this
     */
    public function setRequest(Mage_Shipping_Model_Rate_Request $request)
    {
        $this->_request = $request;

        $r = new Varien_Object();

        // Set request all items
        $r->setRequestAllItems($this->_getRequestAllItems($request));

        // Set origin country
        if ($request->hasOrigCountryId()) {
            $origCountry = $request->getOrigCountryId();
        } else {
            $origCountry = Mage::getStoreConfig(
                Mage_Shipping_Model_Shipping::XML_PATH_STORE_COUNTRY_ID, $request->getStoreId()
            );
        }

        $r->setOrigCountry($origCountry);

        // Set destination country
        $destCountry = ($request->getDestCountryId())
            ? $request->getDestCountryId()
            : self::RUSSIA_COUNTRY_ID;

        $r->setDestCountry(Mage::getModel('directory/country')->load($destCountry)->getIso2Code());

        // Set destination region ID
        $r->setDestRegionId($request->getDestRegionId());

        // Set destination postal code
        $r->setDestPostcode($request->getDestPostcode());

        // Set original postal code
        $origPostcode = trim((string) $this->getConfigData('post_office_zipcode'));

        if ($request->hasOrigPostcode()) {
            $r->setOrigPostcode($request->getOrigPostcode());
        } elseif (!empty($origPostcode)) {
            $r->setOrigPostcode($origPostcode);
        } else {
            $r->setOrigPostcode(
                Mage::getStoreConfig(Mage_Shipping_Model_Shipping::XML_PATH_STORE_ZIP, $request->getStoreId())
            );
        }

        // Set total weight
        $r->setWeight($this->getTotalNumOfBoxes($request->getPackageWeight()));

        // Set free method weight
        if ($request->getFreeMethodWeight() != $request->getPackageWeight()) {
            $r->setFreeMethodWeight($request->getFreeMethodWeight());
        }

        // Set declared value/valuation
        $r->setValue($this->_calculateDeclaredValue($request->getPackagePhysicalValue()));

        // Set include VAT
        $r->setIncludeVat($this->getConfigFlag('include_vat'));

        // Set administrator email
        $r->setEmail($this->getConfigData('admin_email'));

        // Set site domain name
        $r->setSite(Mage::app()->getRequest()->getHttpHost());

        // Set insurance base/type
        $r->setInsuranceBase($this->getConfigData('insurance_type'));

        // Set processing and packing fees amount
        foreach (array('processing', 'packing') as $feeType) {

            $feeAmount = $this->_calculateIncludedFee(
                $this->getConfigData('include_' . $feeType . '_fee'),
                $this->getConfigData($feeType . '_fee_amount'),
                $request->getPackageValue()
            );

            if (0 < $feeAmount) {
                $r->{'set' . ucfirst($feeType) . 'Fee'}($feeAmount);
            }
        }

        // Set shipment date offset
        $shipmentOffset = intval($this->getConfigData('shipment_date_offset'));

        if (0 < $shipmentOffset) {
            $r->setShipmentOffset($shipmentOffset);
        }

        // Set software
        $r->setSoftware('Astrio\\Postcalc_' . Mage::getConfig()->getModuleConfig('Astrio_Postcalc')->version);

        // Set max (highest) item weight (in grams)
        $r->setMaxItemWeight($this->_getMaxItemsWeight($r->getRequestAllItems()));

        // Set base subtotal (including tax) for free shipping method
        $r->setBaseSubtotalInclTax($request->getBaseSubtotalInclTax());

        Mage::dispatchEvent('astrio_postcalc_set_request', array('rawRequest' => $r, 'request' => $request));

        $this->_rawRequest = $r;

        return $this;
    }

    /**
     * Does remote request and handles errors
     *
     * @return Mage_Shipping_Model_Rate_Result
     */
    protected function _getQuotes()
    {
        $response = $this->_doRatesRequest();

        return $this->_prepareRateResponse($response);
    }

    /**
     * Makes remote request to the carrier and returns a response
     *
     * @return string
     */
    protected function _doRatesRequest()
    {
        $apiUrl = $this->_getAPIUrl();
        $requestParams = $this->_prepareRequestParams();

        $requestString = serialize($requestParams);

        $responseBody = (0 < self::CACHE_TTL)
            ? $this->_getCachedQuotes($requestString)
            : null;

        $debugData = array('request' => array('url' => $apiUrl, 'params' => $requestParams));

        if ($responseBody === null) {

            try {

                $httpClient = new Varien_Http_Client(
                    $apiUrl,
                    array(
                        'timeout' => self::REQUEST_TIMEOUT,
                    )
                );

                $httpClient->setParameterGet($requestParams);

                $response = $httpClient->request();

                if ($response->isSuccessful()) {

                    $responseBody = $response->getBody();
                    $unsResponseBody = @unserialize($responseBody);

                    if (false === $unsResponseBody) {

                        // Error: response body cannot be unserialized
                        $debugData['result'] = $responseBody;
                        $responseBody = null;

                        throw new Exception($this->_getHelper()->__(
                            'Response body cannot be unserialized: %s', $debugData['result']
                        ));
                    }

                    if ($unsResponseBody['Status'] != 'OK') {

                        // Error: something wrong with the request
                        throw new Exception($this->_getHelper()->__(
                            'Postcalc Response Error (%s): %s',
                            $unsResponseBody['Status'],
                            $unsResponseBody['Message']
                        ));
                    }

                    if ($unsResponseBody['Status'] != 'OK') {

                        // Error: something wrong with the request
                        throw new Exception($this->_getHelper()->__(
                            'Postcalc Response Error (%s): %s',
                            $unsResponseBody['Status'],
                            $unsResponseBody['Message']
                        ));
                    }

                    if (0 < self::CACHE_TTL) {
                        $this->_setCachedQuotes($requestString, $responseBody);
                    }

                    $debugData['result'] = $unsResponseBody;

                } else {

                    // Error: HTTP connection error
                    throw new Exception(
                        $this->_getHelper()->__('HTTP error (%s): %s', $response->getCode(), $response->getMessage()),
                        $response->getCode()
                    );
                }

            } catch (Exception $e) {

                $debugData['result'] = array('error' => $e->getMessage(), 'code' => $e->getCode());
            }

        } else {

            $debugData['result'] = unserialize($responseBody);
        }

        $this->_debug($debugData);

        return $responseBody;
    }

    /**
     * Prepares and returns request params
     *
     * @return array
     */
    protected function _prepareRequestParams()
    {
        $r = $this->_rawRequest;

        $params = array(
            'f'  => $r->getOrigPostcode(),
            'c'  => $r->getDestCountry(),
            't'  => trim((string) $r->getDestPostcode()),
            'w'  => $this->_convertSystemWeightToGrams($r->getWeight()),
            'st' => $r->getSite(),
            'ml' => $r->getEmail(),
            'o'  => self::RESPONSE_FORMAT,
            'ib' => $r->getInsuranceBase(),
            'vt' => intval($r->getIncludeVat()),
            'cs' => self::RESPONSE_CHARSET,
            'sw' => $r->getSoftware(),
        );

        /* @var $baseCurrency Mage_Directory_Model_Currency */
        $baseCurrency = $this->_request->getBaseCurrency();

        if (self::CURRENCY_RUB == $baseCurrency->getCode()) {

            // Base currency is russian rubles, not need for converting it
            $params['v'] = $this->_formatPrice($r->getValue());

            if ($r->hasProcessingFee()) {
                $params['pr'] = $this->_formatPrice($r->getProcessingFee());
            }

            if ($r->hasPackingFee()) {
                $params['pk'] = $this->_formatPrice($r->getPackingFee());
            }

        } else {

            // Convert base currency to russian rubles
            $params['v'] = $this->_formatPrice($baseCurrency->convert($r->getValue(), self::CURRENCY_RUB));

            if ($r->hasProcessingFee()) {
                $params['pr'] = $this->_formatPrice($baseCurrency->convert($r->getProcessingFee(), self::CURRENCY_RUB));
            }

            if ($r->hasPackingFee()) {
                $params['pk'] = $this->_formatPrice($baseCurrency->convert($r->getPackingFee(), self::CURRENCY_RUB));
            }
        }

        if ($r->hasShipmentOffset()) {
            $params['d'] = '+' . $r->getShipmentOffset() . 'days';
        }

        return $params;
    }

    /**
     * Returns formatted price
     *
     * @param float $price Price
     *
     * @return string
     */
    protected function _formatPrice($price)
    {
        return sprintf('%01.2f', Mage::app()->getStore($this->_request->getStoreId())->roundPrice($price));
    }

    /**
     * Prepares shipping rates response
     *
     * @param string $response Response data (raw response)
     *
     * @return Mage_Shipping_Model_Rate_Result|boolean
     */
    protected function _prepareRateResponse($response)
    {
        $result = false;

        $response = trim($response);

        if (!empty($response)) {

            $response = unserialize($response);

            /* @var $result Mage_Shipping_Model_Rate_Result */
            $result = Mage::getModel('shipping/rate_result');

            if ('OK' == $response['Status']) {

                foreach ($this->getAllowedMethods() as $methodCode => $methodTitle) {

                    $data = $this->_getRateResponseMethod($methodCode, $response);

                    if ($this->_isRateResponseMethodValid($data)) {
                        $result->append($this->_prepareRateResultMethod($methodCode, $data));
                    }
                }

            } else {

                // Error: must be an error in request

                /* @var $error Mage_Shipping_Model_Rate_Result_Error */
                $error = Mage::getModel('shipping/rate_result_error');

                $error->setCarrier($this->getCarrierCode());
                $error->setCarrierTitle($this->getConfigData('title'));
                $error->setErrorMessage($this->getConfigData('specificerrmsg'));

                $result->append($error);
            }
        }

        return $result;
    }

    /**
     * Return shipping rate from the response by code
     *
     * @param string $code     Shipping method code
     * @param array  $response Response data
     *
     * @return array|null
     */
    protected function _getRateResponseMethod($code, $response)
    {
        $method = $this->getAllMethods($code);

        return (isset($response['Отправления'][$method['code']]))
            ? $response['Отправления'][$method['code']]
            : null;
    }

    /**
     * Returns "true" if rate response if valid
     *
     * @param array|null $data Response method data
     *
     * @return boolean
     */
    protected function _isRateResponseMethodValid($data)
    {
        $maxItemWeight = $this->_convertSystemWeightToGrams($this->_rawRequest->getMaxItemWeight());

        return (
            !empty($data)
            && is_array($data)
            && isset($data['Доставка'])
            && 0 < doubleval($data['Тариф'])
            && doubleval($data['ПредельныйВес']) >= $maxItemWeight
        );
    }

    /**
     * Prepares and returns shipping rate result
     *
     * @param string $code Shipping method code
     * @param array  $data Response method data
     *
     * @return Mage_Shipping_Model_Rate_Result_Method
     */
    protected function _prepareRateResultMethod($code, $data)
    {
        $methodData = $this->getAllMethods($code);

        /* @var $method Mage_Shipping_Model_Rate_Result_Method */
        $method = Mage::getModel('shipping/rate_result_method');

        $method->setCarrier($this->getCarrierCode());
        $method->setCarrierTitle($this->getConfigData('title'));

        $method->setMethod($code);
        $method->setMethodTitle($methodData['title']);

        $deliveryTimeFrames = $this->_prepareMethodDeliveryTimeFrames($data);

        if (
            $this->getConfigFlag('display_delivery_time')
            && !empty($deliveryTimeFrames)
        ) {
            // Add delivery time frames to method title
            $method->setMethodTitle(
                sprintf('%s (%s %s)', $method->getMethodTitle(), $deliveryTimeFrames, $this->_getHelper()->__('day(s)'))
            );
        }

        $method->setDeliveryTime($deliveryTimeFrames);

        //$method->setMethodDescription($methodTitle);

        $cost = doubleval($data['Доставка']);

        /* @var $baseCurrency Mage_Directory_Model_Currency */
        $baseCurrency = $this->_request->getBaseCurrency();

        if (self::CURRENCY_RUB != $baseCurrency->getCode()) {
            // Convert base currency to russian rubles if necessary
            $cost = $cost * $this->_getBaseCurrencyRate(self::CURRENCY_RUB);
        }

        $method->setCost($cost);

        $price = $this->_prepareRateResultMethodPrice($cost, $code, $data);

        if (self::CURRENCY_RUB != $baseCurrency->getCode()) {
            // Round up the price to avoid losing value
            $price = ceil($price * 100) / 100;
        }

        $method->setPrice($price);

        return $method;
    }

    /**
     * Prepares rate result method price
     *
     * @param float  $cost Cost
     * @param string $code Shipping method code
     * @param array  $data Response method data
     *
     * @return float
     */
    protected function _prepareRateResultMethodPrice($cost, $code, $data)
    {
        // Save original _numBoxes value
        $_numBoxes = $this->_numBoxes;

        $packagesQty = intval($data['Количество']);

        if (1 < $packagesQty) {
            $this->_numBoxes = $packagesQty;
            $cost = $cost / $packagesQty;
        }

        $price = $this->getMethodPrice($cost, $code);

        // Restore original _numBoxes value
        $this->_numBoxes = $_numBoxes;

        return $price;
    }

    /**
     * Prepares delivery time frames fora shipping method
     *
     * @param array $data Response method data
     *
     * @return string
     */
    protected function _prepareMethodDeliveryTimeFrames($data)
    {
        $result = '';

        if (
            isset($data['СрокДоставки'])
            && !empty($data['СрокДоставки'])
        ) {
            $offset = intval($this->getConfigData('delivery_time_offset'));
            $timeFrame = explode('-', trim($data['СрокДоставки']));

            foreach ($timeFrame as $k => $v) {
                $timeFrame[$k] = intval($v) + $offset;
            }

            $result = implode('-', $timeFrame);
        }

        return $result;
    }

    /**
     * Returns base currency rate
     *
     * @param string $code Currency code
     *
     * @return double
     */
    protected function _getBaseCurrencyRate($code)
    {
        if (!isset($this->_baseCurrencyRate[$code])) {
            $this->_baseCurrencyRate[$code] = Mage::getModel('directory/currency')
                ->load($code)
                ->getAnyRate($this->_request->getBaseCurrency()->getCode());
        }

        return $this->_baseCurrencyRate[$code];
    }

    /**
     * Processing additional validation to check if carrier applicable.
     *
     * @param Mage_Shipping_Model_Rate_Request $request Shipping rate request model
     *
     * @return Mage_Shipping_Model_Carrier_Abstract|Mage_Shipping_Model_Rate_Result_Error|boolean
     */
    public function proccessAdditionalValidation(Mage_Shipping_Model_Rate_Request $request)
    {
        $result = $this;

        $this->setRequest($request);

        $r = $this->_rawRequest;

        $requestItems = $r->getRequestAllItems();

        if (!empty($requestItems)) {

            // Run additional validation if there is something to validate

            $allowedCurrencies = Mage::getModel('directory/currency')->getConfigAllowCurrencies();

            $errorMsg = '';

            if (self::RUSSIA_COUNTRY_ID != $r->getOrigCountry()) {

                // Error: origin country must be Russia
                $errorMsg = $this->_getHelper()->__(
                    'This shipping method is not available, the origin country must be Russia'
                );

            } elseif (
                $this->isZipCodeRequired($r->getDestCountry())
                && (
                    !$r->getDestPostcode()
                    || (
                        self::RUSSIA_COUNTRY_ID == $r->getDestCountry()
                        && !preg_match('/^\d{6}$/', trim((string) $r->getDestPostcode()))
                    )
                )
            ) {

                // Error: zip code is required for shipping calculation
                $errorMsg = $this->_getHelper()->__(
                    'This shipping method is not available, please specify a correct ZIP-code'
                );

            } elseif (!in_array(self::CURRENCY_RUB, $allowedCurrencies, true)) {

                // Error: russian rubles are not allowed
                $errorMsg = $this->_getHelper()->__(
                    'Russian ruble currency is not allowed in current store configuration'
                );
            }

            if (
                !empty($errorMsg)
                && $this->getConfigFlag('showmethod')
            ) {
                // Show error message on frontend if allowed
                $error = Mage::getModel('shipping/rate_result_error');

                $error->setCarrier($this->getCarrierCode());
                $error->setCarrierTitle($this->getConfigData('title'));
                $error->setErrorMessage($errorMsg);

                $result = $error;

            } elseif (!empty($errorMsg)) {

                $result = false;
            }
        }

        return $result;
    }

    /**
     * Returns all available methods
     *
     * @param string $code Method code (OPTIONAL)
     *
     * @return array|null
     */
    public function getAllMethods($code = null)
    {
        if (!isset($this->_methods)) {
            $this->_methods = $this->_loadMethodsFromXml();
        }

        return (isset($code) && !empty($code))
            ? $this->_methods[$code]
            : $this->_methods;
    }

    /**
     * Loads and returns shipping methods from XML file
     *
     * @return array
     */
    protected function _loadMethodsFromXml()
    {
        $methods = array();

        $helper = $this->_getHelper();

        $methodsXml = new Varien_Simplexml_Config(
            Mage::getModuleDir('etc', 'Astrio_Postcalc') . DS . 'methods.xml'
        );

        foreach ($methodsXml->getXpath('/methods/*') as $method) {
            /* @var $method Varien_Simplexml_Element */
            $methods[$method->getName()] = array(
                'title' => $helper->__((string) $method->title),
                'code'  => (string) $method->code,
                'max_weight' => intval($method->max_weight), // in grams
            );
        }

        return $methods;
    }

    /**
     * Returns maximum weight of a product in request
     *
     * @param array $items
     *
     * @return float (weight, in system default)
     */
    protected function _getMaxItemsWeight($items)
    {
        $maxWeight = 0;

        foreach ($items as $item) {
            /* @var $item Mage_Sales_Model_Quote_Item */
            if (
                $item->getProduct()
                && $item->getProduct()->getId()
            ) {
                $weight       = $item->getProduct()->getWeight();
                $stockItem    = $item->getProduct()->getStockItem();
                $doValidation = true;

                if ($stockItem->getIsQtyDecimal() && $stockItem->getIsDecimalDivided()) {
                    if ($stockItem->getEnableQtyIncrements() && $stockItem->getQtyIncrements()) {
                        $weight = $weight * $stockItem->getQtyIncrements();
                    } else {
                        $doValidation = false;
                    }
                } elseif ($stockItem->getIsQtyDecimal() && !$stockItem->getIsDecimalDivided()) {
                    $weight = $weight * $item->getQty();
                }

                if ($doValidation) {
                    $maxWeight = max($maxWeight, $weight);
                }
            }
        }

        return $maxWeight;
    }

    /**
     * Return items for further shipment rate evaluation
     *
     * We need to pass children of a bundle instead passing thebundle itself, otherwise we may not
     * get a rate at all (e.g. when total weight of a bundle exceeds max weight despite each item by itself is not)
     *
     * @param Mage_Shipping_Model_Rate_Request $request Shipping request model
     *
     * @return array
     */
    protected function _getRequestAllItems(Mage_Shipping_Model_Rate_Request $request)
    {
        $items = array();

        if ($request->hasAllItems()) {
            foreach ($request->getAllItems() as $item) {
                /* @var $item Mage_Sales_Model_Quote_Item */

                if ($item->getProduct()->isVirtual() || $item->getParentItem()) {
                    // Don't process children here - we will process (or already have processed) them below
                    continue;
                }

                if ($item->getHasChildren() && $item->isShipSeparately()) {
                    foreach ($item->getChildren() as $child) {
                        if (!$child->getFreeShipping() && !$child->getProduct()->isVirtual()) {
                            $items[] = $child;
                        }
                    }
                } else {
                    // Ship together - count compound item as one solid
                    $items[] = $item;
                }
            }
        }

        return $items;
    }

    /**
     * Calculates included fee amount
     *
     * @param string        $feeType   Fee type
     * @param float|integer $feeAmount Fee amount
     * @param float         $value     Value
     *
     * @return float|integer
     */
    protected function _calculateIncludedFee($feeType, $feeAmount, $value)
    {
        $result = 0;

        if (
            self::FEE_TYPE_DISABLED != $feeType
            && !empty($feeAmount)
        ) {
            $feeAmount = abs(doubleval($feeAmount));

            switch ($feeType) {

                case self::FEE_TYPE_FIXED:
                    $result = $feeAmount;
                    break;

                case self::FEE_TYPE_PERCENT:
                    $result = $value * $feeAmount / 100;
                    break;

                case self::FEE_TYPE_DISABLED:
                default:
                    $result = 0;
            }
        }

        return $result;
    }

    /**
     * Calculates declared value
     *
     * @param float $value Value
     *
     * @return float
     */
    protected function _calculateDeclaredValue($value)
    {
        $modifier = $this->getConfigData('declared_value_percentage');

        if (100 != $modifier) {
            $value *= doubleval($modifier) / 100;
        }

        return $value;
    }

    /**
     * Converts weight from system unit to grams
     *
     * @param float $weight Weight
     *
     * @return float (weight, in grams)
     */
    protected function _convertSystemWeightToGrams($weight)
    {
        /* @var $helper Astrio_Postcalc_Helper_Weight */
        $helper = Mage::helper('astrio_postcalc/weight');

        return $helper->convert($weight, $this->getConfigData('unit_of_mass'), $helper::UNIT_G);
    }

    /**
     * Returns API URL
     *
     * @return string
     */
    protected function _getAPIUrl()
    {
        return ($this->getConfigFlag('test_mode')) ? self::CARRIER_TEST_API_URL : self::CARRIER_LIVE_API_URL;
    }

    /**
     * Returns default modulehelper
     *
     * @return Astrio_Postcalc_Helper_Data
     */
    protected function _getHelper()
    {
        return Mage::helper('astrio_postcalc');
    }

    /**
     * Returns cache key for some request to carrier quotes service
     *
     * @param string|array $requestParams Request parameters
     *
     * @return string
     */
    protected function _getQuotesCacheKey($requestParams)
    {
        if (is_array($requestParams)) {
            $requestParams = implode(',', array_merge(array($this->getCarrierCode()), array_keys($requestParams), $requestParams));
        }

        return 'ASTRIO_POSTCALC_' . md5($requestParams);
    }

    /**
     * Checks whether some request to rates have already been done, so we have cache for it
     * Used to reduce number of same requests done to carrier service during one session
     *
     * @param string|array $requestParams Request parameters
     *
     * @return null|string
     */
    protected function _getCachedQuotes($requestParams)
    {
        $result = Mage::app()->getCache()->load($this->_getQuotesCacheKey($requestParams), false, true);

        return (false !== $result) ? $result : null;
    }

    /**
     * Sets received carrier quotes to cache
     *
     * @param string|array $requestParams Request parameters
     * @param string       $response      Response data
     *
     * @return $this
     */
    protected function _setCachedQuotes($requestParams, $response)
    {
        Mage::app()->getCache()->save(
            $response,
            $this->_getQuotesCacheKey($requestParams),
            array(self::CACHE_TAG),
            self::CACHE_TTL
        );

        return $this;
    }
}
