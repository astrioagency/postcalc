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
 * Weight helper
 *
 * @category Astrio
 * @package  Astrio_Postcalc
 * @author   Vladimir Khalzov <v.khalzov@astrio.net>
 */
class Astrio_Postcalc_Helper_Weight extends Mage_Core_Helper_Abstract
{
    /**
     * Units of mass
     */
    const UNIT_LB = 'lb';
    const UNIT_KG = 'kg';
    const UNIT_OZ = 'oz';
    const UNIT_G  = 'g';

    /**
     * Unit of mass conversion values
     */
    const OZ_IN_LB = 16;
    const OZ_IN_KG = 35.2739619;
    const G_IN_LB  = 453.59237;
    const G_IN_KG  = 1000;
    const G_IN_OZ  = 28.3495231;
    const LB_IN_KG = 2.20462262;

    /**
     * Units list
     *
     * @var array|null
     */
    protected $_units = null;

    /**
     * Returns list of the units of mass
     *
     * @return array
     */
    public function getUnits()
    {
        if (!isset($this->_units)) {
            // Init units list
            $this->_units = array(
                self::UNIT_LB => 'Pounds',
                self::UNIT_KG => 'Kilograms',
                self::UNIT_OZ => 'Ounces',
                self::UNIT_G  => 'Grams',
            );
        }

        return $this->_units;
    }

    /**
     * Convert weight from one unit type to another
     *
     * @param float  $value Value
     * @param string $from  From unit
     * @param string $to    To unit
     *
     * @return float|boolean
     */
    public function convert($value, $from = self::UNIT_LB, $to = self::UNIT_KG)
    {
        $value = abs(doubleval($value));

        if ($from !== $to) {

            $method = '_convertWeightFrom' . ucfirst($from) . 'To' . ucfirst($to);

            $value = (method_exists($this, $method))
                ? $this->{$method}($value)
                : false;
        }

        return $value;
    }

    // {{{ Convert all units to grams

    /**
     * Converts KG to G
     *
     * @param float $value Value
     *
     * @return float
     */
    protected function _convertWeightFromKgToG($value)
    {
        return ceil($value * self::G_IN_KG);
    }

    /**
     * Converts LB to G
     *
     * @param float $value Value
     *
     * @return float
     */
    protected function _convertWeightFromLbToG($value)
    {
        return ceil($value * self::G_IN_LB);
    }

    /**
     * Converts OZ to G
     *
     * @param float $value Value
     *
     * @return float
     */
    protected function _convertWeightFromOzToG($value)
    {
        return ceil($value * self::G_IN_OZ);
    }

    // }}}
}
