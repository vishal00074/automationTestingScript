<?php // script failed due to Connection refused  script working fine 
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

    // Server-Portal-ID: 80141 - Last modified: 02.09.2025 13:47:16 UTC - User: 1

    public $baseUrl = 'https://app.clickup.com';
    public $loginUrl = 'https://app.clickup.com/login';
    public $invoicePageUrl = '';
    public $username_selector = 'form input#email-input, input#login-email-input';
    public $password_selector = 'form input#password-input, input#login-password-input';
    public $remember_me_selector = '';
    public $submit_login_btn = 'form button#login-submit, button.login-page-new__main-form-button[type="submit"]';
    public $check_login_failed_selector = '.show form#login-form .cu-form__error.show';
    public $check_login_success_selector = 'div.cu-avatar-container, a#signout, .account-picker a, .user-main-settings-menu__avatar';
    public $isNoInvoice = true;

    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->disable_extensions();

        $this->exts->openUrl($this->baseUrl);
        sleep(3);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->checkFillLogin();

            $this->waitFor('input.cu-form__input.validate-input', 40);
            if ($this->exts->getElement('input.cu-form__input.validate-input') != null) {
                $this->exts->log(">>>>>>>>>>>>>>>checkFillTwoFactor!");
                $this->checkFillTwoFactor();
            }

            if ($this->exts->getElement('div.cu-modal__close, [class*=conflict-management__timezone-options-item]') != null) {
                $this->exts->click_by_xdotool('div.cu-modal__close, [class*=conflict-management__timezone-options-item]');
                sleep(5);
            }
        }

        if ($this->checkLogin() || $this->exts->getElement('input.cu-form__input.validate-input') != null) {
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            if ($this->isExists('div.cu-modal__close, [class*=conflict-management__timezone-options-item]')) {
                $this->exts->click_by_xdotool('div.cu-modal__close, [class*=conflict-management__timezone-options-item]');
                sleep(5);
            }
            $teamId = explode('/', array_pop(explode('app.clickup.com/', $this->exts->getUrl())))[0];
            $this->exts->log('teamId ' . $teamId);

            $this->invoicePageUrl = 'https://app.clickup.com/' . $teamId . '/settings/billing';
            // Open invoices url
            $this->exts->openUrl($this->invoicePageUrl);
            $this->processInvoices($teamId);
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if (count($this->exts->getElements($this->check_login_failed_selector)) > 0) {
                $this->exts->loginFailure(1);
            } else if (strpos(strtolower($this->exts->extract('[data-test="toast__new-item"]')), 'no account was found') !== false) {
                $this->exts->loginFailure(1);
            } else if (strpos(strtolower($this->exts->extract('[data-test="form__error"]')), 'incorrect password') !== false) {
                $this->exts->loginFailure(1);
            } else if ($this->exts->urlContains('app.clickup.com/onboarding') && $this->isExists('.cu-onboarding-v2')) {
                $this->exts->account_not_ready();
            } else if ($this->exts->urlContains('/team-setup/new')) {
                $this->exts->account_not_ready();
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    public function waitForAny($selectors = array(), $seconds = 10)
    {
        for ($wait = 0; $wait < 2; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            foreach ($selectors as $selector) {
                $this->exts->log('Element Finding:: ' . $selector);
                $isSelectorExists = $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');");
                if ($isSelectorExists) {
                    $this->exts->log('Element Found:: ' . $selector);
                    return true;
                }
            }
            sleep($seconds);
        }
    }


    private function isExists($selector = '')
    {
        $safeSelector = addslashes($selector);
        $this->exts->log('Element:: ' . $safeSelector);
        $isElement = $this->exts->execute_javascript('!!document.querySelector("' . $safeSelector . '")');
        if ($isElement) {
            $this->exts->log('Element Found');
            return true;
        } else {
            $this->exts->log('Element not Found');
            return false;
        }
    }

    public function waitFor($selector, $seconds = 10)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }

    private function checkFillLogin()
    {
        if ($this->exts->getElement($this->username_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            if ($this->isExists('button.cu-btn_sso-google') && !$this->isExists($this->password_selector)) {
                $this->exts->moveToElementAndClick('button.cu-btn_sso-google');
                sleep(15);

                $this->exts->capture("2-pre-login-new-after-continue-email");
                $this->exts->switchToNewestActiveTab();
                sleep(1);
                $this->exts->capture("2-pre-login-new-with-gmail");
                $this->loginGoogleIfRequired();
                sleep(3);
                $this->exts->capture("2-after-login-new-with-gmail-0");
                $this->exts->switchToInitTab();
                sleep(3);
                $this->exts->capture("2-after-login-new-with-gmail-1");
            } else if ($this->isExists('button.login-page-new__main-form-button') && !$this->isExists($this->password_selector) && strpos(strtolower($this->exts->extract('button.login-page-new__main-form-button', null, 'innerText')), 'microsoft') !== false) {
                $this->exts->moveToElementAndClick('button.login-page-new__main-form-button');
                sleep(15);

                $this->exts->capture("2-pre-login-new-after-continue-microsoft");
                $this->exts->switchToNewestActiveTab();
                sleep(1);
                $this->exts->capture("2-pre-login-new-with-microsoft");
                $this->loginMicrosoftIfRequired();
                sleep(3);
                $this->exts->capture("2-after-login-new-with-microsoft-0");

                if ($this->isExists('div#usernameError')) {
                    $this->exts->loginFailure(1);
                }
                $this->exts->switchToInitTab();
                sleep(3);
                $this->exts->capture("2-after-login-new-with-microsoft-1");
            } else {
                $this->exts->log("Enter Password 1 ");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(1);
                $this->exts->capture("2-login-page-filled");
                $this->exts->moveToElementAndClick($this->submit_login_btn);
                sleep(5);
                $this->checkFillRecaptcha();
                $this->exts->moveToElementAndClick($this->submit_login_btn);

                // $is_captcha = $this->solve_captcha_by_clicking(0);
                // if ($is_captcha) {
                //     for ($i = 1; $i < 30; $i++) {
                //         if ($is_captcha == false) {
                //             break;
                //         }
                //         $is_captcha = $this->solve_captcha_by_clicking($i);
                //     }
                // }
                $this->exts->moveToElementAndClick($this->submit_login_btn);
                if (strpos(strtolower($this->exts->extract('[data-test="toast__new-item"]')), 'no account was found') !== false) {
                    $this->exts->loginFailure(1);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
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

    private function solve_captcha_by_clicking($count = 1)
    {
        $this->exts->log("Checking captcha");
        $this->exts->waitTillPresent('iframe[title="recaptcha challenge expires in two minutes"]', 20);
        $language_code = '';
        if ($this->exts->exists('iframe[title="recaptcha challenge expires in two minutes"]')) {
            $this->exts->capture("brevo-captcha");

            $captcha_instruction = $this->exts->makeFrameExecutable('iframe[title="recaptcha challenge expires in two minutes"]')->extract('.rc-imageselect-desc-no-canonical');
            sleep(3);
            $tabs = $this->exts->get_all_tabs();
            $this->exts->log("print the tag ===>" . $tabs);
            if (count($tabs) == 1) {
                $this->exts->switchToInitTab();
                sleep(3);
            }
            if (trim($captcha_instruction) == '') {
                $captcha_instruction = $this->exts->makeFrameExecutable('iframe[title="recaptcha challenge expires in two minutes"]')->extract('.rc-imageselect-desc');
            }

            //$captcha_instruction = $this->exts->extract($iframeElement_instartion,null, 'innerText');
            $this->exts->log('language_code: ' . $language_code . ' Instruction: ' . $captcha_instruction);
            sleep(5);
            $captcha_wraper_selector = 'iframe[title="recaptcha challenge expires in two minutes"]';

            if ($this->exts->exists($captcha_wraper_selector)) {
                $coordinates = $this->getCoordinates($captcha_wraper_selector, $captcha_instruction, '', $json_result = false);


                // if($coordinates == '' || count($coordinates) < 2){
                //  $coordinates = $this->exts->processClickCaptcha($captcha_wraper_selector, $captcha_instruction, '', $json_result=false);
                // }
                if ($coordinates != '') {
                    // $challenge_wraper = $this->exts->querySelector($captcha_wraper_selector);

                    foreach ($coordinates as $coordinate) {
                        $this->click_recaptcha_point($captcha_wraper_selector, (int)$coordinate['x'], (int)$coordinate['y']);
                    }

                    $this->exts->capture("brevo-captcha-selected " . $count);
                    $this->exts->makeFrameExecutable('iframe[title="recaptcha challenge expires in two minutes"]')->click_element('button[id="recaptcha-verify-button"]');
                    sleep(10);
                    return true;
                }
            }

            return false;
        }
    }

    private function click_recaptcha_point($selector = '', $x_on_element = 0, $y_on_element = 0)
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
                            $coordinates[] = ['x' => (int)$matches[1], 'y' => (int)$matches[2]];
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

    private function checkFillTwoFactor()
    {
        sleep(5);
        $two_factor_selector = 'input.cu-form__input.validate-input';
        $two_factor_message_selector = 'div.cu-onboarding__subheader, div.login-page-new__main-form-two-fa .login-page-new__main-form-two-fa-title';
        $two_factor_submit_selector = 'form button[type*="submit"]';

        if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getText() . "\n";
                }
                $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
                $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            }
            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }

            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);

                $this->exts->click_by_xdotool($two_factor_selector);
                $this->exts->type_text_by_xdotool($two_factor_code);

                // $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
                // sleep(5);
                // if ($this->isExists($two_factor_submit_selector)) {
                // 	$this->exts->moveToElementAndClick($two_factor_submit_selector);
                // }
                sleep(15);

                if ($this->exts->getElement($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->notification_uid = '';
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

    private function checkFillRecaptcha()
    {
        $this->exts->log(__FUNCTION__);
        $recaptcha_iframe_selector = 'iframe[src*="/recaptcha/enterprise/anchor?"]';
        $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
        if ($this->isExists($recaptcha_iframe_selector)) {
            $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
            $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
            $this->exts->log("iframe url  - " . $iframeUrl);
            $this->exts->log("SiteKey - " . $data_siteKey);

            $isCaptchaSolved = $this->exts->processRecaptcha(trim($this->exts->getUrl()), $data_siteKey, false);
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

    // -------------------- GOOGLE login
    public $google_username_selector = 'input[name="identifier"]:not([aria-hidden="true"])';
    public $google_submit_username_selector = '#identifierNext, input#submit, input#next';
    public $google_password_selector = 'input[name="password"], input[name="Passwd"]';
    public $google_submit_password_selector = '#passwordNext, #gaia_loginform input#signIn, #passwordNext button, input#submit';
    public $security_phone_number = '';
    public $recovery_email = '';
    private function loginGoogleIfRequired()
    {
        $this->security_phone_number = isset($this->exts->config_array["security_phone_number"]) ? $this->exts->config_array["security_phone_number"] : '';
        $this->recovery_email = isset($this->exts->config_array["recovery_email"]) ? $this->exts->config_array["recovery_email"] : '';
        if ($this->exts->urlContains('google.')) {
            if ($this->exts->urlContains('/webreauth')) {
                $this->exts->moveToElementAndClick('#identifierNext');
                sleep(6);
            }
            $this->googleCheckFillLogin();
            sleep(5);
            if ($this->exts->querySelector('input[name="password"][aria-invalid="true"], input[name="identifier"][aria-invalid="true"], input[name="Passwd"][aria-invalid="true"]') != null) {
                $this->exts->loginFailure(1);
            }
            if ($this->exts->querySelector('form[action*="/signin/v2/challenge/password/"] input[name="password"][aria-invalid="true"], input[name="identifier"][aria-invalid="true"], span#passwordError, input[name="Passwd"][aria-invalid="true"]') != null) {
                $this->exts->loginFailure(1);
            }

            // Click next if confirm form showed
            $this->exts->moveToElementAndClick('div[data-ownership-changed-phone-number] div:nth-child(2) > [role="button"]');
            $this->googleCheckTwoFactorMethod();

            if ($this->isExists('#smsauth-interstitial-remindbutton')) {
                $this->exts->moveToElementAndClick('#smsauth-interstitial-remindbutton');
                sleep(10);
            }
            if ($this->isExists('#tos_form input#accept')) {
                $this->exts->moveToElementAndClick('#tos_form input#accept');
                sleep(10);
            }
            if ($this->isExists('[wizard-step-uid="RecoveryOptionsCollectionWizard:starter"] div:last-child > [role="button"]')) {
                $this->exts->moveToElementAndClick('[wizard-step-uid="RecoveryOptionsCollectionWizard:starter"] div:last-child > [role="button"]');
                sleep(10);
            }
            if ($this->isExists('.action-button.signin-button + a.setup-button[href*="/two-step-verification/"]')) {
                // SKIP setup 2FA
                $this->exts->moveToElementAndClick('.action-button.signin-button');
                sleep(10);
            }
            if ($this->isExists('a.setup-button[href*="two-step-verification/enroll"] + button.signin-button')) {
                // SKIP setup 2FA
                $this->exts->moveToElementAndClick('a.setup-button[href*="two-step-verification/enroll"] + button.signin-button');
                sleep(10);
            }
            if ($this->isExists('[action="/signin/newfeatures/save"] #optionsButton ~ [role="button"]')) {
                $this->exts->moveToElementAndClick('[action="/signin/newfeatures/save"] #optionsButton ~ [role="button"]');
                sleep(10);
            }
            if ($this->isExists('input[name="later"]') && $this->exts->urlContains('/AddressNoLongerAvailable')) {
                $this->exts->moveToElementAndClick('input[name="later"]');
                sleep(7);
            }
            if ($this->isExists('#editLanguageAndContactForm a[href*="/adsense/app"]')) {
                $this->exts->moveToElementAndClick('#editLanguageAndContactForm a[href*="/adsense/app"]');
                sleep(7);
            }
            if ($this->isExists('[data-view-instance-id="/web/chip-V0"] [role="button"]:first-child [jsslot]')) {
                $this->exts->moveToElementAndClick('[data-view-instance-id="/web/chip-V0"] [role="button"]:first-child [jsslot]');
                sleep(10);
            }

            if ($this->isExists('#submit_approve_access')) {
                $this->exts->moveToElementAndClick('#submit_approve_access');
                sleep(10);
            } else if ($this->isExists('form #approve_button[name="submit_true"]')) {
                // An application is requesting permission to access your Google Account.
                // Click allow
                $this->exts->moveToElementAndClick('form #approve_button[name="submit_true"]');
                sleep(10);
            }
            $this->exts->capture("3-google-before-back-to-main-tab");
        } else {
            $this->exts->log(__FUNCTION__ . '::Not required google login.');
            $this->exts->capture("3-no-google-required");
        }
    }
    private function googleCheckFillLogin()
    {
        if ($this->isExists('form ul li [role="link"][data-identifier]')) {
            $this->exts->moveToElementAndClick('form ul li [role="link"][data-identifier]');
            sleep(5);
        }

        if ($this->exts->urlContainsAny(['/ServiceLogin/identifier', '/ServiceLogin/webreauth']) && $this->isExists($this->google_submit_username_selector) && !$this->isExists($this->google_username_selector)) {
            $this->exts->capture("google-verify-it-you");
            // To help keep your account secure, Google needs to verify it’s you. Please sign in again to continue to Google Ads
            $this->exts->moveToElementAndClick($this->google_submit_username_selector);
            sleep(5);
        }

        $this->exts->capture("2-google-login-page");
        if ($this->isExists($this->google_username_selector)) {
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->google_username_selector, $this->username);
            sleep(1);
            $this->exts->moveToElementAndClick($this->google_submit_username_selector);
            sleep(5);
            if ($this->isExists('#captchaimg[src]') && !$this->isExists($this->google_password_selector) && $this->isExists($this->google_username_selector)) {
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                $this->exts->moveToElementAndClick($this->google_submit_username_selector);
                sleep(5);
                if ($this->isExists('#captchaimg[src]') && !$this->isExists($this->google_password_selector) && $this->isExists($this->google_username_selector)) {
                    $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                    $this->exts->moveToElementAndClick($this->google_submit_username_selector);
                    sleep(5);
                }
                if ($this->isExists('#captchaimg[src]') && !$this->isExists($this->google_password_selector) && $this->isExists($this->google_username_selector)) {
                    $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                    $this->exts->moveToElementAndClick($this->google_submit_username_selector);
                    sleep(5);
                }
            } else if ($this->exts->urlContains('/challenge/recaptcha')) {
                $this->googlecheckFillRecaptcha();
                $this->exts->moveToElementAndClick('[data-primary-action-label] > div > div:first-child button');
                sleep(5);
            }

            // Which account do you want to use?
            if ($this->isExists('form[action*="/lookup"] button.account-chooser-button')) {
                $this->exts->moveToElementAndClick('form[action*="/lookup"] button.account-chooser-button');
                sleep(5);
            }
            if ($this->isExists('[data-view-id="prbTle"] form [role="link"][data-profileindex]')) {
                $this->exts->moveToElementAndClick('[data-view-id="prbTle"] form [role="link"][data-profileindex]');
                sleep(5);
            }
        }

        if ($this->isExists($this->google_password_selector)) {
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
            sleep(1);
            if ($this->isExists('#captchaimg[src]')) {
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
            }

            $this->exts->capture("2-google-login-page-filled");
            $this->exts->moveToElementAndClick($this->google_submit_password_selector);
            sleep(5);
            if ($this->isExists('#captchaimg[src]') && !$this->isExists('input[name="password"][aria-invalid="true"]') && $this->isExists($this->google_password_selector)) {
                $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
                sleep(1);
                if ($this->isExists('#captchaimg[src]')) {
                    $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                }
                $this->exts->moveToElementAndClick($this->google_submit_password_selector);
                sleep(5);
                if ($this->isExists('#captchaimg[src]') && $this->isExists($this->google_password_selector)) {
                    $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
                    sleep(1);
                    $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                    $this->exts->capture("2-google-login-pageandcaptcha-filled");
                    $this->exts->moveToElementAndClick($this->google_submit_password_selector);
                }
            } else {
                $this->googlecheckFillRecaptcha();
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Google password page not found');
            $this->exts->capture("2-google-password-page-not-found");
        }
    }
    private function googleCheckTwoFactorMethod()
    {
        // Currently we met many two factor methods
        // - Confirm email account for account recovery
        // - Confirm telephone number for account recovery
        // - Call to your assigned phone number
        // - confirm sms code
        // - Solve the notification has sent to smart phone
        // - Use security key usb
        // - Use your phone or tablet to get a security code (EVEN IF IT'S OFFLINE)
        $this->exts->log(__FUNCTION__);
        sleep(5);
        $this->exts->capture("2.0-before-check-two-factor-google");
        // STEP 0 (updated special case 28-Mar-2020): If we meet a unsolvable, click to back to method choosen list
        if ($this->isExists('#assistActionId') && $this->isExists('[data-illustration="securityKeyLaptopAnim"]')) {
            $this->exts->moveToElementAndClick('#assistActionId');
            sleep(5);
        } else if ($this->exts->urlContains('/challenge/wa') && strpos($this->exts->extract('form header h2'), 'QR-Code') !== false) {
            // (updated special case 28-Mar-2020): If we meet QR-Code, click 'Choose another option' to back to method choosen list
            $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
            sleep(5);
            $this->exts->capture("2.0-backed-methods-list-google");
            if ($this->exts->urlContains('/challenge/wa') && strpos($this->exts->extract('form header h2'), 'QR-Code') !== false) {
                $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
                sleep(5);
            }
        } else if ($this->exts->urlContains('/sk/webauthn')) {
            $node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-" . $this->exts->process_uid;
            exec("sudo docker exec " . $node_name . " bash -c 'xdotool key Return'");
            sleep(3);
            $this->exts->capture("2.0-cancel-security-usb-google");
            $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
            sleep(5);
            $this->exts->capture("2.0-backed-methods-list-google");
        } else if ($this->isExists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
            // (updated special case 09-May-2020): If Notification method showed immediately, This method often make user confused
            // So, We try to click 'Choose another option' in order to select easier method
            $this->exts->moveToElementAndClick('[data-view-id] > div > div:nth-child(2)  div:nth-child(2) > [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button, button#assistiveActionOutOfQuota');
            sleep(7);
            $this->exts->capture("2.0-backed-methods-list-google");
        } else if ($this->isExists('input[name="ootpPin"]')) {
            // (updated special case 11-Jun-2020): If "Verify by offline device" immediately, This method often make user confused and maybe they don't have device on hand
            // So, We try to click 'Choose another option' in order to select easier method
            $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
            sleep(7);
            $this->exts->capture("2.0-backed-methods-list-google");
        }

        // STEP 1: Check if list of two factor methods showed, select first
        if ($this->isExists('li [data-challengetype]:not([data-challengeunavailable="true"])')) {
            // We most RECOMMEND confirm security phone or email, then other method
            if ($this->isExists('li [data-challengetype="13"]:not([data-challengeunavailable="true"])') && isset($this->security_phone_number) && $this->security_phone_number != '') {
                $this->exts->moveToElementAndClick('li [data-challengetype="13"]:not([data-challengeunavailable="true"])');
            } else if ($this->isExists('li [data-challengetype="12"]:not([data-challengeunavailable="true"])') && isset($this->recovery_email) && $this->recovery_email != '') {
                $this->exts->moveToElementAndClick('li [data-challengetype="12"]:not([data-challengeunavailable="true"])');
            } else if ($this->isExists('li [data-challengetype="6"]:not([data-challengeunavailable="true"])')) {
                // We RECOMMEND method type = 6 is get code from Google Authenticator
                $this->exts->moveToElementAndClick('li [data-challengetype="6"]:not([data-challengeunavailable="true"])');
            } else if ($this->isExists('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])')) {
                // We second RECOMMEND method type = 9 is get code from SMS
                $this->exts->moveToElementAndClick('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])');
            } else if ($this->isExists('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"]:not([data-challengeunavailable="true"])')) {
                // We recommend method type = 4 and [data-sendauthzenprompt="true"] is  Tap YES on your smartphone or tablet
                $this->exts->moveToElementAndClick('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"]:not([data-challengeunavailable="true"])');
            } else if ($this->isExists('li [data-challengetype="5"]:not([data-challengeunavailable="true"])')) {
                // Use a smartphone or tablet to receive a security code (even when offline)
                $this->exts->moveToElementAndClick('li [data-challengetype="5"]:not([data-challengeunavailable="true"])');
            } else if ($this->isExists('li [data-challengetype]:not([data-challengetype="4"]):not([data-challengetype="2"]):not([data-challengeunavailable="true"])')) {
                // We DONT recommend method is QR code OR is Security USB, we can not solve this type of 2FA
                $this->exts->moveToElementAndClick('li [data-challengetype]:not([data-challengetype="4"]):not([data-challengetype="2"]):not([data-challengeunavailable="true"])');
            } else {
                $this->exts->moveToElementAndClick('li [data-challengetype]:not([data-challengeunavailable="true"])');
            }
            sleep(10);
        } else if ($this->isExists('#smsButton, [data-illustration="accountRecoverySmsPin"]')) {
            // Sometime user must confirm before google send sms
            $this->exts->moveToElementAndClick('#smsButton, div:first-child > [role="button"], [data-secondary-action-label] > div > div:nth-child(1) button');
            sleep(10);
        } else if ($this->isExists('#authzenNext') && $this->isExists('[data-view-id*="authzenView"], [data-illustration*="authzen"]')) {
            $this->exts->moveToElementAndClick('[data-view-id] #authzenNext');
            sleep(10);
        } else if ($this->isExists('#idvpreregisteredemailNext') && !$this->isExists('form input:not([type="hidden"])')) {
            $this->exts->moveToElementAndClick('#idvpreregisteredemailNext');
            sleep(10);
        }

        // STEP 2: (Optional)
        if ($this->isExists('input#knowledge-preregistered-email-response, input[name="knowledgePreregisteredEmailResponse"]')) {
            // If methos is recovery email, send 2FA to ask for email
            $this->exts->two_factor_attempts = 2;
            $input_selector = 'input#knowledge-preregistered-email-response, input[name="knowledgePreregisteredEmailResponse"]';
            $message_selector = '[data-view-id] form section div > div[jsslot] > div:first-child';
            $submit_selector = '';
            if (isset($this->recovery_email) && $this->recovery_email != '') {
                $this->exts->moveToElementAndType($input_selector, $this->recovery_email);
                $this->exts->type_key_by_xdotool("Return");
                sleep(7);
            }
            if ($this->isExists($input_selector)) {
                $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
                sleep(5);
            }
        } else if ($this->isExists('[data-view-id*="knowledgePreregisteredPhoneView"] input[type="tel"]')) {
            // If methos confirm recovery phone number, send 2FA to ask
            $this->exts->two_factor_attempts = 3;
            $input_selector = '[data-view-id*="knowledgePreregisteredPhoneView"] input[type="tel"]';
            $message_selector = '[data-view-id] form section div > div[jsslot] > div:first-child';
            $submit_selector = '';
            if (isset($this->security_phone_number) && $this->security_phone_number != '') {
                $this->exts->moveToElementAndType($input_selector, $this->security_phone_number);
                $this->exts->type_key_by_xdotool("Return");
                sleep(5);
            }
            if ($this->isExists($input_selector)) {
                $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
                sleep(5);
            }
        } else if ($this->isExists('input#phoneNumberId')) {
            // Enter a phone number to receive an SMS with a confirmation code.
            $this->exts->two_factor_attempts = 3;
            $input_selector = 'input#phoneNumberId';
            $message_selector = '[data-view-id] form section > div > div > div:first-child';
            $submit_selector = '';
            if (isset($this->security_phone_number) && $this->security_phone_number != '') {
                $this->exts->moveToElementAndType($input_selector, $this->security_phone_number);
                $this->exts->type_key_by_xdotool("Return");
                sleep(7);
            }
            if ($this->isExists($input_selector)) {
                $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
            }
        } else if ($this->isExists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
            // Check your smartphone. Google has sent a notification to your smartphone. Tap Yes in the notification, then tap 91 on your smartphone to continue
            $this->exts->two_factor_attempts = 3;
            $message_selector = '[data-view-id*="authzenView"] form, [data-view-id] form[method="post"]';
            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Enter \"OK\" after confirmation on device";
            $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->googleFillTwoFactor(null, null, '');
            sleep(5);
        } else if ($this->isExists('[data-view-id*="securityKeyWebAuthnView"], [data-view-id*="securityKeyView"]')) {
            // Method: insert your security key and touch it
            $this->exts->two_factor_attempts = 3;
            $this->exts->two_factor_notif_msg_en = 'Use chrome, login then insert your security key and touch it' . "\n>>>Enter \"OK\" after confirmation on device";
            $this->exts->two_factor_notif_msg_de = '[Chrome] Stecken Sie den Sicherheitsschlussel in den USB-Anschluss Ihres Computers ein. Wenn er eine Taste hat, tippen Sie darauf.' . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->googleFillTwoFactor(null, null, '');
            sleep(5);
            // choose another option: #assistActionId
        }

        // STEP 3: (Optional)  After choose method and confirm email or phone or.., google may asked confirm one more time before send code
        if ($this->isExists('#smsButton, [data-illustration="accountRecoverySmsPin"]')) {
            // Sometime user must confirm before google send sms
            $this->exts->moveToElementAndClick('#smsButton, div:first-child > [role="button"], [data-secondary-action-label] > div > div:nth-child(1) button');
            sleep(10);
        } else if ($this->isExists('#authzenNext') && $this->isExists('[data-view-id*="authzenView"], [data-illustration*="authzen"]')) {
            $this->exts->moveToElementAndClick('[data-view-id] #authzenNext');
            sleep(10);
        } else if ($this->isExists('#idvpreregisteredemailNext') && !$this->isExists('form input:not([type="hidden"])')) {
            $this->exts->moveToElementAndClick('#idvpreregisteredemailNext');
            sleep(10);
        } else if (count($this->exts->getElements('li [data-challengetype]:not([data-challengeunavailable="true"]):not([data-challengetype="undefined"])')) > 0) {
            $this->exts->moveToElementAndClick('li [data-challengetype]:not([data-challengeunavailable="true"]):not([data-challengetype="undefined"])');
            sleep(7);
        }


        // STEP 4: input code
        if ($this->isExists('form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin')) {
            $input_selector = 'form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin';
            $message_selector = 'form > span > section > div > div > div:first-child';
            $submit_selector = '#idvPreregisteredPhoneNext, #idvpreregisteredemailNext, #totpNext, #idvanyphoneverifyNext, #backupCodeNext, #view_container div.pwWryf.bxPAYd div.zQJV3 div.qhFLie > div > div > button';
            $this->exts->two_factor_attempts = 0;
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector);
        } else if ($this->isExists('input[name="ootpPin"], input#securityKeyOtpInputId')) {
            $input_selector = 'input[name="ootpPin"], input#securityKeyOtpInputId';
            $message_selector = 'form > span > section > div > div > div:first-child';
            $submit_selector = '';
            $this->exts->two_factor_attempts = 0;
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
        } else if ($this->isExists('input[name="Pin"]')) {
            $input_selector = 'input[name="Pin"]';
            $message_selector = 'form > span > section > div > div > div:first-child';
            $submit_selector = '';
            $this->exts->two_factor_attempts = 0;
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
        } else if ($this->isExists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
            // Check your smartphone. Google has sent a notification to your smartphone. Tap Yes in the notification, then tap 91 on your smartphone to continue
            $this->exts->two_factor_attempts = 3;
            $message_selector = '[data-view-id*="authzenView"] form, [data-view-id] form[method="post"]';
            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Enter \"OK\" after confirmation on device";
            $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->googleFillTwoFactor(null, null, '');
            sleep(5);
        } else if ($this->isExists('input[name="secretQuestionResponse"]')) {
            $input_selector = 'input[name="secretQuestionResponse"]';
            $message_selector = 'form > span > section > div > div > div:first-child';
            $submit_selector = '[data-secondary-action-label] > div > div:nth-child(1) button';
            $this->exts->two_factor_attempts = 0;
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector);
        }
    }
    private function googleFillTwoFactor($input_selector, $message_selector, $submit_selector, $submit_by_enter = false)
    {
        $this->exts->log(__FUNCTION__);
        $this->exts->log("Google two factor page found.");
        $this->exts->capture("2.1-two-factor-google");

        if ($this->exts->querySelector($message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'innerText'));
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
        }

        $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        $this->exts->notification_uid = "";
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            if ($this->exts->querySelector($input_selector) != null) {
                if (substr(trim($two_factor_code), 0, 2) === 'G-') {
                    $two_factor_code = end(explode('G-', $two_factor_code));
                }
                if (substr(trim($two_factor_code), 0, 2) === 'g-') {
                    $two_factor_code = end(explode('g-', $two_factor_code));
                }
                $this->exts->log(__FUNCTION__ . ": Entering two_factor_code: " . $two_factor_code);
                $this->exts->moveToElementAndType($input_selector, '');
                $this->exts->moveToElementAndType($input_selector, $two_factor_code);
                sleep(1);
                if ($this->exts->allExists(['input[type="checkbox"]:not(:checked) + div', 'input[name="Pin"]'])) {
                    $this->exts->moveToElementAndClick('input[type="checkbox"]:not(:checked) + div');
                    sleep(1);
                }
                $this->exts->capture("2.2-google-two-factor-filled-" . $this->exts->two_factor_attempts);

                if ($this->isExists($submit_selector)) {
                    $this->exts->log(__FUNCTION__ . ": Clicking submit button.");
                    $this->exts->moveToElementAndClick($submit_selector);
                } else if ($submit_by_enter) {
                    $this->exts->type_key_by_xdotool("Return");
                }
                sleep(10);
                $this->exts->capture("2.2-google-two-factor-submitted-" . $this->exts->two_factor_attempts);
                if ($this->exts->querySelector($input_selector) == null) {
                    $this->exts->log("Google two factor solved");
                } else {
                    if ($this->exts->two_factor_attempts < 3) {
                        $this->exts->notification_uid = '';
                        $this->exts->two_factor_attempts++;
                        $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector);
                    } else {
                        $this->exts->log("Google Two factor can not solved");
                    }
                }
            } else {
                $this->exts->log("Google not found two factor input");
            }
        } else {
            $this->exts->log("Google not received two factor code");
            $this->exts->two_factor_attempts = 3;
        }
    }
    private function googlecheckFillRecaptcha()
    {
        $this->exts->log(__FUNCTION__);
        $recaptcha_iframe_selector = 'form iframe[src*="/recaptcha/"]';
        $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
        if ($this->isExists($recaptcha_iframe_selector)) {
            $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
            $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
            $this->exts->log("iframe url  - " . $iframeUrl);
            $this->exts->log("SiteKey - " . $data_siteKey);
            $url = reset(explode('?', $this->exts->getUrl()));
            $isCaptchaSolved = $this->exts->processRecaptcha($url, $data_siteKey, false);
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
	} else {
		var result = ""; var found = false;
		function recurse (cur, prop, deep) {
		    if(deep > 5 || found){ return;}console.log(prop);
		    try {
		        if(prop.indexOf(".callback") > -1){result = prop; found = true; return;
		        } else { if(cur == undefined || cur == null || cur instanceof Element || Object(cur) !== cur || Array.isArray(cur)){ return;}deep++;
		            for (var p in cur) { recurse(cur[p], prop ? prop + "." + p : p, deep);}
		        }
		    } catch(ex) { console.log("ERROR in function: " + ex); return; }
		}

		recurse(___grecaptcha_cfg.clients[0], "", 0);
		found ? "___grecaptcha_cfg.clients[0]." + result : null;
	}
');
                $this->exts->log('Callback function: ' . $gcallbackFunction);
                if ($gcallbackFunction != null) {
                    $this->exts->execute_javascript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
                    sleep(10);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
        }
    }
    // End GOOGLE login

    // -------------------- MICROSOFT login
    public $microsoft_username_selector = 'input[name="loginfmt"]';
    public $microsoft_password_selector = 'input[name="passwd"]';
    public $microsoft_remember_me_selector = 'input[name="KMSI"] + span';
    public $microsoft_submit_login_selector = 'input[type="submit"]#idSIButton9';


    public $microsoft_account_type = 0;
    public $microsoft_phone_number = '';
    public $microsoft_recovery_email = '';

    private function loginMicrosoftIfRequired()
    {
        $this->microsoft_phone_number = isset($this->exts->config_array["phone_number"]) ? $this->exts->config_array["phone_number"] : '';
        $this->microsoft_recovery_email = isset($this->exts->config_array["recovery_email"]) ? $this->exts->config_array["recovery_email"] : '';
        $this->microsoft_account_type = isset($this->exts->config_array["account_type"]) ? (int)@$this->exts->config_array["account_type"] : 0;

        if ($this->exts->urlContains('microsoft') || $this->exts->urlContains('live.')) {
            $this->checkFillMicrosoftLogin();
            sleep(10);
            if ($this->isExists('input#newPassword')) {
                $this->exts->account_not_ready();
            } else if ($this->exts->getElement('#passwordError a[href*="ResetPassword"], #passwordError a[href*="passwordreset"]') != null) {
                $this->exts->loginFailure(1);
            }
            $this->checkConfirmMicrosoftButton();
            $this->checkMicrosoftTwoFactorMethod();
            $this->checkConfirmMicrosoftButton();

            if ($this->isExists('input#newPassword')) {
                $this->exts->account_not_ready();
            } else if ($this->exts->getElement('#passwordError a[href*="ResetPassword"], #passwordError a[href*="passwordreset"]') != null) {
                $this->exts->loginFailure(1);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Not required microsoft login.');
            $this->exts->capture("3-no-microsoft-required");
        }
    }
    private function checkFillMicrosoftLogin()
    {
        $this->exts->log(__FUNCTION__);
        // When open login page, sometime it show previous logged user, select login with other user.
        if ($this->isExists('[role="listbox"] .row #otherTile[role="option"], [role="listitem"] #otherTile')) {
            $this->exts->moveToElementAndClick('[role="listbox"] .row #otherTile[role="option"], [role="listitem"] #otherTile');
            sleep(10);
        }

        $this->exts->capture("2-microsoft-login-page");
        if ($this->exts->getElement($this->microsoft_username_selector) != null) {
            sleep(3);
            $this->exts->log("Enter microsoft Username");
            $this->exts->moveToElementAndType($this->microsoft_username_selector, $this->username);
            sleep(1);
            $this->exts->moveToElementAndClick($this->microsoft_submit_login_selector);
            sleep(10);
        }

        if ($this->isExists('a[data-bind*="href: svr.urlSwitch"][href*="/logout"]')) {
            // if site show: Already login with .. account, click logout and login with other account
            $this->exts->moveToElementAndClick('a[data-bind*="href: svr.urlSwitch"][href*="/logout"]');
            sleep(10);
        }
        if ($this->isExists('a#mso_account_tile_link, #aadTile, #msaTile')) {
            // if site show: This Email is being used with multiple Microsoft accounts, Select depending on account_type variable
            //if account type is 1 then only personal account will be selected otherwise business account.
            if ($this->microsoft_account_type == 1) {
                $this->exts->moveToElementAndClick('#msaTile');
            } else {
                $this->exts->moveToElementAndClick('a#mso_account_tile_link, #aadTile');
            }
            sleep(10);
        }
        if ($this->isExists('form #idA_PWD_SwitchToPassword')) {
            $this->exts->moveToElementAndClick('form #idA_PWD_SwitchToPassword');
            sleep(5);
        }

        if ($this->exts->getElement($this->microsoft_password_selector) != null) {
            $this->exts->log("Enter microsoft Password");
            $this->exts->moveToElementAndType($this->microsoft_password_selector, $this->password);
            sleep(1);
            if ($this->isExists('input[id*="wlspispSolutionElement"]')) {
                $this->exts->processCaptcha('img[id*="wlspispHIPBimg"]', 'input[id*="wlspispSolutionElement"]');
            }
            $this->exts->moveToElementAndClick($this->microsoft_remember_me_selector);
            sleep(2);
            $this->exts->capture("2-microsoft-password-page-filled");
            $this->exts->moveToElementAndClick($this->microsoft_submit_login_selector);
            sleep(10);
            $this->exts->capture("2-microsoft-after-submit-password");
        } else {
            $this->exts->log(__FUNCTION__ . '::microsoft Password page not found');
        }
    }
    private function checkConfirmMicrosoftButton()
    {
        // After submit password, It have many button can be showed, check and click it
        if ($this->isExists('form input[name="DontShowAgain"] + span')) {
            // if site show: Do this to reduce the number of times you are asked to sign in. Click yes
            $this->exts->moveToElementAndClick('form input[name="DontShowAgain"] + span');
            sleep(10);
        }
        if ($this->isExists('a[data-bind*=SkipMfaRegistration]')) {
            $this->exts->moveToElementAndClick('a[data-bind*=SkipMfaRegistration]');
            sleep(10);
        }
        if ($this->isExists('input#idSIButton9[aria-describedby="KmsiDescription"]')) {
            $this->exts->moveToElementAndClick('input#idSIButton9[aria-describedby="KmsiDescription"]');
            sleep(10);
        }
        if ($this->isExists('input#idSIButton9[aria-describedby*="landingDescription"]')) {
            $this->exts->moveToElementAndClick('input#idSIButton9[aria-describedby*="landingDescription"]');
            sleep(3);
        }
        if ($this->exts->getElement("#verifySetup a#verifySetupCancel") != null) {
            $this->exts->moveToElementAndClick("#verifySetup a#verifySetupCancel");
            sleep(10);
        }
        if ($this->exts->getElement('#authenticatorIntro a#iCancel') != null) {
            $this->exts->moveToElementAndClick('#authenticatorIntro a#iCancel');
            sleep(10);
        }
        if ($this->exts->getElement("input#iLooksGood") != null) {
            $this->exts->moveToElementAndClick("input#iLooksGood");
            sleep(10);
        }
        if ($this->exts->getElement("input#StartAction") != null) {
            $this->exts->moveToElementAndClick("input#StartAction");
            sleep(10);
        }
        if ($this->exts->getElement(".recoveryCancelPageContainer input#iLandingViewAction") != null) {
            $this->exts->moveToElementAndClick(".recoveryCancelPageContainer input#iLandingViewAction");
            sleep(10);
        }
        if ($this->exts->getElement("input#idSubmit_ProofUp_Redirect") != null) {
            $this->exts->moveToElementAndClick("input#idSubmit_ProofUp_Redirect");
            sleep(10);
        }
        if ($this->exts->urlContains('mysignins.microsoft.com/register') && $this->isExists('#id__11')) {
            // Great job! Your security information has been successfully set up. Click "Done" to continue login.
            $this->exts->moveToElementAndClick(' #id__11');
            sleep(10);
        }
        if ($this->exts->getElement('div input#iNext') != null) {
            $this->exts->moveToElementAndClick('div input#iNext');
            sleep(10);
        }
        if ($this->exts->getElement('input[value="Continue"]') != null) {
            $this->exts->moveToElementAndClick('input[value="Continue"]');
            sleep(10);
        }
        if ($this->exts->getElement('form[action="/kmsi"] input#idSIButton9') != null) {
            $this->exts->moveToElementAndClick('form[action="/kmsi"] input#idSIButton9');
            sleep(10);
        }
        if ($this->exts->getElement('a#CancelLinkButton') != null) {
            $this->exts->moveToElementAndClick('a#CancelLinkButton');
            sleep(10);
        }
        if ($this->isExists('form[action*="/kmsi"] input[name="DontShowAgain"]')) {
            // if site show: Do this to reduce the number of times you are asked to sign in. Click yes
            $this->exts->moveToElementAndClick('form[action*="/kmsi"] input[name="DontShowAgain"] + span');
            sleep(3);
            $this->exts->moveToElementAndClick('form[action*="/kmsi"] input#idSIButton9');
            sleep(10);
        }
    }
    private function checkMicrosoftTwoFactorMethod()
    {
        // Currently we met 4 two factor methods
        // - Email 
        // - Text Message
        // - Approve request in Microsoft Authenticator app
        // - Use verification code from mobile app
        $this->exts->log(__FUNCTION__);
        // sleep(5);
        $this->exts->capture("2.0-microsoft-two-factor-checking");
        // STEP 0 if it's hard to solve, so try back to choose list
        if ($this->isExists('[value="PhoneAppNotification"]') && $this->isExists('a#signInAnotherWay')) {
            $this->exts->moveToElementAndClick('a#signInAnotherWay');
            sleep(5);
            $this->exts->capture("2.0-microsoft-backed-to-2fa-list");
        } else if ($this->isExists('input[name="mfaAuthMethod"][value="TwoWayVoiceOffice"]') && $this->isExists('a#signInAnotherWay')) {
            $this->exts->moveToElementAndClick('a#signInAnotherWay');
            sleep(5);
            $this->exts->capture("2.0-microsoft-backed-to-2fa-list");
        }

        // STEP 1: Check if list of two factor methods showed, select first
        if ($this->isExists('#idDiv_SAOTCS_Proofs_Section #idDiv_SAOTCS_Proofs [role="option"]')) {
            if ($this->isExists('#idDiv_SAOTCS_Proofs_Section #idDiv_SAOTCS_Proofs [role="option"]:not([data-value*="Voice"])')) {
                $this->exts->moveToElementAndClick('#idDiv_SAOTCS_Proofs_Section #idDiv_SAOTCS_Proofs [role="option"]:not([data-value*="Voice"])');
            } else {
                $this->exts->moveToElementAndClick('#idDiv_SAOTCS_Proofs_Section #idDiv_SAOTCS_Proofs [role="option"]');
            }
            sleep(3);
        } else if ($this->isExists('#iProofList input[name="proof"]')) {
            $this->exts->moveToElementAndClick('#iProofList input[name="proof"]');
            sleep(3);
        } else if ($this->isExists('#idDiv_SAOTCS_Proofs [role="listitem"]')) {
            // Updated 11-2020
            if ($this->isExists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="OneWaySMS"]')) {
                $this->exts->moveToElementAndClick('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="OneWaySMS"]');
            } else if ($this->isExists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppOTP"]')) {
                $this->exts->moveToElementAndClick('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppOTP"]');
            } else if ($this->isExists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppNotification"]')) {
                $this->exts->moveToElementAndClick('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppNotification"]');
            } else {
                $this->exts->moveToElementAndClick('#idDiv_SAOTCS_Proofs [role="listitem"]');
            }
            sleep(5);
        }

        // STEP 2: (Optional)
        if ($this->isExists('#idDiv_RemoteNGC_PollingDescription, #idRemoteNGC_DisplaySign, .confirmIdentityPageControl #iPollSessionDesc')) {
            // If method is click some number on Microsoft Authenticator app, send 2FA to ask user to do click it
            $message_selector = '#idDiv_RemoteNGC_PollingDescription, #idRemoteNGC_DisplaySign, .confirmIdentityPageControl #iPollSessionDesc';
            $this->exts->two_factor_notif_msg_en = trim(join("\n", $this->exts->getElementsAttribute($message_selector, 'innerText')));
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . "\n>>>Enter \"OK\" after confirming on device";
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en . "\n>>>Geben Sie danach hier unten \"OK\" ein.";

            $this->exts->two_factor_attempts = 2;
            $this->fillMicrosoftTwoFactor('', '', '', '');
        } else if ($this->isExists('[data-bind*="Type.TOTPAuthenticatorV2"]')) {
            // If method is Microsoft Authenticator app: send 2FA to ask user approve on Microsoft app.
            // Then wait. If not success, click to select two factor by code from mobile app
            $input_selector = '';
            $message_selector = 'div#idDiv_SAOTCAS_Description';
            $remember_selector = 'label#idLbl_SAOTCAS_TD_Cb';
            $submit_selector = '';
            $this->exts->two_factor_attempts = 2;
            $this->fillMicrosoftTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
            sleep(30);

            if ($this->isExists('a#idA_SAASTO_TOTP')) {
                $this->exts->moveToElementAndClick('a#idA_SAASTO_TOTP');
                sleep(5);
            }
        } else if ($this->isExists('input[name="ProofConfirmation"]:not([type="hidden"]), .confirmIdentityPageControl [id^="iProof"][style*="display: block"] >  input[type="email"][name^="iProof"], .confirmIdentityPageControl [id^="iProof"][style*="display: table"] >  input[name^="iProof"][type="email"]:not([type="hidden"])')) {
            // If method is email code or phone code, This site may be ask for confirm phone/email first, So send 2FA to ask user phone/email
            $input_selector = 'input[name="ProofConfirmation"]:not([type="hidden"]), .confirmIdentityPageControl [id^="iProof"][style*="display: block"] >  input[type="email"][name^="iProof"], .confirmIdentityPageControl [id^="iProof"][style*="display: table"] >  input[name^="iProof"][type="email"]:not([type="hidden"])';
            $message_selector = '#idDiv_SAOTCS_ProofConfirmationDesc, #iAdditionalProofInfo #iEnterProofDesc, #iAdditionalProofInfo #iEnterProofDesc ~ * #iConfirmProofEmailDomain';
            $remember_selector = '';
            $submit_selector = 'input#idSubmit_SAOTCS_SendCode, input#iSelectProofAction[type="submit"]';
            $this->exts->two_factor_attempts = 1;
            if ($this->microsoft_recovery_email != '') {
                $this->exts->moveToElementAndType($input_selector, $this->microsoft_recovery_email);
                sleep(3);
                $this->exts->moveToElementAndClick($submit_selector);
                sleep(10);
            } else {
                $this->exts->two_factor_attempts = 1;
                $this->fillMicrosoftTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
            }
        } else if ($this->isExists('input[name="ProofConfirmation"]:not([type="hidden"]), .confirmIdentityPageControl [id^="iProof"][style*="display: block"] >  input[type="tel"][name^="iProof"], .confirmIdentityPageControl [id^="iProof"][style*="display: table"] >  input[name^="iProof"][type="tel"]:not([type="hidden"])')) {
            // If method is phone code, This site may be ask for confirm phone first, So send 2FA to ask user phone
            $input_selector = 'input[name="ProofConfirmation"]:not([type="hidden"]), .confirmIdentityPageControl [id^="iProof"][style*="display: block"] >  input[type="tel"][name^="iProof"], .confirmIdentityPageControl [id^="iProof"][style*="display: table"] >  input[name^="iProof"][type="tel"]:not([type="hidden"])';
            $message_selector = '#idDiv_SAOTCS_ProofConfirmationDesc, #iAdditionalProofInfo #iEnterProofDesc, #iAdditionalProofInfo #iEnterProofDesc ~ * #iConfirmProofEmailDomain';
            $remember_selector = '';
            $submit_selector = 'input#idSubmit_SAOTCS_SendCode, input#iSelectProofAction[type="submit"]';
            $this->exts->two_factor_attempts = 1;
            if ($this->microsoft_phone_number != '') {
                $this->exts->moveToElementAndType($input_selector, $this->microsoft_phone_number);
                sleep(3);
                $this->exts->moveToElementAndClick($submit_selector);
                sleep(10);
            } else {
                $this->exts->two_factor_attempts = 1;
                $this->fillMicrosoftTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
            }
        }

        // STEP 3: input code
        if ($this->isExists('input[name="otc"], input[name="iOttText"]')) {
            $input_selector = 'input[name="otc"], input[name="iOttText"]';
            $message_selector = 'div#idDiv_SAOTCC_Description, .OTTLabel, #idDiv_SAOTCC_Description';
            $remember_selector = 'label#idLbl_SAOTCC_TD_Cb';
            $submit_selector = 'input#idSubmit_SAOTCC_Continue, input#iVerifyCodeAction';
            $this->exts->two_factor_attempts = 0;
            $this->fillMicrosoftTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
        }
    }
    private function fillMicrosoftTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector)
    {
        $this->exts->log(__FUNCTION__);
        $this->exts->log("microsoft Two factor page found.");
        $this->exts->capture("2.1-microsoft-two-factor-page");
        $this->exts->log($message_selector);
        if ($this->exts->getElement($message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = join("\n", $this->exts->getElementsAttribute($message_selector, 'innerText'));
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
        }
        $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        $this->exts->notification_uid = "";

        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (empty($two_factor_code) || trim($two_factor_code) == '') {
            $this->exts->notification_uid = "";
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        }
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            if ($this->exts->getElement($input_selector) != null) {
                $this->exts->log("microsoftfillTwoFactor: Entering two_factor_code." . $two_factor_code);
                $this->exts->moveToElementAndType($input_selector, $two_factor_code);
                sleep(2);
                if ($this->isExists($remember_selector)) {
                    $this->exts->moveToElementAndClick($remember_selector);
                }
                $this->exts->capture("2.2-microsoft-two-factor-filled-" . $this->exts->two_factor_attempts);

                if ($this->isExists($submit_selector)) {
                    $this->exts->log("microsoftfillTwoFactor: Clicking submit button.");
                    $this->exts->moveToElementAndClick($submit_selector);
                }
                sleep(15);

                if ($this->exts->getElement($input_selector) == null) {
                    $this->exts->log("microsoftTwo factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->fillMicrosoftTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
                } else {
                    $this->exts->log("microsoft Two factor can not solved");
                }
            } else {
                $this->exts->log("Not found microsoft two factor input");
            }
        } else {
            $this->exts->log("Not received microsoft two factor code");
        }
    }
    // End MICROSOFT login

    /**
     * Method to Check where user is logged in or not
     * return boolean true/false
     */

    public  function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->check_login_success_selector . "');") != 1; $wait++) {
                $this->exts->log('Waiting for login.....');
                sleep(10);
            }
            if ($this->isExists($this->check_login_success_selector)) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (Exception $exception) {

            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }

    public $totalInvoices = 0;
    private function processInvoices($teamId)
    {
        sleep(10);
        try {
            $this->waitForAny(['button.tab-item.ng-star-inserted', 'cu-billing-invoices-table table tbody tr a.cu-billing-invoices-download-button'], 10);
        } catch (TypeError $e) {
            $this->exts->log("TypeError: " . $e->getMessage());
            sleep(30);
        }

        $this->exts->capture('4-invoices-page');
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        $invoice_tab_button = $this->exts->getElementByText('button.tab-item.ng-star-inserted', 'invoice', null, false);
        if ($invoice_tab_button != null) {
            try {
                $this->exts->log('Click invoice tab button');
                $invoice_tab_button->click();
            } catch (\Exception $exception) {
                $this->exts->log('Click invoice tab button by javascript');
                $this->exts->execute_javascript("arguments[0].click()", [$invoice_tab_button]);
            }
            sleep(10);
        }
        if ($this->exts->getElement('div.billing-plan__invoices-item') != null) {
            $this->exts->execute_javascript('
        closeButtons= document.querySelectorAll(".cu-modal__close");
        for(var i = 0; i< closeButtons.length; i ++){
            closeButtons[i].click();
        }
    ');

            $this->exts->log('Invoices found');
            $this->exts->capture("4-page-opened");
            $invoices = [];
            $paths = explode('/', $this->exts->getUrl());
            $currentDomainUrl = $paths[0] . '//' . $paths[2];
            $maxBackDate = date('Y-m-d', strtotime('-1 years'));
            if ($restrictPages == 3) {
                $maxBackDate = date('Y-m-d', strtotime('-6 months'));
            }
            $rows = $this->exts->getElements('div.billing-plan__invoices-item');
            foreach ($rows as $index => $row) {

                if ($restrictPages != 0 && $this->totalInvoices >= 50) {
                    return;
                }

                $tags = $this->exts->getElements('div', $row);
                if (count($tags) >= 3) {

                    $invoiceName = trim($tags[0]->getAttribute('innerText'));
                    $invoiceDate = trim($tags[1]->getAttribute('innerText'));
                    $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[2]->getAttribute('innerText'))) . ' USD';

                    $parsed_date = is_null($invoiceDate) ? null : $this->exts->parse_date($invoiceDate, 'Y-m-d', 'Y-m-d');
                    $this->exts->log('invoiceDate: ' . $invoiceDate);
                    $this->exts->log('parsed_date: ' . $parsed_date);
                    if ($parsed_date < $maxBackDate) {
                        $this->exts->log('____ max back date ' . $maxBackDate . ' reached');
                        break;
                    }

                    $this->exts->execute_javascript("arguments[0].setAttribute('id', 'custom-pdf-download-button-" . $index . "');", [$row]);
                    sleep(2);
                    // Click row to get url
                    $this->exts->moveToElementAndClick('div#custom-pdf-download-button-' . $index);
                    sleep(1);
                    if ($this->isExists('div#custom-pdf-download-button-' . $index) && !$this->isExists('a[href*="blob"][download][cutooltip=Download]')) {
                        $this->exts->moveToElementAndClick('div#custom-pdf-download-button-' . $index);
                    }
                    sleep(1);
                    if ($this->isExists('div#custom-pdf-download-button-' . $index) && !$this->isExists('a[href*="blob"][download][cutooltip=Download]')) {
                        $this->exts->moveToElementAndClick('div#custom-pdf-download-button-' . $index);
                    }
                    sleep(15);

                    $invoiceUrl = $this->exts->extract('a[href*="blob"][download][cutooltip=Download]', null, 'href');

                    // Close modal
                    if ($this->exts->getElement('div.comment-viewer__control_close') != null) {
                        $this->exts->moveToElementAndClick('div.comment-viewer__control_close');
                    } else if ($this->isExists('div.comment-viewer__header2-control_close')) {
                        $this->exts->moveToElementAndClick('div.comment-viewer__header2-control_close');
                    } else {
                        $this->exts->moveToElementAndClick('div.comment-viewer div[class*="control_close"]');
                    }
                    sleep(10);

                    if (empty($invoiceUrl)) {
                        $invoiceUrl = $this->exts->extract('a[href*="blob"]', $row, 'href');
                    }
                    if (strpos($invoiceUrl, $currentDomainUrl) === false && strpos($invoiceUrl, 'http') === false) {
                        $invoiceUrl = $currentDomainUrl . $invoiceUrl;
                    }
                    $this->exts->log('invoiceUrl: ' . $invoiceUrl);
                    $this->exts->log('----------------------------------------------------');
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
                $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'Y-m-d', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

                $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                    $this->totalInvoices++;
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
        } else if ($this->isExists('cu-billing-invoices-table table tbody tr a.cu-billing-invoices-download-button')) {
            $rows = count($this->exts->getElements('cu-billing-invoices-table table tbody tr'));
            for ($i = 0; $i < $rows; $i++) {

                if ($restrictPages != 0 && $this->totalInvoices >= 50) {
                    return;
                }

                //$row = $this->exts->getElements('#recieptList table > tbody > tr')[$i];
                $row = $this->exts->getElements('cu-billing-invoices-table table tbody tr')[$i];
                $tags = $this->exts->getElements('td', $row);
                if (count($tags) >= 4 && $this->exts->getElement('cu-billing-invoices-download-button', $tags[3]) != null) {
                    $this->isNoInvoice = false;
                    $download_button = $this->exts->getElement('cu-billing-invoices-download-button', $tags[3]);
                    $invoiceName = trim($tags[1]->getAttribute('innerText'));
                    $invoiceFileName = !empty($invoiceName) ?  $invoiceName . '.pdf' : '';
                    $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                    $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[2]->getAttribute('innerText'))) . ' USD';

                    $this->exts->log('--------------------------');
                    $this->exts->log('invoiceName: ' . $invoiceName);
                    $this->exts->log('invoiceDate: ' . $invoiceDate);
                    $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                    $parsed_date = $this->exts->parse_date($invoiceDate, 'm/d/Y', 'Y-m-d');
                    $this->exts->log('Date parsed: ' . $parsed_date);

                    // Download invoice if it not exisited
                    if ($this->exts->invoice_exists($invoiceName)) {
                        $this->exts->log('Invoice existed ' . $invoiceFileName);
                    } else {
                        $downloaded_file = $this->exts->click_and_download($download_button, 'pdf', $invoiceFileName);

                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                            $this->totalInvoices++;
                        } else {
                            $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                            $this->exts->capture('4-failed-download');
                        }
                    }
                }
            }
        } else {
            $this->exts->log('Timeout processInvoices');
            $this->exts->capture('4-no-invoices');
            $this->exts->no_invoice();
        }
    }
}
