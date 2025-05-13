<?php

/**
 * Chrome Remote via Chrome devtool protocol script, for specific process/portal
 *
 * @package uwa
 *
 * @copyright   GetMyInvoices
 */

define('KERNEL_ROOT', '/var/www/remote-chrome/utils/');

$gmi_browser_core = realpath('/var/www/remote-chrome/utils/GmiChromeManager.php');
require_once($gmi_browser_core);
class PortalScriptCDP
{

    private $exts;
    public $setupSuccess = false;
    private $chrome_manage;
    private $username;
    private $password;

    public function __construct($mode, $portal_name, $process_uid, $username, $password)
    {
        $this->username = $username;
        $this->password = $password;

        $this->exts = new GmiChromeManager();
        $this->exts->screen_capture_location = '/var/www/remote-chrome/screens/';
        $this->exts->init($mode, $portal_name, $process_uid, $username, $password);
        $this->setupSuccess = true;
    }

    /**
     * Method that called first for executing portal script, this method should not be altered by Users.
     */
    public function run()
    {
        if ($this->setupSuccess) {
            try {
                // Start portal script execution
                $this->initPortal(0);
            } catch (\Exception $exception) {
                $this->exts->log('Exception: ' . $exception->getMessage());
                $this->exts->capture("error");
                var_dump($exception);
            }


            $this->exts->log('Execution completed');

            $this->exts->process_completed();
            $this->exts->dump_session_files();
        } else {
            echo 'Script execution failed.. ' . "\n";
        }
    }

    // Server-Portal-ID: 8808 - Last modified: 08.07.2024 14:05:34 UTC - User: 1

    public $baseUrl = "https://thenounproject.com/accounts/login/";
    public $username_selector = '#user_id, input[type="text"]';
    public $password_selector = '#id_password, input[type="password"]';
    public $submit_btn = 'form#login-form button.button-process';
    public $logout_btn = '[href="/logout/"], img[class*="UserAvatar"]';


    /**
     * Method to click on element by css selector
     * @param String $sel Css selector
     * @param Integer $sleep Sleep time (in seconds) after click
     */
    function click($sel, $sleep = 3)
    {
        $el = $this->exts->getElementByCssSelector($sel);
        if ($el != null) {
            $this->exts->log("Execute Click on " . $sel);
            $el->click();
            sleep($sleep);
            return true;
        } else {
            $this->exts->log("Can't execute Click on " . $sel);
            return false;
        }
    }

    /**
     * Method to sendKeys on element by css selector
     * @param String $sel Css selector
     * @param String $keys Keys to send
     * @param Integer $sleep Sleep time (in seconds) after sendKeys
     */
    function sendKeys($sel, $keys, $sleep = 3)
    {
        $el = $this->exts->getElementByCssSelector($sel);
        if ($el != null) {
            $el->clear();
            $el->sendKeys($keys);
            sleep($sleep);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Method to change value of select box
     * @param String $sel Css selector
     * @param String $value Value to send
     * @param Integer $sleep Sleep time (in seconds) after sendKeys
     */

    function changeSelectbox($sel, $value, $sleep = 3)
    {
        $el = $this->exts->getElementByCssSelector($sel);
        if ($el != null) {
            $this->exts->selectDropdownByValue($el, $value);
        }
        sleep($sleep);
    }

    /**
     * check if a element is exists
     * @param String $sel Css selector
     */
    function exists($sel)
    {
        try {
            return $this->exts->getElementByCssSelector($sel) != null;
        } catch (\Exception $exception) {
            $this->exts->log("Exception exists - " . $sel . "- " . $exception->getMessage());
            return false;
        }
    }

    /**
     * Method to sendKeys on element by css selector
     * @param String $sel Css selector
     * @param WebDriverElement $parent parent element to search from
     * @param String $attr attribute to get
     */
    function extract($sel, $parent, $attr = 'text')
    {
        $el = $this->exts->getElementByCssSelector($sel, $parent);
        if ($el != null) {
            if ($attr == 'text') {
                return $el->getText();
            } else {
                return $el->getAttribute($attr);
            }
        } else {
            return null;
        }
    }

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->install_xdotool();
        $isCookieLoaded = false;
        if ($this->exts->loadCookiesFromFile()) {
            sleep(1);
            $isCookieLoaded = true;
        }

        $this->exts->openUrl($this->baseUrl);
        sleep(10);

        $str = "var div = document.querySelector('div.two-million-modal-container'); if (div != null) {  div.style.display = \"none\"; }";
        $this->exts->executeSafeScript($str);
        sleep(2);

        if ($isCookieLoaded) {
            $this->exts->capture("Home-page-with-cookie");
        } else {
            $this->exts->capture("Home-page-without-cookie");
        }

        if (!$this->checkLogin()) {
            $this->exts->capture("after-login-clicked");
            $this->fillForm(0);
            sleep(20);

            $str = "var div = document.querySelector('div.two-million-modal-container'); if (div != null) {  div.style.display = \"none\"; }";
            $this->exts->executeSafeScript($str);
            sleep(2);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");


            $this->exts->moveToElementAndClick("div.user-navigation div.avatar-container, img[class*='UserAvatar']");
            sleep(5);

            $this->exts->moveToElementAndClick('[href*="/settings/invoices/"]');
            sleep(20);
            $this->downloadInvoice(0);
        } else {
            $this->exts->capture("LoginFailed");
            if (strpos($this->exts->extract('form#login-form div.error, div[class*=FormError]'), 'password you specified are not correct') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    /**
     * Method to fill login form
     * @param Integer $count Number of times portal is retried.
     */
    function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {

            if ($this->exists($this->username_selector)) {
                sleep(2);
                $this->exts->capture("1-pre-login");

                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);

                $this->exts->capture("1-pre-login-1");
                $this->checkFillRecaptcha(0);
                $this->check_solve_hcaptcha_challenge();
                $this->check_solve_hcaptcha_challenge();

                if ($this->exts->exists($this->submit_btn)) {
                    $this->exts->moveToElementAndClick($this->submit_btn);
                }
                if ($this->exts->getElement('.//button[contains(text(),"Log In")][not(contains(text(),"Facebook"))]', null, 'xpath') != null) {
                    $login_button = $this->exts->getElement('.//button[contains(text(),"Log In")][not(contains(text(),"Facebook"))]', null, 'xpath');
                    try {
                        $this->exts->log(__FUNCTION__ . ' trigger click.');
                        $login_button->click();
                    } catch (\Exception $exception) {
                        $this->exts->log(__FUNCTION__ . ' by javascript' . $exception);
                        $this->exts->executeSafeScript("arguments[0].click()", [$login_button]);
                    }
                    sleep(10);
                }
            } else if ($this->exists("iframe[src*=\"https://www.google.com/recaptcha/api2/anchor?\"]") && $this->exists("textarea[name=\"g-recaptcha-response\"]") &&  $count < 3) {
                $this->checkFillRecaptcha(0);
                $count++;
                $this->fillForm($count);
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    function checkFillRecaptcha($counter)
    {
        $this->exts->log(__FUNCTION__);
        $recaptcha_iframe_selector = 'iframe[src*="/recaptcha/api2/anchor?"]';
        $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
        if ($this->exts->exists($recaptcha_iframe_selector)) {
            $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
            $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
            $this->exts->log("iframe url  - " . $iframeUrl);
            $this->exts->log("SiteKey - " . $data_siteKey);

            $isCaptchaSolved = $this->exts->processRecaptcha($this->exts->getUrl(), $data_siteKey, false);
            $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);

            if ($isCaptchaSolved) {
                // Step 1 fill answer to textarea
                $this->exts->log(__FUNCTION__ . "::filling reCaptcha response..");
                $recaptcha_textareas =  $this->exts->getElements($recaptcha_textarea_selector);
                for ($i = 0; $i < count($recaptcha_textareas); $i++) {
                    $this->exts->executeSafeScript("arguments[0].innerHTML = '" . $this->exts->recaptcha_answer . "';", [$recaptcha_textareas[$i]]);
                }
                sleep(2);
                $this->exts->capture('recaptcha-filled');

                // Step 2, check if callback function need executed
                $gcallbackFunction = $this->exts->executeSafeScript('
                if(document.querySelector("[data-callback]") != null){
                    return document.querySelector("[data-callback]").getAttribute("data-callback");
                }

                var result = ""; var found = false;
                function recurse (cur, prop, deep) {
                    if(deep > 5 || found){ return;}console.log(prop);
                    try {
                        if(cur == undefined || cur == null || cur instanceof Element || Object(cur) !== cur || Array.isArray(cur)){ return;}
                        if(prop.indexOf(".callback") > -1){result = prop; found = true; return;
                        } else { deep++;
                            for (var p in cur) { recurse(cur[p], prop ? prop + "." + p : p, deep);}
                        }
                    } catch(ex) { console.log("ERROR in function: " + ex); return; }
                }

                recurse(___grecaptcha_cfg.clients[0], "", 0);
                return found ? "___grecaptcha_cfg.clients[0]." + result : null;
            ');
                $this->exts->log('Callback function: ' . $gcallbackFunction);
                if ($gcallbackFunction != null) {
                    $this->exts->executeSafeScript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
                    sleep(10);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
        }
    }

    /**
     * Method to Check where user is logged in or not
     * return boolean true/false
     */
    function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {

            for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->check_login_success_selector . "');") != 1; $wait++) {
                $this->exts->log('Waiting for login.....');
                sleep(10);
            }
            if ($this->exists($this->logout_btn) && $this->exists($this->username_selector) == false) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }

        return $isLoggedIn;
    }

    // solve hcaptcha by clicking
    private function check_solve_hcaptcha_challenge()
    {
        $unsolved_hcaptcha_submit_selector = 'button[name="login"].h-captcha[data-size="invisible"]';
        $hcaptcha_challenge_selector = 'div[style*="visibility: visible"] > div  >  iframe[src*="frame=challenge"]';
        if (!$this->check_exist_by_chromedevtool($hcaptcha_challenge_selector) && $this->check_exist_by_chromedevtool('div[data-testid="captcha-container"]')) {
            $this->click_by_xdotool('div[data-testid="captcha-container"]');
            sleep(5);
        }
        if ($this->check_exist_by_chromedevtool($hcaptcha_challenge_selector)) {
            // $this->click_hcaptcha_checkbox($unsolved_hcaptcha_submit_selector);
            // for ($w=0; $w < 15 && !$this->check_exist_by_chromedevtool($hcaptcha_challenge_selector); $w++) { 
            // 	sleep(1);
            // }
            // sleep(5);
            if ($this->check_exist_by_chromedevtool($hcaptcha_challenge_selector)) {
                $wraper_height = $this->evaluate_by_chromedevtool('
                window.lastMousePosition = null;
                window.addEventListener("mousemove", function(e){
                    window.lastMousePosition = e.clientX +"|" + e.clientY;
                });
                var coo = document.querySelector(atob("' . base64_encode($hcaptcha_challenge_selector) . '")).getBoundingClientRect();
                coo.height;
            ')['result']['value'];

                $this->exts->log('Select English language ' . $wraper_height);
                if ((int)$wraper_height > 650) { // 520
                    $this->click_by_xdotool($hcaptcha_challenge_selector, 22, $wraper_height - ($wraper_height / 10));
                } else {
                    $this->click_by_xdotool($hcaptcha_challenge_selector, 22, $wraper_height - ($wraper_height / 8));
                }

                sleep(1);
                $this->capture_by_chromedevtool("hcaptcha-click-langu");
                $this->type_key_by_xdotool('e');
                sleep(1);
                $this->type_key_by_xdotool('Return');
                sleep(2);
            }

            $this->process_hcaptcha_by_clicking();
            $this->process_hcaptcha_by_clicking();
            sleep(10);
            $this->capture_by_chromedevtool("2-after-solving-hcaptcha");
        }
    }
    private function process_hcaptcha_by_clicking()
    {
        $hcaptcha_iframe_selector = '.h-captcha[data-size="normal"] iframe[data-hcaptcha-response=""]';
        $hcaptcha_challenger_wraper_selector = 'div[style*="visibility: visible"] > div  >  iframe[src*="frame=challenge"]';
        if ($this->check_exist_by_chromedevtool($hcaptcha_challenger_wraper_selector)) { // if exist hcaptcha and it isn't solved
            $this->capture_by_chromedevtool("hcaptcha");
            // Check if challenge images hasn't showed yet, Click checkbox to show images challenge
            if (!$this->check_exist_by_chromedevtool($hcaptcha_challenger_wraper_selector)) {
                $unsolved_hcaptcha_iframe_selector = '.h-captcha[data-size="normal"] iframe[data-hcaptcha-response=""]';
                $this->click_hcaptcha_checkbox($unsolved_hcaptcha_iframe_selector);
                sleep(5);
            }
            if ($this->check_exist_by_chromedevtool($hcaptcha_challenger_wraper_selector)) { // If image chalenge doesn't displayed, maybe captcha solved after clicking checkbox
                $captcha_instruction = '';
                $old_height = $this->evaluate_by_chromedevtool('
                var wrapper = document.querySelector(atob("' . base64_encode($hcaptcha_challenger_wraper_selector) . '"));
                var old_height = wrapper.style.height;
                wrapper.style.height = "600px";
                old_height
            ')['result']['value'];
                $coordinates = $this->processClickCaptcha($hcaptcha_challenger_wraper_selector, $captcha_instruction, '', $json_result = true, 85); // use $language_code and $captcha_instruction if they changed captcha content
                if ($coordinates == '') {
                    $coordinates = $this->processClickCaptcha($hcaptcha_challenger_wraper_selector, $captcha_instruction, '', $json_result = true, 85);
                }
                if ($coordinates != '') {
                    if ($this->check_exist_by_chromedevtool($hcaptcha_challenger_wraper_selector)) {
                        if (!empty($old_height)) {
                            $this->evaluate_by_chromedevtool('
                            document.querySelector(atob("' . base64_encode($hcaptcha_challenger_wraper_selector) . '")).style.height = "' . $old_height . '";
                        ');
                        }
                        $element_coo = $this->evaluate_by_chromedevtool('
                        window.lastMousePosition = null;
                        window.addEventListener("mousemove", function(e){
                            window.lastMousePosition = e.clientX +"|" + e.clientY;
                        });
                        var coo = document.querySelector(atob("' . base64_encode($hcaptcha_challenger_wraper_selector) . '")).getBoundingClientRect();
                        coo.x + "|" + coo.y;
                    ')['result']['value'];
                        $element_coo = explode('|', $element_coo);

                        $node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-" . $this->exts->process_uid;
                        $this->exts->log(' Move mouse to get root position: ');
                        exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove --sync 100 200'");
                        $current_cursor = $this->evaluate_by_chromedevtool('window.lastMousePosition')['result']['value'];
                        $this->exts->log('current_cursor: ' . $current_cursor);
                        $current_cursor = explode('|', $current_cursor);
                        $extra_offset_x = 100 - (int)$current_cursor[0];
                        $extra_offset_y = 200 - (int)$current_cursor[1];
                        foreach ($coordinates as $coordinate) {
                            if (!$this->check_exist_by_chromedevtool($hcaptcha_challenger_wraper_selector)) {
                                $this->exts->log('Error');
                                return;
                            }

                            // sleep(1);
                            // $this->click_by_xdotool($hcaptcha_challenger_wraper_selector, intval($coordinate['x']), intval($coordinate['y']));



                            $target_x = (int)$element_coo[0] + (int)$coordinate['x'] + (int)$extra_offset_x;
                            $target_y = (int)$element_coo[1] + (int)$coordinate['y'] + (int)$extra_offset_y;
                            $this->exts->log('Clicking X/Y: ' . $target_x . '/' . $target_y);
                            exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . $target_x . " " . $target_y . "; xdotool click 1;'");
                            // sleep(1);
                            if (!$this->check_exist_by_chromedevtool($hcaptcha_challenger_wraper_selector)) {
                                $this->exts->log('Error');
                                return;
                            }
                            // $actions = $this->exts->webdriver->action();
                            // $challenge_wraper = $this->exts->getElement($hcaptcha_challenger_wraper_selector);
                            // $actions->moveToElement($challenge_wraper, intval($coordinate['x']), intval($coordinate['y']))->click()->perform();
                        }
                        $this->capture_by_chromedevtool("hcaptcha-selected-" . time());

                        $wraper_width = $this->evaluate_by_chromedevtool('
                        window.lastMousePosition = null;
                        window.addEventListener("mousemove", function(e){
                            window.lastMousePosition = e.clientX +"|" + e.clientY;
                        });
                        var coo = document.querySelector(atob("' . base64_encode($hcaptcha_challenger_wraper_selector) . '")).getBoundingClientRect();
                        coo.width;
                    ')['result']['value'];
                        $wraper_height = $this->evaluate_by_chromedevtool('
                        window.lastMousePosition = null;
                        window.addEventListener("mousemove", function(e){
                            window.lastMousePosition = e.clientX +"|" + e.clientY;
                        });
                        var coo = document.querySelector(atob("' . base64_encode($hcaptcha_challenger_wraper_selector) . '")).getBoundingClientRect();
                        coo.height;
                    ')['result']['value'];
                        $this->exts->log('Submit captcha ' . $wraper_width);
                        // if((int)$wraper_width > 500) { // 520
                        // 	$this->click_by_xdotool($hcaptcha_challenger_wraper_selector, 470, 600);
                        // } else {
                        // 	$target_x = (int)$element_coo[0] + 345 + (int)$extra_offset_x;
                        // 	$target_y = (int)$element_coo[1] + 572 + (int)$extra_offset_y;
                        //     exec("sudo docker exec ".$node_name." bash -c 'xdotool mousemove ".$target_x." ".$target_y."; xdotool click 1;'");
                        // }
                        $this->click_by_xdotool($hcaptcha_challenger_wraper_selector, $wraper_width - 22, $wraper_height - 22);
                        sleep(2);
                    }
                }
            }
            return true;
        }
        return false;
    }
    private function install_xdotool()
    {
        $node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-" . $this->exts->process_uid;
        exec("sudo docker exec " . $node_name . " bash -c 'sudo apt-get clean'");
        exec("sudo docker exec " . $node_name . " bash -c 'sudo apt-get -y update'");
        // exec("sudo docker exec ".$node_name." bash -c 'sudo apt-get install -y xdotool'");
        exec("sudo docker exec " . $node_name . " bash -c 'sudo dpkg -l | sudo grep -qw xdotool || sudo apt-get install -y xdotool'");
    }
    private function type_text_by_xdotool($text = '', $delay = true)
    {
        $tmp = preg_split('~~u', $text, -1, PREG_SPLIT_NO_EMPTY);

        $node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-" . $this->exts->process_uid;

        foreach ($tmp as $char) {
            $this->exts->log($char);
            if ($delay) {
                sleep(rand(0.1, 1.5));
            }

            $char = '0x' . dechex(mb_ord($char));
            exec("sudo docker exec " . $node_name . " bash -c 'xdotool key " . $char . "'");
        }
    }
    private function type_key_by_xdotool($key = '')
    {
        $node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-" . $this->exts->process_uid;
        exec("sudo docker exec " . $node_name . " bash -c 'xdotool key " . $key . "'");
    }
    private function capture_by_chromedevtool($filename)
    {
        try {
            $devTools = new Chrome\ChromeDevToolsDriver($this->exts->webdriver);
            $base64_string = $devTools->execute(
                'Page.captureScreenshot',
                []
            );
            $ifp = fopen($this->exts->screen_capture_location . $filename . '.png', 'wb');
            fwrite($ifp, base64_decode($base64_string["data"]));
            fclose($ifp);
            $this->exts->log('Screenshot saved - ' . $this->exts->screen_capture_location . $filename . '.png');
            $node = $page_html = $devTools->execute(
                'DOM.getDocument',
                []
            )['root'];
            $page_html = $devTools->execute(
                'DOM.getOuterHTML',
                ['nodeId' => $node['nodeId'], 'backendNodeId' => $node['backendNodeId']]
            );
            file_put_contents($this->exts->screen_capture_location . $filename . '.html', $page_html);
        } catch (\Exception $exception) {
            $this->exts->log('Error in capture - ' . $exception->getMessage());
        }
    }
    private function evaluate_by_chromedevtool($javascript_expression = '')
    {
        $devTools = new Chrome\ChromeDevToolsDriver($this->exts->webdriver);
        $data_siteKey = $devTools->execute(
            'Runtime.evaluate',
            ['expression' => $javascript_expression]
        );
        return $data_siteKey;
    }
    private function click_hcaptcha_checkbox($hcaptcha_iframe_selector = '')
    {
        $hcaptcha_iframe_selector = base64_encode($hcaptcha_iframe_selector);
        $element_coo = $this->evaluate_by_chromedevtool('
        var selector = atob("' . $hcaptcha_iframe_selector . '");
        window.lastMousePosition = null;
        window.addEventListener("mousemove", function(e){
            window.lastMousePosition = e.clientX +"|" + e.clientY;
        });
        var coo = document.querySelector(selector).getBoundingClientRect();
        Math.round(coo.x + 16 + 15) + "|" + Math.round(coo.y + 23 + 15);
    ')['result']['value'];
        sleep(1);
        $this->exts->log('X/Y: ' . $element_coo);
        $element_coo = explode('|', $element_coo);
        $node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-" . $this->exts->process_uid;
        $this->exts->log(' Move: ');
        exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove --sync 100 250'");
        sleep(2);
        $current_cursor = $this->evaluate_by_chromedevtool('window.lastMousePosition')['result']['value'];
        $this->exts->log('current_cursor: ' . $current_cursor);

        $current_cursor = explode('|', $current_cursor);
        $offset_x = (int)$element_coo[0] - (int)$current_cursor[0];
        $offset_y = (int)$element_coo[1] - (int)$current_cursor[1];
        exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove_relative --sync " . $offset_x . " " . $offset_y . " click 1;'");
    }
    private function click_by_xdotool($selector = '', $x_on_element = 0, $y_on_element = 0)
    {
        $selector = base64_encode($selector);
        $element_coo = $this->evaluate_by_chromedevtool('
        var x_on_element = ' . $x_on_element . '; 
        var y_on_element = ' . $y_on_element . ';
        window.lastMousePosition = null;
        window.addEventListener("mousemove", function(e){
            window.lastMousePosition = e.clientX +"|" + e.clientY;
        });
        var coo = document.querySelector(atob("' . $selector . '")).getBoundingClientRect();
        // Default get center point in element, if offset inputted, out put them
        if(x_on_element > 0 || y_on_element > 0) {
            Math.round(coo.x + x_on_element) + "|" + Math.round(coo.y + y_on_element);
        } else {
            Math.round(coo.x + coo.width/2) + "|" + Math.round(coo.y + coo.height/2);
        }
        
    ')['result']['value'];
        // sleep(1);
        $this->exts->log('X/Y: ' . $element_coo);
        $element_coo = explode('|', $element_coo);
        $node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-" . $this->exts->process_uid;
        $this->exts->log(' Move: ');
        exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove --sync 550 550'");
        // sleep(1);
        $current_cursor = $this->evaluate_by_chromedevtool('window.lastMousePosition')['result']['value'];
        $this->exts->log('current_cursor: ' . $current_cursor);
        if ($current_cursor == null || empty($current_cursor)) {
            $this->evaluate_by_chromedevtool('
            window.lastMousePosition = null;
            window.addEventListener("mousemove", function(e){
                window.lastMousePosition = e.clientX +"|" + e.clientY;
            });');
            exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove 350 450; xdotool mousemove 500 500; xdotool mousemove 350 450'");
            sleep(2);
            $current_cursor = $this->evaluate_by_chromedevtool('window.lastMousePosition')['result']['value'];
            $this->exts->log('relocated current_cursor: ' . $current_cursor);
        }
        $current_cursor = explode('|', $current_cursor);
        $offset_x = (int)$element_coo[0] - (int)$current_cursor[0];
        $offset_y = (int)$element_coo[1] - (int)$current_cursor[1];
        exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove_relative " . $offset_x . " " . $offset_y . "; xdotool click 1;'");
    }
    private function click_double_by_xdotool($selector = '')
    {
        $selector = base64_encode($selector);
        $element_coo = $this->evaluate_by_chromedevtool('
        var selector = atob("' . $selector . '");
        window.lastMousePosition = null;
        window.addEventListener("mousemove", function(e){
            window.lastMousePosition = e.clientX +"|" + e.clientY;
        });
        var coo = document.querySelector(selector).getBoundingClientRect();
        Math.round(coo.x + coo.width/2) + "|" + Math.round(coo.y + coo.height/2);
    ')['result']['value'];
        sleep(3);
        $this->exts->log('X/Y: ' . $element_coo);
        if ($element_coo != null && !empty($element_coo)) {
            $element_coo = explode('|', $element_coo);
            $node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-" . $this->exts->process_uid;
            $this->exts->log(' Move: ');
            exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove 350 450'");
            $current_cursor = $this->evaluate_by_chromedevtool('window.lastMousePosition')['result']['value'];
            $this->exts->log('current_cursor: ' . $current_cursor);
            if ($current_cursor == null || empty($current_cursor)) {
                $this->evaluate_by_chromedevtool('
                window.lastMousePosition = null;
                window.addEventListener("mousemove", function(e){
                    window.lastMousePosition = e.clientX +"|" + e.clientY;
                });');
                exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove 350 450; xdotool mousemove 500 500; xdotool mousemove 350 450'");
                sleep(2);
                $current_cursor = $this->evaluate_by_chromedevtool('window.lastMousePosition')['result']['value'];
                $this->exts->log('relocated current_cursor: ' . $current_cursor);
            }

            $current_cursor = explode('|', $current_cursor);
            $offset_x = (int)$element_coo[0] - (int)$current_cursor[0];
            $offset_y = (int)$element_coo[1] - (int)$current_cursor[1];
            exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove_relative " . $offset_x . " " . $offset_y . " click --repeat 2 1;'");
        }
    }
    private function click_hold_by_xdotool($selector = '', $hold_seconds = 5)
    {
        $selector = str_replace('"', '\\"', $selector);
        $element_coo = $this->evaluate_by_chromedevtool('
        window.lastMousePosition = null;
        window.addEventListener("mousemove", function(e){
            window.lastMousePosition = e.clientX +"|" + e.clientY;
        });
        var coo = document.querySelector("' . $selector . '").getBoundingClientRect();
        Math.round(coo.x + coo.width/2) + "|" + Math.round(coo.y + coo.height/2);
    ')['result']['value'];
        sleep(3);
        $this->exts->log('X/Y: ' . $element_coo);
        $element_coo = explode('|', $element_coo);
        $node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-" . $this->exts->process_uid;
        $this->exts->log(' Move: ');
        exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove 350 450'");
        $current_cursor = $this->evaluate_by_chromedevtool('window.lastMousePosition')['result']['value'];
        $this->exts->log('current_cursor: ' . $current_cursor);

        $current_cursor = explode('|', $current_cursor);
        $offset_x = (int)$element_coo[0] - (int)$current_cursor[0];
        $offset_y = (int)$element_coo[1] - (int)$current_cursor[1];
        exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove_relative " . $offset_x . " " . $offset_y . " mousedown 1 sleep " . $hold_seconds . " mouseup 1;'");
    }
    private function get_input_length_by_chromedevtool($selector = '')
    {
        $selector = base64_encode($selector);
        return $this->evaluate_by_chromedevtool('
        var selector = atob("' . $selector . '");
        var input_element = document.querySelector(selector);
        if(input_element != null){
            input_element.value.length;
        } else {
            0;
        }
    ')["result"]['value'];
    }
    private function check_exist_by_chromedevtool($selector = '')
    {
        $selector = base64_encode($selector);
        return $this->evaluate_by_chromedevtool('
        var selector = atob("' . $selector . '");
        var elements = document.querySelectorAll(selector);
        if(elements.length > 0){
            true;
        } else {
            false;
        }
    ')["result"]['value'];
    }
    private function captureElement($fileName, $selector = null)
    {
        $screenshot = $this->exts->screen_capture_location . time() . ".png";
        $devTools = new Chrome\ChromeDevToolsDriver($this->exts->webdriver);
        $base64_string = $devTools->execute(
            'Page.captureScreenshot',
            []
        );
        $ifp = fopen($screenshot, 'wb');
        fwrite($ifp, base64_decode($base64_string["data"]));
        fclose($ifp);

        if (!file_exists($screenshot)) {
            $this->log("Could not save screenshot");
            return $screenshot;
        }

        if (!(bool)$selector) {
            return $screenshot;
        }

        $selector = base64_encode($selector);
        $javascript_expression = '
        var element = document.querySelector(atob("' . $selector . '"));
        var bcr = element.getBoundingClientRect();
        JSON.stringify(bcr);
    ';
        $result_text = $devTools->execute(
            'Runtime.evaluate',
            ['expression' => $javascript_expression]
        )["result"]['value'];
        $coodinate = json_decode($result_text, true);
        print_r($coodinate);

        // Copy
        $element_screenshot = $this->exts->screen_capture_location . $fileName . ".png";
        $src = imagecreatefrompng($screenshot);
        $dest = imagecreatetruecolor(round($coodinate['width']), round($coodinate['height']));
        imagecopy($dest, $src, 0, 0, round($coodinate['x']), round($coodinate['y']), round($coodinate['width']), round($coodinate['height']));
        imagepng($dest, $element_screenshot);

        if (!file_exists($element_screenshot)) {
            $this->exts->log("Could not save screenshot");

            return $screenshot;
        }

        return $element_screenshot;
    }
    private function processClickCaptcha(
        $captcha_image_selector,
        $instruction = '',
        $lang_code = '',
        $json_result = false,
        $image_dpi = 90
    ) {
        $this->exts->log("--CAll CLICK CAPTCHA SERVICE-");
        $response = '';
        $image_path = $this->captureElement($this->exts->process_uid, $captcha_image_selector);
        $source_image = imagecreatefrompng($image_path);
        imagejpeg($source_image, $this->exts->screen_capture_location . $this->exts->process_uid . '.jpg', $image_dpi);

        $cmd = $this->exts->config_array['click_captcha_shell_script'] . " --PROCESS_UID::" . $this->exts->process_uid . " --CAPTCHA_INSTRUCTION::" . urlencode($instruction) . " --LANG_CODE::" . urlencode($lang_code) . " --JSON_RESULT::" . urlencode($json_result);
        $this->exts->log('Executing command : ' . $cmd);
        exec($cmd, $output, $return_var);
        $this->exts->log('Command Result : ' . print_r($output, true));

        if (!empty($output)) {
            $output = trim($output[0]);
            if ($json_result) {
                if (strpos($output, '"status":1') !== false) {
                    $response = json_decode($output, true);
                    $response = $response['request'];
                }
            } else {
                if (strpos($output, 'coordinates:') !== false) {
                    $response = trim(end(explode("coordinates:", $output)));
                }
            }
        }
        if ($response == '') {
            $this->exts->log("Can not get result from API");
        }
        return $response;
    }
    //END block

    function downloadInvoice($count)
    {
        $this->exts->log("Begin download invoice - " . $count);
        try {
            if ($this->exists('.billing-history-table > tbody > tr')) {
                $this->exts->capture("2-download-invoice");
                $invoices = [];

                $rows = $this->exts->getElementsByCssSelector('.billing-history-table > tbody > tr');
                foreach ($rows as $row) {
                    $tags = $this->exts->getElementsByCssSelector('td', $row);
                    if (
                        count($tags) >= 4 && $this->exts->getElementByCssSelector('.payment-row-actions a[href*="/invoices"][class*="Link"]', $tags[3]) != null
                        && strpos(strtolower($tags[1]->getAttribute('innerText')), 'your card was declined') === false
                    ) {
                        $invoiceUrl = $this->exts->getElementByCssSelector('.payment-row-actions a[href*="/invoices"][class*="Link"]', $tags[3])->getAttribute("href");
                        $invoiceName = trim($tags[2]->getAttribute('innerText'));
                        $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                        $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[3]->getAttribute('innerText'))) . ' EUR';

                        array_push($invoices, array(
                            'invoiceName' => $invoiceName,
                            'invoiceDate' => $invoiceDate,
                            'invoiceAmount' => $invoiceAmount,
                            'invoiceUrl' => $invoiceUrl
                        ));
                        $this->isNoInvoice = false;
                    }
                }

                // Download all invoices
                $this->exts->log('Invoices found: ' . count($invoices));
                foreach ($invoices as $invoice) {
                    $this->exts->log('--------------------------');
                    $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
                    $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
                    $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
                    $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

                    $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
                    $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'm/d/Y', 'Y-m-d');
                    $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

                    $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $downloaded_file);
                        sleep(1);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }
            } else {
                $this->exts->log("No invoice !!! ");
                $this->exts->no_invoice();
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception downloading invoice " . $exception->getMessage());
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
