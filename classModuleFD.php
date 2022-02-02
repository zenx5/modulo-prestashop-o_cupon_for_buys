<?php
	
	define('ACTIVE_MODULE_FD', 1);
	class ModuleFD extends Module	{
		public $hooks = array();

		public function addHooks( $hooks , $register = true){
			$r = true;
			foreach ($hooks as $hook) {
				if( ! is_array($hook) ){
					$hook = array($hook,0);
				}
				if($register){
					$r = $r && $this->registerHook($hook[0]);
					if( $hook[1] !== 0){
						$id_hook = Hook::getIdByName($hook[0]);
						$this->updatePosition($id_hook, 0, $hook[1]);
					}
				}
				else{
					$r = $r && $this->unregisterHook($hook[0]);
				}
			}
			return $r;
		}

		
		public function validateData($data) {
			return (!$data ) || empty($data) || !Validate::isGenericName($data);
		}

	
		public function validateDataArray($dataArray){
			return true;
			$result = true;
			foreach ($dataArray as $data) {
				$result = $result && $this->validateData($data);
			}
			return $result;
		}


		public function getLangPrefix($id = null){
			global $cookie;
			if(!$id){
				$id = $cookie->id_lang;
			}
			$langs = Language::getLanguages();
			$prefix = array();
			foreach ($langs as $lang) {
				$prefix[$lang['id_lang']] = $lang['iso_code'];
			}
			return $prefix[$id];
		}
		protected function getModuleConfigurationPageLink(){
	        $parsedUrl = parse_url($this->context->link->getAdminLink('AdminModules', false));

	        $urlParams = http_build_query([
				'configure' => $this->name,
				'tab_module' => $this->tab,
				'module_name' => $this->name,
			]);

	        if (!empty($parsedUrl['query'])) {
	            $parsedUrl['query'] .= "&$urlParams";
	        }
	        else{
	            $parsedUrl['query'] = $urlParams;
	        }
	    	
	    	return http_build_url($parsedUrl);
		}
		
	}