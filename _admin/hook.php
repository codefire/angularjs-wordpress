<?php

/**
 * Class AngularJsEnabledAdmin This is the class that hooks the plugin into the admin
 */
class AngularJsEnabledAdmin
{
    public function __construct()
    {
        // run the methods needed to set up the plugin
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_plugin_page'));
            add_action('admin_init', array($this, 'page_init'));
            add_action('admin_enqueue_scripts', array($this, 'add_assets'));
            add_action('wp_ajax_' . self::getApiActionName(), array($this, 'api'));
        }
    }

    /**
     * Wordpress needs and action for the ajax to work. Only need to change it in one place
     * /wp-admin/admin-ajax.php?action=angular_admin_api
     * @return string
     */
    public function getApiActionName()
    {
        return 'angular_admin_api';
    }

    /**
     * Add a menu link for the plugin
     */
    public function add_plugin_page()
    {
        // This page will be under "Settings"
        add_options_page('Settings Admin', 'AngularJS Admin', 'manage_options', 'angularjs-wordpress', array($this, 'create_admin_page'));
    }

    /**
     * Add the necessary JS and CSS
     */
    public function add_assets()
    {
        // currently we use CDN served angular to reduce size of the plugin
        wp_enqueue_script('angularjs', '//cdnjs.cloudflare.com/ajax/libs/angular.js/1.3.0/angular.js');
        // the angular js app
        wp_enqueue_script('angular_app', plugins_url('angular-wordpress.js', __FILE__), array('angularjs'));
        wp_enqueue_style('admin_css_angular', plugins_url('angular-wordpress.css', __FILE__), false, '1.0.0');
    }

    /**
     * Shows the HTML for the admin page
     */
    public function create_admin_page()
    {
        // this shows the actual admin page, we pull in an external file because it is messy to have html in here
        ob_start();
        $apiName = self::getApiActionName();
        require plugin_dir_path(__FILE__) . '/form.phtml';
        $view = ob_get_clean();
        echo $view;
    }

    public function page_init()
    {
//        // any additional init can go here
    }

    /**
     * Returns a list of fields names that we are expecting, bit of increased peace of mind, don't save any old data to the DB
     * @return array
     */
    private function getExpectedFields()
    {
        return [
            'my_value'
        ];
    }

    /**
     * Handle API calls
     */
    public function api()
    {
        // Make sure the time returned is based on the WP timezone and not the server timezone
        $timezone = get_option('gmt_offset');
        $dateTime = new DateTime();

        $timeinterval = null;
        if ($timezone > 0) {
            $timeinterval = DateInterval::createFromDateString('+ ' . $timezone . ' hours');
        } else if ($timezone < 0) {
            $timeinterval = DateInterval::createFromDateString('- ' . $timezone . ' hours');
        }

        if (!empty($timeinterval))
            $dateTime->add($timeinterval);

        $time = $dateTime->format('H:i:s');
        // END time zone stuff

        // the data array will be returned as JSON
        $data = array(
            'time' => time(),
            'errors' => array(),
            'messages' => array()
        );

        // check for input from AngularJS
        $input = json_decode(file_get_contents("php://input"));
        if (empty($input)) {
            $data['errors'][] = array('content' => 'API did not receive any input');
        } else {
            /**
             * $input is expected to be a stdClass with properties:
             *  $input->command - string with the name of the command eg, load, save etc
             *  $input->adminData - object of key value pairs
             */
            if (!empty($input->command)) {

                // only save expected fields
                $expectedFields = self::getExpectedFields();

                switch ($input->command) {
                    case 'load':

                        $adminData = [];

                        if (!empty($input->adminData)) {
                            // loop through the fields, and load the setting, if the setting does not exist, then add the default value
                            foreach ($input->adminData as $key => $value) {
                                if (in_array($key, $expectedFields)) {
                                    // get value from the database
                                    $settingName = self::setDbSettingName($key);
                                    $adminData[$key] = get_option($settingName);
                                    if (false === $adminData[$key]) {
                                        add_option($settingName, $value);
                                        $adminData[$key] = $value;
                                    }
                                } else {
                                    $data['errors'][] = array('content' => 'Field ' . $key . ' is not in the list of expected fields');
                                }
                            }
                        } else {
                            $data['errors'][] = array('content' => 'Admin Data not sent from the form');
                        }

                        $data['adminData'] = $adminData;

                        break;
                    case 'save':

                        $adminData = [];
                        if (!empty($input->adminData)) {
                            foreach ($input->adminData as $key => $value) {
                                if (in_array($key, $expectedFields)) {
                                    // get value from the database
                                    $settingName = self::setDbSettingName($key);
                                    $adminData[$key] = get_option($settingName);
                                    // add or update as appropriate
                                    if (false === $adminData[$key]) {
                                        add_option($settingName, $value);
                                    } else {
                                        update_option($settingName, $value);
                                    }
                                    $adminData[$key] = $value;
                                } else {
                                    $data['errors'][] = array('content' => 'Field ' . $key . ' is not in the list of expected fields');
                                }
                            }
                        } else {
                            $data['errors'][] = array('content' => 'Admin Data not sent from the form');
                        }

                        $data['adminData'] = $adminData;
                        $data['messages'][] = array('content' => 'Data Saved @ ' . $time);

                        break;
                    default:
                        $data['errors'][] = array('content' => 'API is not configured for command : "' . $input->command . '"');
                }

            } else {
                $data['errors'][] = array('content' => 'API did not receive a command');
            }
            $data['input'] = $input;
        }

        // adding a header is correct, but has been known to break some shared hosts
        //header('Content-Type', 'application/json');
        echo json_encode($data);
        die();
    }

    private function nameSafe($name)
    {
        $key = preg_replace('/[^a-z0-9\- ]/i', '', trim($name));
        $key = str_replace(' ', '-', $key);
        return strtolower($key);
    }

    private function setDbSettingName($name)
    {
        return 'angularjs_admin_data_' . $this->nameSafe($name);
    }
}

$angularJsAdmin = new AngularJsEnabledAdmin();