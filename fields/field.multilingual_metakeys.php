<?php
	if( !defined('__IN_SYMPHONY__') ) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');



	require_once(EXTENSIONS.'/frontend_localisation/extension.driver.php');
	require_once(EXTENSIONS.'/frontend_localisation/lib/class.FLang.php');
	require_once(EXTENSIONS.'/field_metakeys/fields/field.metakeys.php');
	require_once(EXTENSIONS.'/multilingual_metakeys/extension.driver.php');



	Class fieldMultilingual_MetaKeys extends fieldMetaKeys
	{

		/*------------------------------------------------------------------------------------------------*/
		/*  Definition  */
		/*------------------------------------------------------------------------------------------------*/

		public function __construct(){
			parent::__construct();

			$this->_name = __('Multilingual Meta Keys');
		}

		public function createTable(){
			$field_id = $this->get('id');

			$query = "
				CREATE TABLE IF NOT EXISTS `tbl_entries_data_{$field_id}` (
					`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
					`entry_id` INT(11) UNSIGNED NOT NULL,
					`key_handle` VARCHAR(255) NULL,
					`key_value` TEXT NULL,
					`value_handle` VARCHAR(255) DEFAULT NULL,
					`value_value` TEXT NULL,";

			foreach( FLang::getLangs() as $lc )
				$query .= "
					`key_handle-{$lc}` VARCHAR(255) NULL,
				    `key_value-{$lc}` TEXT NULL,
				    `value_handle-{$lc}` VARCHAR(255) DEFAULT NULL,
				    `value_value-{$lc}` TEXT NULL,";

			$query .= "
					PRIMARY KEY (`id`),
					KEY `entry_id` (`entry_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

			return Symphony::Database()->query($query);
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Utilities  */
		/*------------------------------------------------------------------------------------------------*/

		/**
		 * @param null $key - array of language codes => keys
		 * @param null $value - array of language codes => values
		 * @param string $i - position
		 *
		 * @return XMLElement
		 */
		public function buildPair($key = null, $value = null, $i = '-1') {
			$main_lang = FLang::getMainLang();
			$all_langs = FLang::getAllLangs();
			$langs = FLang::getLangs();

			$element_name = $this->get('element_name');

			$metakey = new XMLElement('li');
			$metakey->setAttribute('class',(!is_null($key) ? 'instance expanded' : 'template') . ' field-multilingual');

			// Header
			$header = new XMLElement('header');
			$header->setAttribute('data-name', 'pair');

			foreach( $langs as $lc ){
				$div = new XMLElement('div', null, array('class' => 'tab-element tab-'.$lc));
				$label = isset($key[$lc]) ? $key[$lc] : __('New Pair');
				$div->appendChild(new XMLElement('h4', '<strong>' . $label . '</strong>'));
				$header->appendChild($div);
			}

			$metakey->appendChild($header);


			$container = new XMLElement('div', null, array('class' => 'container'));


			/*------------------------------------------------------------------------------------------------*/
			/*  Tabs  */
			/*------------------------------------------------------------------------------------------------*/

			$ul = new XMLElement('ul', null, array('class' => 'tabs'));
			foreach( $langs as $lc ){
				$li = new XMLElement('li', $all_langs[$lc], array('class' => $lc));
				$lc === $main_lang ? $ul->prependChild($li) : $ul->appendChild($li);
			}

			$container->appendChild($ul);


			/*------------------------------------------------------------------------------------------------*/
			/*  Panels  */
			/*------------------------------------------------------------------------------------------------*/

			foreach( $langs as $lc ){
				$div = new XMLElement('div', null, array('class' => 'tab-panel tab-'.$lc));

				// Key
				$label = Widget::Label();
				$label->appendChild(
					Widget::Input(
						"fields[$element_name][$lc][$i][key]", $key[$lc], 'text', array('placeholder' => __('Key'))
					)
				);
				$div->appendChild($label);

				// Value
				$label = Widget::Label();
				$label->appendChild(
					Widget::Input(
						"fields[$element_name][$lc][$i][value]", $value[$lc], 'text', array('placeholder' => __('Value'))
					)
				);
				$div->appendChild($label);

				$container->appendChild($div);
			}

			$metakey->appendChild($container);

			return $metakey;
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Settings  */
		/*------------------------------------------------------------------------------------------------*/

		public function findDefaults(&$fields){
			parent::findDefaults($fields);

			$fields['def_ref_lang'] = 'no';
		}

		public function displaySettingsPanel(XMLElement &$wrapper, $errors = null){
			parent::displaySettingsPanel($wrapper, $errors);


			/* Default to ref lang */

			$input = Widget::Input("fields[{$this->get('sortorder')}][def_ref_lang]", 'yes', 'checkbox');
			if( $this->get('def_ref_lang') == 'yes' ) $input->setAttribute('checked', 'checked');
			$wrapper->appendChild(Widget::Label(
				__('%s Use value from main language if selected language has empty value.', array($input->generate()))
			));


			/* Default keys */

			Extension_Frontend_Localisation::appendAssets();

			$wrapper->setAttribute('class', $wrapper->getAttribute('class').' field-multilingual');

			$order = $this->get('sortorder');

			$main_lang = FLang::getMainLang();
			$all_langs = FLang::getAllLangs();
			$langs = FLang::getLangs();

			$container = new XMLElement('div', null, array('class' => 'container column'));


			/*------------------------------------------------------------------------------------------------*/
			/*  Label  */
			/*------------------------------------------------------------------------------------------------*/

			$label = Widget::Label(__('Default Keys'));
			$label->appendChild(
				new XMLElement('i', __('Optional'))
			);
			$container->appendChild($label);


			/*------------------------------------------------------------------------------------------------*/
			/*  Tabs  */
			/*------------------------------------------------------------------------------------------------*/

			$ul = new XMLElement('ul', null, array('class' => 'tabs'));
			foreach( $langs as $lc ){
				$li = new XMLElement('li', $all_langs[$lc], array('class' => $lc));
				$lc === $main_lang ? $ul->prependChild($li) : $ul->appendChild($li);
			}

			$container->appendChild($ul);


			/*------------------------------------------------------------------------------------------------*/
			/*  Panels  */
			/*------------------------------------------------------------------------------------------------*/

			foreach( $langs as $lc ){
				$div = new XMLElement('div', null, array('class' => 'tab-panel tab-'.$lc, 'data-lang_code' => $lc));

				$div->appendChild(Widget::Input(
					"fields[{$order}][default_keys-{$lc}]", $this->get("default_keys-{$lc}")
				));

				$container->appendChild($div);
			}

			if( $errors['default_keys-*'] ){
				$container = Widget::Error($container, $errors['default_keys-*']);
			}

			foreach( $wrapper->getChildrenByName('div') as $div )

				if( $div->getAttribute('class') === 'two columns' ){
					$div->replaceChildAt(0, $container);
					return true;
				}
		}

		public function checkFields(array &$errors, $checkForDuplicates = true){
			parent::checkFields($errors, $checkForDuplicates);

			$count_defaults = count(preg_split('/,\s*/', $this->get('default_keys-'.FLang::getMainLang()), -1, PREG_SPLIT_NO_EMPTY));

			foreach( FLang::getLangs() as $lc ){
				if( count(preg_split('/,\s*/', $this->get("default_keys-$lc"), -1, PREG_SPLIT_NO_EMPTY)) !== $count_defaults ){
					$errors["default_keys-*"] = __('Number of keys must match.');
					break;
				}
			}

			return (is_array($errors) && !empty($errors) ? self::__ERROR__ : self::__OK__);
		}

		public function commit(){
			if( !parent::commit() ) return false;

			$query = sprintf("UPDATE `tbl_fields_%s` SET", $this->handle());

			foreach( FLang::getLangs() as $lc ){
				$query .= sprintf(" `default_keys-%s` = '%s',", $lc, $this->get("default_keys-{$lc}"));
			}

			$query .= sprintf(" `def_ref_lang` = '%s' WHERE `field_id` = '%s';",
				$this->get('def_ref_lang') === 'yes' ? 'yes': 'no',
				$this->get('id')
			);


			return Symphony::Database()->query($query);
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Publish  */
		/*------------------------------------------------------------------------------------------------*/

		public function displayPublishPanel(XMLElement &$wrapper, $data = null, $flagWithError = null, $fieldnamePrefix = null, $fieldnamePostfix = null, $entry_id = null){
			Extension_Frontend_Localisation::appendAssets();
			Extension_Multilingual_Metakeys::appendAssets();

			$langs = FLang::getLangs();
			$main_lang = FLang::getMainLang();

			// Label
			$label = Widget::Label($this->get('label'));
			if( $this->get('required') == 'no' ){
				$label->appendChild(new XMLElement('i', __('Optional')));
			}

			// Setup Duplicator
			$duplicator = new XMLElement('ol');
			$duplicator->setAttribute('class', 'filters-duplicator');
			$duplicator->setAttribute('data-add', __('Add pair'));
			$duplicator->setAttribute('data-remove', __('Remove pair'));

			// Add a blank template
			$duplicator->appendChild(
				$this->buildPair()
			);

			// Loop through the default keys if this is a new entry.
			if( is_null($entry_id) && !is_null($this->get('default_keys-'.FLang::getMainLang())) ){
				$defaults = array();

				$field_handle = $this->get('element_name');

				// set defaults from POST
				if( isset($_POST['fields'][$field_handle]) ){

					foreach( $langs as $lc ){

						foreach( $_POST['fields'][$field_handle][$lc] as $i => $data ){
							$defaults[$i]['keys'][$lc] = $data['key'];
							$defaults[$i]['vals'][$lc] = $data['value'];
						}

					}
				}

				// show default keys
				else{
					foreach( $langs as $lc ){
						$parts = preg_split('/,\s*/', $this->get("default_keys-$lc"), -1, PREG_SPLIT_NO_EMPTY);

						if( is_array($parts) && !empty($parts) ){
							foreach( $parts as $i => $key ){
								$defaults[$i]['keys'][$lc] = $key;
							}
						}
					}
				}

				foreach( $defaults as $def ){
					$duplicator->appendChild(
						$this->buildPair($def['keys'], $def['vals'])
					);
				}

			}

			// If there is actually $data, show that
			else if( !is_null($data) ){

				// If there's only one 'pair', we'll need to make them an array
				// so the logic remains consistant
				if( !is_array($data['key_value']) ){
					foreach( $langs as $lc ){
						$data["key_value-{$lc}"] = array($data["key_value-{$lc}"]);
						$data["key_handle-{$lc}"] = array($data["key_handle-{$lc}"]);
						$data["value_value-{$lc}"] = array($data["value_value-{$lc}"]);
						$data["value_handle-{$lc}"] = array($data["value_handle-{$lc}"]);
					}
				}

				for( $i = 0, $ii = count($data['key_value-'.FLang::getMainLang()]); $i < $ii; $i++ ){
					$keys = array();
					$vals = array();

					foreach( $langs as $lc ){
						$keys[$lc] = $data["key_value-$lc"][$i];
						$vals[$lc] = $data["value_value-$lc"][$i];
					}

					$duplicator->appendChild(
						$this->buildPair($keys, $vals, $i)
					);
				}
			}

			$wrapper->appendChild($label);
			$wrapper->appendChild($duplicator);

			if( !is_null($flagWithError) ){
				$wrapper = Widget::Error($wrapper, $flagWithError);
			}
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Input  */
		/*------------------------------------------------------------------------------------------------*/

		public function checkPostFieldData($data, &$message, $entry_id = null){
			$error = self::__OK__;
			$field_data = $data;
			$all_langs = FLang::getAllLangs();
			$main_lang = FLang::getMainLang();

			foreach( FLang::getLangs() as $lc ){

				$file_message = '';
				$data = $field_data[$lc];

				$status = parent::checkPostFieldData($data, $file_message, $entry_id);

				// if one language fails, all fail
				if( $status != self::__OK__ ){

					if( $lc === $main_lang ){
						$message = "<br />{$all_langs[$lc]}: {$file_message}" . $message;
					}
					else{
						$message .= "<br />{$all_langs[$lc]}: {$file_message}";
					}

					$error = self::__ERROR__;
				}
			}

			return $error;
		}

		public function processRawFieldData($data, &$status, &$message = null, $simulate = false, $entry_id = null){
			if( !is_array($data) || empty($data) ) return parent::processRawFieldData($data, $status, $message, $simulate, $entry_id);

			$result = array();
			$field_data = $data;

			foreach( FLang::getLangs() as $lc ){

				$data = $field_data[$lc];

				$parts = parent::processRawFieldData($data, $status, $message, $simulate, $entry_id, $lc);

				if( is_array($parts) ){
					foreach( $parts as $key => $value ){
						$result[$key.'-'.$lc] = $value;
					}
				}
			}

			return $result;
		}

		public function getExampleFormMarkup(){
			$label = Widget::Label($this->get('label').'
					<!-- '.__('Modify just current language value').' -->
					<input name="fields['.$this->get('element_name').'][][value-{$url-fl-language}]" type="text" />

					<!-- '.__('Modify all values').' -->');

			if( $this->get('text_size') === 'single' )
				foreach( FLang::getLangs() as $lc )
					$label->appendChild(Widget::Input("fields[{$this->get('element_name')}][][value-{$lc}]"));
			else
				foreach( FLang::getLangs() as $lc )
					$label->appendChild(Widget::Input("fields[{$this->get('element_name')}][][value-{$lc}]"));

			return $label;
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Output  */
		/*------------------------------------------------------------------------------------------------*/

		public function fetchIncludableElements(){
			return array(
				$this->get('element_name')
			);
		}

		public function appendFormattedElement(XMLElement &$wrapper, $data, $encode = false, $mode = null, $entry_id = null){
			$lang_code = FLang::getLangCode();

			// If value is empty for this language, load value from main language
			if( $this->get('def_ref_lang') == 'yes' && $data['key_value-'.$lang_code] === '' ){
				$lang_code = FLang::getMainLang();
			}

			$data['key_handle'] = $data['key_handle-'.$lang_code];
			$data['key_value'] = $data['key_value-'.$lang_code];
			$data['value_handle'] = $data['value_handle-'.$lang_code];
			$data['value_value'] = $data['value_value-'.$lang_code];

			parent::appendFormattedElement($wrapper, $data);
		}

		public function getParameterPoolValue(array $data, $entry_id = NULL){
			$lang_code = FLang::getLangCode();

			// If value is empty for this language, load value from main language
			if( $this->get('def_ref_lang') == 'yes' && $data['key_value-'.$lang_code] === '' ){
				$lang_code = FLang::getMainLang();
			}

			return $data["key_handle-$lang_code"];
		}

		public function prepareTableValue($data, XMLElement $link = null, $entry_id = null){
			if( is_null($data) ) return __('None');

			$lang_code = FLang::getLangCode();

			// If value is empty for this language, load value from main language
			if( $this->get('def_ref_lang') == 'yes' && $data['key_value-'.$lang_code] === '' ){
				$lang_code = FLang::getMainLang();
			}

			$data['value_value'] = $data["value_value-$lang_code"];

			return parent::prepareTableValue($data, $link, $entry_id);
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Filtering  */
		/*------------------------------------------------------------------------------------------------*/

		public function buildDSRetrievalSQL($data, &$joins, &$where, $andOperation = false){
			parent::buildDSRetrievalSQL($data, $joins, $where, $andOperation);

			$lc = FLang::getLangCode();

			$where = str_replace('.key_value', ".`key_value-{$lc}`", $where);
			$where = str_replace('.key_handle', ".`key_handle-{$lc}`", $where);
			$where = str_replace('.value_value', ".`value_value-{$lc}`", $where);
			$where = str_replace('.value_handle', ".`value_handle-{$lc}`", $where);

			return true;
		}

	}
