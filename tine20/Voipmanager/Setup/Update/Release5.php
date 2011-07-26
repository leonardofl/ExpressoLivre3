<?php
/**
 * Tine 2.0
 *
 * @package     Voipmanager
 * @subpackage  Setup
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schüle <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2011 Metaways Infosystems GmbH (http://www.metaways.de)
 */

/**
 * Voipmanager updates for version 5.x
 *
 * @package     Voipmanager
 * @subpackage  Setup
 */
class Voipmanager_Setup_Update_Release5 extends Setup_Update_Abstract
{
    /**
     * shorten some db fields
     * 
     * @return void
     */
    public function update_0()
    {
        $shortenFieldNames = array(
            'web_language_writable'        => 'web_language_w',
            "language_writable"            => 'language_w',
            "display_method_writable"      => 'display_method_w',
            "call_waiting_writable"        => 'call_waiting_w',
            "mwi_notification_writable"    => 'mwi_notification_w',
            "mwi_dialtone_writable"        => 'mwi_dialtone_w',
            "headset_device_writable"      => 'headset_device_w',
            "message_led_other_writable"   => 'message_led_other_w',
            "global_missed_counter_writable" => 'global_missed_counter_w',
            "scroll_outgoing_writable"     => 'scroll_outgoing_w',
            "show_local_line_writable"     => 'show_local_line_w',
            "show_call_status_writable"    => 'show_call_status_w',
        );
        
        foreach ($shortenFieldNames as $old => $new) {
            $declaration = new Setup_Backend_Schema_Field_Xml('
                <field>
                    <name>' . $new . '</name>
                    <type>boolean</type>
                    <default>false</default>
                </field>');
            $this->_backend->alterCol('snom_default_settings', $declaration, $old);
        }
        
        $this->setTableVersion('snom_default_settings', 2);
        
        $this->setApplicationVersion('Voipmanager', '5.1');
    }

    /**
     * replace enums
     * 
     * @return void
     */
    public function update_1()
    {
        $tables = array(
            'snom_location' => array(
                'version' => 2,
                'fields'  => array(
                    'update_policy' => array('default' => 'auto_update'),
                    'admin_mode' => array('default' => 'false'),
                    'webserver_type' => array('default' => 'https'),
                    'tone_scheme' => array(),
                )
            ),
            'snom_templates' => array(
                'version' => 2,
                'fields'  => array(
                    'model' => array('default' => 'snom300'),
                )
            ),
            'snom_phones' => array(
                'version' => 2,
                'fields'  => array(
                    'current_model' => array('default' => 'snom300'),
                    'redirect_event' => array('default' => 'none'),
                )
            ),
            'asterisk_sip_peers' => array(
                'version' => 2,
                'fields'  => array(
                    'dtmfmode' => array('default' => 'rfc2833'),
                    'insecure' => array('default' => 'no'),
                    'nat' => array('default' => 'no'),
                    'qualify' => array('default' => 'no'),
                    'type' => array('default' => 'friend'),
                    'cfi_mode' => array('default' => 'off'),
                    'cfb_mode' => array('default' => 'off'),
                    'cfd_mode' => array('default' => 'off'),
                )
            ),
            'snom_default_settings' => array(
                'version' => 3,
                'fields'  => array(
                    'web_language' => array('default' => 'English'),
                    'language' => array('default' => 'English'),
                    'display_method' => array('default' => 'full_contact'),
                    'mwi_notification' => array('default' => 'silent'),
                    'mwi_dialtone' => array('default' => 'normal'),
                    'headset_device' => array('default' => 'none'),
                    'call_waiting' => array('default' => 'on'),
                )
            ),
            'snom_phone_settings' => array(
                'version' => 2,
                'fields'  => array(
                    'web_language' => array('default' => 'English'),
                    'language' => array('default' => 'English'),
                    'display_method' => array('default' => 'full_contact'),
                    'mwi_notification' => array('default' => 'silent'),
                    'mwi_dialtone' => array('default' => 'normal'),
                    'headset_device' => array('default' => 'none'),
                    'call_waiting' => array('default' => 'on'),
                )
            ),
            'snom_phones_acl' => array(
                'version' => 2,
                'fields'  => array(
                    'account_type' => array('default' => 'user'),
                )
            ),
            'asterisk_redirects' => array(
                'version' => 2,
                'fields'  => array(
                    'cfi_mode' => array('default' => 'off'),
                    'cfb_mode' => array('default' => 'off'),
                    'cfd_mode' => array('default' => 'off'),
                )
            ),
        );
        
        foreach ($tables as $table => $data) {
            foreach ($data['fields'] as $field => $fieldData) {
                $declaration = new Setup_Backend_Schema_Field_Xml('
                    <field>
                        <name>' . $field . '</name>
                        <type>text</type>
                        <length>32</length>
                        ' . ((! empty($fieldData)) ? '<default>' . $fieldData['default'] . '</default><notnull>true</notnull>' : '') . '
                    </field>');
                $this->_backend->alterCol($table, $declaration);
            }
            $this->setTableVersion($table, $data['version']);
        }
        
        $this->setApplicationVersion('Voipmanager', '5.2');
    }
}
