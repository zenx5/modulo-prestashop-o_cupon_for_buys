<?php

	if(!defined('_PS_VERSION_')) {
		exit;
	}	

	
	if( ! defined('ACTIVE_MODULE_FD') ){
		include_once 'classModuleFD.php';
	}
	


	class o_cupon_for_buys extends ModuleFD{

		public function __construct(){
			$this->name = 'o_cupon_for_buys';
			$this->tab = 'front_office_features';
			$this->version = '1.0.0';
			$this->author = 'Octavio Martinez';
			$this->need_instance = 0;
			$this->ps_versions_compliancy = [
				'min' => '1.6',
				'max' => _PS_VERSION_
			];
			$this->bootstrap = true;
			parent::__construct();
			
			$this->displayName = 'Cupon For Buys';
			$this->description = 'Genera cupones segun hitos de ventas';
			$this->confirmUninstall = 'Are you sure you want to Uninstall?';

			if(!Configuration::get('MYMODULE_NAME')) {
				$this->warning = 'No name provided';
			}

			
			$this->hooks[] = array('displayOrderConfirmation','1');
			$this->hooks[] = array('displayHeader',0);

		}
		

		/******************
		**** INSTALL ******
		*******************/

		public function install(){
			//$this->installTab();
			
			if(Shop::isFeatureActive()) {
				Shop::setContext(Shop::CONTEXT_ALL);
			}
			return parent::install() && $this->addHooks($this->hooks);
		}

		public function uninstall(){
			//$this->uninstallTab();
			return parent::uninstall() && $this->addHooks($this->hooks, false);
		}

		/****************
		**** HOOKS ******
		*****************/
		public function hookDisplayHeader(){
			$this->context->controller->registerStylesheet('module-o-cupon-for-buys-style',
	            'modules/'.$this->name.'/css/cupon-card.css',
	            [
	              'media' => 'all',
	              'priority' => 200
	            ]
	        );
			
			$this->context->controller->registerJavascript('module-o-cupon-for-buys-js',
	            'modules/'.$this->name.'/js/scratch-card.js',
	            [	
	              'media' => 'all',
	              'priority' => 200
	            ]
	        );
		}

		public function hookDisplayOrderConfirmation($order){
			$langs = Language::getLanguages();
			$paid = true;
			$shipped = true;
			$idOrder = $order["order"]->id;
			
			$order = new Order($idOrder);
			$customer = $order->getCustomer();
			$list = OrderDetail::getList($idOrder);
			$price = $list[0]['total_price_tax_incl'];


			$ids = json_decode( Configuration::get('cfb_all_customer_id'), true );
			if(!is_array($ids)){
				$ids = array();
			}

			if(!in_array($customer->id, $ids)){
				$ids[] = $customer->id;
				Configuration::updateValue('cfb_all_customer_id', json_encode($ids));
			}

			$total = Configuration::get('cfb_total_'.$customer->id);
			$total += $price;
			
			if($total >= Configuration::get('target')){
				if(Configuration::get('cfb_ready_'.$customer->id) != 'READY'){
					Configuration::updateValue('cfb_total_'.$customer->id, $total);
					do{	
						$code = '';
						$chars = "123456789ABCDEFGHIJKLMNPQRSTUVWXYZ";
						for ($i = 1; $i <= 8; ++$i) {
							$code .= $chars[rand(0,strlen($chars))];
						}

					}while(CartRule::cartRuleExists($code));
					Configuration::updateValue('cfb_code_'.$customer->id, $code);
					
					$rule = new CartRule();
					$rule->name = array();
					foreach ($langs as $lang) {
						$rule->name[$lang['id_lang']] = 'Bonificación por Fidelidad';
					}
					$rule->id_customer = $customer->id;
					$rule->date_from = '2021-06-11 05:00:00';
					$rule->date_to = '2021-07-11 05:00:00';
					$rule->description = 'Cupo ganado por haber superado  ';
					$rule->code = $code;
					$rule->minimum_amount = Configuration::get('minimum_amount');
					$rule->minimum_amount_currency = 1;
					$rule->minimum_amount_tax = 0; //tax exclude, with 1 include
					$rule->minimum_amount_shipping = 0;//shipping exclude, with 1 include
					if(Configuration::get('type_discount') == 0){
						if( Configuration::get('discount') > 100 ){
							$rule->reduction_percent = 100;
						}else{
							$rule->reduction_percent = Configuration::get('discount');
						}
					}
					else{
						$rule->reduction_amount = Configuration::get('discount');
					}
					$rule->add();
					Configuration::updateValue('cfb_ready_'.$customer->id, 'READY');
					$this->context->smarty->assign([
						'code' => $code,
						'mensaje' => Configuration::get('cfb_mensaje'),
					]);
					return $this->display(__FILE__,'views/templates/hook/cupon.tpl');

					//AdminCartRulesController::
					//https://tienda.kavavdigital.com/bfahzgbhz5b7cavn/index.php?controller=AdminCartRules&token=3da188fef24af3aa3046a658448ebb1b&addcart_rule
				}
			}else{
				Configuration::updateValue('cfb_total_'.$customer->id, $total);
			}
					
		}
		

		/************************
		**** CONFIGURATION ******
		************************/

		public function getContent(){
			$output = null;
			if(Tools::isSubmit('submit')) {
				Configuration::updateValue('cfb_mensaje', Tools::getValue('cfb_mensaje'));
				Configuration::updateValue('target', Tools::getValue('target'));
				Configuration::updateValue('type_discount', Tools::getValue('type_discount'));
				Configuration::updateValue('discount', Tools::getValue('discount'));
				Configuration::updateValue('minimum_amount', Tools::getValue('minimum_amount'));

				$output .= $this->displayConfirmation('Se actualizo la Configuracion del Modulo');
			}
			return $output.$this->displayForm().$this->list();
		}

		public function list(){
			global $cookie;
		
			$html = "<div class='panel col-lg-12'>";
			$html .= "<table class='table table-hover'>";
			$html .= "<tr>";
			$html .= "<th>ID</th>";
			$html .= "<th>Usuario</th>";
			$html .= "<th>Total Compras</th>";
			$html .= "<th>Cupon</th>";
			
			$html .= "</tr>";

			$ids = json_decode( Configuration::get('cfb_all_customer_id'), true );
			if(is_array($ids)){
				foreach ($ids as $id) {
					$customer = new Customer($id);
					$html .= "<tr>";
					$html .= "<td>$id</td>";
					$html .= "<td>".$customer->firstname." ".$customer->lastname." (".$customer->email.")</td>";
					$html .= "<td>".Configuration::get('cfb_total_'.$customer->id)."</td>";
					$html .= "<td>".Configuration::get('cfb_code_'.$customer->id)."</td>";
					//CartRule::getCartsRuleByCode(Configuration::get('cfb_code_'.$customer->id), $cookie->id_lang);
					//if( Configuration::get('cfb_ready_'.$customer->id) == "READY"){
					//	$html .= "<td>No Usado</td>";
					//}
					//$html .= "<td><input type='submit' value='Reset' /></td>";
					$html .= "</tr>";
				}				
				$html .= "</table></div>";
			}
			else{
				$html .= "</table>";
				$html .= "<p>No Hay Elementos Para Mostrar Aun.</p>";
				$html .= "</div>";
			}
			return $html;
		}

		public function displayForm(){
			$lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
			
			$inputs = array(
				array(
					'type' => 'text',
					'label' => $this->l('Mensaje para el Ganador'),
					'name' => 'cfb_mensaje',
					'desc' => $this->l("Mensaje para mostrar cuando el usuario gane un cupon"),
				),
				array(
					'type' => 'html',
					'label' => $this->l('Monto'),
					'name' => 'target',
					'desc' => $this->l("El monto objetivo para generar el cupon"),
					'html_content' => '<input type="number" name="target" min="0" value="'.Configuration::get('target').'"/>',
				),
				array(
					'type' => 'select',
					'label' => $this->l('Tipo de Descuento'),
					'name' => 'type_discount',
					'options' => array(
						'query' => array(
							array( 'id' => '0', 'name' => 'Porcentaje'),
							array( 'id' => '1', 'name' => 'Monto'),
						),
						'id' => 'id',
						'name' => 'name',
					),
					'desc' => $this->l("..."),	
				),
				array(
					'type' => 'html',
					'label' => $this->l('Decuento'),
					'name' => 'discount',
					'desc' => $this->l("Descuento del cupon"),
					'html_content' => '<input type="number" name="discount" min="0" value="'.Configuration::get('discount').'"/>',
				),
				array(
					'type' => 'html',
					'label' => $this->l('Monto Minimo para Usar'),
					'name' => 'minimum_amount',
					'desc' => $this->l("Monto minimo del carrito para poder usar el cupon"),
					'html_content' => '<input type="number" name="minimum_amount" min="0" value="'.Configuration::get('minimum_amount').'"/>',
				),
				
			);

			$fields_form = array(
				'form' => array(
		            'legend' => array(
						'title' => 'Titulo',
						'icon' => 'icon-cogs'
		            ),
		            'input' => $inputs, 
		            'submit' => array(
		                'name' => 'submit',
		                'title' => $this->trans('Save', array(), 'Admin.Actions')
		            ),
		        ),
        	);

        	$helper = new HelperForm();
	        $helper->module = $this;
	        $helper->table = $this->name;
	        $helper->token = Tools::getAdminTokenLite('AdminModules');
	        $helper->currentIndex = $this->getModuleConfigurationPageLink();
	        
	        $helper->default_form_language = $lang->id;
	        
	        $helper->title = $this->displayName;
	        $helper->show_toolbar = false;
	        $helper->toolbar_scroll = false;
	        
	        $helper->submit_action = 'submit';
	        

			$helper->identifier = $this->identifier;


	        $helper->tpl_vars = array(
	            'languages' => $this->context->controller->getLanguages(),
	            'id_language' => $this->context->language->id,    
	            'fields_value' => array( 
	            	'cfb_mensaje' => Configuration::get('cfb_mensaje'),
	            	'target' => Configuration::get('target'),
	            	'discount' => Configuration::get('discount'),
	            	'type_discount' => Configuration::get('type_discount'),
	            	'minimum_amount' => Configuration::get('minimum_amount'),

	            ),
	        );

	        return $helper->generateForm(array($fields_form));
		}


		/**************
		**** TABS ******
		***************/
		private function installTab(){
			return true;
			/*
			$response = true;

			$subTab = new Tab();
			$subTab->active = 1;
			$subTab->name = array();
			$subTab->class_name = 'OscLinkTab';
			$subTab->icon = 'menu';
			foreach (Language::getLanguages() as $lang) {
				$subTab->name[$lang['id_lang']] = 'Subcategories Cards';
			}

			$subTab->id_parent = (int)Tab::getIdFromClassName('AdminCatalog');
			$subTab->module = $this->name;
			$response &= $subTab->add();

			return $response;*/
		}

		private function uninstallTab(){
			return true;
			/*$response = true;
			$tab_id = (int)Tab::getIdFromClassName('OscLinkTab');
			if(!$tab_id){
				return true;
			}

			$tab = new Tab($tab_id);
			$response &= $tab->delete();
			return $response;*/
		}
	}
		
