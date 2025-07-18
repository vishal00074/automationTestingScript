<?php // 

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

    // Server-Portal-ID: 70820 - Last modified: 24.06.2025 14:43:47 UTC - User: 1

    public $baseUrl = "https://members.helium10.com/user/signin";
    public $loginUrl = "https://members.helium10.com/user/signin";
    public $homePageUrl = "https://members.helium10.com/";
    public $billPageUrl = "https://members.helium10.com/profile/billing";
    public $username_selector = "#loginform-email";
    public $password_selector = "#loginform-password";
    public $login_button_selector = "button[type='submit']";
    public $login_confirm_selector = 'a[href="/user/signout"], a[href*="subscribe?accountId="]';
    public $billingPageUrl = "https://my.leadpages.net/#/my-pages";
    public $account_selector = "a[href=\"/my-account/\"]";
    public $billing_selector = "a[href=\"/my-account/subscription/\"]";
    public $billing_history_selector = "a[href=\"/my-account/billing-history/\"]";
    public $dropdown_selector = "#img_DropDownIcon";
    public $dropdown_item_selector = "#di_billCycleDropDown";
    public $more_bill_selector = ".view-more-bills-btn";
    public $login_tryout = 0;
    public $restrictPages = 3;
    public $isNoInvoice = true;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->disable_extensions();
        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;

        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture("Home-page-without-cookie");

        $isCookieLoginSuccess = false;
        if ($this->exts->loadCookiesFromFile()) {
            $this->exts->openUrl($this->homePageUrl);
            sleep(15);
            if ($this->exts->getElement("#navbar-user-menu-button") != null) {
                $this->exts->moveToElementAndClick("#navbar-user-menu-button");
                sleep(2);
            }
            if ($this->checkLogin()) {
                $isCookieLoginSuccess = true;
            }
        }

        if (!$isCookieLoginSuccess) {
            $this->exts->capture("after-login-clicked");
            if ($this->exts->getElement('button[aria-label="close button"]') != null) {
                $this->exts->moveToElementAndClick('button[aria-label="close button"]');
                sleep(4);
            }
            $this->fillForm(0);

            if ($this->exts->exists($this->username_selector) && strpos($this->exts->extract('.field-loginform-recaptcha .invalid-feedback'), 'verification data is incorrect') !== false) {
                $this->fillForm(0);
            }
            sleep(5);

            if ($this->exts->getElement('span.intercom-post-close') != null) {
                $this->exts->moveToElementAndClick('span.intercom-post-close');
                sleep(3);
            }
            if ($this->exts->getElement("#navbar-user-menu-button") != null) {
                $this->exts->moveToElementAndClick("#navbar-user-menu-button");
                sleep(5);
            }
            sleep(10);
            $this->checkFillTwoFactor();
            sleep(5);
            if ($this->exts->urlContains('/email-accept-required')) {
                $this->checkFill2FAPushNotification();
            }
            sleep(30);

            if (!$this->exts->exists('[src*="images/avatars"]') && $this->exts->exists('h3.dashboard-welcome-title')) {
                $this->exts->refresh();
                sleep(20);
            }

            if ($this->checkLogin()) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $this->exts->capture("LoginSuccess");

                sleep(5);
                if ($this->exts->exists('div[class*="Gzf"]')) {
                    $this->exts->click_by_xdotool('div[class*="Gzf"]');
                    sleep(15);
                }

                if ($this->exts->exists('a[href*="/subscribe?accountId="]')) {
                    $this->exts->click_by_xdotool('a[href*="/subscribe?accountId="]');
                    sleep(15);
                }
                if ($this->exts->exists('a[href*="/account/billing?accountId="]')) {
                    $this->exts->click_by_xdotool('a[href*="/account/billing?accountId="]');
                    sleep(15);
                }

                if ($this->restrictPages == '0') {
                    $currentUrl = $this->exts->getUrl();
                    $this->exts->log('URL - ' . $currentUrl);

                    $startDate = date('m/d/Y', strtotime('-24 months'));
                    $endtDate = date('m/d/Y');
                    $this->billPageUrl = $currentUrl . '&BillingStatementSearch[dateStart]=' . $startDate . '&BillingStatementSearch[dateEnd]=' . $endtDate;

                    $this->exts->openUrl($this->billPageUrl);
                    sleep(10);
                }

                $this->downloadInvoice();

                $this->exts->openUrl('https://members.helium10.com/account/billing');
                $this->processInvoices();

                // Final, check no invoice
                if ($this->isNoInvoice) {
                    $this->exts->no_invoice();
                }
                $this->exts->success();
            } else {

                if (strpos($this->exts->extract('.field-loginform-password div.invalid-feedback'), 'passwor') !== false) {
                    $this->exts->capture("LoginFailed");
                    $this->exts->loginFailure(1);
                } else {
                    $this->exts->capture("LoginFailed");
                    $this->exts->loginFailure();
                }
            }
        } else {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            if ($this->exts->exists('a[href*="/subscribe?accountId="]')) {
                $this->exts->click_by_xdotool('a[href*="/subscribe?accountId="]');
                sleep(15);
            }
            if ($this->exts->exists('a[href*="/account/billing?accountId="]')) {
                $this->exts->click_by_xdotool('a[href*="/account/billing?accountId="]');
                sleep(15);
            }


            if ($this->restrictPages == '0') {
                $currentUrl = $this->exts->getUrl();
                $this->exts->log('URL - ' . $currentUrl);

                $startDate = date('m/d/Y', strtotime('-24 months'));
                $endtDate = date('m/d/Y');
                $this->billPageUrl = $currentUrl . '&BillingStatementSearch[dateStart]=' . $startDate . '&BillingStatementSearch[dateEnd]=' . $endtDate;
                $this->exts->openUrl($this->billPageUrl);
                sleep(15);
            }

            $this->downloadInvoice();

            $this->exts->openUrl('https://members.helium10.com/account/billing');
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        }
    }

    private function disable_extensions()
    {
        $this->exts->openUrl('chrome://extensions/'); // disable Block origin extension
        sleep(2);
        $this->exts->execute_javascript("
		let manager = document.querySelector('extensions-manager');
		if (manager && manager.shadowRoot) {
			let itemList = manager.shadowRoot.querySelector('extensions-item-list');
			if (itemList && itemList.shadowRoot) {
				let items = itemList.shadowRoot.querySelectorAll('extensions-item');
				items.forEach(item => {
					let toggle = item.shadowRoot.querySelector('#enableToggle[checked]');
					if (toggle) toggle.click();
				});
			}
		}
	");
    }

    private function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {

            if ($this->exts->getElement($this->username_selector) != null && $this->exts->getElement($this->password_selector) != null) {
                sleep(2);

                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(2);
                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(2);

                $this->solve_captcha_by_clicking();

                if ($this->exts->exists($this->login_button_selector)) {
                    $this->exts->click_by_xdotool($this->login_button_selector);
                    sleep(10);
                }

                if (strpos($this->exts->extract('.field-loginform-password div.invalid-feedback'), 'passwor') !== false) {
                    $this->exts->capture("LoginFailed");
                    $this->exts->loginFailure(1);
                }

                if ($this->exts->exists('iframe[src*="/recaptcha/api2/anchor?"]')) {
                    $this->checkFillRecaptcha();
                    $this->exts->click_by_xdotool($this->login_button_selector);
                    sleep(10);
                }

                $this->solve_captcha_by_clicking();

                if ($this->exts->exists($this->login_button_selector)) {
                    $this->exts->click_by_xdotool($this->login_button_selector);
                    sleep(10);
                }

                if (strpos($this->exts->extract('.field-loginform-password div.invalid-feedback'), 'passwor') !== false) {
                    $this->exts->capture("LoginFailed");
                    $this->exts->loginFailure(1);
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
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
                    $this->exts->execute_javascript("arguments[0].innerHTML = '" . $this->exts->recaptcha_answer . "';", [$recaptcha_textareas[$i]]);
                }
                sleep(2);
                $this->exts->capture('recaptcha-filled');

                // Step 2, check if callback function need executed
                $gcallbackFunction = $this->exts->execute_javascript('
				if(document.querySelector("[data-callback]") != null){
					document.querySelector("[data-callback]").getAttribute("data-callback");
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
				found ? "___grecaptcha_cfg.clients[0]." + result : null;
			');
                $this->exts->log('Callback function: ' . $gcallbackFunction);
                $this->exts->log('Callback function: ' . $this->exts->recaptcha_answer);
                if ($gcallbackFunction != null) {
                    $this->exts->execute_javascript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
                    sleep(10);
                } else {
                    $this->exts->execute_javascript('onReCaptchaSubmit' . '("' . $this->exts->recaptcha_answer . '");');
                    sleep(10);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
        }
    }

    private function solve_captcha_by_clicking($count = 1)
    {
        $this->exts->log("Checking captcha");

        $unsolved_captcha_submit_selector = 'iframe[title="reCAPTCHA"]';
        $captcha_challenger_wraper_selector = 'div[style*="visibility: visible"] > div > iframe[title="recaptcha challenge expires in two minutes"]';

        $this->exts->waitTillAnyPresent([$unsolved_captcha_submit_selector, $captcha_challenger_wraper_selector], 20);

        if ($this->exts->check_exist_by_chromedevtool($unsolved_captcha_submit_selector) || $this->exts->exists($captcha_challenger_wraper_selector)) {
            $this->exts->capture("bahn-captcha");

            if (!$this->exts->check_exist_by_chromedevtool($captcha_challenger_wraper_selector)) {
                $this->exts->click_by_xdotool($unsolved_captcha_submit_selector);
                $this->exts->waitTillPresent($captcha_challenger_wraper_selector, 20);
            }
            $captcha_instruction = '';
            $language_code = '';

            //$captcha_instruction = $this->exts->extract($iframeElement_instartion,null, 'innerText');
            $this->exts->log('language_code: ' . $language_code . ' Instruction: ' . $captcha_instruction);
            sleep(5);

            if ($this->exts->exists($captcha_challenger_wraper_selector)) {
                $coordinates = $this->getCoordinates($captcha_challenger_wraper_selector, $captcha_instruction, '', $json_result = false);
                if ($coordinates != '') {
                    foreach ($coordinates as $coordinate) {
                        $this->click_hcaptcha_point($captcha_challenger_wraper_selector, (int) $coordinate['x'], (int) $coordinate['y']);
                    }

                    $this->exts->capture("02sqitch-captcha-selected " . $count);
                    if ($this->exts->exists($captcha_challenger_wraper_selector)) {
                        $this->exts->makeFrameExecutable($captcha_challenger_wraper_selector)->click_element('button[id="recaptcha-verify-button"]');
                    }
                    sleep(10);
                    return true;
                }
            }
            return false;
        }
    }

    private function click_hcaptcha_point($selector = '', $x_on_element = 0, $y_on_element = 0)
    {
        $this->exts->log(__FUNCTION__ . " $selector $x_on_element $y_on_element");
        $selector = base64_encode($selector);
        $element_coo = $this->exts->execute_javascript('
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
        // sleep(1);
        $this->exts->log("Browser clicking position: $element_coo");
        $element_coo = explode('|', $element_coo);

        $root_position = $this->exts->get_brower_root_position();
        $this->exts->log("Browser root position");
        $this->exts->log(print_r($root_position, true));

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

    private function getCoordinates(
        $captcha_image_selector,
        $instruction = '',
        $lang_code = '',
        $json_result = false,
        $image_dpi = 75
    ) {
        $this->exts->log("--GET Coordinates By 2CAPTCHA--");
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
                    $array = explode("coordinates:", $output);
                    $response = trim(end($array));
                    $coordinates = [];
                    $pairs = explode(';', $response);
                    foreach ($pairs as $pair) {
                        preg_match('/x=(\d+),y=(\d+)/', $pair, $matches);
                        if (!empty($matches)) {
                            $coordinates[] = ['x' => (int) $matches[1], 'y' => (int) $matches[2]];
                        }
                    }
                    $this->exts->log('>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>');
                    $this->exts->log(print_r($coordinates, true));
                    return $coordinates;
                }
            }
        }

        if ($response == '') {
            $this->exts->log("Can not get result from API");
        }
        return $response;
    }

    /**
     * Method to Check where user is logged in or not
     * return boolean true/false
     */
    private function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        for ($i = 0; $i < 5 && $this->exts->getElements('[src*="images/avatars"]') == null; $i++) {
            sleep(5);
        }
        try {
            if ($this->exts->getElement($this->login_confirm_selector) != null) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            } else if ($this->exts->getElement('[src*="images/avatars"]') != null) {
                $this->exts->moveToElementAndClick('[src*="images/avatars"]');
                sleep(2);
                if ($this->exts->getElement($this->login_confirm_selector) != null) {
                    $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                    $isLoggedIn = true;
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }

    private function checkFillTwoFactor()
    {
        $this->exts->capture("2-2fa-checking");

        if ($this->exts->getElement('form#login-form input#verification-code-input') != null && $this->exts->urlContains('/code-required')) {
            $two_factor_selector = 'form#login-form input#verification-code-input';
            $two_factor_message_selector = 'form#login-form';
            $two_factor_submit_selector = 'form#login-form button[type="submit"]';
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
                }
                $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            }
            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            $this->exts->notification_uid = "";
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(10);

                if ($this->exts->getElement($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->checkFillTwoFactor();
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }

    private function checkFill2FAPushNotification()
    {
        $two_factor_message_selector = 'h3[class="login-form-wrapper__title"]';
        $two_factor_submit_selector = '';
        if ($this->exts->getElement($two_factor_message_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");
            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = join("\n", $this->exts->getElementsAttribute($two_factor_message_selector, 'innerText'));
                $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en) . ' Please input "OK" when finished!!';
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
                $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            }
            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }
            $two_factor_code = trim(strtolower($this->exts->fetchTwoFactorCode()));
            if (!empty($two_factor_code) && trim($two_factor_code) == 'ok') {
                $this->exts->log("checkFillTwoFactorForMobileAcc: Entering two_factor_code." . $two_factor_code);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
                sleep(15);
                if ($this->exts->getElement('div.eds-modal__content svg#mail-chunky_svg__eds-icon--mail-chunky_svg') == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->checkFill2FAPushNotification();
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }

    /**
     *method to download incoice
     */
    private function downloadInvoice($pageCount = 1)
    {
        $this->exts->waitTillPresent('div#billing-container > div > table > tbody tr', 30);
        $this->exts->log("Begin downlaod invoice ");
        $this->exts->capture("Invoice-Page-" . $pageCount);
        sleep(2);
        try {
            if ($this->exts->getElement("div#billing-container > div > table > tbody tr > td:nth-child(3) > div > div:nth-child(2) > div > button") != null) {
                $receipts = $this->exts->getElements("div#billing-container > div > table > tbody tr");
                $this->exts->log("No of Invoice " . count($receipts));
                $this->isNoInvoice = false;
                for ($j = 1; $j <= count($receipts); $j++) {
                    $receiptNameSelector = "div#billing-container > div > table > tbody > tr:nth-child(" . ($j) . ")";
                    $receiptName = $this->exts->getElement($receiptNameSelector)->getHtmlAttribute("data-key");
                    $this->exts->log("Receipt Name:" . $receiptName);
                    $receiptDateSelector = "div#billing-container > div > table > tbody > tr:nth-child(" . ($j) . ") > td:nth-child(2)";
                    $receiptDateInit = $this->exts->getElement($receiptDateSelector)->getAttribute('innerText');
                    $receiptDate = substr($receiptDateInit, 0, strpos($receiptDateInit, ",") + 1 + strpos(substr($receiptDateInit, strpos($receiptDateInit, ",") + 1), ","));
                    $this->exts->log("Receipt Date:" . $receiptDate);
                    $parsed_date = $this->exts->parse_date($receiptDate, 'F j, Y', 'Y-m-d');
                    $this->exts->log("Parsed Date:" . $parsed_date);
                    $receiptAmountSelector = "div#billing-container > div > table > tbody > tr:nth-child(" . ($j) . ") > td:nth-child(4)";
                    $receiptAmount = str_replace("$", "", $this->exts->getElement($receiptAmountSelector)->getAttribute('innerText')) . " USD";
                    $this->exts->log("receipt amount: " . $receiptAmount);
                    $receiptSelector = "div#billing-container > div > table > tbody > tr:nth-child(" . ($j) . ") > td:nth-child(3) > div > div:nth-child(2) > div > button";
                    sleep(2);
                    $this->exts->moveToElementAndClick($receiptSelector);
                    sleep(15);
                    $downloadSelector = "div#billing-container > div > table > tbody > tr:nth-child(" . ($j) . ") > td:nth-child(3) > div > div:nth-child(2) > div > ul > li:nth-child(3)";
                    $downloaded_file = $this->exts->click_and_download($downloadSelector, 'pdf', $receiptName . '.pdf');
                    sleep(10);
                    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($receiptName, $parsed_date, $receiptAmount, $downloaded_file);
                        sleep(2);
                    }
                }
            } else if ($this->exts->getElement("div#billing-container table tbody tr td:nth-child(3) > div > div:nth-child(2) > div > button") != null) {
                $receipts = $this->exts->getElements("div#billing-container table tbody tr");
                $this->exts->log("No of Invoice " . count($receipts));
                $this->isNoInvoice = false;
                foreach ($receipts as $receipt) {
                    $tags = $this->exts->getElements('td', $receipt);
                    if (count($tags) >= 4 && $this->exts->getElement('button', $tags[2]) != null) {
                        $receiptName = trim($receipt->getHtmlAttribute("data-key"));
                        $this->exts->log("Receipt Name:" . $receiptName);

                        $receiptDateInit = trim($tags[1]->getAttribute('innerText'));
                        $receiptDate = substr($receiptDateInit, 0, strpos($receiptDateInit, ",") + 1 + strpos(substr($receiptDateInit, strpos($receiptDateInit, ",") + 1), ","));
                        $this->exts->log("Receipt Date:" . $receiptDate);
                        $parsed_date = $this->exts->parse_date($receiptDate, 'F j, Y', 'Y-m-d');
                        $this->exts->log("Parsed Date:" . $parsed_date);

                        $receiptAmount = str_replace("$", "", $tags[3]->getAttribute('innerText')) . " USD";
                        $this->exts->log("receipt amount: " . $receiptAmount);

                        $invoiceBtn = $this->exts->getElement('button', $tags[2]);
                        try {
                            $invoiceBtn->click();
                        } catch (\Exception $exception) {
                            $this->exts->execute_javascript('arguments[0].click();', [$invoiceBtn]);
                        }
                        sleep(5);

                        $downloadSelector = $this->exts->getElement('.dropdown .dropdown-item[onclick*="&type=pdf"]', $tags[2]);
                        if ($downloadSelector != null) {
                            try {
                                $downloadSelector->click();
                            } catch (\Exception $exception) {
                                $this->exts->execute_javascript('arguments[0].click();', [$downloadSelector]);
                            }
                            sleep(30);

                            $this->exts->wait_and_check_download('pdf');

                            $downloaded_file = $this->exts->find_saved_file('pdf', $receiptName . '.pdf');
                            if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                                $this->exts->new_invoice($receiptName, $parsed_date, $receiptAmount, $downloaded_file);
                                sleep(2);
                            } else {
                                sleep(30);
                                $this->exts->wait_and_check_download('pdf');

                                $downloaded_file = $this->exts->find_saved_file('pdf', $receiptName . '.pdf');
                                if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                                    $this->exts->new_invoice($receiptName, $parsed_date, $receiptAmount, $downloaded_file);
                                    sleep(2);
                                }
                            }
                        }
                    }
                }
            }

            $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
            if ($restrictPages == 0 && $pageCount < 50 && $this->exts->querySelector('.pagination .page-item.next:not(.disabled) a') != null) {
                $pageCount++;
                $this->exts->click_by_xdotool('.pagination .page-item.next:not(.disabled) a');
                sleep(5);
                $this->downloadInvoice($pageCount);
            } else if ($restrictPages > 0 && $pageCount < $restrictPages && $this->exts->querySelector('.pagination .page-item.next:not(.disabled) a') != null) {
                $pageCount++;
                $this->exts->click_by_xdotool('.pagination .page-item.next:not(.disabled) a');
                sleep(5);
                $this->downloadInvoice($pageCount);
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception downlaoding invoice " . $exception->getMessage());
        }
    }
    private function processInvoices($paging_count = 1)
    {
        $this->exts->waitTillPresent('table tbody tr', 50);
        // Filter Date upto 2 year back and use restricted page to handle the the number of pages to download
        $this->exts->waitTillPresent('input#datepicker-start', 30);
        if ($this->exts->exists('input#datepicker-start')) {
            $date = date('m/d/Y', strtotime('-2 years'));
            $this->exts->log($date);
            $this->exts->click_by_xdotool('input#datepicker-start');
            sleep(2);
            $this->exts->type_key_by_xdotool('ctrl+a');
            sleep(2);
            $this->exts->type_key_by_xdotool('Delete');
            sleep(2);
            $this->exts->type_text_by_xdotool($date);
            sleep(2);
            $this->exts->type_key_by_xdotool('Return');
            sleep(5);
        }
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('table tbody tr');
        foreach ($rows as $row) {
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 6 && $this->exts->querySelector('div.dropdown-item[onclick*="pdf"]', $tags[5]) != null) {
                $invoiceUrl = '';
                $invoiceName = trim($tags[2]->getAttribute('innerText'));
                $invoiceAmount = trim($tags[4]->getAttribute('innerText'));
                $invoiceDate = trim($tags[2]->getAttribute('innerText'));

                $downloadBtn = $this->exts->querySelector('div.dropdown-item[onclick*="pdf"]', $tags[5]);

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl,
                    'downloadBtn' => $downloadBtn
                ));
                $this->isNoInvoice = false;
            } else if (count($tags) >= 5 && $this->exts->querySelector('div.dropdown-item[onclick*="pdf"]', $tags[3]) != null) {
                $invoiceUrl = '';
                $invoiceName = trim($tags[1]->getAttribute('innerText'));
                $invoiceAmount = trim($tags[4]->getAttribute('innerText'));
                $invoiceDate = trim($tags[2]->getAttribute('innerText'));

                $downloadBtn = $this->exts->querySelector('div.dropdown-item[onclick*="pdf"]', $tags[3]);

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl,
                    'downloadBtn' => $downloadBtn
                ));
                $this->isNoInvoice = false;
            }
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd-M-y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $this->exts->execute_javascript("arguments[0].click();", [$invoice['downloadBtn']]);
            $this->exts->wait_and_check_download('pdf');
            $downloaded_file = $this->exts->find_saved_file('pdf');
            $invoiceFileName = basename($downloaded_file);

            $invoice['invoiceName'] = substr($invoiceFileName, 0, strrpos($invoiceFileName, '.'));

            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
        // next page
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
        if (
            $restrictPages == 0 && $paging_count < 50 &&
            $this->exts->querySelector('li.next:not(.disabled) a') != null
        ) {
            $paging_count++;
            $this->exts->click_element('li.next:not(.disabled) a');
            sleep(5);
            $this->processInvoices($paging_count);
        } else if ($restrictPages > 0 && $paging_count < $restrictPages && $this->exts->querySelector('li.next:not(.disabled) a') != null) {
            $paging_count++;
            $this->exts->click_element('li.next:not(.disabled) a');
            sleep(5);
            $this->processInvoices($paging_count);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
