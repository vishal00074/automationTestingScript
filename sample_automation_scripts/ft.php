<?php // migrated updated recaptcha code updated selector and message to trigger login failed confirmed

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

    // Server-Portal-ID: 72624 - Last modified: 29.05.2024 14:09:33 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = "https://accounts.ft.com/login";
    public $loginUrl = "https://accounts.ft.com/login";
    public $homePageUrl = "https://www.ft.com/";
    public $username_selector = "#enter-email";
    public $username_confirm_selector = "#enter-email-next";
    public $password_selector = "#enter-password";
    public $login_button_selector = "button#sign-in-button";
    public $login_confirm_selector = "a[href='/logout']";
    public $account_selector = 'nav[role="navigation"] a[href="https://myaccount.ft.com/details/core/view"]';
    public $billing_selector = 'a[href*="account"]';
    public $billing_history_selector = 'a[href="/my-account/billing-history/"]';
    public $dropdown_selector = "#img_DropDownIcon";
    public $dropdown_item_selector = "#di_billCycleDropDown";
    public $more_bill_selector = ".view-more-bills-btn";
    public $login_tryout = 0;
    public $loginSucces = 0;
    public $noInvoice = true;
    public $restrictPages = 3;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture("Home-page-without-cookie");

        $isCookieLoginSuccess = false;
        if ($this->exts->loadCookiesFromFile()) {
            $this->exts->openUrl($this->homePageUrl);
            sleep(30);
            if ($this->checkLogin()) {
                $isCookieLoginSuccess = true;
            }
        }

        if (!$isCookieLoginSuccess) {
            $this->exts->openUrl($this->loginUrl);
            sleep(5);
            $this->exts->capture("after-login-clicked");
            $this->fillForm(0);
            sleep(15);
            if ($this->checkLogin()) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $this->exts->capture("LoginSuccess");
                $this->invoicePage();
            } else {

                //
                $error_text = strtolower($this->exts->extract('p.o-message__content-main'));

                $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);
                if (stripos($error_text, strtolower('incorrect')) !== false) {
                    $this->exts->loginFailure(1);
                } else if ($this->exts->exists('form#login-form div.alert-message p')) {
                    $this->exts->log('Wrng credentials!!');
                    $this->exts->loginFailure(1);
                } else {
                    $this->exts->capture("LoginFailed");
                    $this->exts->loginFailure();
                }
            }
        } else {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->invoicePage();
        }
    }

    private  function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {

            if ($this->exts->getElement($this->username_selector) != null) {

                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(2);
                $this->exts->moveToElementAndClick($this->username_confirm_selector);
                sleep(5);
            }
            if ($this->exts->getElement($this->password_selector) != null) {
                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(2);
                $this->exts->moveToElementAndClick($this->login_button_selector);
                sleep(7);

                $this->check_solve_hcaptcha_challenge();
                for ($i = 0; $i < 2; $i++) {
                    if ($this->exts->exists('iframe[src*="/recaptcha/api2/anchor?"]')) {
                        $this->checkFillRecaptcha();
                    } else {
                        break;
                    }
                }
                $this->check_solve_hcaptcha_challenge();

                if ($this->exts->exists($this->login_button_selector)) {
                    $this->exts->moveToElementAndClick($this->login_button_selector);
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }


    private function check_solve_hcaptcha_challenge()
    {
        $this->exts->log("Start Solving Captcha");
        $unsolved_hcaptcha_submit_selector = 'iframe[src*="hcaptcha.com/captcha"]'; // script selector
        $hcaptcha_challenger_wraper_selector = 'div[style*="visibility: visible"] > div  >  iframe[src*="frame=challenge"]'; // script selector
        if ($this->exts->exists($unsolved_hcaptcha_submit_selector) || $this->exts->exists($hcaptcha_challenger_wraper_selector)) { // if exist hcaptcha and it isn't solved
            // Check if challenge images hasn't showed yet, Click checkbox to show images challenge
            $this->exts->log("Captcha found");
            if (!$this->exts->exists($hcaptcha_challenger_wraper_selector)) {
                $this->exts->click_by_xdotool($unsolved_hcaptcha_submit_selector);
                sleep(5);
            }

            if ($this->exts->exists($hcaptcha_challenger_wraper_selector)) { // Select language English always
                $this->exts->log("Select language English always");
                $wraper_side = $this->exts->evaluate('
				window.lastMousePosition = null;
				window.addEventListener("mousemove", function(e){
					window.lastMousePosition = e.clientX +"|" + e.clientY;
				});
				var coo = document.querySelector(atob("' . base64_encode($hcaptcha_challenger_wraper_selector) . '")).getBoundingClientRect();
				coo.width + "|" + coo.height;
			');
                $evalJson = json_decode($wraper_side, true);
                $wraper_side = $evalJson['result']['result']['value'];

                $this->exts->log('Select English language ' . $wraper_side);
                $wraper_side = explode('|', $wraper_side);
                $this->exts->click_by_xdotool($hcaptcha_challenger_wraper_selector, 20, (int)$wraper_side[1] - 71);
                sleep(1);
                $this->exts->type_key_by_xdotool('e');
                sleep(1);
                $this->exts->type_key_by_xdotool('Return');
                sleep(2);
            }
            $this->exts->log("prcess hcaptcha start");

            $this->process_hcaptcha_by_clicking();
            $this->process_hcaptcha_by_clicking();
            sleep(5);
            if ($this->exts->exists($hcaptcha_challenger_wraper_selector)) {
                $this->process_hcaptcha_by_clicking();
                $this->process_hcaptcha_by_clicking();
                $this->process_hcaptcha_by_clicking();
                $this->process_hcaptcha_by_clicking();
                sleep(5);
            }
            if ($this->exts->exists($hcaptcha_challenger_wraper_selector)) {
                $this->process_hcaptcha_by_clicking();
                $this->process_hcaptcha_by_clicking();
                sleep(5);
            }
            sleep(10);
            $this->exts->capture("2-after-solving-hcaptcha");
        } else {
            $this->exts->log("Captcha Not found");
        }
    }
    private function process_hcaptcha_by_clicking()
    {
        $unsolved_hcaptcha_submit_selector = 'button[name="login"].h-captcha[data-size="invisible"]';
        $hcaptcha_challenger_wraper_selector = 'div[style*="visibility: visible"] > div  >  iframe[src*="frame=challenge"]';
        if ($this->exts->exists($unsolved_hcaptcha_submit_selector) || $this->exts->exists($hcaptcha_challenger_wraper_selector)) { // if exist hcaptcha and it isn't solved
            $this->exts->capture("hcaptcha");
            // Check if challenge images hasn't showed yet, Click checkbox to show images challenge
            if (!$this->exts->exists($hcaptcha_challenger_wraper_selector)) {
                $this->exts->click_by_xdotool($unsolved_hcaptcha_submit_selector);
                sleep(5);
            }
            // $this->exts->switchToDefault();
            if ($this->exts->exists($hcaptcha_challenger_wraper_selector)) { // If image chalenge doesn't displayed, maybe captcha solved after clicking checkbox
                $captcha_instruction = '';
                $old_height = $this->exts->evaluate('
				var wrapper = document.querySelector(atob("' . base64_encode($hcaptcha_challenger_wraper_selector) . '"));
				var old_height = wrapper.style.height;
				wrapper.style.height = "600px";
				old_height
			');
                $evalJson = json_decode($old_height, true);
                $old_height = $evalJson['result']['result']['value'];
                $coordinates = $this->processClickCaptcha($hcaptcha_challenger_wraper_selector, $captcha_instruction, '', $json_result = true, 85); // use $language_code and $captcha_instruction if they changed captcha content
                if ($coordinates == '') {
                    $coordinates = $this->processClickCaptcha($hcaptcha_challenger_wraper_selector, $captcha_instruction, '', $json_result = true, 85);
                }
                if ($coordinates != '') {
                    if ($this->exts->exists($hcaptcha_challenger_wraper_selector)) {
                        if (!empty($old_height)) {
                            $this->exts->evaluate('
							document.querySelector(atob("' . base64_encode($hcaptcha_challenger_wraper_selector) . '")).style.height = "' . $old_height . '";
						');
                        }

                        foreach ($coordinates as $coordinate) {
                            if (!$this->exts->exists($hcaptcha_challenger_wraper_selector)) {
                                $this->exts->log('Error');
                                return;
                            }
                            $this->click_hcaptcha_point($hcaptcha_challenger_wraper_selector, (int)$coordinate['x'], (int)$coordinate['y']);
                            // sleep(1);
                            if (!$this->exts->exists($hcaptcha_challenger_wraper_selector)) {
                                $this->exts->log('Error');
                                return;
                            }
                        }
                        $marked_time = time();
                        $this->exts->capture("hcaptcha-selected-" . $marked_time);

                        $wraper_side = $this->exts->evaluate('
						var coo = document.querySelector(atob("' . base64_encode($hcaptcha_challenger_wraper_selector) . '")).getBoundingClientRect();
						coo.width + "|" + coo.height;
					');

                        $evalJson = json_decode($wraper_side, true);
                        $wraper_side = $evalJson['result']['result']['value'];

                        $wraper_side = explode('|', $wraper_side);
                        $this->click_hcaptcha_point($hcaptcha_challenger_wraper_selector, (int)$wraper_side[0] - 50, (int)$wraper_side[1] - 30);

                        sleep(5);
                        $this->exts->capture("hcaptcha-submitted-" . $marked_time);
                    }
                }
            }
            return true;
        }
        return false;
    }

    private function click_hcaptcha_point($selector = '', $x_on_element = 0, $y_on_element = 0)
    {
        $this->exts->log(__FUNCTION__ . " $selector $x_on_element $y_on_element");
        $selector = base64_encode($selector);
        $element_coo = $this->exts->evaluate('
		var x_on_element = ' . $x_on_element . '; 
		var y_on_element = ' . $y_on_element . ';
		var coo = document.querySelector(atob("' . $selector . '")).getBoundingClientRect();
		// Default get center point in element, if offset inputted, out put them
		if(x_on_element > 0 || y_on_element > 0) {
			Math.round(coo.x + x_on_element) + "|" + Math.round(coo.y + y_on_element);
		} else {
			Math.round(coo.x + coo.width/2) + "|" + Math.round(coo.y + coo.height/2);
		}
		
	');
        $evalJson = json_decode($element_coo, true);
        $element_coo = $evalJson['result']['result']['value'];
        // sleep(1);
        $this->exts->log("Browser clicking position: $element_coo");
        $element_coo = explode('|', $element_coo);

        $root_position = $this->get_brower_root_position();
        $this->exts->log("Browser root position");
        print_r($root_position);

        $clicking_x = (int)$element_coo[0] + (int)$root_position['root_x'];
        $clicking_y = (int)$element_coo[1] + (int)$root_position['root_y'];
        $this->exts->log("Screen clicking position: $clicking_x $clicking_y");
        $node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-" . $this->exts->process_uid;
        // move randomly
        exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 60, $clicking_x + 60) . " " . rand($clicking_y - 50, $clicking_y + 50) . "'");
        exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 50, $clicking_x + 50) . " " . rand($clicking_y - 50, $clicking_y + 50) . "'");
        exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 40, $clicking_x + 40) . " " . rand($clicking_y - 41, $clicking_y + 40) . "'");
        exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 30, $clicking_x + 30) . " " . rand($clicking_y - 35, $clicking_y + 30) . "'");
        exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 20, $clicking_x + 20) . " " . rand($clicking_y - 25, $clicking_y + 25) . "'");
        exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 10, $clicking_x + 10) . " " . rand($clicking_y - 10, $clicking_y + 10) . "'");

        exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . $clicking_x . " " . $clicking_y . " click 1;'");
    }

    private function get_brower_root_position($force_relocated = false)
    {
        if (isset($GLOBALS['browser_root_position']) && is_array($GLOBALS['browser_root_position']) && !$force_relocated) {
            return $GLOBALS['browser_root_position'];
        }

        $GLOBALS['browser_root_position'] = null;
        $node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-" . $this->exts->process_uid;
        for ($i = 0; $i < 5; $i++) {
            $x = rand(100, 355);
            $y = rand(370, 500);
            $this->exts->log("Getting browser current cursor... Screen reference point $x $y");
            $this->exts->evaluate('
			window.localStorage["lastMousePosition"] = "";
			window.addEventListener("mousemove", function(e){
				window.localStorage["lastMousePosition"] = e.clientX +"|" + e.clientY;
			});
		');
            exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove --sync $x $y '");
            exec("sudo docker exec " . $node_name . " bash -c 'xdotool getmouselocation'", $output);
            $this->exts->log("Latest mouse posision on screen: ");
            print_r($output);

            $result = $this->exts->evaluate('window.localStorage["lastMousePosition"]');
            $evalJson = json_decode($result, true);
            $result = $evalJson['result']['result']['value'];

            if (isset($result['value']) && !empty($result['value'])) {
                $this->exts->log('Browser current cursor: ' . $result['value']);
                $current_cursor = explode('|', $result['value']);
                $GLOBALS['browser_root_position'] = array(
                    'root_x' => $x - (int)$current_cursor[0],
                    'root_y' => $y - (int)$current_cursor[1],
                );
                return $GLOBALS['browser_root_position'];
            }
        }

        $this->exts->log('CAN NOT detect root position of browser webview');
        return null;
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
        $image_path = $this->exts->captureElement($this->exts->process_uid, $captcha_image_selector);
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

    private function checkFillRecaptcha()
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
    private  function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        if ($this->exts->exists('a[data-trackable="keep-current-product"]')) {
            $this->exts->moveToElementAndClick('a[data-trackable="keep-current-product"]');
            sleep(10);
        }
        $isLoggedIn = false;

        try {
            if ($this->exts->getElement($this->login_confirm_selector) != null) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }

    private function invoicePage()
    {
        if ($this->exts->exists('a[href*="/details/core/view"]')) {
            $this->exts->moveToElementAndClick('a[href*="/details/core/view"]');
            sleep(15);
        }

        $this->exts->moveToElementAndClick($this->billing_selector);
        sleep(20);

        if ($this->exts->getElement($this->username_selector) != null && $this->exts->getElement($this->username_confirm_selector) != null) {
            $this->exts->clearCookies();
            sleep(2);
            $this->exts->openUrl($this->loginUrl);
            sleep(15);

            $this->fillForm(0);
            sleep(15);
            if (!$this->checkLogin()) {
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure();
            }
            // $this->invoicePage();
            // return;

            if ($this->exts->exists('a[href*="/details/core/view"]')) {
                $this->exts->moveToElementAndClick('a[href*="/details/core/view"]');
                sleep(15);
            }
        }

        $mesg = strtolower(trim($this->exts->extract('div.ncf__error-page h1', null, 'innerText')));
        if (strpos($mesg, 'this is not available online') !== false) {
            $this->exts->clearCookies();
            sleep(2);
            $this->exts->openUrl($this->loginUrl);
            sleep(15);

            $this->fillForm(0);
            sleep(15);
            if (!$this->checkLogin()) {
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure();
            }

            if ($this->exts->exists('a[href*="/details/core/view"]')) {
                $this->exts->moveToElementAndClick('a[href*="/details/core/view"]');
                sleep(15);
            }
        }

        $this->exts->moveToElementAndClick('a[href="#o-header-drawer"]');
        sleep(15);

        $this->exts->moveToElementAndClick('nav.o-header__drawer-menu--user a[href*="/details/core/view"]');
        sleep(15);

        $this->exts->moveToElementAndClick($this->billing_selector);
        sleep(20);

        $this->exts->moveToElementAndClick('a[href*="purchase"]');
        sleep(20);
        // $this->downloadInvoice();
        $this->downloadInvoiceNew();

        if ($this->noInvoice) {
            $this->exts->log("No Invoice ");
            $this->exts->no_invoice();
        }

        $this->exts->success();
    }

    /**
     *method to download incoice
     */

    private function downloadInvoice($paging_count = 1)
    {
        $this->exts->log("Begin download invoice ");
        sleep(2);
        try {
            if ($this->exts->getElement('table#pms-invoices > tbody > tr') != null) {
                $receipts = $this->exts->getElements('table#pms-invoices > tbody > tr:not([style="display: none;"])');
                $invoices = array();
                foreach ($receipts as $i => $receipt) {
                    $tags = $this->exts->getElements('td', $receipt);
                    if (count($tags) == 4 && $this->exts->getElement('a[href*="/invoices/download"]', $receipt) != null) {
                        $receiptDate = trim($tags[0]->getText());
                        $receiptName = trim($tags[1]->getText());
                        $receiptUrl = $this->exts->extract('a[href*="/invoices/download"]', $receipt, 'href');
                        $amountText = trim($tags[2]->getText());
                        $receiptAmount = preg_replace('/[^\d\.\,]/', '', $amountText);
                        if (stripos($amountText, 'A$') !== false) {
                            $receiptAmount = $receiptAmount . ' AUD';
                        } else if (stripos($amountText, '$') !== false) {
                            $receiptAmount = $receiptAmount . ' USD';
                        } else if (stripos(urlencode($amountText), '%C2%A3') !== false) {
                            $receiptAmount = $receiptAmount . ' GBP';
                        } else {
                            $receiptAmount = $receiptAmount . ' EUR';
                        }

                        $receiptFileName = !empty($receiptName) ? $receiptName . '.pdf' : '';

                        $this->exts->log("_____________________" . ($i + 1) . "___________________________________________");
                        $this->exts->log("Invoice Date: " . $receiptDate);
                        $this->exts->log("Invoice Name: " . $receiptName);
                        $this->exts->log("Invoice Amount: " . $receiptAmount);
                        $this->exts->log("Invoice Url: " . $receiptUrl);
                        $this->exts->log("Invoice FileName: " . $receiptFileName);
                        $this->exts->log("________________________________________________________________");

                        $invoice = array(
                            'receiptDate' => $receiptDate,
                            'receiptName' => $receiptName,
                            'receiptAmount' => $receiptAmount,
                            'receiptUrl' => $receiptUrl,
                            'receiptFileName' => $receiptFileName
                        );
                        array_push($invoices, $invoice);
                    }
                }
                $this->exts->log('Invoice found: ' . count($invoices));
                sleep(5);
                foreach ($invoices as $invoice) {
                    $downloaded_file = $this->exts->direct_download($invoice['receiptUrl'], 'pdf', $invoice['receiptFileName']);
                    $this->exts->log("Download file: " . $downloaded_file);

                    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoice['receiptName'], $invoice['receiptDate'], $invoice['receiptAmount'], $downloaded_file);
                        sleep(1);
                    }
                    $this->noInvoice = false;
                }
            }

            if (
                $this->exts->exists('#pagination-bottom button.pg-normal[aria-selected="true"] + button') &&
                !$this->exts->exists('#pagination-bottom button.pg-normal[aria-selected="true"] + button > i') && $this->restrictPages == 0 && $paging_count < 50
            ) {
                $paging_count++;
                $this->exts->moveToElementAndClick('#pagination-bottom button.pg-normal[aria-selected="true"] + button');
                sleep(5);
                $this->downloadInvoice($paging_count);
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception downlaoding invoice " . $exception->getMessage());
        }
    }



    private function downloadInvoiceNew($paging_count = 1)
    {
        $this->exts->log("Begin download invoice ");
        sleep(2);
        try {
            if ($this->exts->getElement('table > tbody > tr') != null) {
                $receipts = $this->exts->getElements('table > tbody > tr:not([style="display: none;"])');
                $invoices = array();
                foreach ($receipts as $i => $receipt) {
                    $tags = $this->exts->getElements('td', $receipt);
                    if (count($tags) == 3) {
                        $receiptDate = trim($tags[0]->getText());
                        $receiptName = $receiptDate;
                        $receiptUrl = $this->exts->extract('a', $tags[2], 'href');

                        $amountText = trim($tags[1]->getText());
                        $receiptAmount = preg_replace('/[^\d\.\,]/', '', $amountText);
                        if (stripos($amountText, 'A$') !== false) {
                            $receiptAmount = $receiptAmount . ' AUD';
                        } else if (stripos($amountText, '$') !== false) {
                            $receiptAmount = $receiptAmount . ' USD';
                        } else if (stripos(urlencode($amountText), '%C2%A3') !== false) {
                            $receiptAmount = $receiptAmount . ' GBP';
                        } else {
                            $receiptAmount = $receiptAmount . ' EUR';
                        }

                        $receiptFileName = !empty($receiptName) ? $receiptName . '.pdf' : '';

                        $this->exts->log("_____________________" . ($i + 1) . "___________________________________________");
                        $this->exts->log("Invoice Date: " . $receiptDate);
                        $this->exts->log("Invoice Name: " . $receiptName);
                        $this->exts->log("Invoice Amount: " . $receiptAmount);
                        $this->exts->log("Invoice Url: " . $receiptUrl);
                        $this->exts->log("Invoice FileName: " . $receiptFileName);
                        $this->exts->log("________________________________________________________________");

                        $invoice = array(
                            'receiptDate' => $receiptDate,
                            'receiptName' => $receiptName,
                            'receiptAmount' => $receiptAmount,
                            'receiptUrl' => $receiptUrl,
                            'receiptFileName' => $receiptFileName
                        );
                        array_push($invoices, $invoice);
                    }
                }
                $this->exts->log('Invoice found: ' . count($invoices));
                sleep(5);
                foreach ($invoices as $invoice) {
                    $downloaded_file = $this->exts->direct_download($invoice['receiptUrl'], 'pdf', $invoice['receiptFileName']);
                    $this->exts->log("Download file: " . $downloaded_file);

                    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoice['receiptName'], $invoice['receiptDate'], $invoice['receiptAmount'], $downloaded_file);
                        sleep(1);
                    }
                    $this->noInvoice = false;
                }
            }

            if (
                $this->exts->exists('#pagination-bottom button.pg-normal[aria-selected="true"] + button') &&
                !$this->exts->exists('#pagination-bottom button.pg-normal[aria-selected="true"] + button > i') && $this->restrictPages == 0 && $paging_count < 50
            ) {
                $paging_count++;
                $this->exts->moveToElementAndClick('#pagination-bottom button.pg-normal[aria-selected="true"] + button');
                sleep(5);
                $this->downloadInvoiceNew($paging_count);
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception downlaoding invoice " . $exception->getMessage());
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$portal = new PortalScriptCDP("optimized-chrome-v2", 'WeWork Account Central', '2675013', 'Y2hyaXN0aWFuLndpbGRAc2VuZi5hcHA=', 'SGFsbG9TZW5mMTIz');
$portal->run();
