<?php
/**
 * Class for API Calls
 * @package    dd_cf7_constant_contact_v3
 * @subpackage dd_cf7_constant_contact_v3/admin
 * @since    1.0.0
 */

class dd_ctct_api {

    private $api_url = 'https://api.cc.email/v3/';
    private $details = array('first_name' => '', 'last_name' => '', 'job_title' => '', 'comapny_name' => '', 'create_source' => '', 'birthday_month' => '', 'birthday_day' => '', 'anniversary' => '');
    private $street_address = array('kind' => '', 'street' => '', 'city' => '', 'state' => '', 'postal_code' => '', 'country' => '');

    public function __construct() {
        add_action('wpcf7_before_send_mail', array($this, 'cf7_process_form'), 10, 3);
        add_action('wpcf7_mail_sent', function ($cf7) {
            $this->push_to_constant_contact();
        });
        add_action('wpcf7_init', array($this, 'check_auth'));
    }

    private function get_api_key() {

        $options = get_option('cf7_ctct_settings');
        if (isset($options['access_token'])) {
            return $options['access_token'];
        } else {
            return null;
        }
    }

    public function check_auth() {
        // Make sure mailing lists are in place
        if (null == (get_option('dd_cf7_mailing_lists')) || get_option('dd_cf7_mailing_lists') == '1') {
            $options = get_option('cf7_ctct_settings');
            if (false !== $options && isset($options['access_token'])) $this->get_lists();
        }
    }

    public function get_admin_email() {
        $options = get_option('cf7_ctct_extra_settings');
        return esc_attr($options['admin_email']);
    }

    public function email_headers() {
        $website = parse_url(get_bloginfo('url'))['host'];
        $headers = array('Content-Type: text/html; charset=UTF-8');
        $headers[] = "From: " . get_bloginfo('name') . ' <wordpress@' . $website . '>' . PHP_EOL;
        return $headers;
    }

    public function wants_email() {
        $options = get_option('cf7_ctct_extra_settings');
        if (false == $options) $options = array('send_email' => 'true');
        // Set default value.
        $send = ($options['send_email'] == 'true') ? true : false;
        return $send;
    }

    public function cf7_process_form($contact_form, &$abort, $submission) {

        /**
         * Added Bot Detection and Rejection by auto submitted forms.
         */
        $submission = WPCF7_Submission::get_instance();
        if ($submission) {
            $posted_data = $submission->get_posted_data();
        }
        // If Bots add a submit field
        if (isset($posted_data['submit'])) {
            $abort = true;
            return false;
        }
        $submitted_values = $this->get_form_data();
        if (false !== $submitted_values) {
            set_transient('ctct_to_process', $submitted_values, 3 * MINUTE_IN_SECONDS);
        }
    }

    public function push_to_constant_contact($c = 1, $failed = null) {
        if (null !== $failed) {
            $submitted_values = $failed;
        } else {
            if (false === ($submitted_values = get_transient('ctct_to_process'))) {
                return false;
            }
            $submitted_values = maybe_unserialize(get_transient('ctct_to_process'));
        }

        // Check if E-Mail Address is valid

        if (!isset($submitted_values['email_address']) || empty($submitted_values['email_address'])) return false;

        $email = sanitize_email($submitted_values['email_address']);

        $valid_email = $this->validate_email($email);

        if (false == $valid_email) {
            $website = get_bloginfo('name');
            $body = "<p>The following is from a user who attempted to enter in an invalid domain name on Contact Form ID {$submitted_values['formid']} at your website {$website}</p>";
            ob_start();
            echo '<pre>';
            print_r($submitted_values);
            echo '</pre>';
            $body .= ob_get_clean();
            if ($this->wants_email()) wp_mail($this->get_admin_email(), 'Constant Contact API Error', $body, $this->email_headers());
            return false;
        }

        $exists = $this->check_email_exists($submitted_values['email_address']);
        $tname = 'ctct_process_failure_' . time();
        if ($exists == 'unauthorized') {
            if ($c > 2) {
                set_transient($tname, $submitted_values, 5 * DAY_IN_SECONDS);
                return false;
            }
            $options = get_option('cf7_ctct_settings');
            if (isset($options['refresh_token'])) {
                dd_cf7_ctct_admin_settings::refreshToken($c);
                $this->push_to_constant_contact($c + 1);
            } else {
                $website = get_bloginfo('name');
                $body = "<p>While Attempting to connect to Constant Contact from Contact Form ID {$submitted_values['formid']}, an error was encountered. This is a fatal error, and you will need to revisit the Constant Contact settings page and re-authorize the application on your website {$website}.</p>";
                if ($this->wants_email()) wp_mail($this->get_admin_email(), 'Constant Contact API Error', $body, $this->email_headers());
                set_transient($tname, $submitted_values, 5 * DAY_IN_SECONDS);
                return false;
            }
        } elseif (false == $exists) {
            $ctct = $this->create_new_subscription($submitted_values);
        } elseif ($exists == 'connection_error') {
            set_transient($tname, $submitted_values, 5 * DAY_IN_SECONDS);
            return false;
        } else {
            $ctct = $this->update_contact($submitted_values, $exists);
        }

        // If API Call Failed

        if (isset($ctct)) {
            if (true !== $ctct['success']) {
                ob_start();
                echo 'Message from: ' . get_bloginfo('name') . '<br><br>';
                echo "{$ctct['message']}\r\n\r\n";
                echo '<pre>';
                print_r($submitted_values);
                echo '</pre>';
                $body = ob_get_clean();
                if ($this->wants_email()) wp_mail($this->get_admin_email(), 'Constant Contact API Error', $body, $this->email_headers());
                return false;
            }
        }
        return true;
    }

    public function get_form_data() {
        $submitted_values = array();

        $submission = WPCF7_Submission::get_instance(); 
        if ($submission) {
            $posted_data = $submission->get_posted_data();
        }

        $settings = get_post_meta($submission->get_contact_form()->id(), '_ctct_cf7', true);

        /**
         * Check to see if the checkbox option is used or not
         *
         * @since    1.0.0
         */
        if (isset($settings['ignore-form'])) {
            $ctct_list = array();
            foreach ($posted_data as $key => $value) {
                if ($key == 'ctct-list') {
                    foreach ($value as $listid) {
                        $ctct_list[] = $listid;
                    }
                }
            }
            if (!isset($posted_data['ctct-list-optin'])) {
                return false;
            }
            if (!empty($ctct_list)) {
                $submitted_values['chosen-lists'] = $ctct_list;
            } else {
                // if no checkbox is checked, return.
                return false;
            }

        } else {
            $submitted_values['chosen-lists'] = $settings['chosen-lists'];
        }
        foreach ($settings['fields'] as $field => $value) {
            if (array_key_exists($field, $posted_data)) {
                // Remove Empty Fields
                // Sanitize and remove accents
                if (!empty($posted_data[$field])) {
                    $data = sanitize_text_field($posted_data[$field]);
                    $data = remove_accents($data);
                    $submitted_values[$value[0]] = $data;
                }
            }
        }
        // ADD Form ID for Error Reporting
        $submitted_values['formid'] = $submission->get_contact_form()->id();

        return $submitted_values;
    }

    // Retrieve Lists
    public function get_lists() {

        $url = "https://api.cc.email/v3/contact_lists";

        $args = array(
            "headers" => array(
                "Accept" => "*/*",
                "Accept-Encoding" => "gzip, deflate",
                "Authorization" => "Bearer {$this->get_api_key()}",
                "Content-Type" => "application/json",
            )
        );

        $response = wp_remote_get($url, $args);
        $ctct = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);

        if ($code !== 200) {
            $website = get_bloginfo('name');
            $body = "While attempting to retrieve the constant contact lists on {$website}. \r\n";
            $body .= "Error #:" . $code . "\r\n";
            $body .= $ctct['error_message'];
            error_log($body);
            return false;
        } else {
            $lists_array = array();
            foreach ($ctct['lists'] as $list) {
                $lists_array[$list['list_id']] = $list['name'];
            }
            update_option('dd_cf7_mailing_lists', $lists_array);
            return true;
        }
    }

    public function check_email_exists($email) {

        $url = $this->api_url . "contacts?email=" . urlencode($email) . "&include=street_addresses,list_memberships&include_count=true";

        $args = array(
            "headers" => array(
                "Accept" => "*/*",
                "Accept-Encoding" => "gzip, deflate",
                "Authorization" => "Bearer {$this->get_api_key()}",
                "Content-Type" => "application/json",
            )
        );

        $response = wp_remote_get($url, $args);
        $ctct = json_decode(wp_remote_retrieve_body($response));
        $code = wp_remote_retrieve_response_code($response);
        if (empty($code) || ($code == 500)) {
            return 'connection_error';
        }
        if ($code !== 200) {
            if ($code == 401) {
                return 'unauthorized';
            } else {
                return false;
            }
        } else {
            if ($ctct->contacts_count == 0) {
                return false;
            } else {
                return $ctct;
            }
        }
    }

    public function create_new_subscription($submitted_values) {
        $return = array();
        if (empty($submitted_values)) $return['success'] = false;
        $names = $this->create_new_contact_array($this->details, $submitted_values);
        $address = $this->create_new_contact_array($this->street_address, $submitted_values);

        $chosen_lists = array();
        if (isset($submitted_values['chosen-lists'])) {
            foreach ($submitted_values['chosen-lists'] as $list) {
                if ($list == '') continue;
                $chosen_lists[] = $list;
            }
        } else {
            $return['success'] = false;
            return $return;
        }

        $json_data = array_merge($names, array(
                "email_address" => array(
                    "address" => $submitted_values['email_address'],
                    "permission_to_send" => "explicit",
                ),
                "create_source" => "Contact",
                "street_addresses" => array(array_filter($address)),
                "list_memberships" => $chosen_lists,
            )
        );

        $content_length = strlen(json_encode($json_data));

        /**
         * Prepare the API Call Initiate CURL
         *
         * @since    1.0.0
         */
        $url = "{$this->api_url}contacts";

        $args = array(
            "headers" => array(
                "Accept" => "*/*",
                "Accept-Encoding" => "gzip, deflate",
                "Authorization" => "Bearer {$this->get_api_key()}",
                "Content-Type" => "application/json",
                "Content-Length" => $content_length,
            ),
            "body" => json_encode($json_data),
        );

        $response = wp_remote_post($url, $args);
        $code = wp_remote_retrieve_response_code($response);
        $message = json_decode(wp_remote_retrieve_body($response));
        if (empty($code)) $code = 503;

        if ($code !== 201) {
            if ($code == 409) {
                $this->trigger_unsubscribed_email($submitted_values);
            } else {
                ob_start();
                echo "<p>While trying to add a new email address, there was an error</p>";
                echo "<p>The error code was {$code}</p>";
                echo "<p>Message from Constant Contact: {$message[0]->error_message}</p>";
                echo "<p>This was submitted through FormID: {$submitted_values['formid']}</p>";
                $body = ob_get_clean();
                $return['success'] = false;
                $return['message'] = $body;
                return $return;
            }
        } else {
            $return['success'] = true;
            return $return;
        }

        return $return;

    }

    private function create_new_contact_array($item, $submitted_values) {
        /**
         * @param $item = array of personal details or address
         * @param $submitted_values = form submission
         *
         * @since    1.0.0
         */
        foreach ($item as $key => $val) {
            if (isset($submitted_values[$key])) {
                $item[$key] = $submitted_values[$key];
            } elseif ($key == 'kind') {
                $item[$key] = "home";
            } else {
                unset($item[$key]);
            }
        }
        return $item;
    }

    public function update_contact($submitted_values, $ctct_data) {
        /**
         * Retrieve Transients from Form Submission
         *
         * @param $submitted_values = Form Data from CF7
         * @param $ctct_data = response from CTCT with Contact info
         * @since    1.0.0
         */
        $return = array();
        $ctct = $ctct_data->contacts[0];
        $ctct_addr = $ctct->street_addresses[0];

        // Merge List Memberships
        /**
         * ToDo Make checkbox to set form option to add or remove
         *
         * @since    1.0.0
         */
        //$list_memberships = array_unique( array_merge( $ctct->list_memberships, $submitted_values[ 'chosen-lists' ] ) );
        $lists = array();
        foreach ($submitted_values['chosen-lists'] as $key => $value) {
            if ($value == '') continue;
            $lists[] = $value;
        }

        $deets = $this->build_ctct_array($ctct, $this->details, $submitted_values);
        $sa = $this->build_ctct_array($ctct_addr, $this->street_address, $submitted_values);
        // Build JSON Array for Put on CTCT
        $json_data = array_merge($deets, array(
                "email_address" => array(
                    "address" => "{$submitted_values['email_address']}",
                ),
                "street_addresses" => array(array_filter($sa)),
                "list_memberships" => $lists,
                "update_source" => "Contact",
            )
        );

        $contact_id = $ctct_data->contacts[0]->contact_id;

        $content_length = strlen(json_encode($json_data));

        $url = "{$this->api_url}contacts/{$contact_id}";

        //error_log(json_encode($json_data));

        $args = array(
            "headers" => array(
                "Accept" => "*/*",
                "Accept-Encoding" => "gzip, deflate",
                "Authorization" => "Bearer {$this->get_api_key()}",
                "Content-Type" => "application/json",
                "Content-Length" => $content_length,
            ),
            "body" => json_encode($json_data),
            "method" => "PUT",
        );

        $response = wp_remote_request($url, $args);
        $code = wp_remote_retrieve_response_code($response);
        $message = json_decode(wp_remote_retrieve_body($response));

        if ( 500 == $code ) {
            $tname = 'ctct_process_failure_' . time();
            set_transient($tname, $submitted_values, 5 * DAY_IN_SECONDS);
            $return['success'] = true;
            return $return;
        } elseif ($code !== 200) {
            if (strpos($message[0]->error_message, 'unsubscribed') !== false) {
                $this->trigger_unsubscribed_email($submitted_values);
                $return['success'] = true;
                return $return;
            }
            $body = "While trying to update an existing contact, there was an error \r\n";
            $body .= "Error #:" . $code . "\r\n";
            $body .= "The Message from Constant Contact was: {$message[0]->error_message}\r\n";
            $body .= "This was submitted through FormID: {$submitted_values['formid']} \r\n";
            $return['success'] = false;
            $return['message'] = $body;
            return $return;
        } else {
            $return['success'] = true;
            return $return;
        }
    }

    public function build_ctct_array($ctct, $item, $submitted_values) {
        /**
         * @param $ctct = fields from ctct api object
         * @param $item = array of fields being submitted to ctct - details or addresses
         * @param $submitted_values = cf7 form field submissions from transient
         * @since    1.0.0
         */

        foreach ($item as $key => $val) {
            if (isset($ctct->$key)) {
                if ((isset($submitted_values[$key]) && $submitted_values[$key] == $ctct->$key) || !isset($submitted_values[$key])) {
                    $item[$key] = $ctct->$key;
                } else {
                    $item[$key] = ($submitted_values[$key]);
                }
            } else {
                if (isset($submitted_values[$key])) {
                    $item[$key] = ($submitted_values[$key]);
                } else {
                    if ($key == 'kind' && !isset($ctct->key)) {
                        $item['kind'] = 'home';
                    } else {
                        unset($item[$key]);
                    }
                }
            }
        }

        return $item;
    }

    /**
     * Validate the MX Record Exists
     *
     * @since    1.0.0
     */
    private function validate_email($email) {
        $domain = substr($email, strpos($email, '@') + 1);
        if (checkdnsrr($domain, "MX")) {
            return true;
        } else {
            return false;
        }
    }

    public function retry_from_failed() {
        global $wpdb;
        $table = "{$wpdb->prefix}options";
        $query = $wpdb->get_results("SELECT * from `{$table}` WHERE `option_name` LIKE '%_transient_ctct_process_failure%';");

        foreach ($query as $t) {
            $submitted_values = maybe_unserialize($t->option_value);
            $retry = $this->push_to_constant_contact(1, $submitted_values);
            $id = $t->option_id;
            $wpdb->delete($table, array('option_id' => $id));
            if ($retry !== true) {
                $tname = 'ctct_process_failure_' . time();
                set_transient($tname, $submitted_values, 5 * DAY_IN_SECONDS);
            }
        }
    }

    public function trigger_unsubscribed_email($submitted_values) {

        $options = get_option('dd_cf7_optin_email_settings');
        if (!isset($options['ctct_re_optin_form_url'])) return;
        $body = wp_kses_post($options['ctct_resubscribe_email_text']);

        $body = str_replace('{first_name}', $submitted_values['first_name'], $body);
        $body = str_replace('{first_name}', $submitted_values['first_name'], $body);
        $body = str_replace('{email}', $submitted_values['email_address'], $body);
        $body = str_replace('{form_url}', $options['ctct_re_optin_form_url'], $body);
        $body = str_replace('{blog_name}', get_bloginfo('name'), $body);

        $to = esc_attr($submitted_values['email_address']);
        $subject = $options['ctct_re_optin_form_subject'];
        $headers = array('Content-Type: text/html; charset=UTF-8');

        wp_mail($to, $subject, $body, $headers);

        return;
    }
}
