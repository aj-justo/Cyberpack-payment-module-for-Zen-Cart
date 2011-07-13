<?php
/**
 * cyberpack payment module
 *
 * @package cyberpack
 * @author  AJweb, http://ajweb.eu, siguiendo modulo CECA dehttp://ZhenIT.com
 * @version 1.0
 * @licence Released under the GNU General Public License
 */

 
class cyberpack {
	var $code, $title, $description, $enabled, $debug;
	var $CYBERTEMPTABLE = 'cyberpac';


	function cyberpack() {
		global $order, $request_type;
				
		$this -> code = 'cyberpack';
		$this -> title = MODULE_PAYMENT_CYBERPACK_TEXT_TITLE;
		$this -> description = MODULE_PAYMENT_CYBERPACK_TEXT_DESCRIPTION;
		$this -> enabled = ((MODULE_PAYMENT_CYBERPACK_STATUS == 'Sí') ? true : false);
		$this -> sort_order = MODULE_PAYMENT_CYBERPACK_SORT_ORDER;
		$this -> debug = true;
		
		if(MODULE_PAYMENT_CYBERPACK_URL_TYPE == 'Real') {
			$this -> form_action_url = "https://sis.sermepa.es/sis/realizarPago";
			$this -> clave = MODULE_PAYMENT_CYBERPACK_CLAVE;
		} else {
			$this -> form_action_url = "https://sis-t.sermepa.es:25443/sis/realizarPago";
			$this -> clave = MODULE_PAYMENT_CYBERPACK_CLAVE2;
		}
		if((int)MODULE_PAYMENT_CYBERPACK_ORDER_STATUS_ID > 0) {
			$this -> order_status = MODULE_PAYMENT_CYBERPACK_ORDER_STATUS_ID;
		}
		$this->merchantId = MODULE_PAYMENT_CYBERPACK_MERCHANTID;
		$this->terminalId = MODULE_PAYMENT_CYBERPACK_TERMINALID;
		
		$this->urlConfirmacion = 'http://'.$_SERVER['SERVER_NAME'].'/cyberpack_confirmar_online.php';

		if(is_object($order)) $this -> update_status();
	}

	function trace($log) {
		if(!$this -> debug)
			return ;
		$fp = fopen(DIR_FS_CATALOG . '/cache/cyberpack.log', "a+");
		fwrite($fp, date("Y-m-d H:i:s") . " - " . $log . "\n");
		fclose($fp);
	}


	function update_status() {
		global $order, $db;

		if(($this -> enabled == true) && ((int)MODULE_PAYMENT_CYBERPACK_ZONE > 0)) {
			$check_flag = false;
			$check_query = $db->Execute("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_CYBERPACK_ZONE . "' and zone_country_id = '" . $order -> billing['country']['id'] . "' order by zone_id");
			while( !$check_query->EOF ) {
				if($check_query->fields['zone_id'] < 1) {
					$check_flag = true;
					break;
				} elseif($check_query->fields['zone_id'] == $order -> billing['zone_id']) {
					$check_flag = true;
					break;
				}
				$check_query->MoveNext();
			}

			if($check_flag == false) {
				$this -> enabled = false;
			}
		}
	}

	function javascript_validation() {
		return false;
	}

	function selection() {
		return array('id' => $this -> code, 'module' => $this -> title);
	}

	function pre_confirmation_check() {
		return false;
	}

	function confirmation() {
		return false;
	}

	function process_button() {
		global $order, $currency, $language, $currencies;
		//Total setup without .
		$Importe = $order -> info['total'];
		$Importe = round($Importe * $order -> info['currency_value'], 2);
		$Importe = number_format($Importe, 2, '.', '');
		$Importe = preg_replace('/\./', '', $Importe);
		$Importe = (int)$Importe;
	
		$products = Array();
		foreach($order->products as $p) {
			$products[] = $p['id'];
		}
		$Descripcion = 'Products: '. implode(', ', $products);
		$Descripcion .= "\nCustomer: ".$order -> customer['lastname'] . ", " . $order -> customer['firstname'] .
						 " (" . $order -> customer['email_address'] . ")";

		$process_button_string = '';


		$details = 'Importe: '.$Importe .', Productos: '.$Descripcion;
		$Num_operacion = (string)$this->saveToTempTable($order -> customer['email_address'], $details);
		// TODO: hay que dar como opcion en admin y variar firma/firmaampliada segun su contenido
		$tipoOperacion = '0'; 
		$firma = $this->firmaAmpliada(MODULE_PAYMENT_CYBERPACK_MERCHANTID, $Importe, $this->moneda(), $Num_operacion, $tipoOperacion, '');
						
		$process_button_string= zen_draw_hidden_field('Ds_Merchant_MerchantCode', MODULE_PAYMENT_CYBERPACK_MERCHANTID) . 
								zen_draw_hidden_field('Ds_Merchant_Titular', 'LA NAUTICA') . 
								zen_draw_hidden_field('Ds_Merchant_Terminal', MODULE_PAYMENT_CYBERPACK_TERMINALID) . 
								zen_draw_hidden_field('Ds_Merchant_Order', $Num_operacion) . 
								zen_draw_hidden_field('Ds_Merchant_Amount', $Importe) . 
								zen_draw_hidden_field('Ds_Merchant_Currency', $this->moneda() ) . 
								zen_draw_hidden_field('Ds_Merchant_MerchantURL', $this->urlConfirmacion ) . 
								zen_draw_hidden_field('Ds_Merchant_ConsumerLanguage', $this->idioma() ) . 
								zen_draw_hidden_field('Ds_Merchant_MerchantSignature', $firma) . 
								zen_draw_hidden_field('Ds_Merchant_UrlOK', zen_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL'), false) . 
								zen_draw_hidden_field('Ds_Merchant_UrlKO', zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL'), false) . 
								zen_draw_hidden_field('Ds_Merchant_MerchantName', 'LA NAUTICA') . 
								zen_draw_hidden_field('Ds_Merchant_TransactionType', $tipoOperacion) . 								
								zen_draw_hidden_field('Ds_Merchant_MerchantData', $_COOKIE['zenid']) . 
								zen_draw_hidden_field('Ds_Merchant_ProductDescription', $Descripcion);
		return $process_button_string;
	}

	function before_process() {
		return false;
	}

	function after_process() {
		global $insert_id;
		
		return false;
	}

	function output_error() {
		return false;
	}

	function check() {
		global $db;
		if(!isset($this -> _check)) {
			$check_query = $db->Execute("select configuration_value from " . 
										TABLE_CONFIGURATION . 
										" where configuration_key = 'MODULE_PAYMENT_CYBERPACK_STATUS'");
			$this -> _check = $check_query->RecordCount();
		}
		return $this -> _check;
	}

	function install() {
		global $db;
		$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('¿Habilitar módulo Cyberpack?', 'MODULE_PAYMENT_CYBERPACK_STATUS', 'Sí', '¿Desea aceptar pagos con Tarjeta de crédito a través de Cyberpack?', '6', '0', 'zen_cfg_select_option(array(\'Sí\', \'No\'), ', now())");

		$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Entorno Cyberpack', 'MODULE_PAYMENT_CYBERPACK_URL_TYPE', 'Real', 'No olvide poner el entorno en \"Real\" para comenzar a vender!!', '6', '1', 'zen_cfg_select_option(array(\'Real\', \'Pruebas\'), ', now())");

		$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('¿Habilitar confirmacion del pago on-line?', 'MODULE_PAYMENT_CYBERPACK_ONLINE_CONF', 'No', '¿Ha configurado la pasarela en Cyberpack para que notifique los pagos on-line?', '6', '2', 'zen_cfg_select_option(array(\'Sí\', \'No\'), ', now())");

		$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Clave_encriptacion', 'MODULE_PAYMENT_CYBERPACK_CLAVE', '00000000', 'Clave de encriptacion para generar la firma', '6', '3', now())");
		$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Clave_encriptacion Pruebas', 'MODULE_PAYMENT_CYBERPACK_CLAVE2', '00000000', 'Clave de encriptacion para generar la firma en el entorno de pruebas', '6', '3', now())");

		$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Merchantid', 'MODULE_PAYMENT_CYBERPACK_MERCHANTID', '111950028', 'Este es el código de comercio facilitado por la Caja', '6', '4', now())");
		$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Terminalid','MODULE_PAYMENT_CYBERPACK_TERMINALID', '001', 'Código que identifica al terminal', '6', '6', now())");

		$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Orden de visualización', 'MODULE_PAYMENT_CYBERPACK_SORT_ORDER', '0', 'Orden de visualización, el más bajo se visualiza primero.', '6', '10', now())");

		$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Zona de pagos', 'MODULE_PAYMENT_CYBERPACK_ZONE', '0', 'Si se selecciona una zona, este módulo solo estará disponible para esa zona.', '6', '11', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");

		$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Estado de los pedidos', 'MODULE_PAYMENT_CYBERPACK_ORDER_STATUS_ID', '0', 'Los pedidos pagados por este método, se pondrán a este estado.', '6', '12', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");

		$sql = "CREATE TABLE {$this->CYBERTEMPTABLE} (  tempOrderId INT(6) ZEROFILL NOT NULL AUTO_INCREMENT , finalOrderId INT, 
				customerEmail varchar(255) , details varchar(255), fecha date, PRIMARY KEY ( tempOrderId ));";
		$result = $db->Execute($sql);
	}

	function remove() {
		global $db;
		$db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this -> keys()) . "')");

		$sql = "DROP TABLE IF EXISTS {$this->CYBERTEMPTABLE}";
		$result = $db->Execute($sql);
	}

	function keys() {
		return array('MODULE_PAYMENT_CYBERPACK_STATUS', 'MODULE_PAYMENT_CYBERPACK_URL_TYPE', 'MODULE_PAYMENT_CYBERPACK_ONLINE_CONF', 'MODULE_PAYMENT_CYBERPACK_CLAVE', 'MODULE_PAYMENT_CYBERPACK_CLAVE2', 'MODULE_PAYMENT_CYBERPACK_MERCHANTID', 'MODULE_PAYMENT_CYBERPACK_TERMINALID', 'MODULE_PAYMENT_CYBERPACK_SORT_ORDER', 'MODULE_PAYMENT_CYBERPACK_ZONE', 'MODULE_PAYMENT_CYBERPACK_ORDER_STATUS_ID');
	}

	/*
	 * para confirmacion online: se llama desde archivo accedido por pasarela
	 */
	function respuesta() {
		// checkear numero operacion contra tabla temporal	
		$url = zen_href_link(FILENAME_CHECKOUT_PROCESS, '', 'NONSSL',false);
			$url .= '&zenid='.$_POST['Ds_Merchant_MerchantData'];
			$this->trace($url);
		if($this->checkFirmaRespuesta()) {
			$url = zen_href_link(FILENAME_CHECKOUT_PROCESS, '', 'NONSSL',false);
			$url .= '&zenid='.$_POST['Ds_Merchant_MerchantData'];
			$this->trace($url);

			if(function_exists('curl_init')) $this->respuestaCurl($url);
			else $this->respuestaHttpClient($url);
		}		
	}
	
	function respuestaCurl($url) {
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_TIMEOUT, 30);
				curl_exec($ch);
	}
	
	function respuestaHttpClient($url) {
		require_once (DIR_FS_CATALOG . '/includes/classes/http_client.php');
		$http2 = new httpClient($url, 80);
		$http2 -> addHeader("Host", $url);
		$status = $http2 -> Get($url);
		if($status != 200) {
			$this -> trace("\nPetición fallida: " . $status . " $url_con_params");
		} else {
			$respuesta = $http2 -> getBody();
			$this -> trace("\nRespuesta: " . $pedido . "\n" . $respuesta);
		}
		$http2 -> Disconnect();
	}
	

	function firma($MerchantID, $Importe, $TipoMoneda, $Referencia) {
		return sha1($Importe. $Referencia. $MerchantID. $TipoMoneda . $this->clave);		
	} 
	
	function firmaAmpliada($MerchantID, $Importe, $TipoMoneda, $Referencia, $tipoTransaccion) {
		return sha1($Importe. $Referencia. $MerchantID. $TipoMoneda . $tipoTransaccion . $this->urlConfirmacion  . $this->clave);
	}

	function checkFirmaRespuesta() {
		$firma = sha1($_POST['Ds_Amount']. $_POST['Ds_Order']. $_POST['Ds_MerchantCode']. 
					$_POST['Ds_Currency'] . $_POST['Ds_Response'] . $this->clave);
		return ($firma == $_POST['Ds_Signature']) ? true : false;
	}
	
	function moneda() {
		$monedas_posibles = Array('EUR'=>978, 'USD'=>840, 'GBP'=>826);
		if( isset($_SESSION['currency']) && !empty($_SESSION['currency']) ) {
			return $monedas_posibles[$_SESSION['currency']];
		}
		else return $monedas_posibles['EUR'];
	}

	function idioma() {
		$lang =  isset($_SESSION['languages_code']) ? $_SESSION['languages_code'] : 'es';

		$supported_langs = Array('es'=>'001', 'en'=>'002','fr'=>'004','de'=>'005');
		return $supported_langs[$lang] ? $supported_langs[$lang] : '001'; 
	}
	
	function saveToTempTable($customerEmail, $details) {
		global $db;
		$sql = "INSERT INTO {$this->CYBERTEMPTABLE} values ( '', '', '{$customerEmail}', '{$details}', '' )";
		$db->Execute($sql);
				
		$id = $db->Execute("SELECT tempOrderId FROM {$this->CYBERTEMPTABLE} ORDER BY tempOrderId DESC limit 1");
		return $id->fields['tempOrderId'];
	}
	
	function getFromTempTable($customerEmail) {
		global $db;
		$sql = "SELECT tempOrderId FROM {$this->CYBERTEMPTABLE} where customerEmail='{$customerEmail}' 
		        ORDER BY tempOrderId DESC limit 1";
		$select = $db->Execute($sql);
		return $select->fields['tempOrderId'];
	}
	
	function updateTempTable($tempOrderId, $field, $value) {
		global $db;
		$sql = "UPDATE {$this->CYBERTEMPTABLE} SET {$field}='{$value}' WHERE tempOrderId='{$tempOrderIdder}'";
		$update = $db->Execute($sql);
	}

}
?>
