<?php

if( !defined('__IN_SYMPHONY__') ) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');



class Extension_Multilingual_Metakeys extends Extension
{
    const FIELD_TABLE = 'tbl_fields_multilingual_metakeys';

    protected static $assets_loaded = false;



    /*------------------------------------------------------------------------------------------------*/
    /*  Installation  */
    /*------------------------------------------------------------------------------------------------*/

    public function install(){
        $query = "CREATE TABLE IF NOT EXISTS `%s` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`field_id` int(11) unsigned NOT NULL,
					`validator` VARCHAR(255) DEFAULT NULL,
					`default_keys` TEXT DEFAULT NULL,
					`delete_empty_keys` INT (1) NOT NULL DEFAULT '1',";

        foreach( FLang::getLangs() as $lc )
            $query .= "
					`default_keys-{$lc}` TEXT DEFAULT NULL,";

        $query .= "
					`def_ref_lang` ENUM('yes','no') DEFAULT 'no',
					PRIMARY KEY (`id`),
					UNIQUE KEY `field_id` (`field_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

        return Symphony::Database()->query(sprintf($query, self::FIELD_TABLE));
    }

    public function uninstall(){
        try{
            Symphony::Database()->query(sprintf(
                "DROP TABLE `%s`",
                self::FIELD_TABLE
            ));
        }
        catch( DatabaseException $dbe ){
            // table deosn't exist
        }

        return true;
    }



    /*------------------------------------------------------------------------------------------------*/
    /*  Delegates  */
    /*------------------------------------------------------------------------------------------------*/

    public function getSubscribedDelegates(){
        return array(
            array(
                'page' => '/system/preferences/',
                'delegate' => 'AddCustomPreferenceFieldsets',
                'callback' => 'dAddCustomPreferenceFieldsets'
            ),
            array(
                'page' => '/extensions/frontend_localisation/',
                'delegate' => 'FLSavePreferences',
                'callback' => 'dFLSavePreferences'
            ),
        );
    }



    /*------------------------------------------------------------------------------------------------*/
    /*  System preferences  */
    /*------------------------------------------------------------------------------------------------*/

    /**
     * Display options on Preferences page.
     *
     * @param array $context
     */
    public function dAddCustomPreferenceFieldsets($context){
        $group = new XMLElement('fieldset');
        $group->setAttribute('class', 'settings');
        $group->appendChild(new XMLElement('legend', __(MMK_NAME)));

        $label = Widget::Label(__('Consolidate entry data'));
        $label->appendChild(Widget::Input('settings[multilingual_metakeys][consolidate]', 'yes', 'checkbox', array('checked' => 'checked')));
        $group->appendChild($label);
        $group->appendChild(new XMLElement('p', __('Check this field if you want to consolidate database by <b>keeping</b> entry values of removed/old Language Driver language codes. Entry values of current language codes will not be affected.'), array('class' => 'help')));

        $context['wrapper']->appendChild($group);
    }

    /**
     * Save options from Preferences page
     *
     * @param array $context
     */
    public function dFLSavePreferences($context){
        // settings table
        $show_columns = Symphony::Database()->fetch(sprintf(
            "SHOW COLUMNS FROM `%s` LIKE 'default_keys-%%'",
            self::FIELD_TABLE
        ));

        $columns = array();

        // Remove obsolete fields
        if( is_array($show_columns) && !empty($show_columns) )
            $consolidate = $context['context']['settings']['multilingual_metakeys']['consolidate'];

        foreach( $show_columns as $column ){
            $lc = substr($column['Field'], strlen($column['Field']) - 2);

            // If not consolidate option AND column lang_code not in supported languages codes -> Drop Column
            if( ($consolidate !== 'yes') && !in_array($lc, $context['new_langs']) ){
                Symphony::Database()->query(sprintf(
                    'ALTER TABLE `%1$s` DROP COLUMN `default_keys-%2$s`;',
                    self::FIELD_TABLE, $lc
                ));
            }
            else{
                $columns[] = $column['Field'];
            }
        }

        // Add new fields
        foreach( $context['new_langs'] as $lc ){
            if( !in_array('default_keys-'.$lc, $columns) ){
                Symphony::Database()->query(sprintf(
                    'ALTER TABLE `%1$s` ADD COLUMN `default_keys-%2$s` TEXT DEFAULT NULL;',
                    self::FIELD_TABLE, $lc
                ));
            }
        }


        // data tables
        $fields = Symphony::Database()->fetch(sprintf(
            'SELECT `field_id` FROM `%s`',
            self::FIELD_TABLE
        ));

        if( is_array($fields) && !empty($fields) ){
            $consolidate = $context['context']['settings']['multilingual_metakeys']['consolidate'];

            // Foreach field check multilanguage values foreach language
            foreach( $fields as $field ){
                $entries_table = 'tbl_entries_data_'.$field["field_id"];

                try{
                    $show_columns = Symphony::Database()->fetch(sprintf(
                        "SHOW COLUMNS FROM `%s` LIKE 'key_handle-%%'",
                        $entries_table
                    ));
                }
                catch( DatabaseException $dbe ){
                    // Field doesn't exist. Better remove it's settings
                    Symphony::Database()->query(sprintf(
                        "DELETE FROM `%s` WHERE `field_id` = '%s';",
                        self::FIELD_TABLE, $field["field_id"]
                    ));
                    continue;
                }

                $columns = array();

                // Remove obsolete fields
                if( is_array($show_columns) && !empty($show_columns) )

                    foreach( $show_columns as $column ){
                        $lc = substr($column['Field'], strlen($column['Field']) - 2);

                        // If not consolidate option AND column lang_code not in supported languages codes -> Drop Column
                        if( ($consolidate !== 'yes') && !in_array($lc, $context['new_langs']) )
                            Symphony::Database()->query(sprintf(
                                'ALTER TABLE `%1$s`
										DROP COLUMN `key_handle-%2$s`,
										DROP COLUMN `key_value-%2$s`,
										DROP COLUMN `value_handle-%2$s`,
										DROP COLUMN `value_value-%2$s`;',
                                $entries_table, $lc
                            ));
                        else
                            $columns[] = $column['Field'];
                    }

                // Add new fields
                foreach( $context['new_langs'] as $lc )

                    if( !in_array('key_handle-'.$lc, $columns) )
                        Symphony::Database()->query(sprintf(
                            'ALTER TABLE `%1$s`
									ADD COLUMN `key_handle-%2$s` VARCHAR(255) NULL,
									ADD COLUMN `key_value-%2$s` TEXT NULL,
									ADD COLUMN `value_handle-%2$s` VARCHAR(255) NULL,
									ADD COLUMN `value_value-%2$s` TEXT NULL;',
                            $entries_table, $lc
                        ));
            }
        }
    }



    /*------------------------------------------------------------------------------------------------*/
    /*  Public utilities  */
    /*------------------------------------------------------------------------------------------------*/

    public static function appendAssets(){
        if( self::$assets_loaded === false
            && class_exists('Administration')
            && Administration::instance() instanceof Administration
            && Administration::instance()->Page instanceof HTMLPage ){

            self::$assets_loaded = true;

            $page = Administration::instance()->Page;

            $page->addStylesheetToHead(URL.'/extensions/multilingual_metakeys/assets/multilingual_metakeys.publish.css', 'screen', null, false);
        }
    }
}
