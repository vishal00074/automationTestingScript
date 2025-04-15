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

    // Server-Portal-ID: 7299 - Last modified: 02.04.2025 14:37:23 UTC - User: 1

    public $baseUrl = "https://www.canva.com/?continue_in_browser=true";
    public $loginUrl = "https://www.canva.com/en/login/";
    public $homePageUrl = "https://www.canva.com/en";
    public $billingUrl = 'https://www.canva.com/account/billing';
    public $username_selector = 'input[name="email"]';
    public $password_selector = 'input[type="password"]';
    public $check_login_success_selector = 'aside a[href*="/folder/trash"], header a[href*="/settings"], a[href="/folder/trash"], a[href="/projects"], button span[style*="avatars/users"]';

    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */

    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->baseUrl);
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->loginUrl);
        $this->exts->waitTillPresent("//a[text()='Continue in browser instead']", 20);
        $this->exts->type_key_by_xdotool('Return');
        sleep(2);
        $this->exts->type_key_by_xdotool('Return');
        sleep(5);
        if ($this->exts->exists("//a[text()='Continue in browser instead']")) {
            $this->exts->log("Clicking Continue in browser instead 1");
            $this->exts->click_element("//a[text()='Continue in browser instead']");
            $this->exts->type_key_by_xdotool('Return');
        }
        $this->check_solve_cloudflare_page();
        $this->exts->capture('1-init-page');
        for ($i = 0; $i < 2 && $this->exts->exists('div.spacer:not([style="display: none; visibility: hidden;"]) div div'); $i++) {
            $this->clearChrome();
            $this->exts->openUrl($this->loginUrl);
            $this->exts->waitTillPresent("//a[text()='Continue in browser instead']", 20);
            $this->exts->type_key_by_xdotool('Return');
            sleep(2);
            $this->exts->type_key_by_xdotool('Return');
            sleep(5);
            if ($this->exts->exists("//a[text()='Continue in browser instead']")) {
                $this->exts->log("Clicking Continue in browser instead 2");
                $this->exts->click_element("//a[text()='Continue in browser instead']");
                $this->exts->type_key_by_xdotool('Return');
            }
            $this->check_solve_cloudflare_page();
            $this->exts->capture('1.1-init-page');
        }

        if ($this->exts->exists('//button//*[text()="Accept all cookies" or text()="Akzeptiere alle Cookies" or text()="Got it" or text()="Alle cookies accepteren"]/..')) {
            $this->exts->click_element('//button//*[text()="Accept all cookies" or text()="Akzeptiere alle Cookies" or text()="Got it" or text()="Alle cookies accepteren"]/..');
        }

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        $this->exts->waitTillPresent($this->check_login_success_selector, 30);
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');

            $continueBtnSelector = "//button[.//text()[contains(.,'continue with email') or contains(.,'Continue another way') or contains(.,'Continue with another account') or contains(.,'Continue with email')]]";

            $this->exts->waitTillPresent($continueBtnSelector);
            $this->exts->click_element($continueBtnSelector);

            sleep(3);
            if ($this->exts->exists($continueBtnSelector)) {
                $this->exts->click_element($continueBtnSelector);
            }
            // $this->exts->clearCookies();
            $login_with_facebook = isset($this->exts->config_array["login_with_facebook"]) ? (int) $this->exts->config_array["login_with_facebook"] : '0';

            // Hardcoded for testing
            $LoginWithGoogle = isset($this->exts->config_array["login_with_google"]) ? trim($this->exts->config_array["login_with_google"]) : '0';
            // $LoginWithGoogle = 1;

            if ($login_with_facebook == 1) {
                $this->exts->openUrl($this->loginUrl);
                sleep(15);
                $this->check_solve_cloudflare_page();
                $this->exts->click_element('//button//*[contains(text(), "Facebook")]');
                sleep(10);
                $facebook_login_tab = $this->exts->findTabMatchedUrl(['.facebook.com']);
                if ($facebook_login_tab != null) {
                    $this->exts->switchToTab($facebook_login_tab);
                }
                $this->loginFacebookIfRequired();
                if ($facebook_login_tab != null) {
                    $this->exts->closeTab($facebook_login_tab);
                }
            } else if ($LoginWithGoogle == 1) {
                $this->exts->openUrl($this->loginUrl);
                sleep(15);
                $this->check_solve_cloudflare_page();
                $this->exts->waitTillPresent('//div[@data-testid="auth_panel"]//button//span[text()="Continue"]');
                if ($this->exts->exists('//div[@data-testid="auth_panel"]//button//span[text()="Continue"]')) {
                    $this->exts->click_element('//div[@data-testid="auth_panel"]//button//span[text()="Continue"]');
                }
                $this->exts->click_element('//button//*[contains(text(), "Google")]');
                sleep(10);
                $google_login_tab = $this->exts->findTabMatchedUrl(['.google.com']);
                if ($google_login_tab != null) {
                    $this->exts->switchToTab($google_login_tab);
                }
                $this->loginGoogleIfRequired();
                sleep(10);
                if ($google_login_tab != null) {
                    $this->exts->closeTab($google_login_tab);
                }
            } else {
                // $this->exts->openUrl($this->loginUrl);
                // sleep(15);
                $this->check_solve_cloudflare_page();
                $this->check_solve_cloudflare_page();
                if ($this->exts->exists('//button//*[text()="Accept all cookies" or text()="Akzeptiere alle Cookies" or text()="Got it" or text()="Alle cookies accepteren"]/..')) {
                    $this->exts->click_element('//button//*[text()="Accept all cookies" or text()="Akzeptiere alle Cookies" or text()="Got it" or text()="Alle cookies accepteren"]/..');
                }
                $this->exts->waitTillPresent("//button[.//text()[contains(.,'continue with email') or contains(.,'Continue another way') or contains(.,'Continue with another account') or contains(.,'Continue with email')]]");
                $this->exts->click_element("//button[.//text()[contains(.,'continue with email') or contains(.,'Continue another way') or contains(.,'Continue with another account') or contains(.,'Continue with email')]]");
                $login_with_email_button = $this->exts->getElementByText('button', ['continue with email', 'Continue another way', 'Continue with another account', 'Continue with email'], null, false);
                if ($login_with_email_button != null) {
                    $this->exts->click_element($login_with_email_button);
                    sleep(3);
                    // Sometimes again one of the below options comes on screen

                    $login_with_email_button = $this->exts->getElementByText('button', ['continue with email', 'Continue another way', 'Continue with another account', 'Continue with email'], null, false);
                    if ($login_with_email_button != null) {
                        $this->exts->click_element($login_with_email_button);
                    }
                } else if ($this->exts->getElement('//li//*[contains(@style, "/avatars/")]/../../..', null, 'xpath') != null) {
                    $this->exts->click_element('//li//*[contains(@style, "/avatars/")]/../../..');
                }

                $this->check_solve_cloudflare_page();
                $this->checkFillLogin();
                sleep(7);
                if ($this->exts->exists('div[style*="visibility: visible"] > div > [title*="recaptcha challenge"]') && $this->exts->exists($this->password_selector)) {
                    $this->checkFillLogin();
                    sleep(7);
                }
                $this->checkFillTwoFactor();
            }
            sleep(25);
            $this->checkFillLogin();
            //click restore
            $this->exts->type_key_by_xdotool('Return');
            sleep(2);
            $this->exts->type_key_by_xdotool('Return');
            sleep(5);
            if ($this->exts->exists("//a[text()='Continue in browser instead']")) {
                $this->exts->log("Clicking Continue in browser instead 3");
                $this->exts->click_element("//a[text()='Continue in browser instead']");
                $this->exts->type_key_by_xdotool('Return');
                sleep(15);
            }
            $tab_buttons = $this->exts->getElements('[role="region"] button');
            foreach ($tab_buttons as $key => $tab_button) {
                $tab_name = trim($tab_button->getAttribute('innerText'));
                if (stripos($tab_name, 'Wiederherstellen') !== false || stripos($tab_name, 'Restore') !== false) {
                    $tab_button->click();
                    sleep(20);
                    break;
                }
            }
        }
        sleep(2);
        $continue_webapp = $this->exts->getElementByText('a[role="button"]', ['daarvan verder in de browser', 'Im Browser fortfahren'], null, false);
        if ($continue_webapp != null) {
            $this->exts->click_element($continue_webapp);
            sleep(20);
        }

        // Close Launch Event Popup
        sleep(15);
        if ($this->exts->exists('button[aria-label="Close"]')) {
            $this->exts->click_element('button[aria-label="Close"]');
        }

        if ($this->exts->urlContains('search')) {
            $this->exts->openUrl('https://www.canva.com/?continue_in_browser=true');
        }
        // wait for user logging in
        for ($wait_count = 1; $wait_count <= 5 && !$this->exts->exists($this->check_login_success_selector); $wait_count++) {
            $this->exts->log('Waiting for login...');
            sleep(5);
        }

        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            // $this->exts->openUrl($this->billingUrl);
            // sleep(15);
            // $multi_languages_view_invoice = ['View invoices', 'Rechnungen anzeigen', 'Voir les factures', 'Ver facturas', 'Visualizza fatture', 'Facturen weergeven'];
            // $view_invoice_button = $this->exts->getElementByText('section a', $multi_languages_view_invoice);
            // if($view_invoice_button == null) {
            //  $this->exts->moveToElementAndClick('a[href*="/billing-and-teams"]');
            //  $view_invoice_button = $this->exts->getElementByText('section a', $multi_languages_view_invoice);
            // }
            // $this->exts->click_element($view_invoice_button);
            $this->exts->openUrl('https://www.canva.com/settings/purchase-history');
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            if ($this->exts->getElementByText('[role="alert"]', ["Wir konnten kein Konto mit der von dir eingegebenen E-Mail-Adresse finden", "We couldn't find an account with the email you entered", "Das eingegebene Passwort ist nicht korrekt", "password you entered is incorrect", "No account with that email"], null, false) != null || $this->exts->exists('input[aria-invalid="true"][name="email"]')) {
                $this->exts->loginFailure(1);
            }
            if ($this->exts->getElementByText('button[type="submit"]', ['Create account', 'Konto erstellen'], null, false) != null) {
                $this->exts->loginFailure(1);
            }
            if ($this->exts->getElementByText('h3', ['Create your account', 'Dein Konto erstellen'], null, false) != null) {
                $this->exts->loginFailure(1);
            }
            if ($this->exts->urlContains('login/reset')) {
                $this->exts->account_not_ready();
            }
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->loginFailure();
        }
    }
    private function check_solve_cloudflare_page()
    {
        $unsolved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) <= 0]';
        $solved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) > 0]';
        $this->exts->capture("cloudflare-checking");
        if (
            !$this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath]) &&
            $this->exts->exists('#cf-please-wait > p:not([style*="display: none"]):not([style*="display:none"])')
        ) {
            for ($waiting = 0; $waiting < 10; $waiting++) {
                sleep(2);
                if ($this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath])) {
                    sleep(3);
                    break;
                }
            }
        }

        if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
            $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
            sleep(5);
            $this->exts->capture("cloudflare-clicked-1", true);
            sleep(3);
            if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
                $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
                sleep(5);
                $this->exts->capture("cloudflare-clicked-2", true);
                sleep(15);
            }
            if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
                $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
                sleep(5);
                $this->exts->capture("cloudflare-clicked-3", true);
                sleep(15);
            }
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
                $recaptcha_textareas = $this->exts->getElements($recaptcha_textarea_selector);
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
                    // $this->exts->executeSafeScript($gcallbackFunction.'("'.$this->exts->recaptcha_answer.'");');
                    sleep(10);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
        }
    }
    private function checkFillLogin()
    {
        sleep(5);
        $this->exts->capture("2-login-page");
        $this->exts->waitTillPresent('//div[@data-testid="auth_panel"]//button//span[text()="Continue"]');
        if (
            $this->exts->exists('//div[@data-testid="auth_panel"]//button//span[text()="Continue"]') && !$this->exts->exists($this->username_selector)
            && !$this->exts->exists('form[method="post"] input[inputmode="numeric"]')
        ) {
            $this->exts->click_element('//div[@data-testid="auth_panel"]//button//span[text()="Continue"]');
            $this->exts->waitTillPresent($this->password_selector);
            $this->exts->click_by_xdotool($this->password_selector);
            sleep(1);
            $this->exts->type_text_by_xdotool($this->password);
            sleep(1);
            $this->exts->click_by_xdotool('form button[type="submit"]');
        }
        if ($this->exts->exists($this->username_selector)) {
            sleep(3);
            $this->exts->log("Enter Username");
            $this->exts->click_by_xdotool($this->username_selector);
            $this->exts->type_key_by_xdotool("Ctrl+a");
            $this->exts->type_key_by_xdotool("Delete");
            $this->exts->type_text_by_xdotool($this->username);
            sleep(3);
            $this->exts->capture("2-username-filled");
            //$this->checkFillRecaptcha();
            $this->exts->click_by_xdotool('form button[type="submit"]');
            sleep(5);
        }

        if ($this->exts->exists($this->password_selector)) {
            $this->exts->log("Enter Password");
            $this->exts->click_by_xdotool($this->password_selector);
            sleep(1);
            $this->exts->type_key_by_xdotool("Ctrl+a");
            $this->exts->type_key_by_xdotool("Delete");
            $this->exts->type_text_by_xdotool($this->password);
            sleep(1);

            $this->exts->capture("2-password-filled");
            //$this->checkFillRecaptcha();
            $this->exts->click_by_xdotool('form button[type="submit"]');
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'form[method="post"] input[inputmode="numeric"]';
        $two_factor_message_selector = 'section div h1, section div h2';
        $two_factor_submit_selector = 'form[method="post"] button';

        $this->exts->waitTillPresent($two_factor_selector, 10);
        if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");
            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = $this->exts->extract($two_factor_message_selector);
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
                $this->exts->type_key_by_xdotool("Return");
                sleep(2);
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
                sleep(2);

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);


                $this->exts->click_by_xdotool($two_factor_submit_selector);
                sleep(15);
                if ($this->exts->querySelector($two_factor_selector) == null) {
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
            if (stripos($this->exts->extract('#login_form #error_box'), 'Wrong credentials') !== false) {
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
    private function clearChrome()
    {
        $this->exts->log("Clearing browser history, cookie, cache");
        $this->exts->openUrl('chrome://settings/clearBrowserData');
        sleep(10);
        $this->exts->execute_javascript('document.querySelector("settings-ui").shadowRoot.querySelector("settings-main").shadowRoot.querySelector("settings-basic-page").shadowRoot.querySelector("settings-privacy-page").shadowRoot.querySelector("settings-clear-browsing-data-dialog").shadowRoot.querySelector("#pages").querySelector("#clearFromBasic").shadowRoot.querySelector("#dropdownMenu").value = 4;');
        sleep(1);
        $this->exts->capture("clear-page");
        $this->exts->execute_javascript('document.querySelector("settings-ui").shadowRoot.querySelector("settings-main").shadowRoot.querySelector("settings-basic-page").shadowRoot.querySelector("settings-privacy-page").shadowRoot.querySelector("settings-clear-browsing-data-dialog").shadowRoot.querySelector("#clearButton").click();');
        sleep(15);
        $this->exts->capture("after-clear");
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
            sleep(10);
            if ($this->exts->exists('input[name="pass"]') && $this->exts->getElement($this->facebook_username_selector) == null) {
                $this->exts->moveToElementAndType('input[name="pass"]', $this->password);
                sleep(1);
                $this->exts->moveToElementAndClick('form[action*="login"] input[type="submit"]');
                sleep(5);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-facebook-login-page-not-found");
        }
    }
    private function checkFillFacebookTwoFactor()
    {
        $facebook_two_factor_selector = 'form.checkpoint[action*="/checkpoint"] input[name*="captcha_response"], form.checkpoint[action*="/checkpoint"] input[name*="approvals_code"], input#recovery_code_entry';
        $facebook_two_factor_message_selector = 'form.checkpoint[action*="/checkpoint"] > div > div:nth-child(2), form.checkpoint[action*="/checkpoint"] strong + div';
        $facebook_two_factor_submit_selector = 'button#checkpointSubmitButton, form[action*="/recover/code"] div.uiInterstitialBar button, form[action*="/recover/code"] button[type="submit"]';

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
            if ($this->exts->exists("//button[.//span[contains(text(), 'Continue') or contains(text(), 'Further') or contains(text(), 'Weiter')]]")) {
                $this->exts->click_element("//button[.//span[contains(text(), 'Continue') or contains(text(), 'Further') or contains(text(), 'Weiter')]]");
                sleep(10);
                $tabs = $this->exts->get_all_tabs();
                if (count($tabs) == 1) {
                    $this->exts->switchToInitTab();
                    sleep(3);
                }
            }
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
                $this->exts->querySelector('input[name="password"][aria-invalid="true"], input[name="identifier"][aria-invalid="true"], input[aria-invalid="true"][name="Passwd"], input[tyoe="email"][aria-invalid="true"]') != null
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
            $this->exts->type_key_by_xdotool('Return');
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
            } else if ($this->exts->exists('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])')) {
                // We second RECOMMEND method type = 9 is get code from SMS
                $this->exts->click_by_xdotool('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"]:not([data-challengeunavailable="true"])')) {
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

    private function processInvoices()
    {
        $this->exts->waitTillPresent('table > tbody > tr a[href*="/invoices/"]');
        $this->exts->capture("4-invoices-page");

        $invoices = [];
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
        $multi_languages_show_more = ['Show more', 'Zeig mehr', 'Montre plus', 'Laat meer zien'];
        $view_invoice_button = $this->exts->getElementByText('main section button', $multi_languages_show_more);
        if ($restrictPages == 0) {
            $maxAttempts = 50;
        } else {
            $maxAttempts = $restrictPages;
        }
        for ($paging_count = 0; ($paging_count < $maxAttempts && $view_invoice_button != null); $paging_count++) {
            $this->exts->execute_javascript("arguments[0].click();", [$view_invoice_button]);

            sleep(8);
            $view_invoice_button = $this->exts->getElementByText('main section button', $multi_languages_show_more);
        }

        $rows = count($this->exts->getElements('table > tbody > tr'));
        for ($i = 0; $i < $rows; $i++) {
            $row = $this->exts->getElements('table > tbody > tr')[$i];
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 2 && $this->exts->getElement('a[href*="/invoices/"]', $row) != null) {
                $invoiceUrl = $this->exts->getElement('a[href*="/invoices/"]', $row)->getAttribute('href');
                $invoiceName = explode(
                    '/',
                    array_pop(explode('/invoices/', $invoiceUrl))
                )[0];
                $invoiceDate = '';
                $invoiceAmount = '';
                $amountText = '';

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl
                ));
                $this->isNoInvoice = false;
            }
        }
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------URL------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf': '';
            if ($this->exts->invoice_exists($invoice['invoiceName'])) {
                $this->exts->log('Invoice existed ' . $invoiceFileName);
            } else {
                $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['invoiceName'], '', $invoice['invoiceAmount'], $downloaded_file);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
