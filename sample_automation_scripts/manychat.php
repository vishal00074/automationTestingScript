<?php //

/**
 * Chrome Remote via Chrome devtool protocol script, for specific process/portal
 *
 * @package	uwa
 *
 * @copyright	GetMyInvoices
 */

define('KERNEL_ROOT', '/var/www/vhosts/worker/httpdocs/src/');

$gmi_selenium_core = realpath(KERNEL_ROOT . 'modules/cust_gmi_worker/includes/GmiChromeManager.php');
require_once($gmi_selenium_core);

class PortalScriptCDP
{

    private    $exts;
    public    $setupSuccess = false;
    private $chrome_manage;
    private    $username;
    private    $password;
    public $support_restart = true;
    public $portal_domain = '';

    public function __construct($mode, $portal_name, $process_uid, $username, $password)
    {

        $this->username = base64_decode($username);
        $this->password = base64_decode($password);

        $this->exts = new GmiChromeManager();
        $this->exts->screen_capture_location = '/var/www/vhosts/worker/httpdocs/fs/cdo/process/2/2673461/screens/';
        $this->exts->init($mode, $portal_name, $process_uid, $this->username, $this->password);
        $this->setupSuccess = true;
        if (!empty($this->exts->config_array['portal_domain'])) {
            $this->portal_domain = $this->exts->config_array['portal_domain'];
        }
    }

    /**
     * Method that called first for executing portal script, this method should not be altered by Users.
     */
    public function run()
    {
        if ($this->setupSuccess) {
            file_put_contents($this->exts->screen_capture_location . '.script_execution_started', time());
            try {
                // Start portal script execution
                $this->initPortal(0);

                $this->exts->dump_session_files();
            } catch (\Exception $exception) {
                $this->exts->log('Selenium Exception: ' . $exception->getMessage());
                $this->exts->capture("error");
                var_dump($exception);
            }

            $this->exts->log('Execution completed');

            $this->exts->process_completed();
        } else {
            echo 'Script execution failed.. ' . "\n";
        }
    }

    // Server-Portal-ID: 19919 - Last modified: 11.07.2025 15:09:13 UTC - User: 1

    public $baseUrl = 'https://manychat.com/profile/dashboard';
    public $loginUrl = 'https://manychat.com/login';
    public $check_login_failed_selector = 'SELECTOR_error';
    public $check_login_success_selector = 'img[src*="graph.facebook.com"]';
    public $isNoInvoice = true;
    public $login_with_google = 0;
    public $login_with_apple = 0;
    public $restrictPages = 3;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
        $this->login_with_google = isset($this->exts->config_array["login_with_google"]) ? (int) $this->exts->config_array["login_with_google"] : $this->login_with_google;
        $this->login_with_apple = isset($this->exts->config_array["login_with_apple"]) ? (int) $this->exts->config_array["login_with_apple"] : $this->login_with_apple;

        $this->exts->log('login_with_google ' . $this->login_with_google);
        $this->exts->log('login_with_apple ' . $this->login_with_apple);

        $this->exts->openUrl($this->baseUrl);


        $this->exts->waitTillPresent('#usercentrics-cmp-ui', 20);
        $this->exts->execute_javascript('
            var shadow = document.querySelector("#usercentrics-cmp-ui");
            if (shadow) {
                var button = shadow.shadowRoot.querySelector("button#accept");
                if (button) {
                    button.click();
                }
            }
        ');
        // Load cookies
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->baseUrl);

        $this->exts->waitTillPresent('#usercentrics-cmp-ui', 20);
        $this->exts->execute_javascript('
            var shadow = document.querySelector("#usercentrics-cmp-ui");
            if (shadow) {
                var button = shadow.shadowRoot.querySelector("button#accept");
                if (button) {
                    button.click();
                }
            }
        ');

        $this->exts->capture('1-init-page');

        $this->solveCaptcha();
        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        $this->exts->waitTillPresent($this->check_login_success_selector);
        if ($this->exts->querySelector($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->openUrl($this->loginUrl);

            $this->exts->waitTillPresent('#usercentrics-cmp-ui', 5);
            $this->exts->execute_javascript('
            var shadow = document.querySelector("#usercentrics-cmp-ui");
            if (shadow) {
                var button = shadow.shadowRoot.querySelector("button#accept");
                if (button) {
                    button.click();
                }
            }
        ');

            $this->solveCaptcha();
            $this->exts->waitTillPresent('#usercentrics-cmp-ui', 5);
            $this->exts->execute_javascript('
            var shadow = document.querySelector("#usercentrics-cmp-ui");
            if (shadow) {
                var button = shadow.shadowRoot.querySelector("button#accept");
                if (button) {
                    button.click();
                }
            }
        ');


            $this->exts->moveToElementAndClick('input#agree-checkbox');
            sleep(5);
            $this->exts->moveToElementAndClick('btn#sign-in-link');
            sleep(5);
            if ($this->exts->exists('button[data-test-id="accept-cookies"]')) {
                $this->exts->moveToElementAndClick('button[data-test-id="accept-cookies"]');
                sleep(5);
            }

            if ($this->exts->exists("#usercentrics-root, #usercentrics-cmp-ui")) {
                $this->exts->execute_javascript('
                var shadow = document.querySelector("#usercentrics-root");
                if(shadow){
                    var button = shadow.shadowRoot.querySelector(\'button[data-testid="uc-ccpa-button\')).querySelector(\'button[data-testid="uc-accept-all-button"]\');
                    if(button){button.click();}
                }
            ');

                $this->exts->waitTillPresent('#usercentrics-cmp-ui', 5);
                $this->exts->execute_javascript('
                var shadow = document.querySelector("#usercentrics-cmp-ui");
                if(shadow){
                    var button = shadow.shadowRoot.querySelector(\'button[aria-label="Accept all"]\');
                    if(button){button.click();}
                }
            ');


                $this->exts->waitTillPresent('#usercentrics-cmp-ui', 5);
                $this->exts->execute_javascript('
                var shadow = document.querySelector("#usercentrics-cmp-ui");
                if(shadow){
                    var button = shadow.shadowRoot.querySelector(\'button[id="uc-ccpa-ok-button"]\');
                    if(button){button.click();}
                }
            ');
                sleep(15);
            }

            if ($this->login_with_google == 1) {
                $googleBtn = $this->exts->queryXpath('//button[contains(., "Sign In With Google")]');
                sleep(3);
                if ($googleBtn != null) {
                    $this->exts->click_element($googleBtn);
                    sleep(5);
                }
                $this->exts->switchToNewestActiveTab();
                sleep(1);

                $this->loginGoogleIfRequired();
            } elseif ($this->login_with_apple == 1) {
                $appleBtn = $this->exts->queryXpath('//button[contains(., "Sign In With Apple")]');
                sleep(3);
                if ($appleBtn != null) {
                    $this->exts->click_element($appleBtn);
                    sleep(5);
                }
                $this->loginAppleIfRequired();
            } else {
                $fbBtn = $this->exts->queryXpath('//button[contains(., "Sign In With Facebook")]');
                sleep(3);
                if ($fbBtn != null) {
                    $this->exts->click_element($fbBtn);
                    sleep(5);
                }
                $this->exts->switchToNewestActiveTab();
                sleep(5);


                if ($this->exts->exists("div[aria-label='Alle Cookies erlauben']:not([aria-disabled='true']")) {
                    $this->exts->click_element("div[aria-label='Alle Cookies erlauben']:not([aria-disabled='true']");
                }


                $this->loginFacebookIfRequired();
                sleep(5);

                if ($this->exts->exists("div[aria-label='Alle Cookies erlauben']:not([aria-disabled='true']")) {
                    $this->exts->click_element("div[aria-label='Alle Cookies erlauben']:not([aria-disabled='true']");
                }
                $this->loginFacebookIfRequired();
            }
        }

        if ($this->exts->querySelector($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");
            sleep(10);
            $this->exts->waitTillPresent('#usercentrics-root');
            $this->exts->execute_javascript('
            var shadow = document.querySelector("#usercentrics-root");
            if(shadow){
                var button = shadow.shadowRoot.querySelector(\'button[data-testid="uc-ccpa-button\');
                if(button){button.click();}
            }
        ');


            if ($this->exts->exists('button[data-test-id="accept-cookies"]')) {
                $this->exts->moveToElementAndClick('button[data-test-id="accept-cookies"]');
                sleep(1);
            }

            // Open invoices url and download invoice
            $this->invoicePage();

            $this->exts->openUrl($this->baseUrl);
            sleep(5);

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        }
    }

    private function solveCaptcha()
    {
        $this->exts->waitTillPresent('button#amzn-captcha-verify-button', 20);
        if ($this->exts->exists('button#amzn-captcha-verify-button')) {
            $this->exts->click_element('button#amzn-captcha-verify-button');
            $is_captcha = $this->solve_captcha_by_clicking(0);
            if ($is_captcha) {
                for ($i = 1; $i < 15; $i++) {
                    if ($is_captcha == false) {
                        break;
                    }
                    $is_captcha = $this->solve_captcha_by_clicking($i);
                }
            }
        }
    }

    private function solve_captcha_by_clicking($count = 1)
    {
        $this->exts->log("Checking captcha");
        $this->exts->waitTillPresent('div.amzn-captcha-modal canvas', 20);
        $language_code = 'en';
        if ($this->exts->exists('button#amzn-captcha-verify-button')) {
            $this->exts->click_element('button#amzn-captcha-verify-button');
            sleep(3);
        }
        if ($this->exts->exists('div.amzn-captcha-modal canvas')) {
            $this->exts->capture("analyze-captcha");



            $captcha_instruction = $this->exts->extract("//div[contains(text(), 'Choose')]");


            //$captcha_instruction = $this->exts->extract($iframeElement_instartion,null, 'innerText');
            $this->exts->log('language_code: ' . $language_code . ' Instruction: ' . $captcha_instruction);
            sleep(5);
            $captcha_wraper_selector = 'div.amzn-captcha-modal canvas';

            if ($this->exts->exists($captcha_wraper_selector)) {
                $coordinates = $this->getCoordinates($captcha_wraper_selector, $captcha_instruction, '', $json_result = false);


                // if($coordinates == '' || count($coordinates) < 2){
                //  $coordinates = $this->exts->processClickCaptcha($captcha_wraper_selector, $captcha_instruction, '', $json_result=false);
                // }
                if ($coordinates != '') {
                    // $challenge_wraper = $this->exts->querySelector($captcha_wraper_selector);

                    foreach ($coordinates as $coordinate) {
                        $this->exts->click_by_xdotool($captcha_wraper_selector, (int) $coordinate['x'], (int) $coordinate['y']);
                    }

                    $this->exts->capture("analyze-captcha-selected " . $count);
                    $this->exts->click_element('button#amzn-btn-verify-internal');
                    sleep(10);
                    return true;
                }
            }

            return false;
        }
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

    /**================================== FACEBOOK LOGIN =================================================**/
    public $facebook_baseUrl = 'https://www.facebook.com';
    public $facebook_loginUrl = 'https://www.facebook.com';
    public $facebook_username_selector = 'form input#email';
    public $facebook_password_selector = 'form input#pass';
    public $facebook_submit_login_selector = 'form button[type="submit"], #logginbutton, #loginbutton';
    public $facebook_check_login_failed_selector = 'div.uiContextualLayerPositioner[data-ownerid="email"], div#error_box, div.uiContextualLayerPositioner[data-ownerid="pass"], input.fileInputUpload';
    public $facebook_check_login_success_selector = '#logoutMenu, #ssrb_feed_start, div[role="navigation"] a[href*="/me"]';

    private function loginFacebookIfRequired()
    {
        if ($this->exts->urlContains('facebook.')) {
            $this->exts->log('Start login with facebook');
            // Sometime it require accept cookie twice
            $this->accept_cookie_page();
            $this->accept_cookie_page();
            $this->checkFillFacebookLogin();
            sleep(5);
            if ($this->exts->exists('#login_form')) {
                $this->exts->capture("2-seconds-login-page");
                $this->checkFillFacebookLogin();
                sleep(5);
            }
            if (stripos($this->exts->extract('#login_form #error_box'), 'Wrong credentials') !== false || stripos($this->exts->extract('#login_form #error_box'), 'incorrect') !== false || stripos(strtolower($this->exts->extract('h2.uiHeaderTitle')), 'old password') !== false) {
                $this->exts->loginFailure(1);
            }
            $this->checkAndCompleteFacebookTwoFactor();
            sleep(10);
            $this->checkAndCompleteFacebookTwoFactor();
            $this->exts->update_process_lock();

            $this->accept_cookie_page();
            $this->accept_cookie_page();
        } else {
            $this->exts->log(__FUNCTION__ . '::Not required facebook login.');
            $this->exts->capture("3-no-facebook-required");
        }
    }
    private function checkAndCompleteFacebookTwoFactor()
    {
        $this->exts->capture('facebook-twofactor-checking');
        $mesg = strtolower($this->exts->extract('form.checkpoint > div, [role="dialog"] [data-tooltip-display="overflow"]', null, 'text'));
        if (
            strpos($mesg, 'temporarily blocked') !== false
            || strpos($mesg, 'Your account has been deactivated') !== false
            || strpos($mesg, 'Download your information') !== false
            || strpos($mesg, 'Your account has been suspended') !== false
            || strpos($mesg, 'Your account has been disabled') !== false
            || strpos($mesg, 'Your file is ready') !== false
            || strpos($mesg, 'bergehend blockiert') !== false
        ) {
            // account locked
            $this->exts->log('User login failed: ' . $this->exts->getUrl());
            $this->exts->account_not_ready();
        } elseif (
            strpos($this->exts->extract('body'), 'Dein Konto wurde gesperrt') !== false
            || strpos($this->exts->extract('body'), 'Dein Konto wurde deaktiviert') !== false
            || strpos($this->exts->extract('body'), 'Deine Informationen herunterladen') !== false
            || strpos($this->exts->extract('body'), 'Dein Konto wurde vorÃ¼bergehend gesperrt') !== false
            || strpos($this->exts->extract('body'), 'Je account is uitgeschakeld') !== false
            || strpos($this->exts->extract('body'), 'Deine Datei steht bereit') !== false
            || strpos($this->exts->extract('body'), 'Suspended Your Account') !== false
        ) {
            // account locked
            $this->exts->log('User login failed: ' . $this->exts->getUrl());
            $this->exts->account_not_ready();
        }

        // Use USB 2FA device => choose different 2FA
        if ($this->exts->exists('input[name="checkpointU2Fauth"]') && $this->exts->exists('a[href*="/checkpoint/?next&no_fido=true"]')) {
            $this->exts->log('// Use USB 2FA device => choose different 2FA');
            $this->exts->openUrl('https://www.facebook.com/checkpoint/?next&no_fido=true');
            sleep(3);
            $this->exts->capture('facebook-twofactor-no_fido');
        }

        // choose 2FA verification method
        $facebook_two_factor_selector = 'form.checkpoint[action*="/checkpoint"] input[name*="captcha_response"], form.checkpoint[action*="/checkpoint"] input[name*="approvals_code"], input#recovery_code_entry';
        if ($this->exts->exists('button#checkpointSubmitButton, form[action*="/recover/code"] button[type="submit"]') && !$this->exts->exists($facebook_two_factor_selector)) {
            if (!$this->exts->exists('input[name="verification_method"]')) {
                $this->exts->moveToElementAndClick('button#checkpointSubmitButton, form[action*="/recover/code"] button[type="submit"]');
                sleep(3);
            }
            $this->exts->capture('verification-method');

            //Approve your login on another computer - 14
            //Log in with your Google account - 35
            //Get a code sent to your email = Receive code by email - 37
            //Get code on the phone - 34
            // Choose send code to phone, if not available, choose send code to email.
            $facebook_verification_method = $this->exts->getElementByText('.uiInputLabelLabel', ['phone', 'telefon', 'telefoon', 'telÃ©fono', 'puhelin', 'Telefone', 'tÃ©lÃ©phone', 'telephone', 'Telefon',], null, false);
            if ($facebook_verification_method == null) {
                $facebook_verification_method = $this->exts->getElementByText('.uiInputLabelLabel', ['email', 'e-mail', 'E-Mail', 'e-mailadres', 'electrÃ³nico', 'elektronisk', 'sÃ¤hkÃ¶posti', 'E-postana'], null, false);
            }
            if ($facebook_verification_method != null) {
                $this->exts->click_element($facebook_verification_method);
            } else {
                $this->exts->log('choose first option.');
                $this->exts->moveToElementAndClick('.uiInputLabelLabel');
            }
            $this->exts->capture('verification-method-selected');
            $this->exts->moveToElementAndClick('button#checkpointSubmitButton, form[action*="/recover/code"] button[type="submit"]');
            sleep(5);
            if ($this->exts->exists('button#checkpointSubmitButton, form[action*="/recover/code"] button[type="submit"]') && !$this->exts->exists($facebook_two_factor_selector)) {
                // Click some Next button
                $this->exts->moveToElementAndClick('button#checkpointSubmitButton, form[action*="/recover/code"] button[type="submit"]');
                sleep(3);
                if ($this->exts->exists('button#checkpointSubmitButton, form[action*="/recover/code"] button[type="submit"]') && !$this->exts->exists($facebook_two_factor_selector)) {
                    $this->exts->moveToElementAndClick('button#checkpointSubmitButton, form[action*="/recover/code"] button[type="submit"]');
                    sleep(3);
                }
            }
            $this->exts->log('fill code and continue: two_factor_response');
            $this->checkFillFacebookTwoFactor();
            $this->exts->capture('verification-method-after-solve');
            if ($this->exts->exists('[data-testid="dialog_title_close_button"]')) {
                $this->exts->moveToElementAndClick('[data-testid="dialog_title_close_button"]');
                sleep(1);
            }
            if ($this->exts->exists('button#checkpointSubmitButton, form[action*="/recover/code"] button[type="submit"]') && !$this->exts->exists($facebook_two_factor_selector)) {
                $this->exts->moveToElementAndClick('button#checkpointSubmitButton, form[action*="/recover/code"] button[type="submit"]');
                sleep(5);
            }
        } else {
            $this->exts->log('fill code and continue: approvals_code');
            $this->checkFillFacebookTwoFactor();
            if ($this->exts->exists('[role="dialog"] [action="cancel"]')) {
                // Close the popup say: It looks like you were misusing this feature by going too fast. YouÃ¢â‚¬â„¢ve been temporarily blocked from using it
                $this->exts->moveToElementAndClick('[role="dialog"] [action="cancel"]');
            }
            if ($this->exts->exists('[data-testid="dialog_title_close_button"]')) {
                $this->exts->moveToElementAndClick('[data-testid="dialog_title_close_button"]');
                sleep(1);
            }
        }
    }
    private function accept_cookie_page()
    {
        if ($this->exts->exists('[data-testid="cookie-policy-dialog-accept-button"], [data-cookiebanner="accept_button"]')) {
            $this->exts->moveToElementAndClick('[data-testid="cookie-policy-dialog-accept-button"], [data-cookiebanner="accept_button"]');
            sleep(3);
        }
        $accept_cookie_button = $this->exts->getElementByText('[role="dialog"] div[role="button"]:not([aria-disabled]) span', ['Alle akzeptieren', 'Accept', 'Cookies erlauben', 'Autoriser tous les cookies', 'Allow All Cookies'], null, false);
        if ($accept_cookie_button !== null && $this->exts->urlContains('/user_cookie_prompt')) {
            $this->exts->click_element($accept_cookie_button);
            sleep(2);
        } else if ($this->exts->urlContains('/user_cookie_prompt')) {
            $accept_cookie_button = $this->exts->getElementByText('[role="dialog"] div[role="button"]:not([aria-disabled]) span', ['Cookie', 'cookie'], null, false);
            $this->exts->click_element($accept_cookie_button);
            sleep(2);
        }
    }
    private function checkFillFacebookLogin()
    {
        if ($this->exts->getElement($this->facebook_password_selector) != null) {
            if ($this->exts->exists('[role="dialog"] button[data-testid="cookie-policy-dialog-accept-button"]')) {
                $this->exts->moveToElementAndClick('[role="dialog"] button[data-testid="cookie-policy-dialog-accept-button"]');
                sleep(2);
            }
            $this->exts->capture("2-facebook-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndClick($this->facebook_username_selector);
            $this->exts->moveToElementAndType($this->facebook_username_selector, '');
            $this->exts->moveToElementAndType($this->facebook_username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndClick($this->facebook_password_selector);
            $this->exts->moveToElementAndType($this->facebook_password_selector, '');
            $this->exts->moveToElementAndType($this->facebook_password_selector, $this->password);
            sleep(1);

            $this->exts->capture("2-facebook-login-page-filled");
            $this->exts->moveToElementAndClick($this->facebook_submit_login_selector);

            // for ($wait = 0; $wait < 15 && $this->exts->executeSafeScript("return !!document.querySelector(\"div[aria-label='Alle Cookies erlauben']:not([aria-disabled='true'])\");") != 1; $wait++) {
            //     $this->exts->log('Waiting for login.....');
            //     sleep(10);
            // }

            Sleep(5);
            if ($this->exts->exists("div[aria-label='Alle Cookies erlauben']:not([aria-disabled='true']")) {
                $this->exts->click_element("div[aria-label='Alle Cookies erlauben']:not([aria-disabled='true']");
            }

            for ($wait = 0; $wait < 15 && $this->exts->executeSafeScript("return !!document.querySelector('div[role=\"button\"]');") != 1; $wait++) {
                $this->exts->log('Waiting for login.....');
                sleep(10);
            }
            sleep(5);
            if ($this->exts->exists("div[role='button']")) {
                $this->exts->click_element("div[role='button']");

                $this->exts->waitTillPresent('//span[contains(., "Mehr Optionen sehen")]', 80);
                $this->exts->click_element('//span[contains(., "Mehr Optionen sehen")]');

                $this->exts->waitTillPresent("//label[contains(., 'SMS')]", 80);
                $this->exts->click_element("//label[contains(., 'SMS')]");

                $this->exts->waitTillPresent('//div[@role="button" and @tabindex="0" and .//span[contains(normalize-space(.), "Weiter")]]', 80);
                $this->exts->click_element('//div[@role="button" and @tabindex="0" and .//span[contains(normalize-space(.), "Weiter")]]');


                sleep(10);
                $this->checkFillFacebookTwoFactor();
            }

            sleep(10);
            if ($this->exts->exists('input[name="pass"]') && $this->exts->getElement($this->facebook_username_selector) == null) {
                $this->exts->moveToElementAndType('input[name="pass"]', $this->password);
                sleep(1);
                $this->exts->moveToElementAndClick('form[action*="login"] input[type="submit"]');
                sleep(5);
            }
            $this->processImageCaptcha();
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-facebook-login-page-not-found");
        }
    }
    private function checkFillFacebookTwoFactor()
    {
        $facebook_two_factor_selector = 'form.checkpoint[action*="/checkpoint"] input[name*="captcha_response"], form.checkpoint[action*="/checkpoint"] input[name*="approvals_code"], input#recovery_code_entry, form[method="GET"] input[type="text"]';
        $facebook_two_factor_message_selector = '//span[contains(., "Enter the code we sent to")]
, form.checkpoint[action*="/checkpoint"] > div > div:nth-child(2), form.checkpoint[action*="/checkpoint"] strong + div, //span[contains(text(), \'Enter the 6-digit code\')]';
        $facebook_two_factor_submit_selector = '//span[contains(., "Further")], button#checkpointSubmitButton, form[action*="/recover/code"] div.uiInterstitialBar button, form[action*="/recover/code"] button[type="submit"], //div[@role=\'button\' and @tabindex=\'0\' and normalize-space(.//span[text()=\'Continue\'])]';

        if ($this->exts->exists($facebook_two_factor_selector)) {
            $this->exts->log("Facebook two factor page found.");
            $this->exts->capture("2.1-facebook-two-factor");

            if ($this->exts->getElement($facebook_two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->getElements($facebook_two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($facebook_two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
                }
                $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            }
            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }
            $this->exts->log("Facebook Message:\n" . $this->exts->two_factor_notif_msg_en);

            $this->exts->two_factor_timeout = 2;
            $this->exts->notification_uid = ''; // set this to clear 2FA response cache
            $facebook_two_factor_code = trim($this->exts->fetchTwoFactorCode());
            $this->exts->update_process_lock();
            if (empty($facebook_two_factor_code)) {
                $this->exts->two_factor_timeout = 2;
                $this->exts->notification_uid = '';
                $facebook_two_factor_code = trim($this->exts->fetchTwoFactorCode());
                $this->exts->update_process_lock();
            }
            if (empty($facebook_two_factor_code)) {
                $this->exts->two_factor_timeout = 7;
                $this->exts->notification_uid = '';
                $facebook_two_factor_code = trim($this->exts->fetchTwoFactorCode());
                $this->exts->update_process_lock();
            }

            if (!empty($facebook_two_factor_code) && trim($facebook_two_factor_code) != '') {
                $this->exts->log("FacebookCheckFillTwoFactor: Entering facebook_two_factor_code." . $facebook_two_factor_code);
                if ($this->exts->exists('[role="dialog"] [action="cancel"]')) {
                    // Close the popup say: It looks like you were misusing this feature by going too fast. YouÃ¢â‚¬â„¢ve been temporarily blocked from using it
                    $this->exts->moveToElementAndClick('[role="dialog"] [action="cancel"]');
                }
                if ($this->exts->exists('[role="dialog"] [data-testid="dialog_title_close_button"]')) {
                    $this->exts->moveToElementAndClick('[role="dialog"] [data-testid="dialog_title_close_button"]');
                }
                $this->exts->moveToElementAndType($facebook_two_factor_selector, $facebook_two_factor_code);
                if ($this->exts->exists('[role="dialog"] [action="cancel"]')) {
                    // Close the popup say: It looks like you were misusing this feature by going too fast. YouÃ¢â‚¬â„¢ve been temporarily blocked from using it
                    $this->exts->moveToElementAndClick('[role="dialog"] [action="cancel"]');
                }
                $this->exts->capture("2.2-facebook-two-factor-filled-" . $this->exts->two_factor_attempts);
                if ($this->exts->exists('[role="dialog"] [action="cancel"]')) {
                    // Close the popup say: It looks like you were misusing this feature by going too fast. YouÃ¢â‚¬â„¢ve been temporarily blocked from using it
                    $this->exts->moveToElementAndClick('[role="dialog"] [action="cancel"]');
                }
                $this->exts->moveToElementAndClick($facebook_two_factor_submit_selector);
                sleep(7);
                $this->exts->capture('2.2-facebook-two-factor-submitted-' . $this->exts->two_factor_attempts);

                if ($this->exts->getElement($facebook_two_factor_selector) == null) {
                    $this->exts->log("Facebook two factor solved");
                    if ($this->exts->exists('input[value*="save_device"]')) {
                        $this->exts->moveToElementAndClick('input[value*="save_device"]');
                        $this->exts->capture('2.2-save-browser');
                        $this->exts->moveToElementAndClick('#checkpointSubmitButton[name="submit[Continue]"]');
                        sleep(7);
                    }
                    if ($this->exts->exists('form.checkpoint #checkpointSubmitButton')) {
                        $this->exts->capture('2.3-review-login');
                        $this->exts->moveToElementAndClick('form.checkpoint #checkpointSubmitButton');
                        sleep(7);
                    }

                    if ($this->exts->exists('button[name="submit[This was me]"]')) {
                        $this->exts->capture('2.3-that-me');
                        $this->exts->moveToElementAndClick('button[name="submit[This was me]"]');
                        sleep(7);
                    }
                }
            } else {
                $this->exts->log("Facebook failed to fetch two factor code!!!");
            }
        }
    }

    private function processImageCaptcha()
    {
        $inputSelector = 'input[type="text"]';
        $imgSelector = 'img[src*="captcha"]';
        $submitSelector = '//div[@role="button"][.//span[contains(text(), "Continue")]]';
        $this->exts->waitTillPresent($imgSelector);
        if ($this->exts->exists($imgSelector)) {
            for ($i = 0; $i < 5; $i++) {
                $this->exts->processCaptcha($imgSelector, $inputSelector);
                if ($this->exts->exists($submitSelector)) {
                    $this->exts->click_element($submitSelector);
                    sleep(5);
                }
                if (!$this->exts->exists($inputSelector)) {
                    break;
                }
            }
        }
    }
    /** ================================= END FACEBOOK LOGIN =========================================== **/

    // -------------------- GOOGLE login
    public $google_username_selector = 'input[name="identifier"]';
    public $google_submit_username_selector = '#identifierNext';
    public $google_password_selector = 'input[name="password"], input[name="Passwd"]';
    public $google_submit_password_selector = '#passwordNext, #passwordNext button';
    public $google_solved_rejected_browser = false;
    private function loginGoogleIfRequired()
    {
        if ($this->exts->urlContains('google.')) {
            $this->checkFillGoogleLogin();
            sleep(10);
            $this->check_solve_rejected_browser();

            if ($this->exts->querySelector('input[name="password"][aria-invalid="true"], input[name="identifier"][aria-invalid="true"], input[aria-invalid="true"][name="Passwd"]') != null) {
                $this->exts->loginFailure(1);
            }

            if ($this->exts->exists('form #approve_button[name="submit_true"]')) {
                // An application is requesting permission to access your Google Account.
                // Click allow
                $this->exts->click_by_xdotool('form #approve_button[name="submit_true"]');
                sleep(10);
            }
            // Click next if confirm form showed
            $this->exts->click_by_xdotool('div[data-ownership-changed-phone-number] div:nth-child(2) > [role="button"]');
            $this->checkGoogleTwoFactorMethod();
            sleep(10);
            if ($this->exts->exists('#smsauth-interstitial-remindbutton')) {
                $this->exts->click_by_xdotool('#smsauth-interstitial-remindbutton');
                sleep(10);
            }
            if ($this->exts->exists('#tos_form input#accept')) {
                $this->exts->click_by_xdotool('#tos_form input#accept');
                sleep(10);
            }
            if ($this->exts->exists('[wizard-step-uid="RecoveryOptionsCollectionWizard:starter"] div:last-child > [role="button"]')) {
                $this->exts->click_by_xdotool('[wizard-step-uid="RecoveryOptionsCollectionWizard:starter"] div:last-child > [role="button"]');
                sleep(10);
            }
            if ($this->exts->exists('.action-button.signin-button + a.setup-button[href*="/two-step-verification/"]')) {
                // SKIP setup 2FA
                $this->exts->click_by_xdotool('.action-button.signin-button');
                sleep(10);
            }
            if ($this->exts->exists('[action="/signin/newfeatures/save"] #optionsButton ~ [role="button"]')) {
                $this->exts->click_by_xdotool('[action="/signin/newfeatures/save"] #optionsButton ~ [role="button"]');
                sleep(10);
            }
            if ($this->exts->exists('input[name="later"]') && $this->exts->urlContains('/AddressNoLongerAvailable')) {
                $this->exts->click_by_xdotool('input[name="later"]');
                sleep(7);
            }
            if ($this->exts->exists('#editLanguageAndContactForm a[href*="/adsense/app"]')) {
                $this->exts->click_by_xdotool('#editLanguageAndContactForm a[href*="/adsense/app"]');
                sleep(7);
            }
            if ($this->exts->exists('[data-view-instance-id="/web/chip-V0"] [role="button"]:first-child [jsslot]')) {
                $this->exts->click_by_xdotool('[data-view-instance-id="/web/chip-V0"] [role="button"]:first-child [jsslot]');
                sleep(10);
            }
            if ($this->exts->urlContains('gds.google.com/web/chip')) {
                $this->exts->click_by_xdotool('[role="button"]:first-child [jsslot]');
                sleep(10);
            }

            if ($this->exts->exists('form #approve_button[name="submit_true"]')) {
                // An application is requesting permission to access your Google Account.
                // Click allow
                $this->exts->click_by_xdotool('form #approve_button[name="submit_true"]');
                sleep(10);
            }


            $this->exts->log('URL before back to main tab: ' . $this->exts->getUrl());
            $this->exts->capture("google-login-before-back-maintab");
            if (
                $this->exts->querySelector('input[name="password"][aria-invalid="true"], input[name="identifier"][aria-invalid="true"], input[aria-invalid="true"][name="Passwd"]') != null
            ) {
                $this->exts->loginFailure(1);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Not required google login.');
            $this->exts->capture("3-no-google-required");
        }
    }
    private function checkFillGoogleLogin()
    {
        if ($this->exts->exists('[data-view-id*="signInChooserView"] li [data-identifier]')) {
            $this->exts->click_by_xdotool('[data-view-id*="signInChooserView"] li [data-identifier]');
            sleep(10);
        } else if ($this->exts->exists('form li [role="link"][data-identifier]')) {
            $this->exts->click_by_xdotool('form li [role="link"][data-identifier]');
            sleep(10);
        }
        if ($this->exts->exists('form [data-profileindex]')) {
            $this->exts->click_by_xdotool('form [data-profileindex]');
            sleep(5);
        }
        $this->exts->capture("2-google-login-page");
        if ($this->exts->querySelector($this->google_username_selector) != null) {
            // $this->fake_user_agent();
            // $this->exts->refresh();
            // sleep(5);

            $this->exts->log("Enter Google Username");
            $this->exts->moveToElementAndType($this->google_username_selector, $this->username);
            sleep(1);
            $this->exts->click_by_xdotool($this->google_submit_username_selector);
            sleep(7);
            if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists($this->google_password_selector) && $this->exts->exists($this->google_username_selector)) {
                $this->exts->moveToElementAndType($this->google_username_selector, $this->username);
                sleep(1);
                $this->exts->processCaptcha('#captchaimg[src]', '#captchaimg[src] ~ * input[type="text"]');
                $this->exts->click_by_xdotool($this->google_submit_username_selector);
                sleep(5);
            }

            // Which account do you want to use?
            if ($this->exts->exists('form[action*="/lookup"] button.account-chooser-button')) {
                $this->exts->click_by_xdotool('form[action*="/lookup"] button.account-chooser-button');
                sleep(5);
            }
            if ($this->exts->exists('[data-view-id="prbTle"] form [role="link"][data-profileindex]')) {
                $this->exts->click_by_xdotool('[data-view-id="prbTle"] form [role="link"][data-profileindex]');
                sleep(5);
            }
        }

        if ($this->exts->querySelector($this->google_password_selector) != null) {
            $this->exts->log("Enter Google Password");
            $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
            sleep(1);

            if ($this->exts->exists('#captchaimg[src]')) {
                $this->exts->processCaptcha('#captchaimg[src]', '#captchaimg[src] ~ * input[type="text"]');
            }

            $this->exts->capture("2-google-password-filled");
            $this->exts->click_by_xdotool($this->google_submit_password_selector);
            sleep(5);
            if ($this->exts->exists('#captchaimg[src]') && $this->exts->exists($this->google_password_selector)) {
                $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
                sleep(1);
                $this->exts->processCaptcha('#captchaimg[src]', '#captchaimg[src] ~ * input[type="text"]');
                $this->exts->capture("2-login-google-pageandcaptcha-filled");
                $this->exts->click_by_xdotool($this->google_submit_password_selector);
                sleep(10);
                if ($this->exts->exists('#captchaimg[src]') && $this->exts->exists($this->google_password_selector)) {
                    $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
                    sleep(1);
                    $this->exts->processCaptcha('#captchaimg[src]', '#captchaimg[src] ~ * input[type="text"]');
                    $this->exts->capture("2-login-google-pageandcaptcha-filled");
                    $this->exts->click_by_xdotool($this->google_submit_password_selector);
                    sleep(10);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::google Password page not found');
            $this->exts->capture("2-google-password-page-not-found");
        }
    }
    private function check_solve_rejected_browser()
    {
        $this->exts->log(__FUNCTION__);
        $root_user_agent = $this->exts->evaluate('(function() { return navigator.userAgent; })();');

        if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) {
            $this->overwrite_user_agent('Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:105.0) Gecko/20100101 Firefox/105.0');
            if ($this->exts->urlContains('/v3/')) {
                $this->exts->click_by_xdotool('a[href*="/restart"]');
            } else {
                $this->exts->refresh();
            }
            sleep(7);
            $this->exts->capture("2-login-alternative-page");
            $this->checkFillLogin_undetected_mode();
        }
        if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) {
            $this->overwrite_user_agent('Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:105.0) Gecko/20100101 Firefox/105.0');
            if ($this->exts->urlContains('/v3/')) {
                $this->exts->click_by_xdotool('a[href*="/restart"]');
            } else {
                $this->exts->refresh();
            }
            sleep(7);
            $this->exts->capture("2-login-alternative-page");
            $this->checkFillLogin_undetected_mode();
        }
        if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) {
            $this->overwrite_user_agent('Mozilla/5.0 (Macintosh; Intel Mac OS X 12.6; rv:105.0) Gecko/20100101 Firefox/105.0');
            if ($this->exts->urlContains('/v3/')) {
                $this->exts->click_by_xdotool('a[href*="/restart"]');
            } else {
                $this->exts->refresh();
            }
            sleep(7);
            $this->exts->capture("2-login-alternative-page");
            $this->checkFillLogin_undetected_mode();
        }
        if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) {
            $this->overwrite_user_agent('Mozilla/5.0 (Macintosh; Intel Mac OS X 12_6) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Safari/605.1.15');
            if ($this->exts->urlContains('/v3/')) {
                $this->exts->click_by_xdotool('a[href*="/restart"]');
            } else {
                $this->exts->refresh();
            }
            sleep(7);
            $this->exts->capture("2-login-alternative-page");
            $this->checkFillLogin_undetected_mode();
        }
        if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) {
            $this->overwrite_user_agent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/105.0.0.0 Safari/537.36 OPR/90.0.4480.107');
            if ($this->exts->urlContains('/v3/')) {
                $this->exts->click_by_xdotool('a[href*="/restart"]');
            } else {
                $this->exts->refresh();
            }
            sleep(7);
            $this->exts->capture("2-login-alternative-page");
            $this->checkFillLogin_undetected_mode();
        }
        if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) {
            $this->overwrite_user_agent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/105.0.0.0 Safari/537.36 OPR/90.0.4480.107');
            if ($this->exts->urlContains('/v3/')) {
                $this->exts->click_by_xdotool('a[href*="/restart"]');
            } else {
                $this->exts->refresh();
            }
            sleep(7);
            $this->exts->capture("2-login-alternative-page");
            $this->checkFillLogin_undetected_mode();
        }

        if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) { // If all above failed, using DN user agent as last solution
            $this->overwrite_user_agent($root_user_agent);
            if ($this->exts->urlContains('/v3/')) {
                $this->exts->click_by_xdotool('a[href*="/restart"]');
            } else {
                $this->exts->refresh();
            }
            sleep(7);
            $this->checkFillLogin_undetected_mode($root_user_agent);
        }
        if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) { // If all above failed, using DN user agent as last solution
            $this->overwrite_user_agent($root_user_agent);
            if ($this->exts->urlContains('/v3/')) {
                $this->exts->click_by_xdotool('a[href*="/restart"]');
            } else {
                $this->exts->refresh();
            }
            sleep(7);
            $this->checkFillLogin_undetected_mode($root_user_agent);
        }
        if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) { // If all above failed, using DN user agent as last solution
            if ($this->exts->urlContains('/v3/')) {
                $this->exts->click_by_xdotool('a[href*="/restart"]');
            } else {
                $this->exts->refresh();
            }
            sleep(7);
            $this->checkFillLogin_undetected_mode($root_user_agent);
        }
        if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) { // If all above failed, using DN user agent as last solution
            if ($this->exts->urlContains('/v3/')) {
                $this->exts->click_by_xdotool('a[href*="/restart"]');
            } else {
                $this->exts->refresh();
            }
            sleep(7);
            $this->checkFillLogin_undetected_mode($root_user_agent);
        }
        if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) { // If all above failed, using DN user agent as last solution
            if ($this->exts->urlContains('/v3/')) {
                $this->exts->click_by_xdotool('a[href*="/restart"]');
            } else {
                $this->exts->refresh();
            }
            sleep(7);
            $this->checkFillLogin_undetected_mode($root_user_agent);
        }
        if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) { // If all above failed, using DN user agent as last solution
            if ($this->exts->urlContains('/v3/')) {
                $this->exts->click_by_xdotool('a[href*="/restart"]');
            } else {
                $this->exts->refresh();
            }
            sleep(7);
            $this->checkFillLogin_undetected_mode($root_user_agent);
        }
    }
    private function overwrite_user_agent($user_agent_string = 'DN')
    {
        $userAgentScript = "
(function() {
if ('userAgentData' in navigator) {
    navigator.userAgentData.getHighEntropyValues({}).then(() => {
        Object.defineProperty(navigator, 'userAgent', { 
            value: '{$user_agent_string}', 
            configurable: true 
        });
    });
} else {
    Object.defineProperty(navigator, 'userAgent', { 
        value: '{$user_agent_string}', 
        configurable: true 
    });
}
})();
";
        $this->exts->execute_javascript($userAgentScript);
    }

    private function checkFillLogin_undetected_mode($root_user_agent = '')
    {
        if ($this->exts->exists('form [data-profileindex]')) {
            $this->exts->click_by_xdotool('form [data-profileindex]');
            sleep(5);
        } else if ($this->exts->urlContainsAny(['/ServiceLogin/identifier', '/ServiceLogin/webreauth']) && $this->exts->exists($this->google_submit_username_selector) && !$this->exts->exists($this->google_username_selector)) {
            $this->exts->capture("2-google-verify-it-you");
            // To help keep your account secure, Google needs to verify it’s you. Please sign in again to continue to Google Ads
            $this->exts->click_by_xdotool($this->google_submit_username_selector);
            sleep(5);
        }

        $this->exts->capture("2-google-login-page");
        if ($this->exts->check_exist_by_chromedevtool($this->google_username_selector)) {
            if (!empty($root_user_agent)) {
                $this->overwrite_user_agent('DN'); // using DN (DONT KNOW) user agent, last solution
            }
            $this->exts->type_key_by_xdotool("F5");
            sleep(5);
            $current_useragent = $this->exts->evaluate('(function() { return navigator.userAgent; })();');

            $this->exts->log('current_useragent: ' . $current_useragent);
            $this->exts->log("Enter Username");
            $this->exts->click_by_xdotool($this->google_username_selector);
            $this->exts->click_by_xdotool($this->google_username_selector);
            $this->exts->type_key_by_xdotool("ctrl+a");
            $this->exts->type_key_by_xdotool("Delete");
            $this->exts->type_text_by_xdotool($this->username);
            sleep(1);
            $this->exts->capture_by_chromedevtool("2-google-username-filled");
            $this->exts->click_by_xdotool($this->google_submit_username_selector);
            sleep(7);
            if ($this->exts->check_exist_by_chromedevtool('#captchaimg[src]') && !$this->exts->check_exist_by_chromedevtool($this->google_password_selector) && $this->exts->check_exist_by_chromedevtool($this->google_username_selector)) {
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                $this->exts->click_by_xdotool($this->google_submit_username_selector);
                sleep(5);
                if ($this->exts->check_exist_by_chromedevtool('#captchaimg[src]') && !$this->exts->check_exist_by_chromedevtool($this->google_password_selector) && $this->exts->check_exist_by_chromedevtool($this->google_username_selector)) {
                    $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                    $this->exts->click_by_xdotool($this->google_submit_username_selector);
                    sleep(5);
                }
                if ($this->exts->check_exist_by_chromedevtool('#captchaimg[src]') && !$this->exts->check_exist_by_chromedevtool($this->google_password_selector) && $this->exts->check_exist_by_chromedevtool($this->google_username_selector)) {
                    $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                    $this->exts->click_by_xdotool($this->google_submit_username_selector);
                    sleep(5);
                }
            }

            if (!empty($root_user_agent)) { // If using DN user agent, we must revert back to root user agent before continue
                $this->overwrite_user_agent($root_user_agent);
                if ($this->exts->check_exist_by_chromedevtool($this->google_password_selector)) {
                    $this->exts->type_key_by_xdotool("F5");
                    sleep(3);
                    $this->exts->type_key_by_xdotool("F5");
                    sleep(3);
                    $this->exts->type_key_by_xdotool("F5");
                    sleep(6);
                    $this->exts->capture_by_chromedevtool("2-google-login-reverted-UA");
                }
            }

            // Which account do you want to use?
            if ($this->exts->check_exist_by_chromedevtool('form[action*="/lookup"] button.account-chooser-button')) {
                $this->exts->click_by_xdotool('form[action*="/lookup"] button.account-chooser-button');
                sleep(5);
            }
            if ($this->exts->check_exist_by_chromedevtool('[data-view-id="prbTle"] form [role="link"][data-profileindex]')) {
                $this->exts->click_by_xdotool('[data-view-id="prbTle"] form [role="link"][data-profileindex]');
                sleep(5);
            }
        }

        if ($this->exts->check_exist_by_chromedevtool($this->google_password_selector)) {
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
            sleep(1);
            if ($this->exts->exists('#captchaimg[src]')) {
                $this->exts->processCaptcha('#captchaimg[src]', '#captchaimg[src] ~ * input[type="text"]');
            }

            $this->exts->capture("2-google-password-filled");
            $this->exts->click_by_xdotool($this->google_submit_password_selector);
            sleep(5);
            if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists('input[name="password"][aria-invalid="true"]') && $this->exts->exists($this->google_password_selector)) {
                $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
                sleep(1);
                if ($this->exts->exists('#captchaimg[src]')) {
                    $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                }
                $this->exts->click_by_xdotool($this->google_submit_password_selector);
                sleep(5);
                if ($this->exts->exists('#captchaimg[src]') && $this->exts->exists($this->google_password_selector)) {
                    $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
                    sleep(1);
                    $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                    $this->exts->capture("2-lgoogle-ogin-pageandcaptcha-filled");
                    $this->exts->click_by_xdotool($this->google_submit_password_selector);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Password page not found');
            $this->exts->capture("2-google-password-page-not-found");
        }
    }
    private function checkGoogleTwoFactorMethod()
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
        $this->exts->capture("2.0-before-check-two-factor");
        // STEP 0 (updated special case 28-Mar-2020): If we meet a unsolvable, click to back to method choosen list
        if ($this->exts->exists('#assistActionId') && $this->exts->exists('[data-illustration="securityKeyLaptopAnim"]')) {
            $this->exts->click_by_xdotool('#assistActionId');
            sleep(5);
        } else if ($this->exts->urlContains('/challenge/wa') && strpos($this->exts->extract('form header h2'), 'QR-Code') !== false) {
            // (updated special case 28-Mar-2020): If we meet QR-Code, click 'Choose another option' to back to method choosen list
            $this->exts->click_by_xdotool('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
            sleep(5);
            $this->exts->capture("2.0-backed-methods-list");
            if ($this->exts->urlContains('/challenge/wa') && strpos($this->exts->extract('form header h2'), 'QR-Code') !== false) {
                $this->exts->click_by_xdotool('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
                sleep(5);
            }
        } else if ($this->exts->urlContains('/sk/webauthn') || $this->exts->urlContains('/challenge/pk')) {
            // CURRENTLY THIS CASE CAN NOT BE SOLVED
            $node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-" . $this->exts->process_uid;
            exec("sudo docker exec " . $node_name . " bash -c 'sudo apt-get clean'");
            exec("sudo docker exec " . $node_name . " bash -c 'sudo apt-get -y update'");
            exec("sudo docker exec " . $node_name . " bash -c 'sudo apt-get install -y xdotool'");
            exec("sudo docker exec " . $node_name . " bash -c 'xdotool key Return'");
            sleep(3);
            $this->exts->capture("2.0-cancel-security-usb");
            $this->exts->click_by_xdotool('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
            sleep(5);
            $this->exts->capture("2.0-backed-methods-list");
        } else if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
            // (updated special case 09-May-2020): If Notification method showed immediately, This method often make user confused
            // So, We try to click 'Choose another option' in order to select easier method
            $this->exts->click_by_xdotool('[data-view-id] > div > div:nth-child(2)  div:nth-child(2) > [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button, button#assistiveActionOutOfQuota');
            sleep(7);
        } else if ($this->exts->exists('input[name="ootpPin"]')) {
            // (updated special case 11-Jun-2020): If "Verify by offline device" immediately, This method often make user confused and maybe they don't have device on hand
            // So, We try to click 'Choose another option' in order to select easier method
            $this->exts->click_by_xdotool('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
            sleep(7);
        } else if ($this->exts->urlContains('/challenge/') && !$this->exts->urlContains('/challenge/pwd') && !$this->exts->urlContains('/challenge/totp')) { // totp is authenticator app code method
            // if this is not password form AND this is two factor form BUT it is not Authenticator app code method, back to selection list anyway in order to choose Authenticator app method if available
            $supporting_languages = [
                "Try another way",
                "Andere Option w",
                "Essayer une autre m",
                "Probeer het op een andere manier",
                "Probar otra manera",
                "Prova un altro metodo"
            ];
            $back_button_xpath = '//*[contains(text(), "Try another way") or contains(text(), "Andere Option w") or contains(text(), "Essayer une autre m")';
            $back_button_xpath = $back_button_xpath . ' or contains(text(), "Probeer het op een andere manier") or contains(text(), "Probar otra manera") or contains(text(), "Prova un altro metodo")';
            $back_button_xpath = $back_button_xpath . ']/..';
            $back_button = $this->exts->getElement($back_button_xpath, null, 'xpath');
            if ($back_button != null) {
                try {
                    $this->exts->log(__FUNCTION__ . ' back to method list to find Authenticator app.');
                    $this->exts->execute_javascript("arguments[0].click();", [$back_button]);
                } catch (\Exception $exception) {
                    $this->exts->executeSafeScript("arguments[0].click()", [$back_button]);
                }
            }
            sleep(5);
            $this->exts->capture("2.0-backed-methods-list");
        }

        // STEP 1: Check if list of two factor methods showed, select first
        if ($this->exts->exists('li [data-challengetype]:not([data-challengeunavailable="true"])')) {
            $this->exts->capture("2.1-2FA-method-list");

            // Updated 03-2023 since we setup sub-system to get authenticator code without request to end-user. So from now, We priority for code from Authenticator app top 1, sms code or email code 2st, then other methods
            if ($this->exts->exists('li [data-challengetype="6"]:not([data-challengeunavailable="true"])')) {
                // We RECOMMEND TOP 1 method type = 6 is get code from Google Authenticator
                $this->exts->click_by_xdotool('li [data-challengetype="6"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype="13"]:not([data-challengeunavailable="true"])') && isset($this->security_phone_number) && $this->security_phone_number != '') {
                $this->exts->click_by_xdotool('li [data-challengetype="13"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype="12"]:not([data-challengeunavailable="true"])') && isset($this->recovery_email) && $this->recovery_email != '') {
                $this->exts->click_by_xdotool('li [data-challengetype="12"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype="1"]:not([data-challengeunavailable="true"])')) {
                // Select enter your passowrd, if only option is passkey
                $this->exts->click_by_xdotool('li [data-challengetype="1"]:not([data-challengeunavailable="true"])');
                sleep(3);
                $this->checkFillGoogleLogin();
                sleep(3);
                $this->checkGoogleTwoFactorMethod();
            } else if ($this->exts->exists('li [data-challengetype="6"]:not([data-challengeunavailable="true"])')) {
                // We RECOMMEND method type = 6 is get code from Google Authenticator
                $this->exts->click_by_xdotool('li [data-challengetype="6"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])') && (isset($this->security_phone_number) && $this->security_phone_number != '')) {
                // We second RECOMMEND method type = 9 is get code from SMS
                $this->exts->click_by_xdotool('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"][data-challengeid="10"]:not([data-challengeunavailable="true"])')) {
                // We recommend method type = 4 and [data-sendauthzenprompt="true"] is  Tap YES on your smartphone or tablet
                $this->exts->click_by_xdotool('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"][data-challengeid="12"]:not([data-challengeunavailable="true"])')) {
                // We recommend method type = 4 and [data-sendauthzenprompt="true"] is  Tap YES on your smartphone or tablet
                $this->exts->click_by_xdotool('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype="5"]:not([data-challengeunavailable="true"])')) {
                // Use a smartphone or tablet to receive a security code (even when offline)
                $this->exts->click_by_xdotool('li [data-challengetype="5"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype]:not([data-challengetype="4"]):not([data-challengetype="2"]):not([data-challengeunavailable="true"])')) {
                // We DONT recommend method is QR code OR is Security USB, we can not solve this type of 2FA
                $this->exts->click_by_xdotool('li [data-challengetype]:not([data-challengetype="4"]):not([data-challengetype="2"]):not([data-challengeunavailable="true"])');
            } else {
                $this->exts->click_by_xdotool('li [data-challengetype]:not([data-challengeunavailable="true"])');
            }
            sleep(10);
        } else if ($this->exts->exists('#smsButton, [data-illustration="accountRecoverySmsPin"]')) {
            // Sometime user must confirm before google send sms
            $this->exts->click_by_xdotool('#smsButton, div:first-child > [role="button"], [data-secondary-action-label] > div > div:nth-child(1) button');
            sleep(10);
        } else if ($this->exts->exists('#authzenNext') && $this->exts->exists('[data-view-id*="authzenView"], [data-illustration*="authzen"]')) {
            $this->exts->click_by_xdotool('[data-view-id] #authzenNext');
            sleep(10);
        } else if ($this->exts->exists('#idvpreregisteredemailNext') && !$this->exts->exists('form input:not([type="hidden"])')) {
            $this->exts->click_by_xdotool('#idvpreregisteredemailNext');
            sleep(10);
        }

        // STEP 2: (Optional)
        if ($this->exts->exists('input#knowledge-preregistered-email-response, input[name="knowledgePreregisteredEmailResponse"]')) {
            // If methos is recovery email, send 2FA to ask for email
            $this->exts->two_factor_attempts = 2;
            $input_selector = 'input#knowledge-preregistered-email-response, input[name="knowledgePreregisteredEmailResponse"]';
            $message_selector = '[data-view-id] form section div > div[jsslot] > div:first-child';
            $submit_selector = '';
            if (isset($this->recovery_email) && $this->recovery_email != '') {
                $this->exts->moveToElementAndType($input_selector, $this->recovery_email);
                $this->exts->type_key_by_xdotool('Return');
                sleep(7);
            }
            if ($this->exts->exists($input_selector)) {
                $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, true);
                sleep(5);
            }
        } else if ($this->exts->exists('[data-view-id*="knowledgePreregisteredPhoneView"] input[type="tel"]')) {
            // If methos confirm recovery phone number, send 2FA to ask
            $this->exts->two_factor_attempts = 3;
            $input_selector = '[data-view-id*="knowledgePreregisteredPhoneView"] input[type="tel"]';
            $message_selector = '[data-view-id] form section div > div[jsslot] > div:first-child';
            $submit_selector = '';
            if (isset($this->security_phone_number) && $this->security_phone_number != '') {
                $this->exts->moveToElementAndType($input_selector, $this->security_phone_number);
                $this->exts->type_key_by_xdotool('Return');
                sleep(5);
            }
            if ($this->exts->exists($input_selector)) {
                $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, true);
                sleep(5);
            }
        } else if ($this->exts->exists('input#phoneNumberId')) {
            // Enter a phone number to receive an SMS with a confirmation code.
            $this->exts->two_factor_attempts = 3;
            $input_selector = 'input#phoneNumberId';
            $message_selector = '[data-view-id] form section > div > div > div:first-child';
            $submit_selector = '';
            if (isset($this->security_phone_number) && $this->security_phone_number != '') {
                $this->exts->moveToElementAndType($input_selector, $this->security_phone_number);
                $this->exts->type_key_by_xdotool('Return');
                sleep(7);
            }
            if ($this->exts->exists($input_selector)) {
                $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, true);
            }
        } else if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
            // Check your smartphone. Google has sent a notification to your smartphone. Tap Yes in the notification, then tap 91 on your smartphone to continue
            $this->exts->two_factor_attempts = 3;
            $message_selector = '[data-view-id*="authzenView"] form, [data-view-id] form[method="post"]';
            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'text')) . "\n>>>Enter \"OK\" after confirmation on device";
            $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'text')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->fillGoogleTwoFactor(null, null, '');
            sleep(5);
        } else if ($this->exts->exists('[data-view-id*="securityKeyWebAuthnView"], [data-view-id*="securityKeyView"]')) {
            // Method: insert your security key and touch it
            $this->exts->two_factor_attempts = 3;
            $this->exts->two_factor_notif_msg_en = 'Use chrome, login then insert your security key and touch it' . "\n>>>Enter \"OK\" after confirmation on device";
            $this->exts->two_factor_notif_msg_de = '[Chrome] Stecken Sie den Sicherheitsschlussel in den USB-Anschluss Ihres Computers ein. Wenn er eine Taste hat, tippen Sie darauf.' . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->fillGoogleTwoFactor(null, null, '');
            sleep(5);
            // choose another option: #assistActionId
        }

        // STEP 3: (Optional)  After choose method and confirm email or phone or.., google may asked confirm one more time before send code
        if ($this->exts->exists('#smsButton, [data-illustration="accountRecoverySmsPin"]')) {
            // Sometime user must confirm before google send sms
            $this->exts->click_by_xdotool('#smsButton, div:first-child > [role="button"], [data-secondary-action-label] > div > div:nth-child(1) button');
            sleep(10);
        } else if ($this->exts->exists('#authzenNext') && $this->exts->exists('[data-view-id*="authzenView"], [data-illustration*="authzen"]')) {
            $this->exts->click_by_xdotool('[data-view-id] #authzenNext');
            sleep(10);
        } else if ($this->exts->exists('#idvpreregisteredemailNext') && !$this->exts->exists('form input:not([type="hidden"])')) {
            $this->exts->click_by_xdotool('#idvpreregisteredemailNext');
            sleep(10);
        } else if (count($this->exts->querySelectorAll('li [data-challengetype]:not([data-challengeunavailable="true"]):not([data-challengetype="undefined"])')) > 0) {
            $this->exts->click_by_xdotool('li [data-challengetype]:not([data-challengeunavailable="true"]):not([data-challengetype="undefined"])');
            sleep(7);
        }


        // STEP 4: input code
        if ($this->exts->exists('form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin, input#idvPin, input#idvPinId')) {
            $input_selector = 'form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin, input#idvPin, input#idvPinId';
            $message_selector = 'form > span > section > div > div > div:first-child';
            $submit_selector = '#idvPreregisteredPhoneNext, #idvpreregisteredemailNext, #totpNext, #idvanyphoneverifyNext, #backupCodeNext';
            $this->exts->two_factor_attempts = 3;
            $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, true);
        } else if ($this->exts->exists('input[name="ootpPin"], input#securityKeyOtpInputId')) {
            $input_selector = 'input[name="ootpPin"], input#securityKeyOtpInputId';
            $message_selector = 'form > span > section > div > div > div:first-child';
            $submit_selector = '';
            $this->exts->two_factor_attempts = 0;
            $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, true);
        } else if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
            // Check your smartphone. Google has sent a notification to your smartphone. Tap Yes in the notification, then tap 91 on your smartphone to continue
            $this->exts->two_factor_attempts = 3;
            $message_selector = '[data-view-id*="authzenView"] form, [data-view-id] form[method="post"]';
            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'text')) . "\n>>>Enter \"OK\" after confirmation on device";
            $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'text')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->fillGoogleTwoFactor(null, null, '');
            sleep(5);
        } else if ($this->exts->exists('[data-view-id*="securityKeyWebAuthnView"], [data-view-id*="securityKeyView"]')) {
            // Method: insert your security key and touch it
            $this->exts->two_factor_attempts = 3;
            $this->exts->two_factor_notif_msg_en = 'Use chrome, login then insert your security key and touch it' . "\n>>>Enter \"OK\" after confirmation on device";
            $this->exts->two_factor_notif_msg_de = '[Chrome] Stecken Sie den Sicherheitsschlussel in den USB-Anschluss Ihres Computers ein. Wenn er eine Taste hat, tippen Sie darauf.' . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->fillGoogleTwoFactor(null, null, '');
            sleep(5);
            // choose another option: #assistActionIdk
        } else if ($this->exts->exists('input[name="secretQuestionResponse"]')) {
            $input_selector = 'input[name="secretQuestionResponse"]';
            $message_selector = 'form > span > section > div > div > div:first-child';
            $submit_selector = '[data-secondary-action-label] > div > div:nth-child(1) button';
            $this->exts->two_factor_attempts = 0;
            $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector);
        }
    }
    private function fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, $submit_by_enter = false)
    {
        $this->exts->log(__FUNCTION__);
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->querySelector($message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'text'));
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            if ($this->exts->two_factor_attempts > 1) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }
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
                $this->exts->log("fillTwoFactor: Entering two_factor_code: " . $two_factor_code);
                $this->exts->moveToElementAndType($input_selector, '');
                $this->exts->moveToElementAndType($input_selector, $two_factor_code);
                sleep(2);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                if ($this->exts->exists($submit_selector)) {
                    $this->exts->log("fillTwoFactor: Clicking submit button.");
                    $this->exts->click_by_xdotool($submit_selector);
                } else if ($submit_by_enter) {
                    $this->exts->type_key_by_xdotool('Return');
                }
                sleep(10);
                $this->exts->capture("2.2-two-factor-submitted-" . $this->exts->two_factor_attempts);
                if ($this->exts->querySelector($input_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else {
                    if ($this->exts->two_factor_attempts < 3) {
                        $this->exts->notification_uid = '';
                        $this->exts->two_factor_attempts++;
                        if ($this->exts->exists('form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin')) {
                            // if(strpos(strtoupper($this->exts->extract('div:last-child[style*="visibility: visible;"] [role="button"]')), 'CODE') !== false){
                            $this->exts->click_by_xdotool('[aria-relevant="additions"] + [style*="visibility: visible;"] [role="button"]');
                            sleep(2);
                            $this->exts->capture("2.2-two-factor-resend-code-" . $this->exts->two_factor_attempts);
                            // }
                        }

                        $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector);
                    } else {
                        $this->exts->log("Two factor can not solved");
                    }
                }
            } else {
                $this->exts->log("Not found two factor input");
            }
        } else {
            $this->exts->log("Not received two factor code");
        }
    }
    // -------------------- GOOGLE login END


    // ==================================BEGIN LOGIN WITH APPLE==================================
    public $apple_username_selector = 'input#account_name_text_field';
    public $apple_password_selector = '#stepEl:not(.hide) .password:not([aria-hidden="true"]) input#password_text_field';
    public $apple_submit_login_selector = 'button#sign-in';
    private function loginAppleIfRequired()
    {
        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->urlContains('apple.com/auth/authorize')) {
            $this->checkFillAppleLogin();
            sleep(1);
            $this->exts->switchToDefault();
            if ($this->exts->exists('iframe[name="aid-auth-widget"]')) {
                $this->switchToFrame('iframe[name="aid-auth-widget"]');
            }
            if ($this->exts->exists('.signin-error #errMsg + a')) {
                $this->exts->loginFailure(1);
            } else if ($this->exts->exists('iframe[src*="/account/repair"], repair-missing-items, button[id*="unlock-account-"]')) {
                $this->exts->account_not_ready();
            }

            $this->exts->switchToDefault();
            $this->checkFillAppleTwoFactor();
            $this->exts->switchToDefault();
            if ($this->exts->exists('iframe[src*="/account/repair"]')) {
                $this->switchToFrame('iframe[src*="/account/repair"]');
                // If 2FA setting up page showed, click to cancel
                if ($this->exts->allExists(['.idms-step-content .icon-2fa', 'button.button-secondary.nav-cancel'])) {
                    // Click "Other Option"
                    $this->exts->moveToElementAndClick('button.button-secondary.nav-cancel');
                    sleep(5);
                    // Click "Dont upgrade"
                    $this->exts->moveToElementAndClick('.confirmCancelContainer.fade-in button.button-secondary.nav-cancel');
                    sleep(15);
                }
                $this->exts->switchToDefault();
            }

            // Click to accept consent temps, Must go inside 2 frame
            if ($this->exts->exists('iframe#aid-auth-widget-iFrame')) {
                $this->switchToFrame('iframe#aid-auth-widget-iFrame');
            }
            if ($this->exts->exists('iframe[src*="/account/repair"]')) {
                $this->switchToFrame('iframe[src*="/account/repair"]');
                // If 2FA setting up page showed, click to cancel
                if ($this->exts->allExists(['.idms-step-content .icon-2fa', 'button.button-secondary.nav-cancel'])) {
                    // Click "Other Option"
                    $this->exts->moveToElementAndClick('button.button-secondary.nav-cancel');
                    sleep(5);
                    // Click "Dont upgrade"
                    $this->exts->moveToElementAndClick('.confirmCancelContainer.fade-in button.button-secondary.nav-cancel');
                    sleep(15);
                }
                $this->exts->switchToDefault();
            }
            if ($this->exts->exists('.privacy-consent.fade-in button.nav-action')) {
                $this->exts->moveToElementAndClick('.privacy-consent.fade-in button.nav-action');
                sleep(15);
            }
            // end accept consent
            $this->exts->capture("3-apple-before-back-to-main-tab");
            $this->exts->switchToInitTab();
        }
    }
    private function checkFillAppleLogin()
    {
        $this->switchToFrame('iframe[name="aid-auth-widget"]');
        $this->exts->capture("2-apple_login-page");
        if ($this->exts->getElement($this->apple_username_selector) != null) {
            sleep(1);
            $this->exts->log("Enter apple_ Username");
            $this->exts->moveToElementAndType($this->apple_username_selector, $this->username);
            sleep(1);
            $this->exts->moveToElementAndClick($this->apple_submit_login_selector);
            sleep(10);
            if ($this->exts->exists('button#continue-password')) {
                $this->exts->moveToElementAndClick('button#continue-password');
                sleep(2);
            }
        }

        if ($this->exts->getElement($this->apple_password_selector) != null) {
            $this->exts->log("Enter apple_ Password");
            $this->exts->moveToElementAndType($this->apple_password_selector, $this->password);
            sleep(1);
            if ($this->exts->exists('#remember-me:not(:checked)')) {
                $this->exts->moveToElementAndClick('label#remember-me-label');
                // sleep(2);
            }
            $this->exts->capture("2-apple_login-page-filled");
            // $this->exts->webdriver->getKeyboard()->pressKey(WebDriverKeys::ENTER);
            $this->exts->moveToElementAndClick($this->apple_submit_login_selector);
            sleep(2);

            $this->exts->capture("2-apple_after-login-submit");
            $this->exts->switchToDefault();

            $this->exts->log(count($this->exts->getElements('iframe[name="aid-auth-widget"]')));
            $this->switchToFrame('iframe[name="aid-auth-widget"]');
            sleep(1);

            if ($this->exts->exists('iframe[src*="/account/repair"]')) {
                $this->switchToFrame('iframe[src*="/account/repair"]');
                // If 2FA setting up page showed, click to cancel
                if ($this->exts->allExists(['.idms-step-content .icon-2fa', 'button.button-secondary.nav-cancel'])) {
                    // Click "Other Option"
                    $this->exts->moveToElementAndClick('button.button-secondary.nav-cancel');
                    sleep(5);
                    // Click "Dont upgrade"
                    $this->exts->moveToElementAndClick('.confirmCancelContainer.fade-in button.button-secondary.nav-cancel');
                    sleep(15);
                }
                $this->exts->switchToDefault();
            }

            $this->exts->log(count($this->exts->getElements('iframe[name="aid-auth-widget"]')));
            $this->switchToFrame('iframe[name="aid-auth-widget"]');
            sleep(1);
            if ($this->exts->getElement($this->apple_password_selector) != null && !$this->exts->exists('.signin-error #errMsg + a')) {
                $this->exts->log("Re-enter apple_ Password");
                $this->exts->moveToElementAndType($this->apple_password_selector, $this->password);
                sleep(1);
                $this->exts->capture("2-re-enter-apple_password");
                $this->exts->type_key_by_xdotool('Return');
            }
        } else {
            $this->exts->capture("2-apple_login-page-not-found");
        }
    }
    private function checkFillAppleTwoFactor()
    {
        $this->switchToFrame('#aid-auth-widget-iFrame');
        if ($this->exts->exists('.devices [role="list"] [role="button"][device-id]')) {
            $this->exts->moveToElementAndClick('.devices [role="list"] [role="button"][device-id]');
            sleep(5);
        }
        if ($this->exts->exists('div#stepEl div.phones div[class*="si-phone-name"]')) {
            $this->exts->log("Choose apple Phone");
            $this->exts->moveToElementAndClick('div#stepEl div.phones div[class*="si-phone-name"]');
            sleep(5);
        }
        if ($this->exts->getElement('input[id^="char"]') != null) {
            $this->exts->two_factor_notif_title_en = 'Apple login for ' . $this->exts->two_factor_notif_title_en;
            $this->exts->two_factor_notif_title_de = 'Apple login fur ' . $this->exts->two_factor_notif_title_de;

            $this->exts->log("Current apple URL - " . $this->exts->webdriver->getCurrentUrl());
            $this->exts->log("Two apple factor page found.");
            $this->exts->capture("2.1-apple-two-factor");

            if ($this->exts->getElement('.verify-code .si-info, .verify-phone .si-info, .si-info') != null) {
                $this->exts->two_factor_notif_msg_en = trim($this->exts->getElement('.verify-code .si-info, .verify-phone .si-info, .si-info')->getAttribute('innerText'));
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            }
            if ($this->exts->two_factor_attempts > 1) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }
            $this->exts->log("apple Message:\n" . $this->exts->two_factor_notif_msg_en);
            if ($this->exts->two_factor_attempts > 1) {
                $this->exts->moveToElementAndClick('.verify-device a#no-trstd-device-pop, .verify-phone a#didnt-get-code, a#didnt-get-code, a#no-trstd-device-pop');
                sleep(1);

                $this->exts->moveToElementAndClick('.verify-device .try-again a#try-again-link, .verify-phone a#try-again-link, .try-again a#try-again-link');
            }

            $two_factor_code = $this->exts->fetchTwoFactorCode();
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log(__FUNCTION__ . ": Entering apple two_factor_code." . $two_factor_code);
                $resultCodes = str_split($two_factor_code);
                $code_inputs = $this->exts->getElements('input[id^="char"]');
                foreach ($code_inputs as $key => $code_input) {
                    if (array_key_exists($key, $resultCodes)) {
                        $this->exts->log(__FUNCTION__ . ': Entering apple key ' . $resultCodes[$key] . 'to input #' . $code_input->getAttribute('id'));
                        $code_input->sendKeys($resultCodes[$key]);
                        $this->exts->capture("2.2-apple-two-factor-filled-" . $this->exts->two_factor_attempts);
                    } else {
                        $this->exts->log(__FUNCTION__ . ': Have no char for input #' . $code_input->getAttribute('id'));
                    }
                }
                sleep(15);
                $this->exts->capture("2.2-apple-two-factor-submitted-" . $this->exts->two_factor_attempts);
                $this->switchToFrame('#aid-auth-widget-iFrame');

                if ($this->exts->getElement('input[id^="char"]') != null && $this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->exts->notification_uid = "";

                    $this->checkFillAppleTwoFactor();
                }

                if ($this->exts->exists('.button-bar button:last-child[id*="trust-browser-"]')) {
                    $this->exts->moveToElementAndClick('.button-bar button:last-child[id*="trust-browser-"]');
                    sleep(10);
                }
            } else {
                $this->exts->log("Not received apple two factor code");
            }
        }
    }
    public function switchToFrame($query_string)
    {
        $this->exts->log(__FUNCTION__ . " Begin with " . $query_string);
        $frame = null;
        if (is_string($query_string)) {
            $frame = $this->exts->queryElement($query_string);
        }

        if ($frame != null) {
            $frame_context = $this->exts->get_frame_excutable_context($frame);
            if ($frame_context != null) {
                $this->exts->current_context = $frame_context;
                return true;
            }
        } else {
            $this->exts->log(__FUNCTION__ . " Frame not found " . $query_string);
        }

        return false;
    }
    // ==================================END LOGIN WITH APPLE==================================

    private function invoicePage()
    {
        if ($this->exts->exists('button[data-test-id="accept-cookies"]')) {
            $this->exts->moveToElementAndClick('button[data-test-id="accept-cookies"]');
            sleep(1);
        }
        $accounts_ids = [];
        if ($this->exts->exists('table tbody tr td:nth-child(1) a')) {
            $rows = $this->exts->querySelectorAll('table tbody tr td:nth-child(1) a');
            foreach ($rows as $row) {
                $accountUrl = $row->getAttribute('href');
                $this->exts->log('URL - ' . $accountUrl);
                $tempArr = explode('/', $accountUrl);
                $accounts_ids[] = trim(end($tempArr));
            }
        } else {
            $accounts_ids = $this->exts->executeSafeScript('
var arr = [];
var items = window.__INIT__["data.accounts.items"];
for (var i = 0; i < items.length; i++){
    console.log(items[i].id);
    arr.push(items[i].id);
};
return arr;');
        }
        $this->exts->log('ACCOUNTS FOUND: ' . count($accounts_ids));

        $csrf_token = $this->exts->executeSafeScript('window.__INIT__["app.currentAccount"].csrf_token;');

        foreach ($accounts_ids as $key => $account_id) {
            $this->exts->log(":::::::csrf_token=" . $csrf_token . ":::::::account_id=" . $account_id);
            $accountUrl = 'https://manychat.com/' . $account_id . '/settings#billing';
            $this->exts->log('Account URL - ' . $accountUrl);
            $this->exts->openUrl($accountUrl);
            sleep(5);
            $this->processInvoices($csrf_token, $account_id);
        }
    }

    private function processInvoices($csrf_token, $account_id)
    {
        $total_invoices = 0;
        sleep(10);
        $this->exts->log(__FUNCTION__ . '-' . $account_id);
        $this->exts->capture(__FUNCTION__ . '-' . $account_id);

        if ($this->exts->exists('#wootric-close')) {
            $this->exts->moveToElementAndClick('#wootric-close');
            sleep(1);
        }

        $invoices = [];

        // display invoice div table (click View Billing History)
        $bill_history = $this->exts->getElement('//a[@data-test-id="edit-invoice-btn"]//following-sibling::a', null, 'xpath');
        $this->exts->click_element($bill_history);
        sleep(5);
        $this->exts->capture(__FUNCTION__ . '-' . $account_id . '-invoices');

        $rows = $this->exts->querySelectorAll('div[open] a[href*="/billing/viewInvoice?invoice_id="]');
        foreach ($rows as $row) {
            $invoiceUrl = $row->getAttribute("href");
            $invoiceName = $account_id . '_' . end(explode('?invoice_id=', $invoiceUrl));
            $invoiceDate = '';
            $invoiceAmount = '';

            array_push($invoices, array(
                'invoiceName' => $invoiceName,
                'invoiceDate' => $invoiceDate,
                'invoiceAmount' => $invoiceAmount,
                'invoiceUrl' => $invoiceUrl
            ));
            $this->isNoInvoice = false;
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            if ($this->restrictPages != 0 && $total_invoices >= 100) break;
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : "";
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $downloaded_file = $this->exts->download_capture($invoice['invoiceUrl'], $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
                $total_invoices++;
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }
}

$portal = new PortalScriptCDP("optimized-chrome-v2", 'LR', '2673461', 'aW5mb0Bway1rb2VuaWcuZGU=', 'S2F0cmluMTgxMDM2');
$portal->run();
