<?php
/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;

defined('_JEXEC') or die;
jimport( 'joomla.plugin.plugin' );
jimport( 'joomla.filesystem.file');
jimport( 'joomla.html.parameter' );
//jimport('joomla.log.log');
//JLog::addLogger( array('text_file' => 'com_phocacart_error_log.php'), JLog::ALL, array('com_phocacart'));
//phocacartimport('phocacart.utils.log');

if (!ComponentHelper::isEnabled('com_phocacart', true)) {
	$app = Factory::getApplication();
	$app->enqueueMessage(Text::_('Phoca Cart Error'), Text::_('Phoca Cart is not installed on your system'), 'error');
	return;
}

if (file_exists(JPATH_ADMINISTRATOR . '/components/com_phocacart/libraries/bootstrap.php')) {
	// Joomla 5 and newer
	require_once(JPATH_ADMINISTRATOR . '/components/com_phocacart/libraries/bootstrap.php');
} else {
	// Joomla 4
	if (!class_exists('PhocaCartLoader')) {
    	require_once( JPATH_ADMINISTRATOR.'/components/com_phocacart/libraries/loader.php');
	}
	JLoader::registerPrefix('Phocacart', JPATH_ADMINISTRATOR . '/components/com_phocacart/libraries/phocacart');
	phocacartimport('phocacart.text.text');
	phocacartimport('phocacart.render.style');
}

class plgPCTVies_Vat_Number_Validation extends CMSPlugin
{
	protected $name = 'vies_vat_number_validation';

	function __construct(&$subject, $config) {
		parent:: __construct($subject, $config);
		$this->loadLanguage();
	}

	/* Checkout - Check VAT and store information about VAT - LOGGED IN USER */
	public function onPCTonUserAddressBeforeSaveCheckout($context, &$data, $eventData) {

		$this->checkVat($context, $data, $eventData);
		$this->checkEuVatCustomer($context, $data, $eventData);
		$this->checkNonEuCustomer($context, $data, $eventData);
		return true;
	}

	/* Checkout - Check VAT and store information about VAT - GUEST */
	public function onPCTonGuestUserAddressBeforeSaveCheckout($context, &$data, $eventData) {

		$data = (object)$data;
		$this->checkVat($context, $data, $eventData);
		$this->checkEuVatCustomer($context, $data, $eventData);
		$this->checkNonEuCustomer($context, $data, $eventData);
		$data = (array)$data;
		return true;
	}

	/* Order - Check VAT and store information about VAT LOGGED IN USER AND GUEST */
	public function onPCTonUserAddressBeforeSaveOrder($context, &$data, $eventData) {
		$this->checkVat($context, $data, $eventData);
		$this->checkEuVatCustomer($context, $data, $eventData);
		$this->checkNonEuCustomer($context, $data, $eventData);
		return true;
	}

	/* Account - Check VAT and store information about VAT - LOGGED IN USER */
	public function onPCTonUserAddressBeforeSaveAccount($context, &$data, $eventData) {

		$this->checkVat($context, $data, $eventData);
		$this->checkEuVatCustomer($context, $data, $eventData);
		$this->checkNonEuCustomer($context, $data, $eventData);
		return true;
	}

	/* Checkout - display information in address about VAT check */
	public function onPCTonAfterUserAddressCheckoutView($context, &$data, $eventData) {

		$s             = PhocacartRenderStyle::getStyles();
		$output            = [];

		$check_field = $this->params->get('check_field', 'vat_1');

		if (!empty($data[0]->params_user)) {
			$paramsUser = json_decode($data[0]->params_user, true);

			$class = '';
			if (isset($paramsUser['vat_vies_message_type'])) {
				$class = 'ph-msg-'.$paramsUser['vat_vies_message_type'] . '-box';
			}

			// VAT field was not filled
			if (!isset($data[0]->$check_field) || (isset($data[0]->$check_field) && $data[0]->$check_field == '')) {
				$output = [];
				return $output;
			}

			// VAT field was filled
			if (isset($paramsUser['vat_vies_message']) && $paramsUser['vat_vies_message'] != '') {
				$output['content'] = '<div class="'.$class.'">'.PhocacartRenderIcon::icon($s['i']['info-sign']) . $paramsUser['vat_vies_message'].'</div>';
			}
		}


		return $output;
	}

	/* Checkout - display information on top of checkout to get info about if the user has valid VAT - useful e.g. because of CSS class */
	public function onPCTonStartCheckoutView($context, &$data, $eventData) {

		$s             = PhocacartRenderStyle::getStyles();
		$output            = [];

		if (!empty($data[0]->params_user)) {
			$paramsUser = json_decode($data[0]->params_user, true);
			if (isset($paramsUser['vat_valid']) && (int)$paramsUser['vat_valid'] == 1) {
				$output['checkout_class'] = 'phVatValid';
			} else {
				$output['checkout_class'] = 'phNotVatValid';
			}
		}

		return $output;
	}

	public function onPCVonCheckoutInsideAddressAfterHeader($context, $data, $eventData) {

		$s             		= PhocacartRenderStyle::getStyles();
		$vat_description 	= $this->params->get('vat_description', '');
		if ($vat_description != '') {
			return '<div class="ph-vies-vat-number-validation-desc ph-msg-info-box">'.PhocacartRenderIcon::icon($s['i']['info-sign']) . $vat_description.'</div>';
		}
		return '';

	}

	public function onPCVonAccountInsideAddressAfterHeader($context, $data, $eventData) {

		$s             		= PhocacartRenderStyle::getStyles();
		$vat_description 	= $this->params->get('vat_description', '');
		if ($vat_description != '') {
			return '<div class="ph-vies-vat-number-validation-desc ph-msg-info-box">'.PhocacartRenderIcon::icon($s['i']['info-sign']).$vat_description.'</div>';
		}
		return '';

	}

	/* Order edit view - administration */
	function onPCTgetUserBillingInfoAdminEdit($context, $item, $eventData) {


		$output = array();

		if (!empty($item->params_user)) {
			$pU = json_decode($item->params_user, true);


			$output['content'] = '<div class="ph-order-edit-user-info-header">'.Text::_('PLG_PCT_VIES_VAT_NUMBER_VALIDATION_VAT_VALIDATION_SUMMARY').'</div>';
			$output['content'] .= '<div class="ph-order-edit-user-info">';


			$output['content'] .= isset($pU['vat_name']) && $pU['vat_name'] != '' ? '<div class="ph-user-info-name ph-item"><div class="ph-label">' . Text::_('PLG_PCT_VIES_VAT_NUMBER_VALIDATION_VAT_NAME') . '</div><div class="ph-value">'. $pU['vat_name'] .'</div></div>' : '';
			$output['content'] .= isset($pU['vat_address']) && $pU['vat_address'] != '' ? '<div class="ph-user-info-address ph-item"><div class="ph-label">' . Text::_('PLG_PCT_VIES_VAT_NUMBER_VALIDATION_VAT_ADDRESS') . '</div><div class="ph-value">'. $pU['vat_address'] .'</div></div>' : '';

			$vat = '';
			if (isset($pU['vat_country_code']) && $pU['vat_country_code'] != '' && isset($pU['vat_number']) && $pU['vat_number'] != '') {
				$vat = $pU['vat_country_code'] . $pU['vat_number'];

				$output['content'] .= '<div class="ph-user-info-vat-number ph-item"><div class="ph-label">' . Text::_('PLG_PCT_VIES_VAT_NUMBER_VALIDATION_VAT_NUMBER') . '</div><div class="ph-value">'. $vat .'</div></div>';

				$output['content'] .= '<div class="ph-user-info-vat-country-code ph-item"><div class="ph-label">' . Text::_('PLG_PCT_VIES_VAT_NUMBER_VALIDATION_VAT_COUNTRY_CODE') . '</div><div class="ph-value">'. $pU['vat_country_code'] .'</div></div>';

			}


			$output['content'] .= isset($pU['vat_country_code_address']) && $pU['vat_country_code_address'] != '' ? '<div class="ph-user-info-vat-country-code-address ph-item"><div class="ph-label">' . Text::_('PLG_PCT_VIES_VAT_NUMBER_VALIDATION_VAT_COUNTRY_CODE_ADDRESS') . '</div><div class="ph-value">'. $pU['vat_country_code_address'] .'</div></div>' : '';

			$output['content'] .= isset($pU['vat_country_request_date']) && $pU['vat_country_request_date'] != '' ? '<div class="ph-user-info-vat-request_date ph-item"><div class="ph-label">' . Text::_('PLG_PCT_VIES_VAT_NUMBER_VALIDATION_VAT_REQUEST_DATE') . '</div><div class="ph-value">'. $pU['vat_request_date'] .'</div></div>' : '';

			if (isset($pU['vat_valid']) && $pU['vat_valid'] != '') {
				if ($pU['vat_valid'] == 1) {
					$validText = Text::_('PLG_PCT_VIES_VAT_NUMBER_VALIDATION_YES');
				} else {
					$validText = Text::_('PLG_PCT_VIES_VAT_NUMBER_VALIDATION_NO');
				}
				$output['content'] .= '<div class="ph-user-info-vat-valid ph-item"><div class="ph-label">' . Text::_('PLG_PCT_VIES_VAT_NUMBER_VALIDATION_VAT_VALID') . '</div><div class="ph-value">'. $validText .'</div></div>';
			}

			$classMsg = '';
			if (isset($pU['vat_vies_message_type']) && $pU['vat_vies_message_type'] != '') {
				if ($pU['vat_vies_message_type'] != '') {
					$classMsg = 'ph-msg-' . $pU['vat_vies_message_type'];
				}
			}

			$output['content'] .= isset($pU['vat_vies_message']) && $pU['vat_vies_message'] != '' ? '<div class="ph-user-info-vat-request_date ph-item"><div class="ph-label">' . Text::_('PLG_PCT_VIES_VAT_NUMBER_VALIDATION_VAT_VIES_MESSAGE') . '</div><div class="'.$classMsg.'">'. $pU['vat_vies_message'] .'</div></div>' : '';


			if (isset($pU['vat_customer_eu_valid_different']) && $pU['vat_customer_eu_valid_different'] != '') {
				if ($pU['vat_customer_eu_valid_different'] == 1) {
					$validText = Text::_('PLG_PCT_VIES_VAT_NUMBER_VALIDATION_YES');
				} else {
					$validText = Text::_('PLG_PCT_VIES_VAT_NUMBER_VALIDATION_NO');
				}
				$output['content'] .= '<div class="ph-user-info-vat-customer-eu-valid-different ph-item"><div class="ph-label">' . Text::_('PLG_PCT_VIES_VAT_NUMBER_VALIDATION_CUSTOMER_EU_VALID_DIFFERENT') . '</div><div class="ph-value">'. $validText .'</div></div>';
			}

			if (isset($pU['vat_customer_non_eu']) && $pU['vat_customer_non_eu'] != '') {
				if ($pU['vat_customer_non_eu'] == 1) {
					$validText = Text::_('PLG_PCT_VIES_VAT_NUMBER_VALIDATION_YES');
				} else {
					$validText = Text::_('PLG_PCT_VIES_VAT_NUMBER_VALIDATION_NO');
				}
				$output['content'] .= '<div class="ph-user-info-vat-customer-non-eu ph-item"><div class="ph-label">' . Text::_('PLG_PCT_VIES_VAT_NUMBER_VALIDATION_CUSTOMER_NON_EU') . '</div><div>'. $validText .'</div></div>';
			}



			$output['content'] .= '</div>';
		}


		return $output;
	}

	/* Real change of VAT, after the information is stored about VAT check, etc, in this event it comes to real change of VAT in price items or calculations */
	public function onPCTonChangeTaxBasedRule($context, &$taxData, $eventData) {

		$vat_assign_tax_non_eu = $this->params->get('vat_assign_tax_non_eu', 0);
		$vat_assign_tax_outside_vendor_country_valid_vat = $this->params->get('vat_assign_tax_outside_vendor_country_valid_vat', 0);


		$user		= PhocacartUser::getUser();
		$guest		= PhocacartUserGuestuser::getGuestUser();
		$paramsUser = [];
		if ($guest && (int)$user->id < 1) {
			$item = PhocacartUserGuestuser::getAddress();

			if (isset($item['params_user'])){
				$paramsUser = json_decode($item['params_user'], true);
			}

		} else {
			$data = PhocacartUser::getUserData();
			if (!empty($data[0]->params_user)) {
				$paramsUser = json_decode($data[0]->params_user, true);
			}
		}



		$price_without_tax_outside_vendor_country_valid_vat = $this->params->get('price_without_tax_outside_vendor_country_valid_vat', 0);
		$price_without_tax_non_eu_customer = $this->params->get('price_without_tax_non_eu_customer', 0);
		$apply_dynamic_tax_rate = $this->params->get('apply_dynamic_tax_rate', 0);


		if ($price_without_tax_outside_vendor_country_valid_vat == 1 && isset($paramsUser['vat_customer_eu_valid_different']) && (int)$paramsUser['vat_customer_eu_valid_different'] == 1) {
			// Apply information about if the VAT customer from EU will have price without VAT or with specific VAT (in case customer EU country is not equal vendor EU country)


			$pluginId = PhocacartUtils::getPluginId('pct', 'vies_vat_number_validation');
			if ((int)$pluginId > 0){
				if ((int)$vat_assign_tax_outside_vendor_country_valid_vat > 0) {
					$tax = PhocacartTax::getTaxById($vat_assign_tax_outside_vendor_country_valid_vat);

					if (!empty($tax)){


						$taxData['taxid'] = $tax['id'];
						$taxData['taxpluginid'] = (int)$pluginId;
						$taxData['taxcountryid']    = 0;
						$taxData['taxregionid']    = 0;
						$taxData['taxrate']     = $tax['tax_rate'];
						$taxData['taxtitle']    = $tax['title'];
						$taxData['taxhide']    = $tax['tax_hide'];

						// We can assign a VAT but we can even apply dynamic tax rate for this
						// Example: Common VAT with 0% will be set but dynamic tax rate can even reset it to country tax or region
						if ($apply_dynamic_tax_rate == 1) {

							$paramsC                   = PhocacartUtils::getComponentParameters();
							$dynamic_tax_rate          = $paramsC->get('dynamic_tax_rate', 0);
							$dynamic_tax_rate_priority = $paramsC->get('dynamic_tax_rate_priority', 1);// country prioritized

							if ($dynamic_tax_rate == 0) {
								return true;
							}

							$taxChangedA                 = array();
							$taxChangedA['taxid']        = (int)$tax['id'];
							$taxChangedA['taxrate']      = $tax['tax_rate'];
							$taxChangedA['taxtitle']     = $tax['title'];
							$taxChangedA['taxcountryid'] = 0;
							$taxChangedA['taxregionid']  = 0;
							$taxChangedA['taxpluginid']  = (int)$pluginId;
							$taxChangedA['taxhide']      = $tax['tax_hide'];

							if ($dynamic_tax_rate_priority == 1) {
								// Country prioritized
								$taxChangedA = PhocacartTax::getTaxByCountry($tax['id']);

								//Not found - try to find region
								if (empty($taxChangedA)) {
									$taxChangedA = PhocacartTax::getTaxByRegion($tax['id']);
								}
								// If country or region based tax does not have title, set the default one
								if (!empty($taxChangedA) && $taxChangedA['taxtitle'] == '') {
									$taxChangedA['taxtitle'] = $tax['title'];
								}
							} else {
								// Region prioritized
								$taxChangedA = PhocacartTax::getTaxByRegion($tax['id']);
								//Not found - try to find country
								if (empty($taxChangedA)) {
									$taxChangedA = PhocacartTax::getTaxByCountry($tax['id']);
								}
								// If country or region based tax does not have title, set the default one
								if (!empty($taxChangedA) && $taxChangedA['taxtitle'] == '') {
									$taxChangedA['taxtitle'] = $tax['title'];
								}
							}

							if (!isset($taxChangedA['taxhide'])) {
							   $taxChangedA['taxhide']= $tax['tax_hide'];
						   }

							$taxData = $taxChangedA;

						}
						return true;
					}

				}

				$taxData['taxid'] = 0;
				$taxData['taxpluginid'] = (int)$pluginId;
				$taxData['taxcountryid']    = 0;
				$taxData['taxregionid']    = 0;
				$taxData['taxrate']     = 0;
				$taxData['taxtitle']    = '';
				$taxData['taxhide']    = [];

				return true;
			}

		} else if ($price_without_tax_non_eu_customer == 1 && isset($paramsUser['vat_customer_non_eu']) && (int)$paramsUser['vat_customer_non_eu'] == 1) {
			// Apply information about if the customer outside EU will have price without VAT or with specific VAT

			$pluginId = PhocacartUtils::getPluginId('pct', 'vies_vat_number_validation');
			if ((int)$pluginId > 0){
				if ((int)$vat_assign_tax_non_eu > 0) {
					$tax = PhocacartTax::getTaxById($vat_assign_tax_non_eu);

					if (!empty($tax)){


						$taxData['taxid'] = $tax['id'];
						$taxData['taxpluginid'] = (int)$pluginId;
						$taxData['taxcountryid']    = 0;
						$taxData['taxregionid']    = 0;
						$taxData['taxrate']     = $tax['tax_rate'];
						$taxData['taxtitle']    = $tax['title'];
						$taxData['taxhide']    = $tax['tax_hide'];

						// We can assign a VAT but we can even apply dynamic tax rate for this
						// Example: Common VAT with 0% will be set but dynamic tax rate can even reset it to country tax or region
						if ($apply_dynamic_tax_rate == 1) {

							$paramsC                   = PhocacartUtils::getComponentParameters();
							$dynamic_tax_rate          = $paramsC->get('dynamic_tax_rate', 0);
							$dynamic_tax_rate_priority = $paramsC->get('dynamic_tax_rate_priority', 1);// country prioritized

							if ($dynamic_tax_rate == 0) {
								return true;
							}

							$taxChangedA                 = array();
							$taxChangedA['taxid']        = (int)$tax['id'];
							$taxChangedA['taxrate']      = $tax['tax_rate'];
							$taxChangedA['taxtitle']     = $tax['title'];
							$taxChangedA['taxcountryid'] = 0;
							$taxChangedA['taxregionid']  = 0;
							$taxChangedA['taxpluginid']  = (int)$pluginId;
							$taxChangedA['taxhide']      = $tax['tax_hide'];

							if ($dynamic_tax_rate_priority == 1) {
								// Country prioritized
								$taxChangedA = PhocacartTax::getTaxByCountry($tax['id']);

								//Not found - try to find region
								if (empty($taxChangedA)) {
									$taxChangedA = PhocacartTax::getTaxByRegion($tax['id']);
								}
								// If country or region based tax does not have title, set the default one
								if (!empty($taxChangedA) && $taxChangedA['taxtitle'] == '') {
									$taxChangedA['taxtitle'] = $tax['title'];
								}
							} else {
								// Region prioritized
								$taxChangedA = PhocacartTax::getTaxByRegion($tax['id']);
								//Not found - try to find country
								if (empty($taxChangedA)) {
									$taxChangedA = PhocacartTax::getTaxByCountry($tax['id']);
								}
								// If country or region based tax does not have title, set the default one
								if (!empty($taxChangedA) && $taxChangedA['taxtitle'] == '') {
									$taxChangedA['taxtitle'] = $tax['title'];
								}
							}

							if (!isset($taxChangedA['taxhide'])) {
							   $taxChangedA['taxhide']= $tax['tax_hide'];
						   }

							$taxData = $taxChangedA;

						}
						return true;
					}


				}

				$taxData['taxid'] = 0;
				$taxData['taxpluginid'] = (int)$pluginId;
				$taxData['taxcountryid']    = 0;
				$taxData['taxregionid']    = 0;
				$taxData['taxrate']     = 0;
				$taxData['taxtitle']    = '';
				$taxData['taxhide']    = [];

				return true;
			}

		}

		return false;
	}

	public function checkVat($context, &$data, $eventData) {


		$check_field = $this->params->get('check_field', 'vat_1');
		$check_address = $this->params->get('check_address', 'http://ec.europa.eu/taxation_customs/vies/services/checkVatService.wsdl');
		//$check_address = PhocacartText::filterValue($check_address, 'text').')';

		$data->vat_valid = 0;

		if (isset($data->$check_field)) {// vat_1, vat_2

			$paramsUser = json_decode($data->params_user, true);

			$vatId = $data->$check_field;
			// remove non alphanum characters
			$vatId = preg_replace("/[^a-zA-Z0-9]]/", "", $vatId);

			// a valid vat id consists of an alpha country code and up to 12 alphanumeric characters
			$vatIdRegex = "/^[a-z]{2}[a-z0-9]{0,12}$/i";

			if (preg_match($vatIdRegex, $vatId) !== 1) {

				$this->cleanParamsUser($paramsUser);
				$paramsUser['vat_vies_message']      = Text::_('PLG_PCT_VIES_VAT_NUMBER_VALIDATION_ERROR_INVALID_VAT_NUMBER_FORMAT') . ' ('.Text::_('PLG_PCT_VIES_VAT_NUMBER_VAT_NUMBER'). ': '.PhocacartText::filterValue($vatId, 'alphanumeric').')';
				$paramsUser['vat_vies_message_type'] = 'error';
				$data->params_user = json_encode($paramsUser);
				return;
			}


			$countryCode	= substr($vatId, 0, 2);
			$vatNumber 		= substr($vatId, 2);

			try {
				$client = new \SoapClient($check_address);
					$response = $client->checkVat([
					  'countryCode' => $countryCode,
					  'vatNumber'   => $vatNumber
					]);

				// TEST AND DEBUG TODO COMMENT
				/*$response = new stdClass();
				$response->countryCode = 'DE';
				$response->vatNumber = '123456789';
				$response->requestDate = "2023-02-23+01:00 ~ 2023-02-23+01:00";
				$response->valid = TRUE;
				$response->name = 'Test GmbH';
				$response->address = 'Teststrasse 13; München; 80331';*/
				// END TEST AND DEBUG

				$paramsUser['vat_country_code_address'] = '';
				if (isset($data->country) ){
					$countryCode = PhocacartCountry::getCountryByCode2($data->country);
					$paramsUser['vat_country_code_address'] = $countryCode;
				}

				$paramsUser['vat_country_code'] = isset($response->countryCode) ? PhocacartText::filterValue($response->countryCode, 'alphanumeric') : '';
				$paramsUser['vat_number'] = isset($response->vatNumber) ? PhocacartText::filterValue($response->vatNumber, 'number')  : '';
				$paramsUser['vat_request_date'] = isset($response->requestDate) ? PhocacartText::filterValue($response->requestDate, 'text')  : '';
				$paramsUser['vat_valid'] = isset($response->valid) ? (int)$response->valid : 0;
				$paramsUser['vat_name'] = isset($response->name) ? PhocacartText::filterValue($response->name, 'text'): '';
				$paramsUser['vat_address'] = isset($response->address) ? PhocacartText::filterValue($response->address, 'text') : '';

				if ((int)$paramsUser['vat_valid'] == 1) {
					$paramsUser['vat_vies_message']      = Text::_('PLG_PCT_VIES_VAT_NUMBER_VALIDATION_SUCCESS_VAT_NUMBER_VALID') . ' (' . Text::_('PLG_PCT_VIES_VAT_NUMBER_VAT_NUMBER') . ': ' . PhocacartText::filterValue($vatId, 'alphanumeric') . ')';
					$paramsUser['vat_vies_message_type'] = 'success';
					$data->vat_valid                     = 1;
				} else {
					$paramsUser['vat_vies_message']      = Text::_('PLG_PCT_VIES_VAT_NUMBER_VALIDATION_VAT_NUMBER_NOT_VALID') . ' (' . Text::_('PLG_PCT_VIES_VAT_NUMBER_VAT_NUMBER') . ': ' . PhocacartText::filterValue($vatId, 'alphanumeric') . ')';
					$paramsUser['vat_vies_message_type'] = 'error';
					$data->vat_valid                     = 0;
				}

				$data->params_user = json_encode($paramsUser);


				return;

			} catch(Exception $e) {

				$errorMsg = trim($e->getMessage());



				switch ($errorMsg) {

					case 'INVALID_INPUT':
						// The provided CountryCode is invalid or the VAT number is empty
						$paramsUser['vat_vies_message'] = Text::_('PLG_PCT_VIES_VAT_NUMBER_VALIDATION_ERROR_INVALID_INPUT');
					break;

					case 'GLOBAL_MAX_CONCURRENT_REQ':
						// Your Request for VAT validation has not been processed; the maximum number of concurrent requests has been reached. Please re-submit your request later or contact TAXUD-VIESWEB@ec.europa.eu for further information": Your request cannot be processed due to high traffic on the web application. Please try again later
						$paramsUser['vat_vies_message'] = Text::_('PLG_PCT_VIES_VAT_NUMBER_VALIDATION_ERROR_GLOBAL_MAX_CONCURRENT_REQ');
					break;

					case 'MS_MAX_CONCURRENT_REQ':
						// Your Request for VAT validation has not been processed; the maximum number of concurrent requests for this Member State has been reached. Please re-submit your request later or contact TAXUD-VIESWEB@ec.europa.eu for further information": Your request cannot be processed due to high traffic towards the Member State you are trying to reach. Please try again later.
						$paramsUser['vat_vies_message'] = Text::_('PLG_PCT_VIES_VAT_NUMBER_VALIDATION_ERROR_MS_MAX_CONCURRENT_REQ');
					break;

					case 'SERVICE_UNAVAILABLE':
						// an error was encountered either at the network level or the Web application level, try again later
						$paramsUser['vat_vies_message'] = Text::_('PLG_PCT_VIES_VAT_NUMBER_VALIDATION_ERROR_SERVICE_UNAVAILABLE');
					break;

					case 'MS_UNAVAILABLE':
						// The application at the Member State is not replying or not available. Please refer to the Technical Information page to check the status of the requested Member State, try again later
						$paramsUser['vat_vies_message'] = Text::_('PLG_PCT_VIES_VAT_NUMBER_VALIDATION_ERROR_MS_UNAVAILABLE');
					break;

					case 'TIMEOUT':
						// The application did not receive a reply within the allocated time period, try again later.
						$paramsUser['vat_vies_message'] = Text::_('PLG_PCT_VIES_VAT_NUMBER_VALIDATION_ERROR_TIMEOUT');
					break;

					default:
						$paramsUser['vat_vies_message'] = Text::_('PLG_PCT_VIES_VAT_NUMBER_VALIDATION_ERROR_VAT_VALIDATION_NOT_PROCESSED');
					break;

				}

				$this->cleanParamsUser($paramsUser);
				$paramsUser['vat_vies_message_internal'] = $errorMsg;
				$paramsUser['vat_vies_message']      .= ' ('.Text::_('PLG_PCT_VIES_VAT_NUMBER_VAT_NUMBER'). ': '.PhocacartText::filterValue($vatId, 'alphanumeric').')';
				$paramsUser['vat_vies_message_type'] = 'error';
				$data->params_user = json_encode($paramsUser);

				PhocacartLog::add(2, 'Error validating VAT - '. $errorMsg, 0, 'VAT: ' . $vatId);

				return;

			}

			if (!empty($paramsUser)){
				$data->params_user = json_encode($paramsUser);
			}

		}

		return;
	}

	public function cleanParamsUser(&$paramsUser){

		// We cannot empty whole object/array as other parts can store another information there, so we only can clean current items
		$paramsUser['vat_country_code'] = '';
		$paramsUser['vat_number'] = '';
		$paramsUser['vat_request_date'] = '';
		$paramsUser['vat_valid'] = 0;
		$paramsUser['vat_name'] = '';
		$paramsUser['vat_address'] = '';
		return true;
	}



	public function checkEuVatCustomer ($context, &$data, $eventData) {

		$paramsUser = json_decode($data->params_user, true);
		$paramsUser['vat_customer_eu_valid_different'] = 0; // If customer is from EU, has valid VAT and his/her country is different to vendor country

		// Store information about if the VAT customer from EU will have price without VAT (in case customer EU country is not equal vendor EU country)
		$price_without_tax_outside_vendor_country_valid_vat = $this->params->get('price_without_tax_outside_vendor_country_valid_vat',0);
		if ($price_without_tax_outside_vendor_country_valid_vat == 1 && $this->isCustomerOutsideVendorCountryAndHasValidVat($paramsUser)) {
			$paramsUser['vat_customer_eu_valid_different'] = 1;
		}

		$data->params_user = json_encode($paramsUser);
		return;

	}

	public function checkNonEuCustomer ($context, &$data, $eventData) {

		$paramsUser = json_decode($data->params_user, true);
		$paramsUser['vat_customer_non_eu'] = 0; // If customer is not from EU

		$countryId = isset($data->country) ? (int)$data->country : 0;
		$countryCode = PhocacartCountry::getCountryByCode2($countryId);

		// Store information about if the non EU customer will have price without VAT
		$price_without_tax_non_eu_customer = $this->params->get('price_without_tax_non_eu_customer', 0);
		if ($price_without_tax_non_eu_customer == 1 && $this->isCustomerOutsideEu($countryCode)) {
			$paramsUser['vat_customer_non_eu'] = 1;
		}

		$data->params_user = json_encode($paramsUser);
		return;
	}



	public function getEuCountryList() {
		$eu = [
			'AT', //'Austria'
			'BE', //'Belgium'
			'BG', //'Bulgaria'
			'CY', //'Cyprus'
			'CZ', //'Czech Republic'
			'DE', //'Germany'
			'DK', //'Denmark'
			'EE', //'Estonia'
			'EL', //'Greece'
			'ES', //'Spain'
			'FI', //'Finland'
			'FR', //'France'
			'HR', //'Croatia'
			'HU', //'Hungary'
			'IE', //'Ireland'
			'IT', //'Italy'
			'LU', //'Luxembourg'
			'LV', //'Latvia'
			'LT', //'Lithuania'
			'MT', //'Malta'
			'NL', //'Netherlands'
			'PL', //'Poland'
			'PT', //'Portugal'
			'RO', //'Romania'
			'SE', //'Sweden'
			'SI', //'Slovenia'
			'SK', //'Slovakia'
			//'GB', //'United Kingdom'
			'XI', //'United Kingdom (Northern Ireland)'
			//'EU', //'MOSS Number'
    	];

		return $eu;
	}

	public function isCustomerOutsideVendorCountryAndHasValidVat($params) {

		$vendor_country = $this->params->get('vendor_country', '');
		// Is customer VAT valid
		if (isset($params['vat_valid']) && (int)$params['vat_valid'] == 1) {
			$euCountries = $this->getEuCountryList();

			// Is vendor in EU country
			if ($vendor_country != '' && in_array($vendor_country, $this->getEuCountryList())) {

				// Is customer in EU country
				if ($params['vat_country_code_address'] != '' && in_array($params['vat_country_code_address'], $this->getEuCountryList())) {

					// Is vendor country different to customer country
					if (isset($params['vat_country_code']) && $params['vat_country_code'] != '' && $params['vat_country_code'] != $vendor_country) {
						return true;
					}
				}
			}
		}
		return false;
	}

	public function isCustomerOutsideEu($countryCode) {

		$vendor_country = $this->params->get('vendor_country', '');
		// Is vendor in EU country
		if ($vendor_country != '' && in_array($vendor_country, $this->getEuCountryList())) {

			// Is vendor country different to customer country
			if ($countryCode != '' && !in_array($countryCode, $this->getEuCountryList())) {
				return true;
			}
		}
		return false;
	}

}
?>
