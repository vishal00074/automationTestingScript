<?php

/**
 * Source code of GMI Chrome Remotely
 *
 * @package      uwa
 *
 * @copyright    GetMyInvoices
 */

// Need to use Web socket lib
$websocket_lib = realpath(KERNEL_ROOT . 'includes/libs/autoload.php');
require_once $websocket_lib;

use WebSocket\Client;

class GmiChromeManager
{
    private $mode;

    public $webdriver = false;
    private $profile;
    public $portal_name;
    public $config_array;
    public $is_manual = false;
    public $browser_crashed = false;
    public $log_file;
    public $page_strategy;

    private $username;
    private $password;
    public $process_uid;
    public $screen_capture_location;
    public $notification_uid;
    private $downloaded_files = [];
    public $download_folder = '';
    public $no_margin_pdf = 0;
    public $document_counter = 0;
    public $docker_need_restart = false;
    public $docker_restart_counter = 0;
    public $checked_documents = [];
    public $recaptcha_answer = '';
    public $send_request_limit = 60;

    // 2FA variables
    public $two_factor_notif_title_en = "%portal% - Two-Factor-Authorization";
    public $two_factor_notif_title_de = "%portal% - Zwei-Faktor-Anmeldung";
    public $two_factor_notif_msg_en = "Please enter the two-factor-authorization code to proceed with the login.";
    public $two_factor_notif_msg_de = "Bitte geben Sie den Code zur Zwei-Faktor-Anmeldung ein.";
    private $default_two_factor_notif_msg_en = "Please enter the two-factor-authorization code to proceed with the login.";
    private $default_two_factor_notif_msg_de = "Bitte geben Sie den Code zur Zwei-Faktor-Anmeldung ein.";
    public $two_factor_notif_msg_retry_en = " (Your last input was either wrong or too late)";
    public $two_factor_notif_msg_retry_de = " (Ihre letzte Eingabe war leider falsch oder zu spät)";
    public $two_factor_timeout = 15;
    public $two_factor_attempts = 0;

    /** @var Redis */
    public $redis = null;

    // Variables for printing
    public $request_start = '===REQUEST===';
    public $login_failed = 'LOGIN_FAILED';
    public $init_diagnostics_failed = 'INIT_DIAGNOSTICS_FAILED';
    public $driver_creation_failed = 'DRIVER_CREATION_FAILED';
    public $profile_creation_failed = 'PROFILE_CREATION_FAILED';
    public $portal_success = 'PORTAL_SUCCESS';
    public $portal_failed = 'PORTAL_FAILED';
    public $cookies_dump = '===COOKIE_JSON===';

    // Language dependent month name
    public $month_names_de = [
        'Januar',
        'Februar',
        'März',
        'April',
        'Mai',
        'Juni',
        'Juli',
        'August',
        'September',
        'Oktober',
        'November',
        'Dezember',
    ];
    public $month_names_en = [
        'January',
        'February',
        'March',
        'April',
        'May',
        'June',
        'July',
        'August',
        'September',
        'October',
        'November',
        'December',
    ];
    public $month_names_fr = [
        'janvier',
        'février',
        'mars',
        'avril',
        'mai',
        'juin',
        'juillet',
        'août',
        'septembre',
        'octobre',
        'novembre',
        'décembre',
    ];
    public $month_names_nl = [
        'januari',
        'februari',
        'maart',
        'april',
        'mei',
        'juni',
        'juli',
        'augustus',
        'september',
        'oktober',
        'november',
        'december',
    ];
    public $month_names_es = [
        'enero',
        'febrero',
        'marzo',
        'abril',
        'mayo',
        'junio',
        'julio',
        'agosto',
        'septiembre',
        'octubre',
        'noviembre',
        'diciembre',
    ];

    // Language dependent month abbreviation
    public $month_abbr_de = ['Jan', 'Feb', 'Mrz', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
    public $month_abbr_en = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    public $month_abbr_fr = [
        'janv',
        'févr',
        'mars',
        'avril',
        'mai',
        'juin',
        'juil',
        'août',
        'sept',
        'oct',
        'nov',
        'déc',
    ];
    public $month_abbr_nl = ['jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];

    /**
     * Constructor. Initialize different member data
     *
     * @param string $mode
     * @param string $portal_name
     * @param int    $process_uid
     * @param string $username
     * @param string $password
     *
     * @return    void
     */
    public function init($mode, $portal_name, $process_uid, $username, $password, $debug_mode = false)
    {
        $this->loadProperties();

        $this->log('>> Init GMI Constructor With : ' . $this->config_array['remote_url']);
        $this->portal_name = $portal_name;
        $this->process_uid = $process_uid;
        $this->username = $username;
        $this->password = $password;
        $this->notification_uid = 0;
        $this->two_factor_attempts = 1;

        $this->user_data_dir = "/home/seluser/profiles/" . $this->process_uid;
        if (!empty($this->config_array['user_data_dir'])) {
            $this->user_data_dir = $this->config_array['user_data_dir'];
        }
        $this->node_name = !empty($this->config_array['node_name']) ? $this->config_array['node_name'] : "selenium-node-" . $this->process_uid;
        // grant permission download folder
        exec("sudo docker exec -i --user root " . $this->node_name . " sh -c 'sudo chmod -R 777 /home/seluser/Downloads/'");
        exec("sudo docker exec -i --user root " . $this->node_name . " sh -c 'sudo chown -R seluser /home/seluser/Downloads/'");

        if ($debug_mode) {
            $this->log('In debug mode');
        } else {
            $this->start_chrome();
            $this->make_connection();

            if (!$this->is_manual) {
                $this->initDiagnostics();
            }
        }
    }

    /**
     * Reconnect opened browser
     *
     * @param empty
     *
     * @return    void
     */
    public function reconnect($sessionId = '')
    {
        $this->loadProperties();
        $this->node_name = !empty($this->config_array['node_name']) ? $this->config_array['node_name'] : "selenium-node-" . $this->process_uid;
        $this->user_data_dir = "/home/seluser/profiles/" . $this->process_uid;
        if (!empty($this->config_array['user_data_dir'])) {
            $this->user_data_dir = $this->config_array['user_data_dir'];
        }
        $this->make_connection();
    }

    /**
     * Loads properties from config.json file from the $screen_capture_location provided at runtime
     */
    private function loadProperties()
    {
        $property_file = $this->screen_capture_location . 'config.json';
        if (file_exists($property_file)) {
            $this->config_array = json_decode(file_get_contents($property_file), true);
            $this->log('Loaded Config : ' . print_r($this->config_array, true));

            if (!empty($this->config_array['download_folder']) && !str_ends_with($this->config_array['download_folder'], '/')) {
                $this->config_array['download_folder'] .= '/';
            }
            $this->remote_url = $this->config_array['remote_url'];
        } else {
            $this->log($property_file . ' - Not found');
        }

        $this->setupRedisConnection();
    }

    private function setupRedisConnection(): void
    {
        $redis_conf_file = $this->screen_capture_location . 'redis_conf.json';
        if (file_exists($redis_conf_file)) {
            $this->log('Connecting Redis');
            $connection_params = json_decode(file_get_contents($redis_conf_file), true);
            $this->redis = new Redis();

            if (!isset($connection_params['auth'])) {
                $this->redis->pconnect($connection_params['host'], $connection_params['port']);
            } else {
                $this->redis->pconnect(
                    $connection_params['host'],
                    $connection_params['port'],
                    $connection_params['timeout'],
                    '',
                    0,
                    0,
                    [
                        'auth' => [
                            $connection_params['auth']['username'],
                            $connection_params['auth']['password'],
                        ],
                    ]
                );
            }

            if (!empty($connection_params['prefix'])) {
                $this->redis->setOption(Redis::OPT_PREFIX, $connection_params['prefix']);
            }
        }
    }

    /**
     * Fetch config data for key in config.json
     */
    public function getConfig($configKey): string
    {
        $this->loadProperties();

        return $this->config_array[$configKey] ?? '';
    }

    /**
     * Restart docker and use last updated cookies
     *
     * @return    void
     */
    public function restart()
    {
        if ($this->docker_restart_counter < 10) {
            $this->log('Restarting docker - ' . $this->docker_restart_counter);

            try {
                // Restart node
                $this->node_name = !empty($this->config_array['node_name']) ? $this->config_array['node_name'] : "selenium-node-" . $this->process_uid;
                exec("docker restart " . $this->node_name);
                $this->start_chrome();
                $this->make_connection();

                $this->docker_restart_counter++;
            } catch (\Exception $exception) {
                $this->log('Restart failed - ' . $exception->getMessage());
                $this->exitFinal();
            }
        } else {
            $this->log('Restart limit reached');
            $this->exitFinal();
        }
    }

    public function profile_loaded()
    {
        $flag_file = $this->config_array['process_folder'] . '.profile_loaded';
        $remote_chrome_cookie_database = $this->config_array['process_folder'] . 'screens/Cookies';

        return file_exists($flag_file) || file_exists($remote_chrome_cookie_database);
    }

    /**
     * Load cookies json from file
     *
     * @param bool $force_cookie_load
     *
     * @return    bool
     */
    public function loadCookiesFromFile($force_cookie_load = false)
    {
        // Load cookies only if profile is not loaded or asked to load forcefully
        if ($this->profile_loaded() && !$force_cookie_load) {
            $this->log("loadCookiesFromFile: profile loaded - skip cookie.txt");

            return true;
        }

        try {
            $cookie_file = $this->screen_capture_location . "cookie.txt";
            if (file_exists($cookie_file)) {
                $cookies_from_file = json_decode(file_get_contents($cookie_file));
                $this->log('loadCookiesFromFile::Loading Cookies From File');
                if (!empty($cookies_from_file)) {
                    $this->send_websocket_request($this->current_context->webSocketDebuggerUrl, 'Network.setCookies', ['cookies' => $cookies_from_file]);

                    return true;
                } else {
                    $this->log('loadCookiesFromFile::Cookies not found');
                }
            } else {
                $this->log('loadCookiesFromFile::Cookies file not found');
            }
        } catch (\Exception $exception) {
            $this->log('loadCookiesFromFile::ERROR in loadCookiesFromFile ');
        }

        return false;
    }

    /**
     * Get all the cookies for the current domain and print it in logs with COOKIES_DUMP Header
     *
     * @param bool $only_save
     * @param null|string $list_urls
     *
     * @return    void
     */
    public function dumpCookies($only_save = false, $list_urls = null)
    {
        $cookie_txt = null;

        try {
            // urls array[ string ]
            // The list of URLs for which applicable cookies will be fetched. If not specified, it's assumed to be set to the list containing the URLs of the page and all of its subframes.
            $param = null;
            if (!empty($list_urls) && count($list_urls) > 0) {
                $param = ['urls' => $list_urls];
            }

            $response_text = $this->send_websocket_request($this->current_context->webSocketDebuggerUrl, 'Network.getCookies', $param);
            $result = json_decode($response_text);
            $cookieArray = $result->result->cookies;
            if (!$only_save) {
                //echo '' . $this->cookies_dump . json_encode($cookieArray) . "\n";
            }
            file_put_contents($this->screen_capture_location . "cookie.txt", json_encode($cookieArray));
        } catch (\Exception $exception) {
            $this->log('ERROR in dumpCookies - ' . $exception->getMessage());
        }
    }

    /**
     * Get all the cookies for the current domain.
     *
     * @return mixed The array of cookies present.
     */
    public function getCookies()
    {
        try {
            $response_text = $this->send_websocket_request($this->current_context->webSocketDebuggerUrl, 'Network.getCookies');
            $result = json_decode($response_text);
            $cookieArray = $result->result->cookies;

            return $cookieArray;
        } catch (Exception $e) {
            $this->log('ERROR in getCookies - ' . $e->getMessage());
        }
    }

    /**
     * Delete all the cookies that are currently visible.
     */
    public function clearCookies()
    {
        try {
            $this->send_websocket_request($this->current_context->webSocketDebuggerUrl, 'Network.clearBrowserCookies');
        } catch (Exception $e) {
            $this->log('ERROR in clearCookies - ' . $e->getMessage());
        }
    }

    /**
     * Method to check if web driver is created for the browser successfully.
     * uses test url to load a json and check if URL is loading properly in the new webdriver instance
     * This method should be called only after profile and capabilities are created
     *
     * @return void
     */
    private function initDiagnostics()
    {
        $this->log('Begin initDiagnostics');

        try {
            $this->openUrl('http://lumtest.com/myip.json');

            $this->log('>>Content of webpage - ' . $this->extract('body', null, 'innerText'));
            $this->log('>>URI of webpage is: ' . $this->getUrl());

            // If Worker ping url is set, use it check configured language
            if (!empty($this->config_array['worker_ping']) && 1 == 2) { // Disable for now, due to ssl error
                $this->openUrl($this->config_array['worker_ping']);

                $this->log('>>Content of webpage - ' . $this->extract('body', null, 'innerText'));
            }

            $this->log('>> Diagnostics Successful!!!');
        } catch (\Exception $exception) {
            $this->log('ERROR in initDiagnostics');
            $this->sendRequestEx($this->init_diagnostics_failed);
            $this->log(print_r($exception, true));
        }
    }

    public function refreshProxyIp()
    {
        if (!empty($this->config_array['proxy_host']) && !empty($this->config_array['proxy_lpm_port'])) {
            exec('curl -X POST "http://' . $this->config_array['proxy_host'] . ':22999/api/refresh_sessions/' . $this->config_array['proxy_lpm_port'] . '"');
            sleep(5);
        }
    }

    /**
     * Send request to notify login success
     *
     * @return    void
     */
    public function triggerLoginSuccess()
    {
        $this->log("--Trigger Login Success--");

        $cmd = $this->config_array['login_success_shell_script'] . " --PROCESS_UID::" . $this->process_uid;
        $this->log($cmd);
        exec($cmd);

        $this->sendRequestEx(
            json_encode([
                'method' => 'LoginSuccess',
            ])
        );
    }

    /**
     * Send request to UI and fetch two factor code
     *
     * @return    string
     */
    public function fetchTwoFactorCode()
    {
        $this->log("--Fetching Two Factor Code--");
        $this->capture("TwoFactorFetchCode");
        if (!$this->two_factor_notif_msg_en || trim($this->two_factor_notif_msg_en) == "") {
            $this->two_factor_notif_msg_en = $this->default_two_factor_notif_msg_en;
        }
        if (!$this->two_factor_notif_msg_de || trim($this->two_factor_notif_msg_de) == "") {
            $this->two_factor_notif_msg_de = $this->default_two_factor_notif_msg_de;
        }
        $extra_data = [
            "en_title" => $this->two_factor_notif_title_en,
            "en_msg" => $this->two_factor_notif_msg_en,
            "de_title" => $this->two_factor_notif_title_de,
            "de_msg" => $this->two_factor_notif_msg_de,
            "timeout" => $this->two_factor_timeout,
            "retry_msg_en" => $this->two_factor_notif_msg_retry_en,
            "retry_msg_de" => $this->two_factor_notif_msg_retry_de,
        ];

        $two_factor_code = $this->sendRequest($this->process_uid, $this->config_array['two_factor_shell_script'], $extra_data, 0, (bool) $this->redis);
        $this->log($two_factor_code);

        return $two_factor_code;
    }

    /**
     * Method to process google recaptcha
     * It will send a request to 2captcha.com using a batch file or shell script and fetch the answer
     *
     * @param string $base_url    Base url of site in which recaptcha need to solve
     * @param string $google_key  Google recaptcha key in portal
     * @param bool   $fill_answer Fill response in dom
     *
     * @return    bool
     */
    public function processRecaptcha($base_url, $google_key = '', $fill_answer = false)
    {
        $this->recaptcha_answer = '';
        // if (empty($google_key)) {
        //     /* @var WebDriverElement $element */
        //     $element = $this->getElementByCssSelector(".g-recaptcha");
        //     $google_key = $this->getElAttribute($element, 'data-sitekey');
        // }

        $this->log("--Google Re-Captcha--");
        if (!empty($this->config_array['recaptcha_shell_script'])) {
            $cmd = $this->config_array['recaptcha_shell_script'] . " --PROCESS_UID::" . $this->process_uid . " --GOOGLE_KEY::" . urlencode($google_key) . " --BASE_URL::" . urlencode($base_url);
            $this->log('Executing command : ' . $cmd);
            exec($cmd, $output, $return_var);
            $this->log('Command Result : ' . print_r($output, true));

            if (!empty($output)) {
                $recaptcha_answer = '';
                foreach ($output as $line) {
                    if (stripos($line, "RECAPTCHA_ANSWER") !== false) {
                        $result_codes = explode("RECAPTCHA_ANSWER:", $line);
                        $recaptcha_answer = $result_codes[1];
                        break;
                    }
                }

                if (!empty($recaptcha_answer)) {
                    if ($fill_answer) {
                        $answer_filled = $this->execute_javascript(
                            "document.getElementById(\"g-recaptcha-response\").innerHTML = arguments[0];return document.getElementById(\"g-recaptcha-response\").innerHTML;",
                            [$recaptcha_answer]
                        );
                        $this->log("recaptcha answer filled - " . $answer_filled);
                    }

                    $this->recaptcha_answer = $recaptcha_answer;

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Method to process rotate image captcha type
     * It will send a request to 2captcha.com using a batch file or shell script and fetch the answer
     *
     * @param string $captcha_image_selector    Selector to the image to rotate or its wrapper
     * @param string $clockwise_handler         Button selector to click to rotate image clockwise
     * @param string $counter_clockwise_handler Button selector to click to rotate image counter clockwise
     * @param string $continue_btn_selector     Button to continue after solving captcha
     *
     * @return    number|bool  $angle                      Value for angle rotated per click on handler. Default 40
     */
    public function processRotateCaptcha(
        $captcha_image_selector,
        $clockwise_handler,
        $counter_clockwise_handler,
        $continue_btn_selector = null,
        $angle = 40
    ) {
        $this->log("--ROTATE CAPTCHA-");

        try {
            $this->capture("RotateImageCaptcha");
        } catch (\Exception $exception) {
            $this->log('1:processRotateCaptcha::ERROR while taking snapshot');
        }

        if ($this->exists($captcha_image_selector)) {
            try {
                $this->captureElement($this->process_uid, $captcha_image_selector);

                if (!empty($this->config_array['rotate_captcha_shell_script'])) {
                    $cmd = $this->config_array['rotate_captcha_shell_script'] . " --PROCESS_UID::" . $this->process_uid . " --ANGLE::" . urlencode($angle);
                    $this->log('Executing command : ' . $cmd);
                    exec($cmd, $output, $return_var);
                    $this->log('Command Result : ' . print_r($output, true));

                    if (!empty($output)) {
                        $array1 = explode("RECAPTCHA_ANSWER::", $output[0]);
                        $array = explode("result angle:", end($array1));
                        $returnedAngle = (int) end($array);
                        $this->log("Returned captcha angle to rotate: " . $returnedAngle);
                        sleep(5);

                        $handlerToClick = $returnedAngle > 0 ? $clockwise_handler : $counter_clockwise_handler;
                        $returnedAngle = abs($returnedAngle);
                        while ($returnedAngle >= $angle) {
                            $this->moveToElementAndClick($handlerToClick);
                            $returnedAngle -= $angle;
                            sleep(3);
                        }

                        if ($continue_btn_selector != null && $this->exists($continue_btn_selector)) {
                            $this->moveToElementAndClick($continue_btn_selector);
                        }

                        return true;
                    } else {
                        $this->log("Cannot get captcha result from services!!!!!!!!");
                    }
                }
            } catch (\Exception $exception) {
                $this->log('2:processRotateCaptcha::ERROR while processing captcha');
            }
        }

        return false;
    }

    /**
     * Method to process geetest captcha
     * It will send a request to 2captcha.com using a batch file or shell script and fetch the answer
     *
     * @param string $formSelector Hidden Geetest form that need to submit the challenge
     * @param string $key          Geetest Page public key
     * @param string $challenge    Challenge key
     * @param string $api_server   API server if any
     * @param string $page_url     Full url of page that showing captcha
     * @param bool   $submit       Submit form on demand, default as true
     *
     * @return    bool
     */
    public function processGeeTestCaptcha(
        $formSelector,
        $key,
        $challenge,
        $page_url,
        $api_server = null,
        $submit = true
    ) {
        $this->log("--GEETEST CAPTCHA- " . $formSelector);

        try {
            $this->capture("GeeTest Captcha");
        } catch (\Exception $exception) {
            $this->log('1:processGeeTestCaptcha::ERROR while taking snapshot');
        }

        try {
            if (!empty($this->config_array['geetest_captcha_shell_script'])) {
                $cmd = $this->config_array['geetest_captcha_shell_script'] . " --PROCESS_UID::" . $this->process_uid . " --GT_KEY::" . $key . " --GT_CHALLENGE::" . $challenge . " --PAGE_URL::" . urlencode(
                    $page_url
                ) . " --GT_API_SERVER::" . urlencode($api_server);
                $this->log('Executing command : ' . $cmd);
                exec($cmd, $output, $return_var);
                $this->log('Command Result : ' . print_r($output, true));

                if (!empty($output)) {
                    $jsonRes = explode("OK|", $output[0])[1];
                    $this->log($jsonRes);
                    $result = json_decode($jsonRes);
                    $this->moveToElementAndType($formSelector . " input[name='geetest_challenge']", $result->{'geetest_challenge'});
                    sleep(3);
                    $this->moveToElementAndType($formSelector . " input[name='geetest_validate']", $result->{'geetest_validate'});
                    sleep(3);
                    $this->moveToElementAndType($formSelector . " input[name='geetest_seccode']", $result->{'geetest_seccode'});
                    sleep(3);

                    if ($submit) {
                        $form = $this->querySelector($formSelector);
                        $this->execute_javascript('arguments[0].submit();', [$formSelector]);
                        sleep(5);
                    }

                    return $jsonRes;
                } else {
                    $this->log("Cannot get catpcha result from services!!!!!!!!");
                }
            }
        } catch (\Exception $exception) {
            $this->log('2:processGeeTestCaptcha::ERROR while processing captcha');
        }

        return false;
    }

    /**
     * Method to process fun captcha by token
     * It will send a request to 2captcha.com using a batch file or shell script and fetch the answer
     *
     * @param string $formSelector   Login form that need to submit with the challenge
     * @param mixed  $inputSelectors Inputs to set returned value from 2captcha service
     * @param string $pkKey          PK key
     * @param string $surl           surl param
     * @param string $pageUrl        Full page url
     * @param bool   $submit         Submit the form on request
     *
     * @return    string                    Captcha result
     */
    public function processFunCaptcha($formSelector, $inputSelectors, $pkKey, $surl, $pageUrl, $submit = true)
    {
        $this->log("--FUN CAPTCHA- " . $formSelector);

        try {
            $this->capture("Fun Captcha");
        } catch (\Exception $exception) {
            $this->log('1:processFunCaptcha::ERROR while taking snapshot');
        }

        try {
            if (!empty($this->config_array['fun_captcha_shell_script'])) {
                $cmd = $this->config_array['fun_captcha_shell_script'] . " --PROCESS_UID::" . $this->process_uid . " --PK_KEY::" . $pkKey . " --SURL::" . urlencode($surl) . " --PAGE_URL::" . urlencode($pageUrl);
                $this->log('Executing command : ' . $cmd);
                exec($cmd, $output, $return_var);
                $this->log('Command Result : ' . print_r($output, true));

                if (!empty($output)) {
                    $jsonRes = explode("OK|", $output[0])[1];
                    $this->log("text: " . $jsonRes);

                    if ($formSelector != null) {
                        foreach ($inputSelectors as $key => $input) {
                            $this->moveToElementAndType($input, $jsonRes);
                            sleep(3);
                        }

                        if ($submit) {
                            $this->execute_javascript('arguments[0].submit();', [$formSelector]);
                            sleep(5);
                        }
                    }

                    return $jsonRes;
                } else {
                    $this->log("Cannot get catpcha result from services!!!!!!!!");
                }
            }
        } catch (\Exception $exception) {
            $this->log('2:processFunCaptcha::ERROR while processing captcha');
        }

        return null;
    }

    public function processHumanCaptcha($sitekey, $page_url)
    {
        try {
            if (!empty($this->config_array['human_captcha_shell_script'])) {
                $cmd = $this->config_array['human_captcha_shell_script'] . " --PROCESS_UID::" . $this->process_uid . " --SITEKEY::" . urlencode($sitekey) . " --PAGE_URL::" . urlencode($page_url);
                $this->log('Executing command : ' . $cmd);
                exec($cmd, $output, $return_var);
                $this->log('Command Result : ' . print_r($output, true));

                if (!empty($output)) {
                    $jsonRes = explode("OK|", $output[0])[1];
                    $this->log("jsonRes: " . $jsonRes);

                    return $jsonRes;
                } else {
                    $this->log("Cannot get captcha result from services!!!!!!!!");
                }
            }
        } catch (\Exception $exception) {
            $this->log('2:processHumanCaptcha::ERROR while processing captcha');
        }

        return null;
    }

    /**
     * Method to process captcha image.
     * This method waits for shell script execution to return the captcha code from the image selector provided and enters the captcha code into the text box selector($captcha_input_selector) provided.
     *
     * @param string $captcha_image_selector Css selector of the captcha image that needs solving
     * @param string $captcha_input_selector Css selector of the input text box where captcha code must be typed
     */
    public function processCaptcha($captcha_image_selector, $captcha_input_selector)
    {
        $this->log("--IMAGE CAPTCHA--");
        $image_dpi = 90;

        try {
            $this->capture("ImageCaptcha");
        } catch (\Exception $exception) {
            $this->log('processCaptcha::ERROR while taking snapshot');
        }
        $instruction = 'Type the text in the image';
        $captcha_code = '';
        if ($this->exists($captcha_image_selector)) {
            try {
                $image_path = $this->captureElement($this->process_uid, $captcha_image_selector);
                // 2captcha limited image size only maximum 100kb, So we must convert to jpeg, reduce resolution of image before sending to api
                $source_image = imagecreatefrompng($image_path);
                imagejpeg($source_image, $this->screen_capture_location . $this->process_uid . '.jpg', $image_dpi);

                if (!empty($this->config_array['captcha_shell_script'])) {
                    $cmd = $this->config_array['captcha_shell_script'] . " --PROCESS_UID::" . $this->process_uid . " --CAPTCHA_INSTRUCTION::" . urlencode($instruction);
                    $this->log('Executing command : ' . $cmd);
                    exec($cmd, $output, $return_var);
                    $this->log('Command Result : ' . print_r($output, true));

                    foreach ($output as $line) {
                        if (stripos($line, "TWO_FACTOR_CODE") !== false) {
                            $result_codes = explode("TWO_FACTOR_CODE:", $line);
                            $captcha_code = $result_codes[1];
                            break;
                        }
                    }

                    if ($captcha_code == '') {
                        $this->log("Can not get result from API");
                    } else {
                        try {
                            $this->moveToElementAndType($captcha_input_selector, $captcha_code);
                            $this->log("Captcha entered -> " . $captcha_code);
                        } catch (\Exception $exception) {
                            $this->log('1:processCaptcha::ERROR while processing captcha');
                        }
                    }
                }
            } catch (\Exception $exception) {
                $this->log('2:processCaptcha::ERROR while processing captcha');
            }
        } else {
            $this->log("Image does not found!");
        }
    }

    /**
     * Generic method to send command requests to external processes
     * This method executes shell commands and returns back the output received from that after processing it.
     *
     * @param string $process_uid process_uid string from caller portal script
     * @param string $script_path The script path that must be executed
     * @param mixed  $extra_data  associative array of params that must be sent to the command
     *
     * @return    string    Final processed output string requested by caller
     */
    public function sendRequest($process_uid, $script_path, $extra_data = [], $count = 0, $use_redis = false)
    {
        $this->log($script_path);
        $Result = "";
        $count++;
        $single_wait = 30;
        if ($count > $this->send_request_limit) {
            $this->log("too many calls to sendRequest, aborting now");
            $this->two_factor_expired();

            return $Result;
        }

        try {
            $json = "";
            if (!empty($extra_data)) {
                $json = json_encode($extra_data, JSON_UNESCAPED_UNICODE);
            }

            $this->log("Json Value is " . $json);

            if (!empty($this->notification_uid)) {
                $cmd = $script_path . " --PROCESS_UID::" . $process_uid . " --NOTIFICATION_UID::" . $this->notification_uid . " 2>&1";
                $this->log('Executing command : ' . $cmd);
                exec($cmd, $output, $return_var);
            } else {
                $cmd = $script_path . " --PROCESS_UID::" . $process_uid . " --EXTRA_DATA::" . urlencode($json);
                $this->log('Executing command : ' . $cmd);
                exec($cmd, $output, $return_var);
            }

            $this->log("Exit Value is " . $return_var);
            $Result = $this->getProcessResult($output);

            $this->log("--RESULT--" . $Result);
            if (stripos($Result, "NOTIFICATION_UID") !== false) {
                $resultCodes = explode("NOTIFICATION_UID:", $Result);
                $this->notification_uid = end($resultCodes);
                if ($use_redis && $this->redis && !empty($this->config_array['redis_2fa_cache_key'])) {
                    $this->log("Using Redis for fetching two factor code");
                    for ($k = 0; $k < 30; $k++) {
                        if ($this->redis->exists($this->config_array['redis_2fa_cache_key'])) {
                            $this->log("Found two factor code from redis");

                            return $this->redis->get($this->config_array['redis_2fa_cache_key']);
                        }
                        sleep(1);
                    }
                } else {
                    sleep($single_wait);
                }
                $Result = $this->sendRequest($process_uid, $script_path, $extra_data, $count, $use_redis);
            } else {
                if (stripos($Result, "TWO_FACTOR_CODE:") !== false) {
                    $resultCodes = explode("TWO_FACTOR_CODE:", $Result);
                    $Result = end($resultCodes);
                } else {
                    if (stripos($Result, "NOTIFICATION_EXPIRED:1") !== false) {
                        $this->two_factor_expired();
                        $Result = "";
                    } else {
                        sleep($single_wait);
                        $Result = $this->sendRequest($process_uid, $script_path, $extra_data, $count, $use_redis);
                    }
                }
            }
        } catch (\Exception $exception) {
            $this->log('sendRequest::ERROR ' . print_r($exception, true));
        }

        return $Result;
    }

    /**
     * Method to parse the output string and get the two_factor_auth_code
     *
     * @param mixed $output Raw output string response received from executing a shell script (usually from sendRequest method)
     *
     * @return    mixed    $two_factor_code    Final processed Two factor code string requested by caller
     */
    public function getProcessResult($output)
    {
        $two_factor_code = "";
        $timeexpired_code = false;

        $this->log("Two factor processing- " . print_r($output, true));

        try {
            foreach ($output as $line) {
                if (stripos($line, "NOTIFICATION_UID:") !== false) {
                    $two_factor_code = $line;
                    break;
                }

                if (stripos($line, "TWO_FACTOR_CODE:") !== false) {
                    $two_factor_code = $line;
                    break;
                }

                if (stripos($line, "NOTIFICATION_EXPIRED:1") !== false) {
                    $two_factor_code = $line;
                    $timeexpired_code = true;
                    break;
                }
            }

            if (trim($two_factor_code) !== "") {
                $this->log("two_factor_code- " . $two_factor_code);

                return $two_factor_code;
            } else {
                if ($timeexpired_code) {
                    return $two_factor_code;
                } else {
                    $this->log("Waiting For two_factor_code- " . $two_factor_code);

                    return $two_factor_code;
                }
            }
        } catch (\Exception $exception) {
            $this->log('getProcessResult::ERROR ');
        }

        return $two_factor_code;
    }

    /**
     * Captures screenshot of the entire active screen and saves it under screen_capture_location
     *
     * @param string $filename file name of the captured image
     *
     * @return string
     */
    public function capture($filename, $save_html = true)
    {
        return $this->capture_by_chromedevtool($filename, $save_html);
    }

    /**
     * Trigger Init Required
     */
    public function init_required()
    {
        $this->capture("init_required");
        $this->sendRequestEx(
            json_encode([
                'method' => 'initRequired',
            ])
        );
        $this->exitFinal(); // Terminate execution
    }

    /**
     * Trigger Two Factor Expired
     */
    public function two_factor_expired()
    {
        $this->capture("two_factor_expired");
        $this->sendRequestEx(
            json_encode([
                'method' => 'TwoFactorExpired',
            ])
        );
    }

    /**
     * Trigger Account Not Required
     */
    public function account_not_ready()
    {
        $this->capture("account_not_ready");
        $this->sendRequestEx(
            json_encode([
                'method' => 'AccountNotReady',
            ])
        );
        $this->exitFinal(); // Terminate execution
    }

    /**
     * Trigger No Permission
     */
    public function no_permission()
    {
        $this->capture("no_permission");
        $this->sendRequestEx(
            json_encode([
                'method' => 'NoPermission',
            ])
        );
        $this->exitFinal(); // Terminate execution
    }

    /**
     * Trigger No Invoice
     */
    public function no_invoice()
    {
        $this->capture("no_invoice");
        $this->sendRequestEx(
            json_encode([
                'method' => 'noInvoice',
            ])
        );
        $this->exitFinal(); // Terminate execution
    }

    /**
     * Trigger Success
     */
    public function success()
    {
        $this->sendRequestEx(
            json_encode([
                'method' => 'success',
            ])
        );
    }

    /**
     * Trigger Invoice Requested
     *
     * @param int $invoice_uid
     */
    public function invoice_requested($invoice_uid)
    {
        $this->sendRequestEx(
            json_encode([
                'method' => 'invoiceRequested',
                'data' => [
                    'invoice_uid' => $invoice_uid,
                ],
            ])
        );
    }

    /**
     * Update Process Lock File
     * If this lock file is not updated for 30 minutes, process will be aborted
     */
    public function update_process_lock()
    {
        $lock_file = $this->config_array['process_folder'] . 'process.gmi';
        file_put_contents($lock_file, time());
    }

    /**
     * Create process completed flag file
     */
    public function process_completed()
    {
        if (!empty($this->config_array['process_folder'])) {
            $lock_file = $this->config_array['process_folder'] . 'process.completed';
            file_put_contents($lock_file, time());

            try {
                $this->send_websocket_request($this->current_context->webSocketDebuggerUrl, 'Browser.close'); // Uncomment this in production
                sleep(1);
            } catch (\Exception $exception) {
                $this->log('ERROR in exitFinal');
            }
        }
    }

    /**
     * Captures screenshot of a specific web element by its CSS selector and saves it under screen_capture_location
     *
     * @param string $fileName file name of the captured image
     *
     * @return    string
     */
    public function captureElement($fileName, $selector_or_object = null, $image_dpi = 100, $scale = 1)
    {
        if ($selector_or_object == null) {
            $this->log(__FUNCTION__ . ' Can not capture null');

            return;
        }
        if ($scale > 2) {
            $this->log(__FUNCTION__ . ' Scale more than 2 is not allowed');
            $scale = 2;
        }

        $element = $selector_or_object;
        if (is_string($selector_or_object)) {
            $element = $this->queryElement($selector_or_object);
        }

        if ($element != null) {
            $coo = $this->execute_javascript(
                '
                { 
                    let coo = arguments[0].getBoundingClientRect();
                    let viewPort = {
                        x: coo.x,
                        y: coo.y,
                        width: coo.width,
                        height: coo.height
                    }
                    viewPort;
                }
            ',
                [$element]
            );
            $coo['scale'] = $scale;
            //print_r($coo);

            try {
                $image_file_path = $this->screen_capture_location . $fileName . '.png';

                $reponse_text = $this->send_websocket_request($this->current_context->webSocketDebuggerUrl, 'Page.captureScreenshot', [
                    "format" => "png", // jpeg, png, webp
                    "quality" => $image_dpi, // Compression quality from range [0..100] (jpeg only).
                    "clip" => $coo,
                ]);
                $base64_string = json_decode($reponse_text, true);
                $ifp = fopen($image_file_path, 'wb');
                fwrite($ifp, base64_decode($base64_string["result"]["data"]));
                fclose($ifp);
                $this->log('Element screenshot saved - ' . $image_file_path);

                return $image_file_path;
            } catch (\Exception $exception) {
                $this->log('Error in capture - ' . $exception->getMessage());
            }
        }
    }

    /**
     * Generic method to move to a specific input/textarea element and type in it, uses javascript fallback if selenium type fails
     *
     * @param string $text text to be typed
     */
    public function moveToElementAndType($query_string_or_element, $text, int $sleep_before_typing = 0, $clear_first = true)
    {
        $this->log(__FUNCTION__ . ":: " . $query_string_or_element);
        $input = $query_string_or_element;
        if (is_string($query_string_or_element)) {
            $input = $this->queryElement($query_string_or_element);
        }
        if (empty($input)) {
            $this->log(__FUNCTION__ . ":: Element not found " . $query_string_or_element);
        } else {
            try {
                $input->scroll_to_and_focus();
                sleep($sleep_before_typing);
                if ($clear_first) {
                    $input->clear(true);
                }
                usleep(500 * 1000);
                $this->cdp_insert_text($text);
            } catch (Exception $e) {
                $this->log($e->getMessage());
                $this->log('moveToElementAndType by JS');
                $this->execute_javascript('arguments[0].value = arguments[1]; arguments[0].dispatchEvent(new Event("change"));', [$input, $text]);
            }
        }
    }

    /**
     * Generic method to move to a specific click-able element and click on it, uses javascript fallback if selenium click fails
     * Click-able element includes button, anchor tags click-able divs etc
     */
    public function moveToElementAndClick($query_string_or_element, $jsFallback = true)
    {
        $this->log(__FUNCTION__ . " Begin with " . $query_string_or_element);
        $element = $query_string_or_element;
        if (is_string($query_string_or_element)) {
            $element = $this->queryElement($query_string_or_element);
        }

        if (empty($element)) {
            $this->log(__FUNCTION__ . ":: Can not find element " . $query_string_or_element);
        } else {
            $this->click_element($element);
        }
    }

    /*** GMIDEV-2384 new utility methods begin */

    /**
     * check if an element exists
     *
     * @param string $selector_or_xpath Css selector or xpath
     */
    public function exists($selector_or_xpath)
    {
        $existed = $this->count_elements($selector_or_xpath) > 0;
        $this->log('Element existed? ' . $existed);
        return $existed;
    }

    /**
     * check if all elements in a list exists
     */
    public function allExists($array_selector_or_xpath)
    {
        foreach ($array_selector_or_xpath as $selector_or_xpath) {
            if (!$this->exists($selector_or_xpath)) {
                return false;
            }
        }

        return true;
    }

    /**
     * check if atleast one element in a list exists
     */
    public function oneExists($array_selector_or_xpath)
    {
        foreach ($array_selector_or_xpath as $selector_or_xpath) {
            if ($this->exists($selector_or_xpath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Method to get element attribute, text by default
     *
     * @param string $attr attribute to get
     */
    public function extract($selector_or_xpath, $parent = null, $attr = 'innerText')
    {
        $element = $this->queryElement($selector_or_xpath, $parent);
        if ($element) {
            return $element->get($attr);
        }

        return '';
    }

    /**
     * reload/refresh current page
     */
    public function refresh()
    {
        try {
            $this->send_websocket_request($this->current_context->webSocketDebuggerUrl, 'Page.reload');
        } catch (\Exception $exception) {
            $this->log(__FUNCTION__ . ' : exception refreshing page' . $exception->getMessage());
        }

        return false;
    }

    // acceptJavascriptAlert(){} DELETED

    /**
     * switch to alert and accept it
     */
    public function switchToFrame($query_string_or_element): bool
    {
        $this->log(__FUNCTION__ . " Begin with " . $query_string_or_element);
        $frame = null;
        if ($query_string_or_element instanceof GmiRemoteElement) {
            $frame = $query_string_or_element;
        } else if (is_string($query_string_or_element)) {
            $frame = $this->queryElement($query_string_or_element);
        }

        if ($frame != null) {
            $frame_context = $this->get_frame_excutable_context($frame);
            if ($frame_context != null) {
                $this->current_context = $frame_context;
                return true;
            }
        } else {
            $this->log(__FUNCTION__ . " Frame not found " . $query_string_or_element);
        }

        return false;
    }

    public function switchToDefault()
    {
        $this->switch_to_default();
    }

    /**
     * * Method to get attribute array from multil elements which have same selector
     *
     * @param string $attribute_name the name of attribute need to get value
     */
    public function getElementsAttribute($selector_or_xpath, $attribute_name, $parent = null)
    {
        $result = [];
        $elements = $this->queryElementAll($selector_or_xpath);
        foreach ($elements as $element) {
            array_push($result, $element->get($attribute_name));
        }

        return $result;
    }

    /**
     * get current page url
     */
    public function getUrl()
    {
        return $this->execute_javascript('window.location.href;');
    }

    /**
     * check if current url contains a given string
     *
     * @param array $str to check in url
     */
    public function urlContains($str)
    {
        $flag = stripos($this->getUrl(), $str) !== false;
        $this->log(__FUNCTION__ . " - " . $str . " ?  :: " . $flag);

        return $flag;
    }

    /**
     * check if current url contains any of the given string in array
     *
     * @param array $strarr array of strings to check
     */
    public function urlContainsAny($strarr)
    {
        foreach ($strarr as $str) {
            if ($this->urlContains($str)) {
                return $str;
            }
        }

        return "";
    }

    /**
     * Waits for an element to be present for a specified number of seconds, default timeout = 15 secs
     *
     * @param string $timeout timeout in seconds to wait for element
     */
    public function waitTillPresent($selector_or_xpath, $timeout = 15)
    {
        $this->log(__FUNCTION__ . ":: " . $selector_or_xpath);
        $this->waitFor(
            function () use ($selector_or_xpath) {
                return $this->count_elements($selector_or_xpath) > 0;
            },
            $timeout
        );
    }

    /**
     * Waits for atleast one element to be present for a specified number of seconds, default timeout = 15 secs
     *
     * @param string $timeout timeout in seconds to wait for elements
     */
    public function waitTillAnyPresent($array_selector_or_xpath, $timeout = 15)
    {
        $this->log(__FUNCTION__ . ":: " . $selector_or_xpath);
        $this->waitFor(
            function () use ($array_selector_or_xpath) {
                foreach ($array_selector_or_xpath as $selector_or_xpath) {
                    if ($this->count_elements($selector_or_xpath) > 0) {
                        return true;
                    }
                }

                return false;
            },
            $timeout
        );
    }

    /**
     * Generic method to get single element as
     * can be used to get single relative element also, pass parent element object to search from that element, else search will be made on document object
     *
     * @param        $parent_element parent element (optional) to search from
     *
     * @return        null if not found
     */
    public function getElement($selector_or_xpath, $parent_element = null)
    {
        return $this->queryElement($selector_or_xpath, $parent_element);
    }

    /**
     * Generic method to get single element as
     * can be used to get single relative element also, pass parent element object to search from that element, else search will be made on document object
     *
     * @param        $parent_element parent element (optional) to search from
     *
     * @return        null if not found
     */
    public function getElements($selector_or_xpath, $parent_element = null)
    {
        return $this->queryElementAll($selector_or_xpath, $parent_element);
    }

    /**
     * End of GMIDEV-2384 new utility methods
     * ** */

    /**
     * Navigate to url
     *
     * @param string $url
     *
     * @return    void
     */
    public function openUrl($url)
    {
        $this->docker_need_restart = false;
        $this->log('Navigating to URL : ' . $url);

        $this->open_and_wait_page_loading(
            function () use ($url) {
                $data = [
                    "url" => $url,
                ];
                $this->send_websocket_request($this->current_context->webSocketDebuggerUrl, 'Page.navigate', $data);
            },
            null,
            null,
            null,
            60
        );
        $this->capture("current_page");
    }

    /**
     * Create log
     *
     * @param string $str
     *
     * @return    void
     */
    public function log($str)
    {
        $mem = memory_get_usage(true);
        $mem_usage = ceil($mem / (1024 * 1024)) . 'MB';
        $log_str = date("Y-m-d H:i:s") . " - " . $mem_usage . " : " . $str . "\n";

        if (!empty($this->log_file)) {
            @file_put_contents($this->log_file, $log_str, FILE_APPEND);
        } elseif (!$this->is_manual) {
            echo $log_str;
        } elseif ((int) $this->process_uid > 0 && !empty($this->config_array['process_folder'])) {
            $log_file = $this->config_array['process_folder'] . 'selenium.log';
            @file_put_contents($log_file, $log_str, FILE_APPEND);
        }
    }

    /**
     * PHP Downloader
     * Download file using curl. In those portals where we get filename as broken utf8, this function can be used to download
     *
     * @param string $orig_file_ext
     * @param string $orig_filename
     *
     * @return    string
     */
    public function custom_downloader($url, $orig_file_ext, $orig_filename = '')
    {
        // Check if already exists
        if ($this->document_exists($orig_filename)) {
            return '';
        }

        $this->no_margin_pdf = 1;

        // Prepare cookie string for curl
        $cookies = $this->getCookies();
        if (!empty($cookies)) {
            $cookie_arr = [];

            /* @var Cookie $cookie */
            foreach ($cookies as $cookie) {
                $cookie_arr[] = $cookie->name . '=' . $cookie->value;
            }

            $cookie_string = implode("; ", $cookie_arr);

            // Prepare language header
            $lang_code = $this->config_array['lang_code'];
            $lang_header = $lang_code == 'en' ? 'en-US,en;q=0.5' : 'de-DE,de;q=0.8,en-US;q=0.6,en;q=0.4';

            // Start curl downloading
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_COOKIESESSION, 1);
            curl_setopt($ch, CURLOPT_HEADER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_ENCODING, "gzip");
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/pdf',
                'Accept-Language: ' . $lang_header,
                'User-Agent: ' . $this->config_array['user_agent'],
                'Connection: Keep-Alive',
                'Accept-Encoding: gzip, deflate',
            ]);
            curl_setopt($ch, CURLOPT_COOKIE, $cookie_string);

            $response = curl_exec($ch);
            $this->log("Response Size: " . strlen($response));

            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            curl_close($ch);

            // Fetch Header and Response Content
            $header = trim(substr($response, 0, $header_size));
            $headers = $this->get_headers_from_curl_response($header);
            $file_content = substr($response, $header_size);
            $this->log("File Content Size:  " . strlen($file_content));

            // Find filename from header
            $filename = '';
            if (isset($headers['Content-Disposition'])) {
                $parts = explode('filename="', $headers['Content-Disposition']);
                if (count($parts) == 2) {
                    $encoded_filename = mb_detect_encoding($parts[1], 'UTF-8', true) ? $parts[1] : utf8_encode($parts[1]);
                    $filename = str_replace('"', '', $encoded_filename);
                    unset($encoded_filename);
                } else {
                    preg_match_all("/\w+\-.\w.+/", $headers['Content-Disposition'], $output);
                    $filename = $output[0][0];
                    $filename = mb_detect_encoding($filename, 'UTF-8', true) ? $filename : utf8_encode($filename);
                    unset($output);
                }
                unset($parts);

                // in some cases, filename header contains too many data with ";" as delimiter
                $file_parts = explode(";", $filename);
                $filename = $file_parts[0];
            }

            $this->log("Header String: " . $header);
            $this->log("Header Array: " . json_encode($headers));
            $this->log("Extracted Filename: " . $filename);

            if (trim($filename) != '') {
                // Clean filename
                $new_filename = $this->clean_filename($filename);
                $this->log("Clean filename - " . $new_filename);
                $filename = trim($new_filename);

                $file_ext = pathinfo($filename, PATHINFO_EXTENSION);

                // In portal mailjet, filename comes without extension
                if (empty($file_ext)) {
                    $file_ext = $orig_file_ext;
                }

                $unique_file = false;
                $counter = 1;
                $temp_filename = $filename;
                do {
                    if (file_exists($this->config_array['download_folder'] . $temp_filename)) {
                        $temp_filename = basename($filename, '.' . $file_ext) . '(' . $counter . ').' . $file_ext;
                        $counter++;
                    } else {
                        $unique_file = true;
                        $filename = $temp_filename;
                    }
                } while (!$unique_file);
            } else {
                $filename = basename($orig_filename);
            }

            $this->log("Target File - " . $filename);
            file_put_contents($this->config_array['download_folder'] . $filename, $file_content);

            sleep(1); // Make sure this write operation is really finished and then enforce a Window Defender check by opening file

            if (file_exists($this->config_array['download_folder'] . $filename)) {
                $saved_content = file_get_contents($this->config_array['download_folder'] . $filename);

                // Check for PDF file
                $ext = strtolower(pathinfo($this->config_array['download_folder'] . $filename, PATHINFO_EXTENSION));
                if ($ext == 'pdf' && strpos(strtoupper($saved_content), '%PDF') === false) {
                    $this->log("Invalid PDF file");
                    @unlink($this->config_array['download_folder'] . $filename);
                } else {
                    $filepath = $this->config_array['download_folder'] . $filename;
                    $this->downloaded_files[] = basename($filepath);
                    $this->log('Downloaded File - ' . $filepath);

                    return $filepath;
                }
            }
        }

        return '';
    }

    /**
     * Helper function to get headers in array
     *
     *
     * @return array
     */
    private function get_headers_from_curl_response($header_text)
    {
        $headers = [];
        $lines = explode("\r\n", $header_text);
        $this->log("Header Count: " . count($lines));

        foreach ($lines as $i => $line) {
            if ($i === 0) {
                $headers['http_code'] = $line;
            } else {
                list($key, $value) = explode(': ', $line);
                $headers[$key] = $value;
            }
        }

        return $headers;
    }

    /**
     * Clean filename
     * Replace Umlaute characters
     *
     *
     * @return mixed
     */
    private function clean_filename($filename)
    {
        $search = ["ä", "ö", "ü", "ß", "Ä", "Ö", "Ü", "é", "á", "ó"];
        $replace = ["ae", "oe", "ue", "ss", "Ae", "Oe", "Ue", "e", "a", "o"];

        $encoded_filename = mb_detect_encoding($filename, 'UTF-8', true) ? $filename : utf8_encode($filename);
        $result = str_replace($search, $replace, $encoded_filename);

        return $result;
    }

    /**
     * Download file with url
     *
     * @param string $url
     * @param string $file_ext
     * @param string $filename
     *
     * @return    string
     */
    public function direct_download($url, $file_ext, $filename = '')
    {
        // Check if already exists
        if ($this->document_exists($filename)) {
            return '';
        }

        $this->no_margin_pdf = 1;

        $this->start_and_wait_download(
            function () use ($url) {
                $this->send_websocket_request($this->current_context->webSocketDebuggerUrl, 'Page.navigate', ["url" => $url]);
            }
        );

        // find new saved file and return its path
        return $this->find_saved_file($file_ext, $filename);
    }

    /**
     * Download file by clicking on the selector provided
     *
     * @param string $file_ext
     * @param string $filename
     * @param int    $wait_time
     *
     * @return    string
     */
    public function click_and_download($query_string_or_element, $file_ext, $filename = '', $wait_time = 30)
    {
        // Check if already exists
        if ($this->document_exists($filename)) {
            return '';
        }

        $this->no_margin_pdf = 1;

        $this->start_and_wait_download(
            function () use ($query_string_or_element) {
                $this->click_element($query_string_or_element);
            }
        );

        // find new saved file and return its path
        return $this->find_saved_file($file_ext, $filename);
    }

    /**
     * Print by click on selector
     *
     * @param string $selector
     * @param string $filename
     *
     * @return    string
     */
    public function click_and_print($selector, $filename)
    {
        // Check if already exists
        if ($this->document_exists($filename)) {
            return '';
        }

        $file_ext = $this->get_file_extension($filename);
        $this->no_margin_pdf = 1;

        try {
            $element = $this->queryElement($selector);
            if ($element != null) {
                $this->log('click_and_print -> ' . $selector);
                $element->click();
            }
        } catch (\Exception $exception) {
            $this->log('ERROR in click_and_print. Could not locate element - ' . $selector);
            $this->log(print_r($exception, true));
        }

        // Wait for completion of file download
        $this->wait_and_check_download($file_ext);

        // find new saved file and return its path
        return $this->find_saved_file($file_ext, $filename);
    }

    /**
     * Open url, Capture screen and save as pdf
     *
     * @param string $url
     * @param string $filename
     * @param int    $delay_before_print
     *
     * @return    string
     */
    public function download_capture($url, $filename, $delay_before_print = 0)
    {
        // Check if already exists
        if ($this->document_exists($filename)) {
            return '';
        }

        $this->no_margin_pdf = 0;
        $filepath = '';

        try {
            // Open new window
            // $this->open_new_window();

            // Open URL in new window
            $this->send_websocket_request($this->current_context->webSocketDebuggerUrl, 'Page.navigate', ["url" => $url]);

            // Trigger print
            $filepath = $this->download_current($filename, $delay_before_print, true);

            // Close new window
            // $this->close_new_window();
        } catch (\Exception $exception) {
            $this->log('ERROR in download_capture.');
            $this->log(print_r($exception, true));
        }

        // find new saved file and return its path
        return $filepath;
    }

    /**
     * Capture current screen and save as pdf
     *
     * @param string $filename
     * @param int    $delay_before_print
     * @param bool   $skip_check
     *
     * @return    string
     */
    public function download_current($filename, $delay_before_print = 0, $skip_check = false)
    {
        try {
            // Check if already exists
            if (!$skip_check && $this->document_exists($filename)) {
                return '';
            }

            $file_ext = $this->get_file_extension($filename);
            $this->no_margin_pdf = 0;
            $filepath = '';

            // Put some delay if page rendering takes time
            // If page is not loaded by ajax, then such delay is not required
            if ($delay_before_print > 0) {
                sleep($delay_before_print);
            }

            // Trigger print
            // Set window title to print, as chrome use window title to save pdf file
            $this->execute_javascript('document.title = "print"; window.print();');
            sleep(5);

            // Wait for completion of file download
            $this->wait_and_check_download($file_ext);

            // find new saved file and return its path
            $filepath = $this->find_saved_file($file_ext, $filename);
        } catch (\Exception $exception) {
            $this->log('ERROR in download_capture.');
            $this->log(print_r($exception, true));
        }

        // find new saved file and return its path
        return $filepath;
    }

    /**
     * Create json request log for new invoice, to be parsed later
     * Also create new invoice record in db
     *
     * @param string $invoice_name
     * @param string $invoice_date
     * @param float  $invoice_amount
     * @param string $invoice_filename
     * @param string $note
     * @param int    $force_doc_number_check
     * @param string $document_type
     * return    void
     */
    public function new_invoice(
        $invoice_name,
        $invoice_date,
        $invoice_amount,
        $invoice_filename,
        $no_validate_fintract = 0,
        $note = '',
        $force_doc_number_check = 0,
        $document_type = '',
        $params = []
    ) {
        $this->update_process_lock();
        $invoice_filename = $this->config_array['download_folder'] . basename($invoice_filename);
        if (file_exists($invoice_filename)) {
            $invoice_data = [
                "noMargin" => $this->no_margin_pdf,
                "invoiceName" => $invoice_name,
                "invoiceDate" => $invoice_date,
                "invoiceAmount" => $invoice_amount,
                "invoiceFilename" => $invoice_filename,
                "noValidateFintract" => $no_validate_fintract,
                "note" => $note,
                "force_doc_number_check" => $force_doc_number_check,
                "document_type" => $document_type,
                "params" => $params,
            ];

            if (!empty($this->config_array['new_invoice_shell_script'])) {
                $cmd = $this->config_array['new_invoice_shell_script'];
                $cmd .= " --PROCESS_UID::" . $this->process_uid;
                $cmd .= " --OPT::saveInvoice";
                $cmd .= " --invoiceData::" . urlencode(json_encode($invoice_data));
                $cmd .= " > /dev/null 2>&1 &"; // Run in background
                $ret = exec($cmd);
                $this->log('New Invoice CMD - ' . $cmd);
            }

            $this->sendRequestEx(
                json_encode([
                    'method' => 'newInvoice',
                    'data' => $invoice_data,
                ])
            );

            if (!empty($invoice_name)) {
                $this->config_array['download_invoices'][] = $invoice_name;
            }
        }

        $this->no_margin_pdf = 0;
    }

    /**
     * Find last downloaded file
     * Rename it if filename passed as argument
     *
     * @param string $file_ext
     * @param string $filename
     *
     * @return    string
     */
    public function find_saved_file($file_ext, $filename = '')
    {
        $filepath = '';
        if (is_dir($this->config_array['download_folder'])) {
            $downloaded_files = $this->get_downloaded_files($file_ext);
            if (!empty($downloaded_files)) {
                foreach ($downloaded_files as $downloaded_file) {
                    if (!in_array(basename($downloaded_file), $this->downloaded_files)) {
                        $filepath = $this->config_array['download_folder'] . basename($downloaded_file);

                        // If filename passed as argument, then rename file
                        if (!empty($filename) && !empty($filepath)) {
                            @rename($filepath, $this->config_array['download_folder'] . $filename);

                            if (file_exists($this->config_array['download_folder'] . $filename)) {
                                $filepath = $this->config_array['download_folder'] . $filename;
                            }
                        }

                        $this->downloaded_files[] = basename($filepath);
                        break;
                    }
                }
            }
        }

        $this->log('Downloaded File - ' . $filepath);

        return $filepath;
    }

    /**
     * Wait for completion of file download
     * timeout 1 min
     *
     * @param string $file_ext
     * @param int    $attempts
     *
     * @return    void
     */
    public function wait_and_check_download($file_ext, $attempts = 0)
    {
        $this->log('Waiting for download completion');
        usleep(10000); // 10 milliseconds
        $new_filepath = '';

        // If there is any .part file then wait for download completion
        // Timeout 5 minutes
        if (is_dir($this->config_array['download_folder'])) {
            $part_files = glob($this->config_array['download_folder'] . '*.' . $file_ext . ".part");
            if (!empty($part_files)) {
                // Wait till all part files got renamed by browser
                $start_time = time();
                while (true) {
                    $time_lapsed = time() - $start_time;
                    if ($time_lapsed >= 300) {
                        break;
                    }

                    usleep(5000); // 5 milliseconds

                    $part_files = glob($this->config_array['download_folder'] . '*.' . $file_ext . ".part");
                    if (empty($part_files)) {
                        break;
                    }
                }
            }
        }

        if (is_dir($this->config_array['download_folder'])) {
            $downloaded_files = $this->get_downloaded_files($file_ext);
            if (!empty($downloaded_files)) {
                foreach ($downloaded_files as $downloaded_file) {
                    if (!in_array(basename($downloaded_file), $this->downloaded_files)) {
                        $new_filepath = $this->config_array['download_folder'] . basename($downloaded_file);
                        break;
                    }
                }
            }
        }
        $this->log('Found new file - ' . $new_filepath);

        // Check after 5 milliseconds if filesize is changed
        // if keep changing, means file is downloading, if not file download completed
        // timeout 5 min
        if (!empty($new_filepath) && file_exists($new_filepath)) {
            $start_time = time();
            $last_filesize = 0;
            while (true) {
                $new_filesize = filesize($new_filepath);
                if ($last_filesize == $new_filesize && $new_filesize > 0) {
                    break;
                }
                $last_filesize = $new_filesize;

                $time_lapsed = time() - $start_time;
                if ($time_lapsed >= 300) {
                    break;
                }

                usleep(5000); // 5 milliseconds
            }

            // If file is downloaded without any extension, then rename it with extension
            $ext = $this->get_file_extension(basename($new_filepath));
            if (empty($ext) || $ext == strtolower(basename($new_filepath)) || basename($ext) == strtolower(basename($new_filepath))) {
                @rename($new_filepath, $new_filepath . '.' . $file_ext);

                // Check if rename is successfull
                if (file_exists($new_filepath . '.' . $file_ext)) {
                    $new_filepath = $new_filepath . '.' . $file_ext;
                }
            }

            $this->log('Download completed - ' . $new_filepath);
        } elseif ($attempts < 5) {
            $attempts++;
            sleep(2);
            $this->wait_and_check_download($file_ext, $attempts);
        } else {
            $this->log('File save failed');
            $this->capture("failed_download");
        }
    }

    /**
     * Get all downloaded files sorted by filetime
     *
     * @param string $file_ext
     *
     * @return    mixed
     */
    private function get_downloaded_files($file_ext)
    {
        $downloaded_files = [];
        if (is_dir($this->config_array['download_folder'])) {
            // Fetch all files from download folder and return only where file extension is matched
            // or no extension
            $downloaded_files = glob($this->config_array['download_folder'] . '*');
            if (!empty($downloaded_files)) {
                foreach ($downloaded_files as $idx => $downloaded_file) {
                    $ext = $this->get_file_extension(basename($downloaded_file));
                    $downloaded_filename = strtolower(basename($downloaded_file));

                    // If file is downloaded without any extension, then use it and after complettion
                    // save it with ext
                    if (empty($ext) || $ext == $downloaded_filename || basename($ext) == $downloaded_filename) {
                        // No extension
                        $this->log('Found file with no extension - ' . $downloaded_file);
                    } elseif ($ext != $downloaded_filename && $ext != $file_ext) {
                        unset($downloaded_files[$idx]);
                    }
                }
            }

            // Sort list by filetime
            if (!empty($downloaded_files)) {
                array_multisort(array_map('filemtime', $downloaded_files), SORT_NUMERIC, SORT_DESC, $downloaded_files);
            }
        }

        return $downloaded_files;
    }

    /**
     * Check if invoice already exists
     *
     * @param string $invoice_number
     *
     * @return    bool
     */
    public function invoice_exists($invoice_number)
    {
        $this->update_process_lock();

        // Update cookie file, So that in case if process terminated, we will have updated cookie always
        $this->dumpCookies(true);

        $this->increase_document_counter($invoice_number);

        if (!empty($invoice_number) && !empty($this->config_array['download_invoices'])) {
            return in_array($invoice_number, $this->config_array['download_invoices']);
        }

        return false;
    }

    /**
     * Increase document counter for found documents
     *
     * @param string $invoice_name
     *
     * @return    void
     */
    public function increase_document_counter($invoice_name)
    {
        $this->update_process_lock();

        if (trim($invoice_name) != '') {
            // if filename is given then strip extension
            $fext = $this->get_file_extension($invoice_name);
            if (!empty($fext)) {
                $invoice_name = basename($invoice_name, "." . $fext);
            }

            if (!in_array($invoice_name, $this->checked_documents)) {
                $this->checked_documents[] = $invoice_name;
                $this->document_counter++;
            }

            // Create a log for success with document counter
            $this->sendRequestEx(
                json_encode([
                    'method' => 'success',
                    'data' => [
                        'documentCounter' => $this->document_counter,
                    ],
                ])
            );
        }
    }

    /**
     * Check if document exists. It internally checks invoice number too
     *
     * @param string $filename
     *
     * @return    bool
     */
    public function document_exists($filename)
    {
        if (!empty($filename)) {
            if (!in_array(basename($filename), $this->downloaded_files)) {
                $this->increase_document_counter($filename);
            }

            $file_ext = $this->get_file_extension($filename);

            $filepath = $this->config_array['download_folder'] . $filename;
            if (file_exists($filepath)) {
                $this->log('File exists - ' . $filename);

                return true;
            }

            // If file not exists, then check if invoice number exists
            $invoice_number = basename($filename, '.' . $file_ext);
            if ($this->invoice_exists($invoice_number)) {
                $this->log('Invoice number exists - ' . $invoice_number);

                return true;
            }
        } else {
            $this->document_counter++;

            // Create a log for success with document counter
            $this->sendRequestEx(
                json_encode([
                    'method' => 'success',
                    'data' => [
                        'documentCounter' => $this->document_counter,
                    ],
                ])
            );
        }

        $this->log('Downloading Document ' . $this->document_counter);

        return false;
    }

    /**
     * Convert captured image to pdf document and delete captured image
     *
     * @param string $capture_name
     * @param string $filename
     *
     * @return    void
     */
    public function generate_pdf($capture_name, $filename)
    {
        // Resize image to 1200x1122, as in casperjs
        $thumb_w = 1200;
        $thumb_path = $this->config_array['download_folder'] . $capture_name . '.png';
        $img = @imagecreatefrompng($thumb_path);
        if (is_resource($img) && strtoupper(get_resource_type($img)) == "GD") {
            $iOrigWidth = imagesx($img);
            $iOrigHeight = imagesy($img);
            if ((int) $iOrigWidth > 0 && (int) $iOrigHeight > 0) {
                $fScale = $thumb_w / $iOrigWidth;
                $iNewWidth = floor($fScale * $iOrigWidth);
                $iNewHeight = floor($fScale * $iOrigHeight);

                $tmpimg = imagecreatetruecolor($iNewWidth, $iNewHeight);
                $white = imagecolorallocate($tmpimg, 255, 255, 255);
                imagefilledrectangle($tmpimg, 0, 0, $iNewWidth, $iNewHeight, $white);
                imagecopyresampled($tmpimg, $img, 0, 0, 0, 0, $iNewWidth, $iNewHeight, $iOrigWidth, $iOrigHeight);
                imagedestroy($img);
                $img = $tmpimg;

                imagepng($img, $thumb_path);
                imagedestroy($img);
            }
        }

        // convert to pdf
        $target_pdf = $this->config_array['download_folder'] . $filename;
        $cmd = 'convert -auto-orient -quality 100 ' . $this->config_array['download_folder'] . $capture_name . '.png' . ' ' . escapeshellarg(trim($target_pdf));
        exec($cmd);

        @unlink($this->config_array['download_folder'] . $capture_name . '.png');
    }

    /**
     * Translate Date abbreviations
     *
     * @param string $date_str
     *
     * @return    string
     */
    public function translate_date_abbr($date_str)
    {
        $lang_code = $this->config_array['lang_code'];
        $source_month_abbr = $this->month_abbr_de;
        if ($lang_code == 'fr') {
            $source_month_abbr = $this->month_abbr_fr;
        } elseif ($lang_code == 'nl') {
            $source_month_abbr = $this->month_abbr_nl;
        }

        for ($i = 0; $i < count($source_month_abbr); $i++) {
            if (stripos($date_str, $source_month_abbr[$i]) !== false) {
                $date_str = str_replace($source_month_abbr[$i], $this->month_abbr_en[$i], $date_str);
                break;
            }
        }

        return $date_str;
    }

    /**
     * Parse Date
     *
     * @param string $date_str
     * @param string $input_date_format
     * @param string $output_date_format
     * @param string $lang_code
     *
     * @return    string
     */
    public function parse_date($date_str, $input_date_format = '', $output_date_format = '', $lang_code = '')
    {
        $output_date_format = empty($output_date_format) ? 'Y-m-d' : $output_date_format;
        $parsed_date = '';

        try {
            // Check if any language parsing is required
            $lang_code = !empty($lang_code) ? $lang_code : $this->config_array['lang_code'];
            $source_month_names = $this->month_names_de;
            if ($lang_code == 'fr') {
                $source_month_names = $this->month_names_fr;
            } elseif ($lang_code == 'nl') {
                $source_month_names = $this->month_names_nl;
            } elseif ($lang_code == 'es') {
                $source_month_names = $this->month_names_es;

                // In amazon.es date has " de " also. which can not be parsed. so need to strip it
                $date_str = str_replace(" de ", "", $date_str);

                // In spanish september can be setiembre or septiembre
                $date_str = str_replace("setiembre", "septiembre", $date_str);
            } elseif ($lang_code == 'en') {
                $source_month_names = $this->month_names_en;
            }

            for ($i = 0; $i < count($source_month_names); $i++) {
                if (stripos($date_str, $source_month_names[$i]) !== false) {
                    $date_str = str_replace($source_month_names[$i], $this->month_names_en[$i], $date_str);
                    break;
                }
            }

            if (!empty($input_date_format)) {
                $d = \DateTime::createFromFormat($input_date_format, $date_str);
            } else {
                $d = new \DateTime($date_str);
            }

            if (!empty($d)) {
                $timestamp = $d->getTimestamp();
                $parsed_date = date($output_date_format, $timestamp);
            }
        } catch (\Exception $exception) {
            $this->log('ERROR in parsing date - ' . $exception->getMessage());
        }

        return $parsed_date;
    }

    /**
     * To send generic string output to the log for parsing
     *
     * @param string $string_request
     * @param string $request_start
     */
    public function sendRequestEx($string_request, $request_start = '')
    {
        if (empty($request_start)) {
            $request_start = $this->request_start;
        }
        if ($this->is_manual && !empty($this->log_file) && !empty($this->config_array['process_folder'])) {
            $log_file = $this->config_array['process_folder'] . 'selenium.log';
            @file_put_contents($this->log_file, $request_start . $string_request . "\n", FILE_APPEND);
        } else {
            echo '' . $request_start . $string_request . "\n";
        }
    }

    /**
     * Called when script execution completes successfully
     */
    public function exitSuccess()
    {
        $this->success();
        $this->exitFinal();
    }

    /**
     * Called when script execution completes with failure
     */
    public function exitFailure()
    {
        $this->sendRequestEx(
            json_encode([
                'method' => 'portalFailed',
            ])
        );
        $this->exitFinal();
    }

    /**
     * Called when script failed with login error
     *
     * @param int $confirmed
     */
    public function loginFailure($confirmed = 0)
    {
        $this->capture("triggered_loginFailed");
        $this->log('Begin loginFailure ');
        $this->sendRequestEx($this->login_failed);

        if ($confirmed == 1) {
            $this->sendRequestEx(
                json_encode([
                    'method' => 'loginFailedConfirmed',
                ])
            );
        } else {
            $this->sendRequestEx(
                json_encode([
                    'method' => 'loginFailedExt',
                ])
            );
        }
        $this->exitFinal();
    }

    /**
     * Return last init url
     *
     * @param string $last_init_url
     */
    public function lastInitUrl($last_init_url)
    {
        $this->sendRequestEx(
            json_encode([
                'method' => 'lastInitUrl',
                'data' => [
                    'url' => $last_init_url,
                ],
            ])
        );
    }

    /**
     * Method to call finally to close
     */
    public function exitFinal()
    {
        // Save updated cookies
        $this->dumpCookies();

        // Trigger process completed
        $this->process_completed();

        $this->dump_session_files();

        if (!$this->is_manual) {
            die;
        }
    }

    /**
     * Returns a file's extension.
     *
     * @param string $filename
     *
     * @return    string
     */
    public function get_file_extension($filename)
    {
        $exploded = explode('.', $filename);

        return strtolower(end($exploded));
    }

    /**
     * Returns a list of files matching a pattern string in a directory and its subdirectories.
     *
     * @link    http://de2.php.net/manual/en/reserved.constants.php
     *
     * @param string
     * @param string
     * @param int    Maximum number of recursion levels, -1 for infinite
     * @param int    Recursion level
     * @param bool    Return folders also ?
     * @param mixed    List of files or directories that will be ignored
     *
     * @return    mixed
     */
    public function search_directory(
        $pattern,
        $dir,
        $maxlevel = 0,
        $level = 0,
        $return_directories = false,
        $ignore_list = 0
    ) {
        $result = [];

        if ($level > $maxlevel && $maxlevel != -1) {
            return $result;
        }
        if (substr($dir, -1) == DIRECTORY_SEPARATOR || substr($dir, -1) == '/') {
            $dir = substr($dir, 0, -1);
        }

        if (is_dir($dir)) {
            if ($dh = @opendir($dir)) {
                while (($file = readdir($dh)) !== false) {
                    if (is_array($ignore_list)) {
                        if (in_array($file, $ignore_list)) {
                            $file = '.';
                        } // Mark to be ignored
                    }

                    if ($file != '.' && $file != '..') {
                        if (is_dir($dir . DIRECTORY_SEPARATOR . $file)) {
                            $test_return = $this->search_directory($pattern, $dir . DIRECTORY_SEPARATOR . $file, $maxlevel, $level + 1, $return_directories);

                            if (is_array($test_return)) {
                                $temp = array_merge($test_return, $result);
                                $result = $temp;
                            }

                            if (is_string($test_return)) {
                                array_push($result, $test_return);
                            }

                            if ($return_directories == true) {
                                $add_it = false;

                                if ($pattern == '/.*/' || $pattern == '') {
                                    $add_it = true;
                                } elseif (preg_match($pattern, $file)) {
                                    $add_it = true;
                                }

                                if ($add_it) {
                                    array_push($result, $dir . DIRECTORY_SEPARATOR . $file);
                                }
                            }
                        } else {
                            $add_it = false;

                            if ($pattern == '/.*/' || $pattern == '') {
                                $add_it = true;
                            } elseif (preg_match($pattern, $file)) {
                                $add_it = true;
                            }

                            if ($add_it) {
                                array_push($result, $dir . DIRECTORY_SEPARATOR . $file);
                            }
                        }
                    }
                }

                closedir($dh);
            }
        }

        return $result;
    }

    public function reuseMfaSecret()
    {
        $mfa_bak_file = $this->config_array['process_folder'] . 'mfa.secret.bak';
        if (file_exists($mfa_bak_file)) {
            $mfa_file = $this->config_array['process_folder'] . 'mfa.secret';
            @rename($mfa_bak_file, $mfa_file);
        }
    }

    /**
     * Check if a string is UTF-8
     *
     * @param string $str
     *
     * @return    bool
     */
    public function is_utf8($str)
    {
        if (function_exists('mb_convert_encoding')) {
            if ($str === mb_convert_encoding(mb_convert_encoding($str, 'UTF-32', 'UTF-8'), 'UTF-8', 'UTF-32')) {
                return true;
            } else {
                return false;
            }
        } else {
            return preg_match(
                '%^(?:
                      [\x09\x0A\x0D\x20-\x7E]            # ASCII
                    | [\xC2-\xDF][\x80-\xBF]             # non-overlong 2-byte
                    |  \xE0[\xA0-\xBF][\x80-\xBF]        # excluding overlongs
                    | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}  # straight 3-byte
                    |  \xED[\x80-\x9F][\x80-\xBF]        # excluding surrogates
                    |  \xF0[\x90-\xBF][\x80-\xBF]{2}     # planes 1-3
                    | [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
                    |  \xF4[\x80-\x8F][\x80-\xBF]{2}     # plane 16
                )*$%xs',
                $str
            );
        }
    }

    // Below is source code for GMI Remote Chrome - version 1.1 - Nov-2024

    // Global variables for Chrome manager
    public $node_name;
    public $remote_url;
    public $user_data_dir = '/home/seluser/profiles';
    public $socket_request_id = 12345;

    public $browser;
    public $init_tab;
    public $current_chrome_tab;
    public $current_context;

    public $marked_tabs = [];
    public $chrome_browser_options_array = [];

    public function start_chrome()
    {
        $this->log('Preparing setting');
        $this->setup_options();
        $this->setup_preferences();

        $this->load_session_files();

        exec("sudo docker exec -d " . $this->node_name . " sudo rm -rf $this->user_data_dir/SingletonLock"); // remove profile locked status.

        usleep(300 * 1000);
        $this->log('Start Chrome');
        exec("sudo docker exec -i --user root $this->node_name sh -c 'sudo chmod -R 777 $this->user_data_dir'");
        exec("sudo docker exec -i --user root $this->node_name sh -c 'sudo chmod -R 777 $this->user_data_dir/Default'");
        // exec("sudo docker exec -d " . $this->node_name . " rm -rf /home/seluser/chrome-profile/SingletonLock");

        $flat_option = implode(' ', $this->chrome_browser_options_array);
        $this->log("sudo docker exec -d " . $this->node_name . " google-chrome  $flat_option");
        exec("sudo docker exec -d " . $this->node_name . " google-chrome  $flat_option");
        exec("sudo docker exec -i $this->node_name timeout 5 xdotool search --sync --class google-chrome"); // This command for waiting chrome window opened
        $this->log('Forwarding port');
        exec("sudo docker exec -d " . $this->node_name . " sudo socat TCP-LISTEN:9999,fork TCP:localhost:9222");
        sleep(1);
        $this->log('Browser is ready');
    }

    // Remote Chrome Block of framework functions
    public function setup_options()
    {
        $this->chrome_browser_options_array = [
            "--remote-debugging-port=9222", // required
            "--lang=" . $this->config_array['lang_code'],
            "--no-default-browser-check",
            "--test-type",
            "--no-first-run",
            "--start-maximized",
            '--disable-dev-shm-usage',
            // '--disable-dev-tools',
            "--user-data-dir=$this->user_data_dir",
            "--kiosk-printing",
            // "--kiosk",
            "--window-size=1920,1080",
            "--homepage=https://www.google.com",
            "--allow-file-access-from-files",
            "--allow-cross-origin-auth-prompt",
            "--allow-file-access",
            // "--disable-notifications", Seem making problem for Google login
            "--disable-popup-blocking",
            "--disable-features=PrivacySandboxSettings4", // Disable "Enhanced ads Pivacy" popup - but not sure. need to test and report
            "--simulate-outdated-no-au=Tue,\ 31\ Dec\ 2099\ 23:59:59\ GMT", // Disable "Remind update browser" popup
        ];
        if (isset($this->config_array['load_interceptor']) && (bool) $this->config_array['load_interceptor']) {
            $this->chrome_browser_options_array[] = "--load-extension=/home/seluser/manuals-ext";
            $this->chrome_browser_options_array[] = "--enable-unsafe-extension-debugging";

            if (!empty($this->config_array['interceptor_ext_config_file_path'])) {
                $interceptor_extension = KERNEL_ROOT . '/../selenium-docker/manuals-ext/chrome-mv3-prod/';
                exec("sudo docker cp " . $interceptor_extension . ". $this->node_name:/home/seluser/manuals-ext");
                exec("sudo docker cp " . $this->config_array['interceptor_ext_config_file_path'] . " $this->node_name:/home/seluser/manuals-ext/config/config.json");
                exec("sudo docker exec -i $this->node_name sh -c 'sudo chmod -R 777 /home/seluser/manuals-ext/'");
            }
        }
        if (!empty($this->config_array['proxy_host']) && !empty($this->config_array['proxy_lpm_port'])) {
            array_push($this->chrome_browser_options_array, "--proxy-server=" . $this->config_array['proxy_host'] . ":" . $this->config_array['proxy_lpm_port']);
        }

        if (!empty($this->config_array['restore_last_session']) && !empty($this->config_array['restore_last_session'])) {
            array_push($this->chrome_browser_options_array, "--restore-last-session");
        }
    }

    public function setup_preferences()
    {
        // Chrome use Preference file to save setting. This file is in json format. File path: ~profile folder~/Default/Preferences
        $inside_profile_default_folder = GmiCommon::join_file_paths($this->user_data_dir, GmiCommon::PROFILE_DEFAULT_FOLDER);
        $inside_prefs_profile_file = GmiCommon::join_file_paths($inside_profile_default_folder, 'Preferences');
        //Copy out Prefs file to here, then modify
        $term_prefs_file = GmiCommon::join_file_paths($this->screen_capture_location, "Preferences_copiedout");
        $modified_prefs_file = GmiCommon::join_file_paths($this->screen_capture_location, "Preferences_copyin");
        $outside_saved_session_folder_path = GmiCommon::join_file_paths($this->screen_capture_location, GmiCommon::SAVED_SESSION_FOLDER, '/');

        // If chrome profile is existed, Preferences file is there
        // If chrome had never run, run it and profile folder will be created.
        // Copy out Preferences file from container to host, and embed our setting to the json
        // Then replace new file.
        // if(file_exists(GmiCommon::join_file_paths($outside_saved_session_folder_path, GmiCommon::PREFERENCE_FILE_NAME))){
        //     $term_prefs_file = GmiCommon::join_file_paths($outside_saved_session_folder_path, GmiCommon::PREFERENCE_FILE_NAME);
        //     $this->start_chrome_first_time();
        // } else
        if ($this->file_exists_incontainer($this->node_name, $inside_prefs_profile_file)) {
            $this->log("Preferences existed");
            exec("sudo docker cp $this->node_name:$inside_prefs_profile_file $term_prefs_file");
        } else {
            $this->start_chrome_first_time();
            exec("sudo docker cp $this->node_name:$inside_prefs_profile_file $term_prefs_file");
        }

        $this->log("Read Preference from: $term_prefs_file");
        $str = file_get_contents($term_prefs_file);

        $preferences = json_decode($str);
        // Add customized setting
        $download_default_directory = '/home/seluser/Downloads/' . $this->process_uid . '/';
        if (!empty($this->config_array['download_map'])) {
            $download_default_directory = $this->config_array['download_map'];
        }

        $preferences = $this->update_preference_setting($preferences, 'download.default_directory', $download_default_directory);
        $preferences = $this->update_preference_setting($preferences, 'download.prompt_for_download', false);
        $preferences = $this->update_preference_setting($preferences, 'download.directory_upgrade', true);
        $preferences = $this->update_preference_setting($preferences, 'download_bubble.partial_view_enabled', false);
        $preferences = $this->update_preference_setting($preferences, 'profile.default_content_setting_values.automatic_downloads', 1);
        $preferences = $this->update_preference_setting($preferences, 'profile.default_content_setting_values.notifications', 2);
        $preferences = $this->update_preference_setting($preferences, "profile.exit_type", "Normal"); // Crashed, Normal
        $preferences = $this->update_preference_setting($preferences, "profile.password_manager_enabled", false);
        $preferences = $this->update_preference_setting($preferences, "credentials_enable_service", false);
        $preferences = $this->update_preference_setting($preferences, "plugins.always_open_pdf_externally", true);
        $preferences = $this->update_preference_setting(
            $preferences,
            "printing.print_preview_sticky_settings.appState",
            json_decode(
                '
            {
                "version": 2,
                "selectedDestinationId": "Save as PDF",
                "recentDestinations": {
                    "id": "Save as PDF",
                    "origin": "local"
                },
                "isHeaderFooterEnabled": false
            }
        '
            )
        );
        $preferences = $this->update_preference_setting($preferences, "printing.print_preview_sticky_settings.savePath", $download_default_directory);
        $preferences = $this->update_preference_setting($preferences, "savefile.default_directory", $download_default_directory);

        $file_string = json_encode($preferences);
        file_put_contents($modified_prefs_file, $file_string);

        exec("sudo chmod -R 777 $modified_prefs_file");
        exec("sudo docker exec -i $this->node_name sh -c 'sudo mkdir -p $inside_profile_default_folder && sudo chmod -R 777 $inside_profile_default_folder'");
        exec("sudo docker cp $modified_prefs_file $this->node_name:$inside_prefs_profile_file");
        exec("sudo docker exec -i $this->node_name sh -c 'sudo chmod -R 777 $this->user_data_dir'");
        exec("sudo docker exec -i $this->node_name sh -c 'sudo chmod -R 777 $inside_profile_default_folder'");
    }

    public function start_chrome_first_time()
    {
        $this->log("Start chrome first time in background.");
        $inside_prefs_profile_path = $this->user_data_dir . "/Default/Preferences";
        $flat_option = implode(' ', $this->chrome_browser_options_array);
        for ($i = 1; $i < 9; $i++) {
            $output_chrome = [];
            // exec('sudo docker exec -i selenium-node- sh -c "sudo xdotool version"', $output);
            exec('sudo docker exec -i ' . $this->node_name . ' sh -c "google-chrome ' . $flat_option . ' &" 2>&1', $output_chrome);
            $check_text = print_r($output_chrome, true);
            $this->log($check_text);
            if (stripos($check_text, 'DevTools listening') !== false || stripos($check_text, 'Opening in existing browser') !== false) {
                exec("sudo docker exec -i $this->node_name pkill chrome");
                usleep(200 * 1000);
                exec("sudo docker exec -i " . $this->node_name . " sudo chmod 777 $this->user_data_dir/Default/");
                usleep(100 * 1000);
                if ($this->file_exists_incontainer($this->node_name, $inside_prefs_profile_path)) {
                    $this->log('Container is ready');
                    $this->log("Preferences created at $i");
                    break;
                }
                sleep(1);
                break;
            }
            exec("sudo docker exec -i $this->node_name pkill chrome");
            usleep(200 * 1000);
        }
    }

    public function update_preference_setting($preferences, $property_path, $value)
    {
        $paths = explode('.', $property_path);
        $target = $preferences;
        foreach ($paths as $key => $property) {
            if ($key == count($paths) - 1) {
                $target->$property = $value;
            } else {
                if (isset($target->$property) && is_object($target->$property)) {
                    $target = $target->$property;
                } else {
                    $target->$property = new stdClass();
                    $target = $target->$property;
                }
            }
        }

        return $preferences;
    }

    public function file_exists_incontainer($container_name, $file_path)
    {
        $this->log(__FUNCTION__ . " $container_name $file_path");
        exec("sudo docker exec -i $container_name sh -c 'ls $file_path' 2>&1", $output);
        $this->log(__FUNCTION__ . print_r($output, true));
        if (isset($output[0]) && $output[0] == $file_path) {
            return true;
        } else {
            return false;
        }
    }

    public function load_session_files($restart_browser = false)
    {
        $this->log(__FUNCTION__);
        $outside_saved_session_folder_path = GmiCommon::join_file_paths($this->screen_capture_location, GmiCommon::SAVED_SESSION_FOLDER);
        $term_cookies_file = $this->screen_capture_location . 'Cookies';
        $term_webdata_file = $this->screen_capture_location . 'WebData';

        $session_source_path = $outside_saved_session_folder_path;
        if (!file_exists(rtrim($session_source_path, '/') . '/Cookies')) {
            $session_source_path = $this->load_session_files_from_saved_profile();
            if ($session_source_path) {
                $this->log('Copied session from saved profile to ' . $session_source_path);
            }
        } else {
            $this->log("Load from $session_source_path");
        }

        // Step 1: Copy Cookies and Web Data from container out to processing folder
        // Step 2: Transfer data from saved profile to Cookies and Web Data.
        // Step 3: Copy processed database file in to container and overwrite them.
        $inside_profile_default_folder = GmiCommon::join_file_paths($this->user_data_dir, GmiCommon::PROFILE_DEFAULT_FOLDER);
        // Nomally, Cookie file is located in profile/Default, but sometime it is in profile/Default/Network, copy file out in this case
        exec(
            "sudo docker exec -i $this->node_name sh -c 'sudo [ -f $inside_profile_default_folder/Network/Cookies ] && cp $inside_profile_default_folder/Network/Cookies $inside_profile_default_folder'"
        );
        // Copy Cookies and Web Data from container to host for processing
        exec("docker cp $this->node_name:$inside_profile_default_folder/Cookies $term_cookies_file");
        exec("docker cp $this->node_name:$inside_profile_default_folder/'Web Data' $term_webdata_file");

        // Compatible transfer cookie and web data.
        $this->sqlite_transfer_database($session_source_path . '/Cookies', $term_cookies_file, ['cookies']);
        $this->sqlite_transfer_database($session_source_path . '/Web Data', $term_webdata_file, ['autofill', 'token_service']);

        // move back to container
        $this->log($inside_profile_default_folder);
        exec("sudo docker exec -i $this->node_name sh -c 'sudo chmod -R 777 $inside_profile_default_folder'");

        exec("sudo chmod -R 777 $term_cookies_file");
        exec("sudo chmod -R 777 $term_webdata_file");
        $this->log("Overwrite session files to container");
        $this->log("sudo docker cp $term_cookies_file $this->node_name:$inside_profile_default_folder/Cookies");
        exec("sudo docker cp $term_cookies_file $this->node_name:$inside_profile_default_folder/Cookies");
        exec("sudo docker cp $term_webdata_file $this->node_name:$inside_profile_default_folder/'Web Data'");
        exec("sudo docker exec -i $this->node_name sh -c 'sudo chmod -R 777 $inside_profile_default_folder'");

        if ($restart_browser) {
            $this->restart_browser();
        }
    }

    public function dump_session_files()
    {
        $this->log(__FUNCTION__);
        $list_of_session_files = GmiCommon::get_list_session_files();
        $outside_saved_session_folder_path = GmiCommon::join_file_paths($this->screen_capture_location, GmiCommon::SAVED_SESSION_FOLDER, '/');
        $inside_profile_default_folder = GmiCommon::join_file_paths($this->user_data_dir, GmiCommon::PROFILE_DEFAULT_FOLDER);
        $inside_profile_network_folder = GmiCommon::join_file_paths($inside_profile_default_folder, GmiCommon::PROFILE_DEFAULT_NETWORK_FOLDER);

        exec("sudo mkdir -p $outside_saved_session_folder_path");
        exec("sudo docker exec -i " . $this->node_name . " sudo chmod -R 777 $inside_profile_default_folder");
        $this->log("sudo docker exec -i " . $this->node_name . " sudo chmod -R 777 $inside_profile_default_folder");

        foreach ($list_of_session_files as $session_file_name) {
            $inside_file_path = "$this->node_name:$inside_profile_default_folder/'$session_file_name'";
            exec("sudo docker cp $inside_file_path $outside_saved_session_folder_path");
        }
        exec("sudo docker exec -i $this->node_name test -d $inside_profile_network_folder && echo 1; 2>&1", $output1);
        $cookie_in_network = isset($output1[0]) && $output1[0] == '1';
        if ($cookie_in_network) {
            exec("sudo docker cp $this->node_name:$inside_profile_network_folder/Cookies $outside_saved_session_folder_path");
            exec("sudo docker cp $this->node_name:$inside_profile_network_folder/'Trust Tokens' $outside_saved_session_folder_path");
        }
        exec("sudo chmod -R 777 '$outside_saved_session_folder_path'");
    }

    public function load_session_files_from_saved_profile($profile_file_location = '')
    {
        if (empty($profile_file_location) && isset($this->config_array['profile_file_location'])) {
            $profile_file_location = $this->config_array['profile_file_location'];
        }
        if (!file_exists(GmiCommon::join_file_paths($profile_file_location, GmiCommon::PROFILE_DEFAULT_FOLDER))) {
            $this->log('Old profile folder is empty - Skip loading session from old profile.');

            return false;
        }
        $this->log(__FUNCTION__ . ': ' . $profile_file_location);
        $list_of_session_files = GmiCommon::get_list_session_files();
        $temp_processing_folder = GmiCommon::join_file_paths($this->screen_capture_location, 'temp');
        exec("sudo mkdir -p $temp_processing_folder");
        exec("sudo chmod -R 777 $temp_processing_folder");
        foreach ($list_of_session_files as $session_file_name) {
            if ($session_file_name == GmiCommon::PREFERENCE_FILE_NAME) {
                continue;
            }
            // Find the file in Default folder
            $source_file_path = GmiCommon::join_file_paths($profile_file_location, GmiCommon::PROFILE_DEFAULT_FOLDER, $session_file_name);
            // If the file is not in Default folder, maybe it is in Default/Network folder
            if (!file_exists($source_file_path)) {
                $source_file_path = GmiCommon::join_file_paths($profile_file_location, GmiCommon::PROFILE_DEFAULT_FOLDER, GmiCommon::PROFILE_DEFAULT_NETWORK_FOLDER, $session_file_name);
            }
            if (file_exists($source_file_path)) {
                exec("sudo cp '$source_file_path' $temp_processing_folder");
            } else {
                $this->log("Session file $session_file_name does not found.");
            }
        }

        return $temp_processing_folder;
    }

    // Processing sqlite database
    public function sqlite_transfer_database(string $source_db_file_path, string $target_db_file_path, $table_list = [])
    {
        try {
            // verify source/target existed
            if (!file_exists($source_db_file_path)) {
                $this->log(__FUNCTION__ . " source database file not found $source_db_file_path");

                return;
            }
            if (!file_exists($target_db_file_path)) {
                $this->log(__FUNCTION__ . " target database file not found $target_db_file_path");

                return;
            }

            $sqlite_connection = new \SQLite3($target_db_file_path, \SQLITE3_OPEN_READWRITE);
            $sqlite_connection->exec("ATTACH '$source_db_file_path' as source_database");
            if (empty($table_list)) {
                // transfer all tables.
                // Not needed currently, implement then
            }
            foreach ($table_list as $table_name) {
                $this->compatible_transfer_table($sqlite_connection, $table_name);
            }
            $sqlite_connection->close();
        } catch (\Exception $e) {
            $this->log($e->getMessage());
        }
    }

    public function compatible_transfer_table(SQLite3 $sqlite_connection, string $table_name, string $new_table_name = '')
    {
        $source_columns = $this->sqlite_get_table_columns($sqlite_connection, $table_name, 'source_database');
        if (empty($source_columns)) {
            // If columns empty, it's mean table doesn't existed in database
            $this->log(__FUNCTION__ . "WARNING: source table $table_name do not found.");

            return;
        }

        $target_columns = $this->sqlite_get_table_columns($sqlite_connection, $table_name);
        // If target table does not existed OR need a new table name, create new table.
        // Else check columns and compatible copy data from source to target
        if (empty($source_columns) || (!empty($new_table_name) && $new_table_name !== $table_name)) {
            $create_table_query = $sqlite_connection->querySingle("SELECT sql FROM source_database.sqlite_master WHERE type='table' AND name='$table_name'");
            if (!empty($new_table_name)) {
                $create_table_query = str_replace("CREATE TABLE $table_name", "CREATE TABLE $new_table_name", $create_table_query);
            } else {
                $new_table_name = $table_name;
            }
            $this->log($create_table_query);
            $sqlite_connection->exec($create_table_query);

            $insert_query = "INSERT OR REPLACE into $new_table_name SELECT * FROM source_database." . $table_name . ";";
            $sqlite_connection->exec($insert_query);
        } else {
            $insert_fields = [];
            foreach ($target_columns as $target_column) {
                $found = false;
                foreach ($source_columns as $source_column) {
                    if ($target_column["name"] == $source_column["name"] && $target_column["type"] == $source_column["type"]) {
                        $found = true;
                    }
                }

                if ($found) {
                    array_push($insert_fields, $target_column["name"]);
                } else {
                    $dflt_value = null;
                    if ($target_column["notnull"] === 1) {
                        switch ($target_column["type"]) {
                            case 'INTEGER':
                            case 'REAL':
                            case 'NUMERIC':
                                if (empty($target_column["dflt_value"])) {
                                    $dflt_value = '0 AS ' . $target_column["name"];
                                } else {
                                    $dflt_value = $target_column["dflt_value"] . ' AS ' . $target_column["name"];
                                }
                                break;
                            case 'TEXT':
                                if (empty($target_column["dflt_value"])) {
                                    $dflt_value = '"" AS ' . $target_column["name"];
                                } else {
                                    $dflt_value = '"' . $target_column["dflt_value"] . '" AS ' . $target_column["name"];
                                }
                                break;
                            default:
                                $dflt_value = '"" AS ' . $target_column["name"];
                                break;
                        }
                    } else {
                        $dflt_value = 'NULL AS ' . $target_column["name"];
                    }

                    array_push($insert_fields, $dflt_value);
                }
            }

            $clean_query = "BEGIN TRANSACTION; DELETE FROM $table_name; COMMIT;";
            $insert_query = "INSERT OR REPLACE into $table_name SELECT " . implode(', ', $insert_fields) . " FROM source_database." . $table_name . ";";

            $sqlite_connection->exec($clean_query);
            $this->log($insert_query);
            $sqlite_connection->exec($insert_query);
            $this->log("$table_name records transfered: " . $sqlite_connection->changes());
        }
    }

    public function sqlite_get_table_columns(SQLite3 $sqlite_connection, string $table_name, string $attached_db_name = null)
    {
        $query_string = 'PRAGMA table_info("' . $table_name . '");';
        if (!empty($attached_db_name)) {
            $query_string = 'PRAGMA ' . $attached_db_name . '.table_info("' . $table_name . '");'; // PRAGMA target_cookie.table_info("cookies");
        }

        $execute_result = $sqlite_connection->query($query_string);
        $table_columns = [];
        while ($row = $execute_result->fetchArray()) {
            $detail = [
                "cid" => $row[0],
                "name" => $row[1],
                "type" => $row[2],
                "notnull" => $row[3],
                "dflt_value" => $row[4],
                "pk" => $row[5],
            ];
            array_push($table_columns, $detail);
        }

        return $table_columns;
    }

    public function make_connection()
    {
        $this->log(__FUNCTION__);
        $this->browser = $this->get_browsers();
        for ($i = 0; $i < 5; $i++) {
            $target_json_list = $this->get_all_target_socket_json();
            $this->init_tab = $this->create_init_tab($target_json_list);
            if (!empty($this->init_tab)) {
                break;
            }
            sleep(2);
        }

        if (!empty($this->init_tab)) {
            $this->current_chrome_tab = clone $this->init_tab;
            $this->switchToTab($this->current_chrome_tab);
        } else {
            $this->log('Can not connect to Chrome');
        }
    }

    public function get_browsers()
    {
        try {
            $response_text = $this->send_remote_request('GET', $this->remote_url . GmiCommon::BROWSER_VERSION);
            $current_browser = json_decode($response_text, true);
            return $current_browser;
        } catch (Exception $e) {
            $this->log($e->getMessage());
        }
    }

    public function send_remote_request($method = 'GET', $url = '', $data = null)
    {
        $curl = curl_init();
        switch ($method) {
            case "POST":
                curl_setopt($curl, CURLOPT_POST, 1);
                if ($data) {
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                }
                break;
            case "PUT":
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
                if ($data) {
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                }
                break;
            default:
                if ($data) {
                    $url = sprintf("%s?%s", $url, http_build_query($data));
                }
        }
        // OPTIONS:
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        // EXECUTE:
        $result = curl_exec($curl);

        curl_close($curl);

        return $result;
    }

    public function send_websocket_event($url = '', $method = '', $expecting_message, $params = null)
    {
        $this->log(__FUNCTION__);
        if (empty($url)) {
            $client = new Client($this->current_context->webSocketDebuggerUrl);
        } else {
            $client = new Client($url);
        }

        if (empty($params)) {
            $params = null;
        }
        $this->socket_request_id++;
        $request_id = $this->socket_request_id;
        $data = [
            'id' => $request_id,
            'method' => $method,
            "params" => $params,
        ];
        $client->text(json_encode($data));
        if (empty($expecting_message)) {
            return $client;
        }
        // else listening message
        $count = 0;
        while (true) {
            try {
                $count++;
                $this->log("Listening $count");
                $message = $client->receive();
                $this->log($message);
                if (stripos($message, $expecting_message) !== false) {
                    $client->disconnect();

                    return $message;
                }
                if ($count > 35) {
                    break;
                }
            } catch (\WebSocket\ConnectionException $e) {
                // Possibly log errors
            }
        }

        $client->disconnect();

        return null;
    }

    public function send_websocket_request($url = '', $method = '', $params = '', $timeout_retry = false, $log_message = false)
    {
        if ($log_message) {
            $this->log(__FUNCTION__ . " $url");
        }
        $this->socket_request_id++;
        $request_id = $this->socket_request_id;
        for ($i = 0; $i < 3; $i++) {
            try {
                $client = new Client($url);

                if (empty($params)) {
                    $params = null;
                }
                $data = [
                    'id'     => $request_id,
                    'method' => $method,
                    "params" => $params,
                ];
                $client->text(json_encode($data));

                // Read response (this is blocking)
                $message = $client->receive();
                if ($log_message) {
                    echo " \n Got message: $message \n";
                }

                // Close connection
                $client->disconnect();

                return $message;
            } catch (Exception $e) {
                $exception_content = $e->getMessage();
                $this->log('Exception: ' . $exception_content);

                if (
                    (stripos($exception_content, '500 Internal Server Error') || stripos($exception_content, 'Unexpected server response: 404') !== false) &&
                    ($this->current_context->executionContextId > 0 || $this->current_context->type == GmiBrowserTarget::TargetTypeFrame)
                ) {
                    $this->switch_to_default();
                    return;
                } else if (stripos($exception_content, 'handshake') !== false) {
                    $this->log("Retry socker request " . $i);
                    $request_id = rand(10000, 999999);
                } else if (stripos($exception_content, 'Client read timeout') !== false && $timeout_retry) {
                    $this->log("Retry socker request " . $i);
                    $request_id = rand(10000, 999999);
                } else {
                    break;
                }
            }
        }
    }

    public function send_websocket_request_multi($url = '', $requests, $log_message = false)
    {
        $client = new Client($url);
        $this->socket_request_id++;
        foreach ($requests as $request) {
            if (!isset($request["params"]) || empty($request["params"])) {
                $request["params"] = null;
            }
            $data = [
                'id' => $this->socket_request_id,
                'method' => $request["method"],
                "params" => $request["params"],
            ];
            // print_r($data);
            $client->text(json_encode($data));
        }
        // Close connection
        $client->disconnect();
        if ($log_message) {
            $this->log('Multi socket request have sent');
        }
    }

    public function search_all($parent_array, $needed_key = '', $needed_value = null)
    {
        $result = [];
        foreach ($parent_array as $key => $element) {
            if (isset($element[$needed_key])) {
                if ($element[$needed_key] === $needed_value) {
                    array_push($result, $element);
                }
            }
        }

        return $result;
    }

    public function search_one($parent_array, $needed_key = '', $needed_value = null)
    {
        $return_object = null;
        foreach ($parent_array as $key => $element) {
            if (isset($element[$needed_key])) {
                if ($element[$needed_key] == $needed_value) {
                    $return_object = $element;
                    break;
                }
            }
        }

        return $return_object;
    }

    public function restart_browser()
    {
        $this->log(__FUNCTION__);
        $this->send_remote_request('PUT', 'localhost:9990/json/new?chrome://restart');
        $this->current_chrome_tab = null;
        $this->current_context = null;
        $this->make_connection();
        $this->log('Browser restarted');
    }

    // START Block of util functions for tab/iframe processing
    public function create_mini_manager(GmiBrowserTarget $target = null): ?GmiChromeManager
    {
        $mini_manager = new GmiChromeManager();
        $mini_manager->process_uid = $this->process_uid;
        $mini_manager->remote_url = $this->remote_url;
        if (isset($this->config_array)) {
            $mini_manager->config_array = $this->config_array;
            $mini_manager->node_name = !empty($this->config_array['node_name']) ? $this->config_array['node_name'] : "selenium-node-" . $this->process_uid;
            $mini_manager->user_data_dir = "/home/seluser/profiles/" . $this->process_uid;
            if (!empty($this->config_array['user_data_dir'])) {
                $mini_manager->user_data_dir = $this->config_array['user_data_dir'];
            }
        }

        // set target for mini manager
        $mini_manager->current_context = $target;
        return $mini_manager;
    }

    public function filter_target_from_json($target_json_list, $target_types = []): array
    {
        $return_targets = [];
        foreach ($target_json_list as $instance) {
            if (in_array($instance['type'], [GmiBrowserTarget::TargetTypePage, GmiBrowserTarget::TargetTypeTab, GmiBrowserTarget::TargetTypeFrame])) {
                if ($instance['type'] == GmiBrowserTarget::TargetTypePage || $instance['type'] == GmiBrowserTarget::TargetTypeTab) {
                    if (stripos($instance['url'], 'chrome-extension:') === 0 || stripos($instance['url'], 'devtools://') === 0) {
                        // if url start with chrome-extension or devtool, it isn't browser tab
                        $this->log('Invalid tab uri: ' . $instance['url']);
                        continue;
                    } else {
                        if (stripos($instance['url'], 'chrome://privacy-sandbox-dialog/') === 0) {
                            // die;// DEBUGGING
                            $param = [
                                'expression' => 'document.querySelector("body > privacy-sandbox-notice-dialog-app").shadowRoot.querySelector("#ackButton").click();',
                            ];
                            $response_text = $this->send_websocket_request($instance['webSocketDebuggerUrl'], 'Runtime.evaluate', $param);
                            $this->log('Closed Privacy dialog: ' . $instance['url']);
                            continue;
                        } else {
                            if (stripos($instance['url'], 'chrome://privacy-sandbox-dialog/combined') === 0) {
                                $param = [
                                    'expression' => 'document.querySelector("body > privacy-sandbox-combined-dialog-app").shadowRoot.querySelector("#consent").shadowRoot.querySelector("#declineButton").click();',
                                ];
                                $response_text = $this->send_websocket_request($instance['webSocketDebuggerUrl'], 'Runtime.evaluate', $param);
                                sleep(3);
                                $param = [
                                    'expression' => 'document.querySelector("body > privacy-sandbox-combined-dialog-app").shadowRoot.querySelector("#notice").shadowRoot.querySelector("#ackButton").click();',
                                ];
                                $response_text = $this->send_websocket_request($instance['webSocketDebuggerUrl'], 'Runtime.evaluate', $param);
                                $this->log('Closed Privacy dialog: ' . $instance['url']);
                                continue;
                            }
                        }
                    }
                }

                if (in_array($instance['type'], $target_types)) {
                    $target = GmiBrowserTarget::createFromSocketJson($instance, $this->remote_url);
                    array_push($return_targets, $target);
                }
            } else {
                if (in_array($instance['type'], $target_types)) {
                    $target = GmiBrowserTarget::createFromSocketJson($instance, $this->remote_url);
                    array_push($return_targets, $target);
                }
            }
        }

        return $return_targets;
    }

    public function filter_target($target_list = [], $target_types = [])
    {
        $return_targets = [];
        foreach ($target_list as $target) {
            if ($target->type == GmiBrowserTarget::TargetTypePage || $target->type == GmiBrowserTarget::TargetTypeTab) {
                if (stripos($target->url, 'chrome-extension:') === 0 || stripos($target->url, 'devtools://') === 0) {
                    // if url start with chrome-extension or devtool, it isn't browser tab
                    $this->log('Invalid tab uri: ' . $target->url);
                    continue;
                } else {
                    if (stripos($target->url, 'chrome://privacy-sandbox-dialog/') === 0) {
                        // die;// DEBUGGING
                        $param = [
                            'expression' => 'document.querySelector("body > privacy-sandbox-notice-dialog-app").shadowRoot.querySelector("#ackButton").click();',
                        ];
                        $response_text = $this->send_websocket_request($target->webSocketDebuggerUrl, 'Runtime.evaluate', $param);
                        $this->log('Closed Privacy dialog: ' . $target->url);
                        continue;
                    } else {
                        if (stripos($target->url, 'chrome://privacy-sandbox-dialog/combined') === 0) {
                            $param = [
                                'expression' => 'document.querySelector("body > privacy-sandbox-combined-dialog-app").shadowRoot.querySelector("#consent").shadowRoot.querySelector("#declineButton").click();',
                            ];
                            $response_text = $this->send_websocket_request($target->webSocketDebuggerUrl, 'Runtime.evaluate', $param);
                            sleep(3);
                            $param = [
                                'expression' => 'document.querySelector("body > privacy-sandbox-combined-dialog-app").shadowRoot.querySelector("#notice").shadowRoot.querySelector("#ackButton").click();',
                            ];
                            $response_text = $this->send_websocket_request($target->webSocketDebuggerUrl, 'Runtime.evaluate', $param);
                            $this->log('Closed Privacy dialog: ' . $target->url);
                            continue;
                        }
                    }
                }

                if (in_array($target->type, $target_types)) {
                    array_push($return_targets, $target);
                }
            } else {
                if (in_array($target->type, $target_types)) {
                    array_push($return_targets, $target);
                }
            }
        }

        return $return_targets;
    }

    public function get_browser_window_detail($target)
    {
        try {
            $response_text = $this->send_websocket_request($target->webSocketDebuggerUrl, 'Browser.getWindowForTarget');
            $result = json_decode($response_text, true);
            $browser_window_detail = $result["result"];

            return $browser_window_detail;
        } catch (Exception $e) {
            $this->log($e->getMessage());
        }

        return null;
    }

    public function get_all_target_socket_json()
    {
        $target_json_list = [];

        try {
            $response_text = $this->send_remote_request('GET', $this->remote_url . GmiCommon::LIST_TARGET);
            $target_json_list = json_decode($response_text, true);
        } catch (Exception $e) {
            $this->log($e->getMessage());
        }

        return $target_json_list;
    }

    public function get_all_target_infors_json()
    {
        $targetInfos = [];

        try {
            $response_text = $this->send_websocket_request($this->current_context->webSocketDebuggerUrl, 'Target.getTargets');
            $response_json = json_decode($response_text, true);
            $targetInfos = $response_json["result"]["targetInfos"];
        } catch (Exception $e) {
            $this->log($e->getMessage());
        }

        return $targetInfos;
    }

    public function get_target_infor_json($targetId)
    {
        $targetInfo = null;

        try {
            $response_text = $this->send_websocket_request($this->current_context->webSocketDebuggerUrl, 'Target.getTargetInfo', ["targetId" => $targetId]);
            $response_json = json_decode($response_text, true);
            $targetInfo = $response_json["result"]["targetInfo"];
        } catch (Exception $e) {
            $this->log($e->getMessage());
        }

        return $targetInfo;
    }

    public function get_all_target_full_infor()
    {
        $targets = [];
        $targets_from_socket = $this->get_all_target_socket_json();
        foreach ($targets_from_socket as $targetJson) {
            $target = GmiBrowserTarget::createFromSocketJson($targetJson, $this->remote_url);
            array_push($targets, $target);
        }

        if (empty($this->current_context)) {
            foreach ($targets as $target) {
                $target->updateDetailFromTargetJson($this->get_target_infor_json($target->id));
                $target->get_browser_window_detail($this);
            }
        } else {
            $targetInforJsons = $this->get_all_target_infors_json();
            foreach ($targets as $target) {
                foreach ($targetInforJsons as $target_infor) {
                    if ($target->id === $target_infor['targetId']) {
                        $target->updateDetailFromTargetJson($target_infor);
                        $target->get_browser_window_detail($this);
                        break;
                    }
                }
            }
        }

        return $targets;
    }
    // END Block of util functions for tab/iframe processing


    // START Block of chrome tab handling functions
    public function create_init_tab($target_json_list = [])
    {
        $tabs = $this->filter_target_from_json($target_json_list, [GmiBrowserTarget::TargetTypePage, GmiBrowserTarget::TargetTypeTab]);

        return reset($tabs);
    }

    public function get_all_tabs(): array
    {
        $target_json_list = $this->get_all_target_socket_json();
        $tab_pages = $this->filter_target_from_json($target_json_list, [GmiBrowserTarget::TargetTypePage, GmiBrowserTarget::TargetTypeTab]);

        return $tab_pages;
    }

    public function find_target_matched_url($url_patterns = [], $target_type = ''): ?GmiBrowserTarget
    {
        if (empty($url_patterns)) {
            return null;
        }
        $targets = $this->get_all_target_socket_json();
        foreach ($targets as $target) {
            $matched = true;
            foreach ($url_patterns as $url_path) {
                if (stripos($target["url"], $url_path) === false) {
                    $matched = false;
                    break;
                }
            }

            if ($matched && ($target['type'] == $target_type || empty($target_type))) {
                $gmiTarget = GmiBrowserTarget::createFromSocketJson($target);

                return $gmiTarget;
            }
        }

        return null;
    }

    public function find_target_matched_condition(callable $finding_condition_function = null, $target_type = ''): ?GmiBrowserTarget
    {
        if (!is_callable($finding_condition_function)) {
            return null;
        }
        $targets = $this->get_all_target_socket_json();
        $mini_manager = $this->create_mini_manager();
        foreach ($targets as $target) {
            if (($target['type'] == $target_type || empty($target_type))) {
                $gmiTarget = GmiBrowserTarget::createFromSocketJson($target);
                $mini_manager->current_context = $gmiTarget;
                if ($finding_condition_function($mini_manager) === true) {
                    return $gmiTarget;
                }
            }
        }

        return null;
    }

    public function switchToTab(GmiBrowserTarget $target_tab): void
    {
        $this->log(__FUNCTION__);
        try {
            $this->send_websocket_request($target_tab->webSocketDebuggerUrl, 'Target.activateTarget', ["targetId" => $target_tab->id], false, true);
            $this->current_chrome_tab = $target_tab;
            $this->current_context = clone $this->current_chrome_tab;
        } catch (Exception $e) {
            $this->log('Exception: ' . $e->getMessage());
        }
    }

    public function switchToInitTab(): void
    {
        $this->switchToTab($this->init_tab);
    }

    public function markCurrentTabByName(string $marked_name = ''): ?GmiBrowserTarget
    {
        $clone_tab = clone $this->current_chrome_tab;
        if (empty($marked_name)) {
            $this->log('Empty marked_name is given, No tab add to marked array.');
        }

        $this->marked_tabs[$marked_name] = $clone_tab;
        return $clone_tab;
    }

    public function getMarkedTab($marked_name): ?GmiBrowserTarget
    {
        if (empty($this->marked_tabs)) {
            $this->log('Marked name is empty - no tab was found');
            return null;
        }
        $current_tab_pages = $this->get_all_tabs();
        if (isset($this->marked_tabs[$marked_name])) {
            if ($this->marked_tabs[$marked_name]->inArray($current_tab_pages)) {
                return $this->marked_tabs[$marked_name];
            } else {
                $this->log("Seem tab marked with name $marked_name has been closed");
            }
        } else {
            $this->log("No tab marked with name $marked_name was found");
        }

        return null;
    }

    public function findTabMatchedCondition(callable $finding_condition_function = null): ?GmiBrowserTarget
    {
        $tab = $this->find_target_matched_condition($finding_condition_function, GmiBrowserTarget::TargetTypePage);

        if ($tab == null) {
            $this->log(__FUNCTION__ . " No tab matched");
            return null;
        } else {
            $this->log(__FUNCTION__ . " Tab found");
            return $tab;
        }
    }

    public function findTabMatchedUrl($url_patterns = []): ?GmiBrowserTarget
    {
        $tab = $this->find_target_matched_url($url_patterns, GmiBrowserTarget::TargetTypePage);
        if ($tab == null) {
            $tab = $this->find_target_matched_url($url_patterns, GmiBrowserTarget::TargetTypeTab);
        }

        if ($tab == null) {
            $this->log(__FUNCTION__ . " No tab matched with this url pattern");
            $this->log(print_r($url_patterns, true));
            return null;
        } else {
            $this->log(__FUNCTION__ . " Tab found");
            return $tab;
        }
    }

    public function openNewTab($url = '', $new_window = false, $attachAlso = true): ?GmiBrowserTarget
    {
        try {
            $params = [
                "url" => $url,
                "newWindow" => $new_window,
            ];
            $response_text = $this->send_websocket_request($this->current_context->webSocketDebuggerUrl, 'Target.createTarget', $params);
            $result = json_decode($response_text, true);
            $targetId = $result["result"]["targetId"];
            $target = GmiBrowserTarget::createFromValues(GmiBrowserTarget::TargetTypePage, $targetId, $this->remote_url);
            if ($attachAlso) {
                $this->switchToTab($target);
            }

            return $target;
        } catch (Exception $e) {
            $this->log($e->getMessage());
        }

        return null;
    }

    public function closeTab(GmiBrowserTarget $tab_to_close, GmiBrowserTarget $back_to_tab = null): void
    {
        try {
            $this->send_websocket_request($tab_to_close->webSocketDebuggerUrl, 'Target.closeTarget', ["targetId" => $tab_to_close->id]);

            // switch to expecting tab
            if (empty($back_to_tab)) {
                // if back_to_tab has not passed, go back to init tab
                // if init tab is closed, switch to first tab stays still
                if ($tab_to_close->isSameWith($this->init_tab, true)) {
                    $tabs_after_close = $this->get_all_tabs();
                    $this->switchToTab(reset($tabs_after_close));
                } else {
                    $this->switchToInitTab();
                }
            } else {
                $this->switchToTab($back_to_tab);
            }
        } catch (Exception $e) {
            $this->log('Exception: ' . $e->getMessage());
        }
    }

    public function closeCurrentTab(): void
    {
        $this->closeTab($this->current_chrome_tab);
    }

    public function closeAllTabsButThis(): void
    {
        // keep current tab and close all the rest
        $this->closeAllTabsExcept();
    }

    public function closeAllTabsExcept(array $tabs_will_kept = []): void
    {
        try {
            $exclude_tabs = [];
            if (empty($tabs_will_kept)) {
                // If no excluded tabs passed in, keep current tab
                array_push($exclude_tabs, $this->current_chrome_tab);
            } else {
                $exclude_tabs = $tabs_will_kept;
            }

            $all_tabs = $this->get_all_tabs();
            foreach ($all_tabs as $tab) {
                if ($tab->inArray($exclude_tabs)) {
                    continue; // skip this tab
                } else {
                    $this->send_websocket_request($tab->webSocketDebuggerUrl, 'Target.closeTarget', ["targetId" => $tab->id], false, true);
                }
            }

            // switch to kept tab
            if (!empty($tabs_will_kept)) {
                $tabs_after_close = $this->get_all_tabs();
                $this->switchToTab(reset($tabs_after_close));
            }
        } catch (Exception $e) {
            $this->log('Exception: ' . $e->getMessage());
        }
    }

    public function startTrakingTabsChange(): void
    {
        $tab_snapshot = [];
        $tab_snapshot['tab_snapshot'] = $this->get_all_tabs();
        $tab_snapshot['time'] = time();
        $this->log('Started tracking tabs change at ' . date('Y-m-d H:i:s', $tab_snapshot['time']));
        $GLOBALS['tab_snapshot'] = $tab_snapshot;
    }

    public function switchToIfNewTabOpened(): bool
    {
        if (isset($GLOBALS['tab_snapshot'])) {
            $before = $GLOBALS['tab_snapshot']['tab_snapshot'];
            $after = $this->get_all_tabs();
            $diff = GmiBrowserTarget::targetArrayDiff($before, $after);
            if (!empty($diff->notInLeft)) {
                $this->switchToTab(reset($diff->notInLeft));
                $this->log('Switched to new tab');
                return true;
            } else {
                $this->log('No new tab has found since ' . date('Y-m-d H:i:s', $GLOBALS['tab_snapshot']['time']));
                $this->log('Stay at current tab still');
            }
        } else {
            $this->log('Switch to new tab failed. You have not started tab tracking yet. Please call startTrakingTabsChange function before new tab opened.');
        }
        return false;
    }

    public function switchToNewestActiveTab(): void
    {
        $tab_pages = $this->get_all_tabs();
        $this->switchToTab(reset($tab_pages));
    }

    public function switchToOldestActiveTab(): void
    {
        $tab_pages = $this->get_all_tabs();
        $this->switchToTab(end($tab_pages));
    }

    // This function is for manual process ONLY
    public function force_close_tab_by_url($searching_url): void
    {
        $target_json_list = [];

        try {
            $response_text = $this->send_remote_request('GET', rtrim($this->remote_url, '/') . '/json');
            $target_json_list = json_decode($response_text, true);
        } catch (Exception $e) {
            $this->log($e->getMessage());
        }

        foreach ($target_json_list as $target_json) {
            if (stripos($target_json['url'], $searching_url) !== false) {
                $this->log("Tab found - closing it");
                $this->log(print_r($target_json, true));

                try {
                    $this->send_websocket_request($target_json['webSocketDebuggerUrl'], 'Target.closeTarget', ["targetId" => $target_json['id']]);
                } catch (Exception $e) {
                    $this->log('Exception: ' . $e->getMessage());
                }
            }
        }
    }
    // END Block of chrome tab handling functions

    // START Block of frame/iframe handling functions
    public function get_same_origin_frame_context($frame): ?GmiBrowserTarget
    {
        $located_string = GmiString::generate_locate_string('sof');
        $this->send_websocket_request($this->current_context->webSocketDebuggerUrl, 'Runtime.discardConsoleEntries');
        $this->execute_javascript('arguments[0].contentWindow.console.log(arguments[1])', [$frame, $located_string]);
        $message = $this->send_websocket_event($this->current_context->webSocketDebuggerUrl, 'Runtime.enable', $located_string);
        // $this->send_websocket_request($this->current_context->webSocketDebuggerUrl, 'Runtime.disable');
        $result = json_decode($message, true);
        $return_context = clone $this->current_context;
        if (isset($result["params"]) && isset($result["params"]["executionContextId"])) {
            $return_context->executionContextId = $result["params"]["executionContextId"];
        }

        return $return_context;
    }

    public function get_cross_origin_frame_context($frame): ?GmiBrowserTarget
    {
        $response_text = $this->send_remote_request('GET', $this->remote_url . GmiCommon::LIST_TARGET);
        $target_json_list = json_decode($response_text, true);
        $return_context = $this->find_cross_origin_frame_socket($target_json_list, $frame);

        // Handling edge case
        // One exception in cross origin frame.
        // same domain => no socket would be created
        // BUT difference origin => can not access from parent
        // Example: in Apple login page
        // host domain and iframe domain are apple.com but origin are "https://idmsa.apple.com" and "https://secure9.store.apple.com",
        if ($return_context == null) {
            $response_text = $this->send_websocket_request($this->current_context->webSocketDebuggerUrl, 'Page.getFrameTree');
            $frame_tree = json_decode($response_text, true)['result'];
            $frames_json = $frame_tree["frameTree"]["childFrames"];

            foreach ($frames_json as $frame_json) {
                $response_text = $this->send_websocket_request($this->current_context->webSocketDebuggerUrl, 'Page.createIsolatedWorld', [
                    "frameId"    => $frame_json["frame"]["id"],
                    "grantUniveralAccess" => true
                ]);
                $created_context_id = json_decode($response_text, true)['result']["executionContextId"];

                $located_expression = '
                    var gmiLocated = "";
                    function fgmilistenMessage(msg) {
                        window.gmiLocated = msg.data;
            }
                    window.addEventListener("message", fgmilistenMessage, false);
                ';
                $this->send_websocket_request($this->current_context->webSocketDebuggerUrl, 'Runtime.evaluate', [
                    "expression" => $located_expression,
                    "contextId" => $created_context_id
                ]);

                $located_string = GmiString::generate_locate_string('sddo');
                $this->execute_javascript('arguments[0].contentWindow.postMessage(arguments[1], "*")', [$frame, $located_string]);
                $response_text = $this->send_websocket_request($this->current_context->webSocketDebuggerUrl, 'Runtime.evaluate', [
                    "expression"    => "window.gmiLocated !== undefined && window.gmiLocated == '$located_string'",
                    "contextId" => $created_context_id,
                    "returnByValue" => true,
                ]);
                $result = json_decode($response_text, true)['result']['result'];
                $this->log('found :' . $result['value']);
                if ($result['value'] == true) {
                    $return_context = clone $this->current_context;
                    $return_context->executionContextId = $created_context_id;
                    break;
                }
            }

            if ($return_context == null) {
                $this->log('Context did not found for frame also.');
            }
        }

        return $return_context;
    }

    public function find_cross_origin_frame_socket($target_json_list = [], $frame): ?GmiBrowserTarget
    {
        $target = null;
        $located_string = GmiString::generate_locate_string('cof');
        foreach ($target_json_list as $target_json) {
            if (isset($target_json['type']) && isset($target_json['url'])) {
                if ($target_json['type'] == 'frame' || $target_json['type'] == 'iframe') {
                    $checking_context = $target_json;
                    $expression = '
                        var gmiLocated = "";
                        function fgmilistenMessage(msg) {
                            window.gmiLocated = msg.data;
                        }
                        window.addEventListener("message", fgmilistenMessage, false);
                    ';
                    $this->log($checking_context["webSocketDebuggerUrl"]);
                    $this->send_websocket_request($checking_context["webSocketDebuggerUrl"], 'Runtime.evaluate', [
                        "expression" => $expression,
                    ]);
                    $this->execute_javascript('arguments[0].contentWindow.postMessage(arguments[1], "*")', [$frame, $located_string]);
                    $response_text = $this->send_websocket_request($checking_context["webSocketDebuggerUrl"], 'Runtime.evaluate', [
                        "expression" => "window.gmiLocated !== undefined && window.gmiLocated == '$located_string'",
                        "returnByValue" => true,
                    ]);
                    $result = json_decode($response_text, true)['result']['result'];
                    $this->log('Frame socket found :' . $result['value']);
                    if ($result['value'] == true) {
                        $target = GmiBrowserTarget::createFromSocketJson($checking_context);
                        break;
                    }
                }
            }
        }

        if ($target == null) {
            $this->log('Socket did not found for frame.');
        }

        return $target;
    }

    public function get_frame_excutable_context($query_string_or_element): ?GmiBrowserTarget
    {
        // $this->log(__FUNCTION__." Begin with ".$query_string_or_element);
        $frame = null;
        if ($query_string_or_element instanceof GmiRemoteElement) {
            $frame = $query_string_or_element;
        } else if (is_string($query_string_or_element)) {
            $frame = $this->queryElement($query_string_or_element);
        }

        if ($frame instanceof GmiRemoteElement) {
            // Check frame is cross origin or not
            $is_cross_origin = $this->execute_javascript(
                '
                if(arguments[0].contentDocument){
                    false;
                } else {
                    true;
                }
            ',
                [$frame]
            );
            if ($is_cross_origin) {
                return $this->get_cross_origin_frame_context($frame);
            } else {
                return $this->get_same_origin_frame_context($frame);
            }
        } else {
            $this->log(__FUNCTION__ . " Frame not found " . $query_string_or_element);
            return null;
        }
    }

    public function switch_to_default(): void
    {
        $this->current_chrome_tab->executionContextId = null;
        $this->current_context = clone $this->current_chrome_tab;
    }

    public function makeFrameExecutable($query_string_or_element): ?GmiChromeManager
    {
        $target_frame = $this->get_frame_excutable_context($query_string_or_element);
        if ($target_frame != null) {
            $mini_manager = $this->create_mini_manager($target_frame);
            return $mini_manager;
        } else {
            $this->log(__FUNCTION__ . " failed - Seem no frame was found");
        }
        return null;
    }
    // END Block of frame/iframe handling functions

    public function start_and_wait_download(callable $trigger_download_function, $on_success = null, $on_error = null, $on_timeout = null, $timeout = 15, $ignore_socket_timeout = false)
    {
        $this->log(__FUNCTION__);
        $client = $this->send_websocket_event(
            $this->browser["webSocketDebuggerUrl"],
            'Browser.setDownloadBehavior',
            null,
            ["behavior" => "default", "eventsEnabled" => true]
        );
        $trigger_download_function();

        $events = [];
        $count = 0;
        $timeout_point = strtotime("+$timeout seconds");
        while (true) {
            $count++;

            try {
                $current_time = strtotime('now');
                if ($current_time > $timeout_point) {
                    $this->log("wait_page_loading Timeout");
                    if (is_callable($on_timeout)) {
                        $on_timeout();
                    }
                    $client->disconnect();

                    return;
                }

                $message = $client->receive();
                if (stripos($message, 'Browser.downloadWillBegin') !== false) {
                    array_push($events, $message);
                } else {
                    if (stripos($message, 'Browser.downloadProgress') !== false && stripos($message, '"state":"completed"') !== false) {
                        array_push($events, $message);

                        $client->disconnect();

                        return is_callable($on_success) ? $on_success($events) : null;
                    }
                }
            } catch (Exception $e) {
                $exception_message = $e->getMessage();
                $this->log($e->getMessage());

                if (is_callable($on_error)) {
                    $on_error();
                }

                if ($count > 3 && $ignore_socket_timeout === false) {
                    $client->disconnect();

                    return;
                }
            }
        }
    }

    public function open_and_wait_page_loading(callable $open_url_function, callable $on_success = null, callable $on_error = null, callable $on_timeout = null, $timeout = 15)
    {
        $this->log(__FUNCTION__);

        $client = $this->send_websocket_event(
            $this->current_context->webSocketDebuggerUrl,
            'Page.enable',
            null
        );
        $open_url_function();

        $count = 0;
        $timeout_point = strtotime("+$timeout seconds");
        while (true) {
            $count++;

            try {
                $current_time = strtotime('now');
                if ($current_time > $timeout_point) {
                    $this->log("wait_page_loading Timeout");
                    if (is_callable($on_timeout)) {
                        $on_timeout();
                    }
                    $client->disconnect();

                    return;
                }

                $message = $client->receive();
                if (stripos($message, 'Page.loadEventFired') !== false) {
                    usleep(300 * 1000);
                    if (is_callable($on_success)) {
                        $on_success();
                    }
                    $client->disconnect();

                    return;
                }
            } catch (Exception $e) {
                $exception_message = $e->getMessage();
                $this->log($e->getMessage());

                if (is_callable($on_error)) {
                    $on_error();
                }

                if ($count > 3) {
                    $client->disconnect();

                    return;
                }
            }
        }
    }

    public function waitFor(callable $condition_to_stop, $timeout = 15, $trigger_start = null, $on_timeout = null, $on_error = null, $condition_to_ignore_error = null)
    {
        if (is_callable($trigger_start)) {
            $trigger_start();
        }
        $timeout_point = strtotime("+$timeout seconds");
        while (true) {
            try {
                $current_time = strtotime('now');
                if ($current_time > $timeout_point) {
                    $this->log("Wait timeout");
                    if (is_callable($on_timeout)) {
                        $on_timeout();
                    }
                    break;
                }

                if ($condition_to_stop()) {
                    $this->log("Wait done");
                    break;
                }
                usleep(500 * 1000);
            } catch (Exception $e) {
                $this->log("Exception: " . $e->getMessage());
                if (is_callable($condition_to_ignore_error)) {
                    if ($condition_to_ignore_error()) {
                        continue;
                    }
                }

                break;
            }
        }
    }
    // End of block of framework functions

    // Block of api functions
    /**
     * Helper Method to click element
     *
     * @param ? $selector_or_object received both css selector, xpath or element object
     */
    public function click_element($query_string_or_element)
    {
        if ($query_string_or_element == null) {
            $this->log(__FUNCTION__ . ' Can not click null');

            return;
        }

        $element = $query_string_or_element;
        if (is_string($query_string_or_element)) {
            $this->log(__FUNCTION__ . '::Click selector/xpath: ' . $query_string_or_element);
            $element = $this->querySelector($query_string_or_element);
            if ($element == null) {
                $element = $this->queryXpath($query_string_or_element);
            }
            if ($element == null) {
                $this->log(__FUNCTION__ . ':: Can not find element with selector/xpath: ' . $query_string_or_element);
            }
        }
        if ($element != null) {
            try {
                $this->log(__FUNCTION__ . ' trigger click. ' . $element->remote_element_id);
                $element->click();
            } catch (\Exception $exception) {
                $this->log(__FUNCTION__ . ' by javascript' . $exception);
                $this->execute_javascript("arguments[0].click()", [$element]);
            }
        }
    }

    public function click_if_existed($selector_or_xpath)
    {
        $element = $this->querySelector($selector_or_xpath);
        if ($element == null) {
            $element = $this->queryXpath($selector_or_xpath);
        }
        if ($element != null) {
            try {
                $this->log(__FUNCTION__ . ' trigger click. ' . $element->remote_element_id);
                $element->click();
            } catch (\Exception $exception) {
                $this->log(__FUNCTION__ . ' by javascript' . $exception);
                $this->execute_javascript("arguments[0].click()", [$element]);
            }
        } else {
            $this->log(__FUNCTION__ . ': Element does not existed');
        }
    }

    /**
     * Entry Method thats indentify and click element by element text
     * Because many website use generated html, It did not have good selector structure, indentify element by text is more reliable
     * This function support seaching element by multi language text or regular expression
     *
     * @param string  $multi_language_texts  the text label of element that want to click, can input single label, or multi language array or regular expression. Exam: 'invoice', ['invoice', 'rechung'], '/invoice|rechung/i'
     * @param Element $parent_element        parent element when we search element inside.
     * @param bool    $is_absolutely_matched tru if want seaching absolutely, false if want to seaching relatively.
     */
    public function getElementByText($selector_or_xpath, $multi_language_texts, $parent_element = null, $is_absolutely_matched = true)
    {
        $this->log(__FUNCTION__);
        if (is_array($multi_language_texts)) {
            $multi_language_texts = join('|', $multi_language_texts);
        }
        // Seaching matched element
        $object_elements = $this->queryElementAll($selector_or_xpath, $parent_element);
        foreach ($object_elements as $object_element) {
            $element_text = trim($object_element->get('innerText'));
            // First, search via text
            // If is_absolutely_matched = true, seach element matched EXACTLY input text, else search element contain the text
            if ($is_absolutely_matched) {
                $multi_language_texts = explode('|', $multi_language_texts);
                foreach ($multi_language_texts as $searching_text) {
                    if (strtoupper($element_text) == strtoupper($searching_text)) {
                        $this->log('Matched element found');

                        return $object_element;
                    }
                }
                $multi_language_texts = join('|', $multi_language_texts);
            } else {
                if (preg_match('/' . $multi_language_texts . '/i', $element_text) === 1) {
                    $this->log('Matched element found');

                    return $object_element;
                }
            }

            // Second, is search by text not found element, support searching by regular expression
            if (@preg_match($multi_language_texts, '') !== false) {
                if (preg_match($multi_language_texts, $element_text) === 1) {
                    $this->log('Matched element found');

                    return $object_element;
                }
            }
        }

        return null;
    }

    public function get_brower_root_position($force_relocated = false)
    {
        if (isset($GLOBALS['browser_root_position']) && is_array($GLOBALS['browser_root_position']) && !$force_relocated) {
            return $GLOBALS['browser_root_position'];
        }

        $GLOBALS['browser_root_position'] = null;
        for ($i = 0; $i < 5; $i++) {
            $x = rand(100, 355);
            $y = rand(370, 500);
            $this->log("Getting browser current cursor... Screen reference point $x $y");
            $this->execute_javascript(
                '
                window.localStorage["lastMousePosition"] = "";
                window.addEventListener("mousemove", function(e){
                    window.localStorage["lastMousePosition"] = e.clientX +"|" + e.clientY;
                });
            '
            );
            // var x = document.elementFromPoint(500, 492);
            exec("sudo docker exec " . $this->node_name . " bash -c 'xdotool mousemove --sync $x $y '");
            exec("sudo docker exec " . $this->node_name . " bash -c 'xdotool getmouselocation'", $output);
            $this->log("Latest mouse posision on screen: ");
            //print_r($output);

            $result = $this->execute_javascript('window.localStorage["lastMousePosition"]');
            if (!empty($result)) {
                $this->log('Browser current cursor: ' . $result);
                $current_cursor = explode('|', $result);
                $GLOBALS['browser_root_position'] = [
                    'root_x' => $x - (int) $current_cursor[0],
                    'root_y' => $y - (int) $current_cursor[1],
                ];

                return $GLOBALS['browser_root_position'];
            }
        }

        $this->log('CAN NOT detect root position of browser webview');

        return null;
    }

    public function click_by_xdotool($selector = '', $x_on_element = 0, $y_on_element = 0)
    {
        $this->log(__FUNCTION__ . " $selector $x_on_element $y_on_element");
        $selector = base64_encode($selector);
        $element_coo = $this->execute_javascript(
            '
            var x_on_element = ' . $x_on_element . ';
            var y_on_element = ' . $y_on_element . ';
            var coo = document.querySelector(atob("' . $selector . '")).getBoundingClientRect();
            // Default get center point in element, if offset inputted, out put them
            if(x_on_element > 0 || y_on_element > 0) {
                Math.round(coo.x + x_on_element) + "|" + Math.round(coo.y + y_on_element);
            } else {
                Math.round(coo.x + coo.width/2) + "|" + Math.round(coo.y + coo.height/2);
            }
            
        '
        );
        // sleep(1);
        $this->log("Browser clicking position: $element_coo");
        $element_coo = explode('|', $element_coo);

        $root_position = $this->get_brower_root_position();
        $this->log("Browser root position");
        //print_r($root_position);

        $clicking_x = (int) $element_coo[0] + (int) $root_position['root_x'];
        $clicking_y = (int) $element_coo[1] + (int) $root_position['root_y'];
        $this->log("Screen clicking position: $clicking_x $clicking_y");
        exec("sudo docker exec " . $this->node_name . " bash -c 'xdotool mousemove " . $clicking_x . " " . $clicking_y . " click 1;'");
    }

    public function type_text_by_xdotool($text = '', $delay = true)
    {
        $tmp = preg_split('~~u', $text, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($tmp as $char) {
            $this->log($char);
            if ($delay) {
                sleep(rand(0.1, 0.9));
            }

            $char = '0x' . dechex(mb_ord($char));
            exec("sudo docker exec " . $this->node_name . " bash -c 'xdotool key " . $char . "'");
        }
    }

    public function type_key_by_xdotool($key = '')
    {
        exec("sudo docker exec " . $this->node_name . " bash -c 'xdotool key " . $key . "'");
    }

    public function capture_by_chromedevtool($filename, $save_html = true)
    {
        try {
            $image_file_path = $this->screen_capture_location . $filename . '.png';
            $reponse_text = $this->send_websocket_request($this->current_context->webSocketDebuggerUrl, 'Page.captureScreenshot', []);
            if (empty($reponse_text)) {
                $reponse_text = $this->send_websocket_request($this->current_context->webSocketDebuggerUrl, 'Page.captureScreenshot', []);
            }
            $base64_string = json_decode($reponse_text, true);
            $ifp = fopen($image_file_path, 'wb');
            fwrite($ifp, base64_decode($base64_string['result']["data"]));
            fclose($ifp);
            $this->log('Screenshot saved - ' . $image_file_path);
            if ($save_html) {
                $page_html = $this->get_page_content();
                file_put_contents($this->screen_capture_location . $filename . '.html', $page_html);
            }

            return $image_file_path;
        } catch (\Exception $exception) {
            $this->log('Error in capture - ' . $exception->getMessage());
        }
    }

    public function check_exist_by_chromedevtool($selector = '')
    {
        $selector = base64_encode($selector);

        return $this->execute_javascript(
            '
            var selector = atob("' . $selector . '");
            var elements = document.querySelectorAll(selector);
            if(elements.length > 0){
                true;
            } else {
                false;
            }
        '
        );
    }

    public function get_page_content()
    {
        // $response_text = $this->send_websocket_request($this->current_context->webSocketDebuggerUrl,
        //     'DOM.getDocument'
        // );
        // print_r($response_text);
        // $node = json_decode($response_text, true)['result']['root'];
        // $response_text =  $this->send_websocket_request($this->current_context->webSocketDebuggerUrl,
        //     'DOM.getOuterHTML',
        //     ['nodeId' => $node['nodeId'], 'backendNodeId' => $node['backendNodeId']]
        // );
        // print_r($response_text);
        // $page_html = json_decode($response_text, true)['result']['outerHTML'];
        $page_html = $this->execute_javascript('document.documentElement.outerHTML');

        return $page_html;
    }

    // basic functions for click and send keys by Chrome devtool protocol
    public function cdp_click($clicking_x, $clicking_y)
    {
        // $this->log(__FUNCTION__." $clicking_x $clicking_y");
        $this->send_websocket_request_multi($this->current_context->webSocketDebuggerUrl, [
            ["method" => "Input.dispatchMouseEvent", "params" => ['type' => "mouseMoved", "x" => $clicking_x, "y" => $clicking_y]],
            ["method" => "Input.dispatchMouseEvent", "params" => ['type' => "mousePressed", "x" => $clicking_x, "y" => $clicking_y, "button" => "left", "clickCount" => 1]],
            ["method" => "Input.dispatchMouseEvent", "params" => ['type' => "mouseReleased", "x" => $clicking_x, "y" => $clicking_y, "button" => "left", "clickCount" => 1]],
        ]);
    }

    public function cdp_javascript_click($remote_element, int $x_on_element = 0, int $y_on_element = 0, $jsFallback = true)
    {
        $returnText = $this->execute_javascript(
            '
                {   
                    let x_on_element = arguments[1];
                    let y_on_element = arguments[2];
                    let simulateMouseEvent = function(element, eventName, coordX, coordY) {
                        element.dispatchEvent(new MouseEvent(eventName, {
                            view: window,
                            bubbles: true,
                            cancelable: true,
                            clientX: coordX,
                            clientY: coordY,
                            button: 0
                        }));
                    };
                    let returnText;

                    let targetedElement = arguments[0];
                    targetedElement.scrollIntoViewIfNeeded();
                    let coo = targetedElement.getBoundingClientRect();
                    let clickSpot = {};
                    // Default get center point in element, if offset inputted, out put them
                    clickSpot.x = Math.round(coo.x + coo.width/2);
                    if(x_on_element > 0) {
                        clickSpot.x = Math.round(coo.x + x_on_element);
            }

                    clickSpot.y = Math.round(coo.y + coo.height/2);
                    if(y_on_element > 0) {
                        clickSpot.y = Math.round(coo.y + y_on_element);
    }

                    let topElement = document.elementFromPoint(clickSpot.x, clickSpot.y);
                    if(targetedElement.isEqualNode(topElement)){
                        if(targetedElement.focus instanceof Function){
                           targetedElement.focus();
                        }
                        simulateMouseEvent(targetedElement, "mouseDown", clickSpot.x, clickSpot.y);
                        simulateMouseEvent(targetedElement, "mouseUp", clickSpot.x, clickSpot.y);
                        simulateMouseEvent(targetedElement, "click", clickSpot.x, clickSpot.y);
                        returnText = "Clicked";
                    } else if(targetedElement.contains(topElement)){
                        if(targetedElement.focus instanceof Function){
                           targetedElement.focus();
                        }
                        simulateMouseEvent(topElement, "mouseDown", clickSpot.x, clickSpot.y);
                        simulateMouseEvent(topElement, "mouseUp", clickSpot.x, clickSpot.y);
                        simulateMouseEvent(topElement, "click", clickSpot.x, clickSpot.y);
                        returnText = "Clicked - Warning: Parent element received click";
                    } else {
                        if(arguments[3]){// jsfallback
                            targetedElement.click();
                            returnText = "Clicked by js click() - Reason: Another element would be received natural click " + topElement.outerHTML.substr(0, 500);
                        } else {
                            returnText = "Can not click - Another element would be received click " + topElement.outerHTML.substr(0, 500);
                        }
                    }
                    returnText;
                }
            ',
            [$remote_element, $x_on_element, $y_on_element, $jsFallback]
        );
        $this->log($returnText);
    }

    public function click($remote_element, int $x_on_element = 0, int $y_on_element = 0, $jsFallback = true)
    {
        // $this->log(__FUNCTION__." $selector $x_on_element $y_on_element");
        if (is_numeric($this->current_context->executionContextId)) {
            // Is in iframe context, click by javascript will work no matter where is the iframe root position
            return $this->cdp_javascript_click($remote_element, $x_on_element, $y_on_element);
        }

        $verifyResult = $this->execute_javascript(
            '
            {
                let x_on_element = arguments[1];
                let y_on_element = arguments[2];
                let targetedElement = arguments[0];

                targetedElement.scrollIntoViewIfNeeded();
                let coo = targetedElement.getBoundingClientRect();
                verifyResult = {
                    x: coo.x,
                    y: coo.y,
                    width: coo.width,
                    height: coo.height,
                    normal: true,
                    topIsParent: false,
                    overlapElement: ""
                }
                let clickSpot = {};
                // Default get center point in element, if offset inputted, out put them
                clickSpot.x = Math.round(coo.x + coo.width/2);
                if(x_on_element > 0) {
                    clickSpot.x = Math.round(coo.x + x_on_element);
                }
                clickSpot.y = Math.round(coo.y + coo.height/2);
                if(y_on_element > 0) {
                    clickSpot.y = Math.round(coo.y + y_on_element);
                } 

                let topElement = document.elementFromPoint(clickSpot.x, clickSpot.y);
                if(targetedElement.isEqualNode(topElement)){
                    verifyResult.normal = true;
                    verifyResult.topIsParent = false;
                } else if(targetedElement.contains(topElement)){
                    verifyResult.normal = false;
                    verifyResult.topIsParent = true;
                } else {
                    verifyResult.normal = false;
                    verifyResult.topIsParent = false;
                    verifyResult.overlapElement = topElement.outerHTML.substr(0, 500);
                }
                verifyResult.clickSpotX = clickSpot.x;
                verifyResult.clickSpotY = clickSpot.y;
                verifyResult;
            }
        ',
            [$remote_element, $x_on_element, $y_on_element]
        );

        if (((int)$verifyResult["width"]) <= 0 || ((int)$verifyResult["height"]) <= 0) {
            $this->log(__FUNCTION__ . " Error - something wrong in element side");
            $this->log(print_r($verifyResult, true));
            if ($jsFallback) {
                $this->log('Try to click by Javascript');
                $this->execute_javascript('arguments[0].click()', [$remote_element]);
            }
        } else {
            if ($verifyResult["normal"] === true) {
                $this->cdp_click($verifyResult["clickSpotX"], $verifyResult["clickSpotY"]);
            } else if ($verifyResult["normal"] === false && $verifyResult["topIsParent"] === true) {
                $this->cdp_click($verifyResult["clickSpotX"], $verifyResult["clickSpotY"]);
            } else {
                $this->log('Exception in click - Another element would be received click');
                $this->log($verifyResult["overlapElement"]);
                if ($jsFallback) {
                    $this->log('Try to click by Javascript');
                    $this->execute_javascript('arguments[0].click()', [$remote_element]);
                }
            }
        }
    }

    public function cdp_send_key($key)
    {
        $args = func_get_args();
        $shift = false;
        $alt = false;
        $ctrl = false;
        $cmd = false;
        $all_messages = [];
        // check modifiers
        foreach ($args as $single_key) {
            if (is_array($single_key)) {
                if ($single_key["keyCode"] == 16) {
                    $shift = true;
                }
                if ($single_key["keyCode"] == 17) {
                    $ctrl = true;
                }
                if ($single_key["keyCode"] == 18) {
                    $alt = true;
                }
            }
        }
        // generate key down
        foreach ($args as $single_key) {
            $keyDown = GmiKeyboardEvent::generate_keyevent('keyDown', $single_key, $shift, $alt, $ctrl, $cmd);
            array_push($all_messages, ["method" => "Input.dispatchKeyEvent", "params" => $keyDown]);
        }
        // generate key up
        foreach ($args as $single_key) {
            $keyUp = GmiKeyboardEvent::generate_keyevent('keyUp', $single_key, $shift, $alt, $ctrl, $cmd);
            array_push($all_messages, ["method" => "Input.dispatchKeyEvent", "params" => $keyUp]);
        }

        $this->send_websocket_request_multi($this->current_context->webSocketDebuggerUrl, $all_messages);
    }

    public function cdp_type_text($text = '', $delay = null)
    {
        $tmp = preg_split('~~u', $text, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($tmp as $char) {
            if ($delay) {
                sleep(rand(0.1, 0.5));
            }

            $keyDown = GmiKeyboardEvent::generate_keyevent('keyDown', $char);
            $keyUp = GmiKeyboardEvent::generate_keyevent('keyUp', $char);
            $this->send_websocket_request_multi($this->current_context->webSocketDebuggerUrl, [
                ["method" => "Input.dispatchKeyEvent", "params" => $keyDown],
                ["method" => "Input.dispatchKeyEvent", "params" => $keyUp],
            ]);
        }
    }

    public function cdp_insert_text($text = '')
    {
        $this->send_websocket_request($this->current_context->webSocketDebuggerUrl, 'Input.insertText', ["text" => $text]);
    }

    public function execute_javascript($expression, $arguments = [], $returnByValue = true)
    {
        $script_before_evaluate = $this->preparing_evalute_environment();
        // Check arguments, embed them to js.
        if (count($arguments) > 0) {
            foreach ($arguments as $argument) {
                if ($argument instanceof GmiRemoteElement) {
                    $script_before_evaluate = $script_before_evaluate . '
                        window.arguments.push(' . $argument->getJavascriptIdentify() . ');
                    ';
                } else {
                    if ($argument == null) {
                        $script_before_evaluate = $script_before_evaluate . '
                        window.arguments.push(null);
                    ';
                    } else {
                        $base64_string = base64_encode($argument);
                        $script_before_evaluate = $script_before_evaluate . '
                        window.arguments.push(atob("' . $base64_string . '"));
                    ';
                    }
                }
            }
        }
        // Add environment variable for execution
        $this->evaluate($script_before_evaluate);

        // execute expression
        $returned_text = $this->evaluate($expression, $returnByValue);

        if (count($arguments) > 0 && stripos($returned_text, 'ReferenceError: arguments is not defined')) {
            // Seem exception because of page reloaded
            $this->evaluate($script_before_evaluate);
            $returned_text = $this->evaluate($expression, $returnByValue);
        }

        // Clean javascript arguments after executed
        if (count($arguments) > 0) {
            // $this->evaluate('var test = window.arguments; window.arguments = undefined;');
            $this->evaluate(
                '
                window.arguments = undefined;
                gmicmenv = undefined;
            '
            );
        }

        // Analyze returned text for checking error, null, or value
        return $this->process_execute_result($returned_text);
    }

    /**
     * Evaluate javascript expression in the current context
     *
     * @param string $expression
     * @param bool   $returnByValue
     * @param bool   $serialization
     *
     * @return    string response from websocket, can be able to converted to JSON object.
     */
    public function evaluate($expression, $returnByValue = true, $serialization = false)
    {
        // Example Runtime.evaluate parameter:
        // {
        //     "id": 1,
        //     "method": "Runtime.evaluate",
        //     "params": {
        //         "expression": "document.querySelector('button');",
        //         "serializationOptions": {
        //             "serialization": "deep"
        //         },
        //         "returnByValue": true
        //     }
        // }

        if (isset($this->current_context->executionContextId) && !empty($this->current_context->executionContextId)) {
            $data = [
                "expression" => $expression,
                "returnByValue" => $returnByValue,
                "contextId"     => $this->current_context->executionContextId,
            ];
        } else {
            $data = [
                "expression" => $expression,
                "returnByValue" => $returnByValue,
            ];
        }
        if ($serialization) {
            $data["serializationOptions"] = [
                "serialization" => "deep",
            ];
        }

        $response_text = $this->send_websocket_request($this->current_context->webSocketDebuggerUrl, 'Runtime.evaluate', $data);
        if ($this->current_context->executionContextId > 0) {
            try {
                $checking_error = json_decode($response_text, true);
                if (isset($checking_error["error"])) {
                    if (isset($checking_error["error"]["code"]) && $checking_error["error"]["code"] == -3200 || isset($checking_error["error"]["message"]) && $checking_error["error"]["message"] == "Cannot find context with specified id") {
                        $this->switch_to_default();
                    }
                }
            } catch (Exception $e) {
            }
        }

        return $response_text;

        // Example of returned response from websocket:
        // {"id":1,"result":{"result":{"type":"string","value":"I am script result"}}}

        // $result["result"]["result"]["value"]
        // evaluate result is Runtime.RemoteObject
        // https://chromedevtools.github.io/devtools-protocol/1-3/Runtime/#type-RemoteObject
        // type - string : object, function, undefined, string, number, bool, symbol, bigint
        // subtype - string : array, null, node, regexp, date
        //                    map, set, weakmap, weakset, iterator, generator, error, proxy, promise, typedarray, arraybuffer, dataview, webassemblymemory, wasmvalue
        // className - string: Object class (constructor) name. Specified for object type values only.
        // value - any <----- we get result here
        //
    }

    public function querySelector($selector = '', $parent = null)
    {
        $this->log(__FUNCTION__ . " $selector");
        $remote_element_id = GmiString::generate_random_string('gmi', 5, 3);
        $expression = str_replace(
            'remote_element_id',
            $remote_element_id,
            '
            if(arguments[1] == null) arguments[1] = document;
            var remote_element_id = arguments[1].querySelector(arguments[0]);
            if(remote_element_id != null){
                true;
            } else {
                false;
            }
        '
        );

        $result = $this->execute_javascript($expression, [$selector, $parent], true);

        if ($result) {
            return new GmiRemoteElement($remote_element_id, $this);
        } else {
            $this->log(__FUNCTION__ . " " . GmiCommon::LABEL_ELEMENT_NOT_FOUND);
            return null;
        }
    }

    public function querySelectorAll($selector = '', $parent = null)
    {
        $this->log(__FUNCTION__ . " $selector");
        $remote_element_id = GmiString::generate_random_string('gmi', 5, 3);
        $expression = str_replace(
            'remote_element_id',
            $remote_element_id,
            '
            if(arguments[1] == null) arguments[1] = document;
            var remote_element_id = arguments[1].querySelectorAll(arguments[0]);
            remote_element_id.length;
        '
        );

        $result = $this->execute_javascript($expression, [$selector, $parent], true);

        $return_array = [];
        if ($result > 0) {
            for ($i = 0; $i < $result; $i++) {
                array_push($return_array, new GmiRemoteElement($remote_element_id, $this, $i));
            }
        } else {
            $this->log(__FUNCTION__ . " " . GmiCommon::LABEL_ELEMENT_NOT_FOUND);
        }

        return $return_array;
    }

    public function queryXpath($xpath = '', $parent = null)
    {
        $this->log(__FUNCTION__ . " $xpath");
        $remote_element_id = GmiString::generate_random_string('gmi', 5, 3);
        $expression = str_replace(
            'remote_element_id',
            $remote_element_id,
            '
            var remote_element_id = null;
            try {
                let queryResult = gmicmenv.getElementsByXPath(arguments[0], arguments[1]);
                if(queryResult.length > 0){
                    remote_element_id = queryResult[0];
                    gmicmenv.returnResult = true;
                } else {
                    gmicmenv.returnResult = false;
                }
            } catch(error){
                gmicmenv.isFatalError = true;
                gmicmenv.errorList.push(error);
                gmicmenv.returnResult = false;
            }
            gmicmenv.returnResult;
        '
        );
        $result = $this->execute_javascript($expression, [$xpath, $parent], true);

        if ($result) {
            return new GmiRemoteElement($remote_element_id, $this);
        } else {
            $this->log(__FUNCTION__ . " " . GmiCommon::LABEL_ELEMENT_NOT_FOUND);
            return null;
        }
    }

    public function queryXpathAll($xpath = '', $parent = null)
    {
        $this->log(__FUNCTION__ . " $xpath");
        $remote_element_id = GmiString::generate_random_string('gmi', 5, 3);
        $expression = str_replace(
            'remote_element_id',
            $remote_element_id,
            '
            var remote_element_id = [];
            try {
                remote_element_id = gmicmenv.getElementsByXPath(arguments[0], arguments[1]);
                gmicmenv.returnResult = remote_element_id.length;
            } catch(error){
                gmicmenv.isFatalError = true;
                gmicmenv.errorList.push(error);
                gmicmenv.returnResult = 0;
            }
            gmicmenv.returnResult;
        '
        );
        $result = $this->execute_javascript($expression, [$xpath, $parent], true);

        $return_array = [];
        if ($result > 0) {
            for ($i = 0; $i < $result; $i++) {
                array_push($return_array, new GmiRemoteElement($remote_element_id, $this, $i));
            }
        } else {
            $this->log(__FUNCTION__ . " " . GmiCommon::LABEL_ELEMENT_NOT_FOUND);
        }

        return $return_array;
    }

    public function queryElement($selector_or_xpath, $parent = null)
    {
        $this->log(__FUNCTION__ . " by $selector_or_xpath");
        $element = null;
        $element = $this->querySelector($selector_or_xpath, $parent);
        if ($element == null) {
            $element = $this->queryXpath($selector_or_xpath, $parent);
        }
        if ($element == null) {
            $this->log(__FUNCTION__ . " " . GmiCommon::LABEL_ELEMENT_NOT_FOUND);
        }

        return $element;
    }

    public function queryElementAll($selector_or_xpath, $parent = null)
    {
        $this->log(__FUNCTION__ . " by $selector_or_xpath");
        $elements = [];
        $elements = $this->querySelectorAll($selector_or_xpath, $parent);
        if (empty($elements)) {
            $elements = $this->queryXpathAll($selector_or_xpath, $parent);
        }
        if (empty($elements)) {
            $this->log(__FUNCTION__ . " " . GmiCommon::LABEL_ELEMENT_NOT_FOUND);
        }

        return $elements;
    }

    public function count_elements($selector_or_xpath, $parent = null)
    {
        // $this->log(__FUNCTION__ . " by $selector_or_xpath");
        $expression = '
           {
                if(arguments[1] == null) arguments[1] = document;
                let element_count = 0;
                try {
                    element_count = arguments[1].querySelectorAll(arguments[0]).length;
                } catch (error) {
                    gmicmenv.errorList.push({"cssError": error});
                }
                if(element_count == 0){
                    try {
                        let query = document.evaluate(arguments[0], arguments[1],
                        null, XPathResult.ORDERED_NODE_SNAPSHOT_TYPE, null);
                        element_count = query.snapshotLength;
                    } catch (error) {
                       gmicmenv.errorList.push({"xpathError": error});
                    }
                }
                gmicmenv.returnResult = element_count;
                gmicmenv;
            }
        ';
        $element_count = $this->execute_javascript($expression, [$selector_or_xpath, $parent]);

        return $element_count["returnResult"];
    }

    public function queryTextNodes(array $multi_language_texts, $parent = null, $is_absolutely_matched = false, $case_sensitive = false)
    {
        $this->log(__FUNCTION__);
        $return_array = [];
        foreach ($multi_language_texts as $searching_text) {
            $contains_statement = GmiCommon::generate_xpath_contains($searching_text);
            $regex_statement = GmiCommon::generate_regex($searching_text, $is_absolutely_matched, $case_sensitive);
            $this->log('//' . $contains_statement);
            $this->log($regex_statement);
            $remote_element_id = GmiString::generate_random_string('gmi', 5, 3);
            $expression = str_replace(
                'remote_element_id',
                $remote_element_id,
                '
                var remote_element_id = [];
                try {
                    remote_element_id = gmicmenv.queryTextNodes(arguments[0], arguments[1], ' . $regex_statement . ');
                    gmicmenv.returnResult = remote_element_id.length;
                } catch(error){
                    gmicmenv.isFatalError = true;
                    gmicmenv.errorList.push(error);
                    gmicmenv.returnResult = 0;
                }
                gmicmenv.returnResult;
            '
            );
            $result = $this->execute_javascript($expression, ['//' . $contains_statement, $parent], true);


            if ($result > 0) {
                // $this->log(__FUNCTION__ . ": Elements found");
                for ($i = 0; $i < $result; $i++) {
                    array_push($return_array, new GmiRemoteElement($remote_element_id, $this, $i));
                }
                return $return_array;
            }
        }

        $this->log(__FUNCTION__ . ": " . GmiCommon::LABEL_ELEMENT_NOT_FOUND);
        return $return_array;
    }

    public function queryTextByRegex(string $reGex, $parent = null)
    {
        $this->log(__FUNCTION__);
        // Implement then
    }

    public function find_by_text_and_click(array $multi_language_texts, $parent = null)
    {
        $targeted_element = $this->queryText($multi_language_texts, $parent);
        if ($targeted_element != null) {
            $targeted_element->click();
        }
    }

    public function executeSafeScript($expression, $arguments = [], $returnByValue = true)
    {
        $this->log(__FUNCTION__);
        // process arguments
        $input_arguments = [];
        foreach ($arguments as $argument) {
            if ($argument instanceof GmiRemoteElement) {
                array_push($input_arguments, $argument->getJavascriptIdentify());
            } else {
                if ($argument == null) {
                    array_push($input_arguments, 'null');
                } else {
                    $base64_string = base64_encode($argument);
                    array_push($input_arguments, 'atob("' . $base64_string . '")');
                }
            }
        }

        // wrap expression in function, make valid return statement
        $wrapped_expression = "
        (function(arguments) {
            $expression
        })([" . implode(", ", $input_arguments) . ']);';


        $this->log($wrapped_expression);
        // execute expression
        $returned_text = $this->evaluate($wrapped_expression, $returnByValue);

        // Analyze returned text for checking error, null, or value
        $function_result = '';

        try {
            $json_object = json_decode($returned_text, true);
            $targeted_result = $json_object['result']['result'];

            if (isset($targeted_result['subtype']) && $targeted_result['subtype'] == 'error') {
                $this->log('ERROR ' . __FUNCTION__ . " executing expression error: \n" . $returned_text);
                $e = new Exception();
                $trace = $e->getTrace();
                $last_call = $trace[1];
                //print_r($last_call);
            } else {
                if (isset($targeted_result['subtype']) && $targeted_result['subtype'] == 'null' && $targeted_result['value'] == null) {
                    $this->log('WARNING ' . __FUNCTION__ . ': expression returned null');
                } else {
                    if ($targeted_result['type'] == 'undefined') {
                        $this->log('WARNING ' . __FUNCTION__ . ': expression returned undefined');
                    } else {
                        if (isset($targeted_result['value'])) {
                            $function_result = $targeted_result['value'];
                        } else {
                            $this->log('WARNING ' . __FUNCTION__ . ': unknown result');
                            $this->log($returned_text);
                        }
                    }
                }
            }
        } catch (\Exception $exception) {
            $this->log('ERROR in executeSafeScript - ' . $exception->getMessage());
        }

        return $function_result;
    }

    private function preparing_evalute_environment()
    {
        $expression = '
            // variable for input argument in executed script
            var arguments = [];

            // environment variable, use for util functions and manage result/exception.
            var gmicmenv = {
                returnResult: null,
                isFatalError: false,
                errorList: [],
                isGmiWrappedOutput: true
            };

            gmicmenv.getElementsByXPath = function(xpath, parent){
                let results = [];
                let query = document.evaluate(xpath, parent || document,
                    null, XPathResult.ORDERED_NODE_SNAPSHOT_TYPE, null);
                for (let i = 0, length = query.snapshotLength; i < length; ++i) {
                    results.push(query.snapshotItem(i));
                }
                return results;
            }

            gmicmenv.queryTextNodes = function(xpath, parent, verifyRegex){
                let collection = [];
                let query = document.evaluate(xpath, parent || document,
                    null, XPathResult.ORDERED_NODE_SNAPSHOT_TYPE, null);
                for (let i = 0, length = query.snapshotLength; i < length; ++i) {
                    let elementSnapshot = query.snapshotItem(i);
                    let nodeValue = elementSnapshot.nodeValue.trim();
                    if(nodeValue != "" && nodeValue.match(verifyRegex)){
                        collection.push(elementSnapshot);
                    }
                }
                
                return collection;
            }
        ';

        return $expression;
    }

    private function process_execute_result($returned_text = '')
    {
        $processed_result = '';
        $json_object = json_decode($returned_text, true);
        $targeted_result = $json_object['result']['result'];

        if (isset($targeted_result["isGmiWrappedOutput"])) {
            // Wrapped output
            if ($targeted_result["isFatalError"]) {
                $this->log("ERROR executing expression error: \n");
                //print_r($targeted_result["errorList"]);
                $e = new Exception();
                $trace = $e->getTrace();
                $this->log(print_r($trace, true));
            } else {
                if (!isset($targeted_result["returnResult"])) {
                    $this->log('WARNING: expression returned (Wrapped) undefined');
                } else {
                    if ($targeted_result["returnResult"] == null) {
                        $this->log('WARNING: expression returned (Wrapped) null');
                    } else {
                        $processed_result = $targeted_result["returnResult"];
                    }
                }
            }
        } else {
            // Original output
            if (isset($targeted_result['subtype']) && $targeted_result['subtype'] == 'error') {
                $this->log("ERROR executing expression error: \n" . $returned_text);
                $e = new Exception();
                $trace = $e->getTrace();
                //print_r($trace);
            } else {
                if (isset($targeted_result['subtype']) && $targeted_result['subtype'] == 'null' && $targeted_result['value'] == null) {
                    $this->log('WARNING: expression returned null');
                } else {
                    if ($targeted_result['type'] == 'undefined') {
                        $this->log('WARNING: expression returned undefined');
                    } else {
                        if (isset($targeted_result['value'])) {
                            $processed_result = $targeted_result['value'];
                        } else {
                            $this->log('WARNING: unknown result');
                            $this->log($returned_text);
                        }
                    }
                }
            }
        }

        return $processed_result;
    }
}

class GmiBrowserTarget
{
    // Properties get from GET api remote_url/json
    public $id;
    public $type;
    public $webSocketDebuggerUrl;
    public $url;
    public $title;
    public $parentId;
    // Properties get from Tartget.getTargets methods
    // "targetId":  this property mapped with id
    public $attached = false; // bool
    public $openerId; // If taret opened by another target, this is opener's ID
    public $canAccessOpener = false;
    public $openerFrameId;
    public $browserContextId;

    // Additional properties
    public $executionContextId;
    public $subIframe;
    public $subTarget;
    public $isActive = false;
    public $browser_window_id;
    public $createdBy;

    public function __construct() {}

    public static function createFromValues(
        $type,
        $id,
        $remote_url = '',
        $webSocketDebuggerUrl = '',
        $url = '',
        $title = '',
        $parentId = '',
        $attached = false,
        $openerId = '',
        $canAccessOpener = false,
        $openerFrameId = '',
        $browserContextId = ''
    ) {
        if (empty($remote_url) && empty($webSocketDebuggerUrl)) {
            //echo "Not allow either both remote_url and webSocketDebuggerUrl are empty. Can not create Page object \n";

            return null;
        }
        $instance = new self();
        $instance->type = $type;
        $instance->id = $id;
        if (empty($webSocketDebuggerUrl)) {
            $parsed_url = parse_url($remote_url);
            $instance->webSocketDebuggerUrl = 'ws://' . $parsed_url['host'] . ':' . $parsed_url['port'] . '/devtools/page/' . $id;
        } else {
            $instance->webSocketDebuggerUrl = $webSocketDebuggerUrl;
        }
        $instance->url = $url;
        $instance->title = $title;

        $instance->attached = $attached;
        $instance->openerId = $openerId;
        $instance->canAccessOpener = $canAccessOpener;
        $instance->openerFrameId = $openerFrameId;
        $instance->browserContextId = $browserContextId;

        $instance->createdBy = __FUNCTION__;

        return $instance;
    }

    public static function createFromSocketJson($instanceJson, $remote_url = '')
    {
        if (!isset($instanceJson['parentId'])) {
            $instanceJson['parentId'] = '';
        }
        $instance = new self();

        $instance->type = $instanceJson['type'];
        $instance->id = $instanceJson['id'];
        $instance->webSocketDebuggerUrl = $instanceJson['webSocketDebuggerUrl'];
        $instance->url = $instanceJson['url'];
        $instance->title = $instanceJson['title'];
        $instance->parentId = $instanceJson['parentId'];

        $instance->createdBy = __FUNCTION__;

        return $instance;
    }

    public static function createFromTargetJson($targetJson, $remote_url = '')
    {
        if (!isset($targetJson['parentId'])) {
            $targetJson['parentId'] = '';
        }
        $instance = new self();

        $instance->type = $targetJson['type'];
        $instance->id = $targetJson['targetId'];
        $instance->url = $targetJson['url'];
        $instance->title = $targetJson['title'];
        $instance->attached = $targetJson['attached'];
        if (isset($targetJson['openerId'])) {
            $instance->openerId = $targetJson['openerId'];
        }
        if (isset($targetJson['canAccessOpener'])) {
            $instance->canAccessOpener = $targetJson['canAccessOpener'];
        }
        if (isset($targetJson['openerFrameId'])) {
            $instance->openerFrameId = $targetJson['openerFrameId'];
        }
        if (isset($targetJson['browserContextId'])) {
            $instance->browserContextId = $targetJson['browserContextId'];
        }

        $parsed_url = parse_url($remote_url);
        $instance->webSocketDebuggerUrl = 'ws://' . $parsed_url['host'] . ':' . $parsed_url['port'] . '/devtools/page/' . $targetJson['targetId'];

        return $instance;
    }

    public static function targetArrayDiff($arrayLeft = [], $arrayRight = [])
    {
        $diff = [];
        $notInLeft = [];
        $notInRight = [];
        $result = new stdClass();
        foreach ($arrayLeft as $left) {
            if (!$left->inArray($arrayRight)) {
                array_push($diff, $left);
                array_push($notInRight, $left);
            }
        }
        foreach ($arrayRight as $right) {
            if (!$right->inArray($arrayLeft)) {
                array_push($diff, $right);
                array_push($notInLeft, $right);
            }
        }
        $result->diff = $diff;
        $result->notInLeft = $notInLeft;
        $result->notInRight = $notInRight;

        return $result;
    }

    public function updateDetailFromTargetJson($targetJson)
    {
        $this->attached = $targetJson['attached'];
        if (isset($targetJson['openerId'])) {
            $this->openerId = $targetJson['openerId'];
        }
        if (isset($targetJson['canAccessOpener'])) {
            $this->canAccessOpener = $targetJson['canAccessOpener'];
        }
        if (isset($targetJson['openerFrameId'])) {
            $this->openerFrameId = $targetJson['openerFrameId'];
        }
        if (isset($targetJson['browserContextId'])) {
            $this->browserContextId = $targetJson['browserContextId'];
        }
    }

    public function get_browser_window_detail($browser_manager)
    {
        $window_detail = $browser_manager->get_browser_window_detail($this);
        $this->browser_window_id = $window_detail['windowId'];

        return $window_detail;
    }

    public function isSameWith($target, $compareIdOnly = false)
    {
        $same = false;
        if ($this->type == $target->type && $this->id == $target->id) {
            if ($compareIdOnly) {
                if (empty($this->webSocketDebuggerUrl) && !empty($target->webSocketDebuggerUrl)) {
                    $this->webSocketDebuggerUrl = $target->webSocketDebuggerUrl;
                }
                if (empty($this->parentId) && !empty($target->parentId)) {
                    $this->parentId = $target->parentId;
                }
            }

            if ($this->webSocketDebuggerUrl == $target->webSocketDebuggerUrl && $this->parentId == $target->parentId) {
                $same = true;
            }
        }

        return $same;
    }

    public function isFromSocketJson($target_json)
    {
        $same = false;
        if ($this->type == $target_json["type"] && $this->id == $target_json["id"] && $this->webSocketDebuggerUrl == $target_json["webSocketDebuggerUrl"]) {
            if (isset($target_json["parentId"]) && $this->parentId == $target_json["parentId"]) {
                $same = true;
            } else {
                if (!isset($target_json["parentId"]) && empty($this->parentId)) {
                    $same = true;
                }
            }
        }

        return $same;
    }

    public function inArray($target_array)
    {
        foreach ($target_array as $target_or_socket_json) {
            if ($target_or_socket_json instanceof GmiBrowserTarget) {
                if ($this->isSameWith($target_or_socket_json, true)) {
                    return true;
                }
            } else {
                if ($this->isFromSocketJson($target_or_socket_json)) {
                    return true;
                }
            }
        }

        return false;
    }

    // CONSTANT
    // type related to target Tab/Page/iframe
    public const TargetTypeTab = "tab";
    public const TargetTypePage = "page";
    public const TargetTypeFrame = "iframe";
    public const TargetTypeDedicatedWorker = "worker";
    public const TargetTypeSharedWorker = "shared_worker";
    public const TargetTypeServiceWorker = "service_worker";
}

class GmiCommon
{
    public const BROWSER_VERSION = '/json/version'; //Browser version metadata
    public const LIST_TARGET = '/json'; //A list of all available websocket targets.
    public const OPEN_NEW_TAB = '/json/new?'; // PUT /json/new?{url}
    public const ACTIVE_TAB = '/json/activate/'; // json/activate/{targetId}
    public const CLOSE_TAB = '/json/close/'; // /json/close/{targetId}

    public const PROFILE_DEFAULT_FOLDER = 'Default';
    public const PROFILE_DEFAULT_NETWORK_FOLDER = 'Network';
    public const PREFERENCE_FILE_NAME = 'Preferences';
    public const SAVED_SESSION_FOLDER = 'session';

    public const ALIAS_SYMBOL = '*';

    public const LABEL_ELEMENT_NOT_FOUND = 'Element not found';

    // static function
    public static function join_file_paths()
    {
        $args = func_get_args();
        $paths = [];
        foreach ($args as $arg) {
            $paths = array_merge($paths, (array) $arg);
        }

        foreach ($paths as $key => &$value) {
            if ($key == 0) {
                $value = rtrim($value, '/');
            } else {
                if ($key == count($paths) - 1) {
                    $value = ltrim($value, '/');
                } else {
                    $value = trim($value, '/');
                }
            }
            $value = trim($value);
        }

        return join('/', $paths);
    }

    public static function get_list_session_files()
    {
        $list_of_session_files = [
            "Login Data",
            "Login Data For Account",
            "Safe Browsing Cookies",
            "Trust Tokens",
            // "Visited Links", //Do not required
            "Web Data", // Just useful for Google login
            "Cookies",
            // "History", //Do not required,
            self::PREFERENCE_FILE_NAME,
        ];

        return $list_of_session_files;
    }

    public static function generate_xpath_contains($processing_text = '', $case_sensitive = false, $attribute_name = '')
    {
        $split_array = explode(GmiCommon::ALIAS_SYMBOL, $processing_text);
        $processed_pieces = [];
        foreach ($split_array as $piece) {
            if ($case_sensitive) {
                $item = 'contains(., "' . $piece . '")';
            } else {
                $piece_lower = strtolower($piece);
                $piece_upper = strtoupper($piece);
                $item = 'contains(translate(., "' . $piece_upper . '", "' . $piece_lower . '"), "' . $piece_lower . '")';
            }
            array_push($processed_pieces, $item);
        }
        if (empty($attribute_name)) {
            $result = 'text()[' . implode(' and ', $processed_pieces) . ']';
        } else {
            $result = "@" . $attribute_name . "[" . implode(" and ", $processed_pieces) . "]";
        }
        return $result;
    }
    public static function generate_regex($processing_text = '', $is_absolutely_matched = false, $case_sensitive = false)
    {
        $regex_string = str_replace(GmiCommon::ALIAS_SYMBOL, '\W', $processing_text);
        $regex_string = str_replace('?', '\?', $regex_string);
        $regex_string = str_replace('+', '\+', $regex_string);
        $regex_string = str_replace('$', '\$', $regex_string);
        $regex_string = str_replace('^', '\^', $regex_string);
        if ($is_absolutely_matched) {
            $regex_string = '/^' . $regex_string . '$/';
        } else {
            $regex_string = '/' . $regex_string . '/';
        }
        if (!$case_sensitive) {
            $regex_string .= 'i';
        }

        return $regex_string;
    }
}

class GmiRemoteElement
{
    public $remote_element_id; // string
    private $exts; // GmiChromeManager
    public $is_array_child = false;
    public $index = -1;

    public function __construct($remote_element_id, &$exts, $index = -1)
    {
        $this->remote_element_id = $remote_element_id;
        $this->exts = $exts;
        if ($index > -1) {
            $this->index = $index;
            $this->is_array_child = true;
        }
    }

    public function __debugInfo()
    {
        return [
            "remote_element_id" => $this->remote_element_id,
            "is_array_child" => $this->is_array_child,
            "index" => $this->index,
        ];
    }

    public function get($attribute_name = '')
    {
        $result = $this->exts->execute_javascript('arguments[0].' . $attribute_name, [$this]);

        return $result;
    }

    public function getText()
    {
        $result = $this->exts->execute_javascript('arguments[0].innerText;', [$this]);

        return $result;
    }

    public function getAttribute($attribute_name = '')
    {
        $result = $this->exts->execute_javascript('arguments[0].' . $attribute_name, [$this]);

        return $result;
    }

    public function getHtmlAttribute($attribute_name = '')
    {
        $result = $this->exts->execute_javascript('arguments[0].getAttribute(arguments[1]);', [$this, $attribute_name]);

        return $result;
    }

    public function querySelector($selector = '')
    {
        return $this->exts->querySelector($selector, $this);
    }

    public function querySelectorAll($selector = '')
    {
        return $this->exts->querySelectorAll($selector, $this);
    }

    public function queryXpath($xpath = '')
    {
        return $this->exts->queryXpath($xpath, $this);
    }

    public function queryXpathAll($xpath = '')
    {
        return $this->exts->queryXpathAll($xpath, $this);
    }

    public function click($x_on_element = 0, $y_on_element = 0)
    {
        $this->exts->click($this, $x_on_element, $y_on_element);
    }

    public function getAmountTextIfAvailable()
    {
        return GmiString::extractAmountAndCurrency($this->get('innerText'));
    }

    public function getJavascriptIdentify()
    {
        if ($this->index > -1) {
            return $this->remote_element_id . "[$this->index]";
        } else {
            return $this->remote_element_id;
        }
    }

    public function clear($forceClearInput = false)
    {
        try {
            $javascript_identify = $this->getJavascriptIdentify();
            $selectInput = ["expression" => "$javascript_identify.focus(); $javascript_identify.select();"];
            $clear_by_js = ["expression" => "$javascript_identify.value = ''"];
            if (is_numeric($this->exts->current_context->executionContextId)) {
                $selectInput["contextId"] = $this->exts->current_context->executionContextId;
            }
            $keyDown = GmiKeyboardEvent::generate_keyevent("keyDown", GmiKeyboardEvent::DELETE);
            $keyUp = GmiKeyboardEvent::generate_keyevent("keyUp", GmiKeyboardEvent::DELETE);
            $all_messages = [
                ["method" => "Runtime.evaluate", "params" => $selectInput],
                ["method" => "Input.dispatchKeyEvent", "params" => $keyDown],
                ["method" => "Input.dispatchKeyEvent", "params" => $keyUp],
            ];

            if ($forceClearInput) {
                $clear_by_js = ["expression" => "$javascript_identify.value = ''"];
                if (is_numeric($this->exts->current_context->executionContextId)) {
                    $clear_by_js["contextId"] = $this->exts->current_context->executionContextId;
                }
                array_push($all_messages, ["method" => "Runtime.evaluate", "params" => $clear_by_js]);
            }
            $this->exts->send_websocket_request_multi($this->exts->current_context->webSocketDebuggerUrl, $all_messages);
        } catch (\Exception $exception) {
            $this->exts->log('Error in clear input - ' . $exception->getMessage());
        }
    }

    public function scroll_to_and_focus()
    {
        try {
            $javascript_identify = $this->getJavascriptIdentify();
            $this->exts->execute_javascript("$javascript_identify.scrollIntoViewIfNeeded(); $javascript_identify.focus();");
        } catch (\Exception $exception) {
            $this->exts->log('Error in clear input - ' . $exception->getMessage());
        }
    }
}

class GmiKeyboardEvent
{
    public const  BACKSPACE = ["key" => "Backspace", "code" => "Backspace", "keyCode" => 8];
    public const  TAB = ["key" => "Tab", "code" => "Tab", "keyCode" => 9];
    public const  NUMLOCK = ["key" => "Clear", "code" => "NumLock"];
    public const  ENTER = ["key" => "Enter", "code" => "Enter", "keyCode" => 13];
    public const  SHIFT = ["key" => "Shift", "code" => "ShiftLeft", "keyCode" => 16];
    public const  SHIFTLEFT = ["key" => "Shift", "code" => "ShiftLeft", "keyCode" => 16];
    public const  SHIFTRIGHT = ["key" => "Shift", "code" => "ShiftRight", "keyCode" => 16];
    public const  CONTROL = ["key" => "Control", "code" => "ControlLeft", "keyCode" => 17];
    public const  CONTROLLEFT = ["key" => "Control", "code" => "ControlLeft", "keyCode" => 17];
    public const  CONTROLRIGHT = ["key" => "Control", "code" => "ControlRight", "keyCode" => 17];
    public const  ALT = ["key" => "Alt", "code" => "AltLeft", "keyCode" => 18];
    public const  ALTLEFT = ["key" => "Alt", "code" => "AltLeft", "keyCode" => 18];
    public const  ALTRIGHT = ["key" => "Alt", "code" => "AltRight", "keyCode" => 18];
    public const  CAPSLOCK = ["key" => "CapsLock", "code" => "CapsLock", "keyCode" => 20];
    public const  ESCAPE = ["key" => "Escape", "code" => "Escape", "keyCode" => 27];
    public const  DELETE = ["key" => "Delete", "code" => "Delete", "keyCode" => 46];

    public static function generate_keyevent($type, $char, $shift = false, $alt = false, $ctrl = false, $cmd = false)
    {
        // $type Allowed Values: keyDown, keyUp, rawKeyDown, char
        if (is_array($char)) {
            // Util keys like Tab, Enter, Delete...
            $modifiers = 0 + ($shift ? 8 : 0) + ($alt ? 1 : 0) + ($ctrl ? 2 : 0) + ($cmd ? 4 : 0);
            $key_event = [
                "type"          => $type,
                "modifiers"     => $modifiers, // Alt=1, Ctrl=2, Meta/Command=4, Shift=8. Ctrl+Alt=3..
                "code"          => $char["code"],
                "key"           => $char["key"],
                "windowsVirtualKeyCode" => $char["keyCode"],
                "nativeVirtualKeyCode" => $char["keyCode"],
                // "isSystemKey"=> true
            ];
        } else {
            $code = '';
            $key = '';
            $keyIdentifier = '';
            if (!empty($char)) {
                $dec_value = mb_ord($char, 'UTF-8');
                $hex_value = strtoupper(dechex($dec_value));
                $keyIdentifier = 'U+' . str_pad($hex_value, 4, '0', STR_PAD_LEFT);
                if (preg_match('/[A-Za-z]/', $char)) {
                    $code = 'Key' . strtoupper($char);
                    $key = $char;
                    if ($type == 'keyUp') {
                        $key = strtolower($char);
                    }
                } else {
                    if (preg_match('/[0-9]/', $char)) {
                        $code = 'Digit' . strtoupper($char);
                        $key = $char;
                    }
                }
            }
            $modifiers = 0 + ($shift ? 8 : 0) + ($alt ? 1 : 0) + ($ctrl ? 2 : 0) + ($cmd ? 4 : 0);
            $key_event = [
                "type" => $type,
                "modifiers" => $modifiers, // Alt=1, Ctrl=2, Meta/Command=4, Shift=8. Ctrl+Alt=3..
                "text" => $char,
                "keyIdentifier" => $keyIdentifier,
                "code" => $code,
                "key" => $key,
                // "isSystemKey"=> true
            ];
            if (preg_match("/[0-9a-zA-Z]/", $char)) {
                $key_event["windowsVirtualKeyCode"] = ord(strtoupper($char));
                $key_event["nativeVirtualKeyCode"] = ord(strtoupper($char));
            }
        }
        return $key_event;
    }
}

class GmiString
{
    private $original_string = '';

    public function __construct($original_string)
    {
        if (empty($original_string)) {
            //echo "Warning: GmiString empty init string source\n";
        } else {
            if (is_numeric($original_string)) {
                //echo "Warning: GmiString Received number instead of string\n";
                $this->$original_string = '' . $original_string;
            }
        }
        if (empty($original_string)) {
            //echo "Warning: GmiString expecting string. Seem you inputed something wrong\n";
            //print_r($original_string);
        } else {
            $this->original_string = $original_string;
        }
    }

    public function __toString()
    {
        return $this->original_string;
    }

    public static function extend($original_string = '')
    {
        $instance = new self($original_string);

        return $instance;
    }

    public function contains($needle, $case_sensitive = false)
    {
        if (empty($needle)) {
            //echo __FUNCTION__ . ": Parameter needle can not be empty\n";

            return false;
        }
        $located_index = stripos($this->original_string, $needle);
        if ($case_sensitive) {
            $located_index = strpos($this->original_string, $needle);
        }
        if ($located_index !== false) {
            return true;
        } else {
            return false;
        }
    }

    public function containsAny(array $needles, $case_sensitive = false)
    {
        if (empty($needles)) {
            //echo __FUNCTION__ . ": Parameter needles can not be empty\n";

            return false;
        }
        foreach ($needles as $needle) {
            if (self::contains($needle, $case_sensitive)) {
                return true;
            }
        }

        return false;
    }

    public function containsAll(array $needles, $case_sensitive = false)
    {
        if (empty($needles)) {
            //echo __FUNCTION__ . ": Parameter needles can not be empty\n";

            return false;
        }
        foreach ($needles as $needle) {
            if ($this->contains($needle, $case_sensitive) === false) {
                return false;
            }
        }

        return true;
    }

    public function indexOf($needle, $case_sensitive = false)
    {
        if (empty($needle)) {
            //echo __FUNCTION__ . ": Parameter needle can not be empty\n";

            return -1;
        }
        $located_index = stripos($this->$original_string, $needle);
        if ($case_sensitive) {
            $located_index = strpos($this->$original_string, $needle);
        }
        if ($located_index !== false) {
            return $located_index;
        } else {
            return -1;
        }
    }

    public function getTextBetween($text_before = '', $text_after = '', $trim_result = true)
    {
        $return_text = '';
        $haystack = $this->original_string;
        if (!empty($text_before) && !empty($text_after)) {
            $first_cut = stripos($haystack, $text_before);
            if ($first_cut !== false) {
                $return_text = substr($haystack, $first_cut + strlen($text_before));
                $temp_array = explode($text_after, $return_text);
                $return_text = reset($temp_array);
            } else {
                if (stripos($haystack, $text_after) !== false) {
                    $temp_array = explode($text_after, $haystack);
                    $return_text = reset($temp_array);
                }
            }
        } else {
            if (!empty($text_before) && stripos($haystack, $text_before) !== false) {
                $first_cut = stripos($haystack, $text_before);
                $return_text = substr($haystack, $first_cut + strlen($text_before));
            } else {
                if (!empty($text_after) && stripos($haystack, $text_after) !== false) {
                    $temp_array = explode($text_after, $haystack);
                    $return_text = reset($temp_array);
                } else {
                    if (empty($text_before) && empty($text_after)) {
                        $return_text = $haystack;
                    }
                }
            }
        }
        if ($trim_result) {
            $return_text = trim($return_text);
        }

        return $return_text;
    }

    public static function extractAmountAndCurrency($amountText = '')
    {
        $invoiceAmount = self::extractAmount($amountText);
        $currency_text = self::extractCurrency($amountText);

        return $invoiceAmount . ' ' . $currency_text;
    }

    public static function extractAmount($amountText = '')
    {
        $invoiceAmount = preg_replace('/[^\d\.\,]/', '', $amountText);

        return trim($invoiceAmount);
    }

    public static function extractCurrency($amountText = '')
    {
        $currency_text = '';
        if (stripos($amountText, 'A$') !== false) {
            $currency_text = 'AUD';
        } else {
            if (stripos($amountText, '$') !== false) {
                $currency_text = 'USD';
            } else {
                if (stripos(urlencode($amountText), '%C2%A3') !== false) {
                    $currency_text = 'GBP';
                } else {
                    if (stripos(urlencode($amountText), '%E2%82%AC') !== false) {
                        $currency_text = 'EUR';
                    }
                }
            }
        }

        return $currency_text;
    }

    public static function generate_locate_string($prefix = '')
    {
        $random_string = substr(str_shuffle(str_repeat($x = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil(3 / strlen($x)))), 1, 3);

        return $prefix . $random_string . time();
    }

    public static function generate_random_string($prefix = 'gmi', $random_part_length = 5, $subfix = '')
    {
        $random_string = substr(str_shuffle(str_repeat($x = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($random_part_length / strlen($x)))), 1, $random_part_length);
        if (is_int($prefix) && $prefix > 0) {
            $prefix = self::generate_random_lowercase_string($prefix);
        }
        if (is_int($subfix) && $subfix > 0) {
            $subfix = self::generate_random_lowercase_string($subfix);
        }
        $return_value = $prefix . $random_string . $subfix;

        return $return_value;
    }

    public static function generate_random_uppercase_string($random_part_length = 5)
    {
        $random_string = substr(str_shuffle(str_repeat($x = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($random_part_length / strlen($x)))), 1, $random_part_length);
        $return_value = $random_string;

        return $return_value;
    }

    public static function generate_random_lowercase_string($random_part_length = 5)
    {
        $random_string = substr(str_shuffle(str_repeat($x = 'abcdefghijklmnopqrstuvwxyz', ceil($random_part_length / strlen($x)))), 1, $random_part_length);
        $return_value = $random_string;

        return $return_value;
    }
}
