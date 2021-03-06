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
 * Postcalc fee type selector model
 *
 * @category Astrio
 * @package  Astrio_Postcalc
 * @author   Vladimir Khalzov <v.khalzov@astrio.net>
 */
class Astrio_Postcalc_Model_Shipping_Carrier_Postcalc_Source_Fee
{
    /**
     * Returns options as array
     *
     * @param boolean $isMultiselect Flag: is multi-select enabled or not (OPTIONAL)
     *
     * @return array
     */
    public function toOptionArray($isMultiselect = false)
    {
        return array(
            array(
                'value' => Astrio_Postcalc_Model_Shipping_Carrier_Postcalc::FEE_TYPE_DISABLED,
                'label' => Mage::helper('astrio_postcalc')->__('Disabled'),
            ),
            array(
                'value' => Astrio_Postcalc_Model_Shipping_Carrier_Postcalc::FEE_TYPE_FIXED,
                'label' => Mage::helper('astrio_postcalc')->__('Fixed'),
            ),
            array(
                'value' => Astrio_Postcalc_Model_Shipping_Carrier_Postcalc::FEE_TYPE_PERCENT,
                'label' => Mage::helper('astrio_postcalc')->__('Percent of the order subtotal'),
            ),
        );
    }
}
