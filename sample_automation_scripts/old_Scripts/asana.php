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

    // Server-Portal-ID: 81812 - Last modified: 11.08.2025 15:13:54 UTC - User: 1

    // Start Script 

    public $baseUrl = "https://app.asana.com/";
    public $username_selector = "input#email_input, form input.LoginContent-emailInput, input.LoginEmailPasswordForm-emailInput, .LoginEmailForm-emailInput";
    public $password_selector = "input#password_input, form input.LoginContent-passwordInput, input.LoginEmailPasswordForm-passwordInput, .LoginPasswordForm-passwordInput";
    public $isNoInvoice = true;
    public $invoicePageUrl = 'https://app.asana.com/admin/billing';
    public $check_login_success_selector = 'form[action="/app/asana/-/logout"]';
    public $invoiceCount = 0;
    public $terminateLoop = false;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */

    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $LoginWithGoogle = isset($this->exts->config_array["login_with_google"]) ? (int) @$this->exts->config_array["login_with_google"] : 0;
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->baseUrl);
        sleep(2);
        $this->exts->capture('1-init-page');
        if (!$this->checkLogin()) {
            if ((int) @$LoginWithGoogle == 1) {
                $this->exts->moveToElementAndClick('div.google-auth-button, div.GoogleSignInButton');
                $this->loginGoogleIfRequired();
            } else {
                $this->fillForm();
                sleep(2);
                $this->checkFillTwoFactor();
                sleep(2);
            }
        }

        if ($this->exts->execute_javascript('return document.body.innerHTML.indexOf("login-error-verification-email-sent") > -1;')) {
            $this->exts->clearCookies();
            $this->exts->openUrl($this->baseUrl);
            sleep(2);
            $this->fillForm();
            sleep(2);
            $this->checkFillTwoFactor();
            sleep(2);
        }
        if ($this->exts->exists('.LoginPasswordForm-captchaCheckbox iframe[src*="/recaptcha/enterprise/anchor?"]') && !$this->checkLogin() && !$this->exts->exists('div.LoginCardLayout span.MessageBanner--error')) {
            $this->exts->clearCookies();
            $this->exts->openUrl($this->baseUrl);
            $this->fillForm();
            sleep(5);
            $this->checkFillTwoFactor();
            sleep(5);
        }

        sleep(2);
        $this->exts->waitTillPresent('.GlobalTopbarStructure-rightChildren .ButtonThemeablePresentation--isEnabled', 10);
        if ($this->exts->exists('.GlobalTopbarStructure-rightChildren .ButtonThemeablePresentation--isEnabled')) {
            $this->exts->click_element('.GlobalTopbarStructure-rightChildren .ButtonThemeablePresentation--isEnabled');
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->invoicePage();
        } else {
            if ($this->exts->execute_javascript('return document.body.innerHTML.indexOf("login-error-verification-email-sent") > -1;')) {
                $this->exts->account_not_ready();
            }
            if (stripos($this->exts->extract('div.LoginCardLayout span.MessageBanner--error', null, 'text'), 'password is invalid') !== false) {
                $this->exts->loginFailure(1);
            } else if (
                stripos($this->exts->extract('.MessageBanner--warning'), 'Aucun compte Asana ') !== false ||
                stripos($this->exts->extract('.MessageBanner--warning'), 't an Asana account associated with') !== false ||
                stripos($this->exts->extract('.MessageBanner--warning'), 'ist kein Asana-Konto ve') !== false ||
                stripos($this->exts->extract('.MessageBanner-contents'), ' passwor') !== false
            ) {
                $this->exts->loginFailure(1);
            } else if (
                stripos($this->exts->extract('[role="banner"] div[aria-live="assertive"] > div'), 'Der von Ihnen eingegebene Code ist') !== false
            ) {
                $this->exts->loginFailure(1);
            } else if ($this->exts->exists('.SignupWithEmailButton') && !$this->exts->exists($this->password_selector)) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function fillForm()
    {
        $this->exts->capture("1-pre-login");
        $this->exts->waitTillPresent($this->username_selector);
        if ($this->exts->exists($this->username_selector)) {
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);
            $this->exts->capture("1-username-filled");
            if (!$this->exts->exists($this->password_selector)) {
                $this->exts->moveToElementAndClick('.LoginEmailForm-continueButton');
                sleep(2);
            }
        }
        $this->exts->waitTillPresent($this->password_selector);
        if ($this->exts->exists($this->password_selector)) {
            $this->checkFillRecaptcha();

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);
            $this->exts->capture("1-password-filled");
            $this->exts->capture("1-login-filled");
            $this->exts->moveToElementAndClick('.LoginPasswordForm-loginButton');
            sleep(2);
            $this->checkFillRecaptcha();
            $this->exts->moveToElementAndClick('.LoginPasswordForm-captchaCheckbox iframe[src*="/recaptcha/enterprise/anchor?"]');
            sleep(2);
            if ($this->exts->exists('.LoginPasswordForm-loginButton')) {
                $this->exts->moveToElementAndClick('.LoginPasswordForm-loginButton');
            }
        } else {
            $this->loginGoogleIfRequired();
        }
    }

    private function checkFillRecaptcha()
    {
        $this->exts->log(__FUNCTION__);
        $recaptcha_iframe_selector = '.LoginPasswordForm-captchaCheckbox iframe[src*="/recaptcha/enterprise/anchor?"]';
        $recaptcha_textarea_selector = '.LoginPasswordForm-captchaCheckbox textarea[name="g-recaptcha-response"]';
        $this->exts->waitTillPresent($recaptcha_iframe_selector);
        if ($this->exts->exists($recaptcha_iframe_selector)) {
            $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
            $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
            $this->exts->log("iframe url  - " . $iframeUrl);
            $this->exts->log("SiteKey - " . $data_siteKey);

            $isCaptchaSolved = $this->processRecaptcha($this->exts->getUrl(), $data_siteKey, false);
            $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);

            if ($isCaptchaSolved) {
                // Step 1 fill answer to textarea
                $this->exts->log(__FUNCTION__ . "::filling reCaptcha response..");
                $this->exts->log("SiteKey - " . $this->exts->recaptcha_answer);
                $recaptcha_textareas = $this->exts->getElements($recaptcha_textarea_selector);
                for ($i = 0; $i < count($recaptcha_textareas); $i++) {
                    $this->exts->execute_javascript("arguments[0].innerHTML = '" . $this->exts->recaptcha_answer . "';", [$recaptcha_textareas[$i]]);
                }
                sleep(2);
                $this->exts->capture('recaptcha-filled');

                // Step 2, check if callback function need executed
                $gcallbackFunction = $this->exts->execute_javascript('
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
                    $this->exts->execute_javascript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
                    sleep(10);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
        }
    }

    public function processRecaptcha($base_url, $google_key = '', $fill_answer = true)
    {
        $this->recaptcha_answer = '';
        // if (empty($google_key)) {
        //     / @var WebDriverElement $element /
        //     $element = $this->exts->getElement(".g-recaptcha");
        //     $google_key = $this->exts->getElAttribute($element, 'data-sitekey');
        // }

        $this->exts->log("--Google Re-Captcha--");
        if (!empty($this->exts->config_array['recaptcha_shell_script'])) {
            $cmd = $this->exts->config_array['recaptcha_shell_script'] . " --PROCESS_UID::" . $this->exts->process_uid . " --GOOGLE_KEY::" . urlencode($google_key) . "&enterprise=1'" . " --BASE_URL::" . urlencode($base_url);
            $this->exts->log('Executing command : ' . $cmd);
            exec($cmd, $output, $return_var);
            $this->exts->log('Command Result : ' . print_r($output, true));

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
                        $answer_filled = $this->exts->execute_javascript(
                            "document.getElementById(\"g-recaptcha-response\").innerHTML = arguments[0];return document.getElementById(\"g-recaptcha-response\").innerHTML;",
                            [$recaptcha_answer]
                        );
                        $this->exts->log("recaptcha answer filled - " . $answer_filled);
                    }

                    $this->exts->recaptcha_answer = $recaptcha_answer;

                    return true;
                }
            }
        }

        return false;
    }

    private function checkFillTwoFactor()
    {
        $two_factor_selector = '.TotpVerificationForm-totpInput';
        $two_factor_message_selector = '.TotpVerificationForm-description';
        $two_factor_submit_selector = '.TotpVerificationForm [role="button"]';
        $this->exts->waitTillPresent($two_factor_selector);
        if ($this->exts->getElement($two_factor_selector) != null) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->exists($two_factor_message_selector)) {
                $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($two_factor_message_selector));
                $this->exts->two_factor_notif_msg_en = explode("\n", $this->exts->two_factor_notif_msg_en)[0];
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
                sleep(15);

                if ($this->exts->getElement($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->exts->notification_uid = "";
                    $this->checkFillTwoFactor();
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        } else if ($this->exts->getElement('//*[contains(text(),"please use the magic link")]', null, 'xpath') != null) {

            $two_factor_message_selector = '//*[contains(text(),"please use the magic link")]';
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->getElement($two_factor_message_selector, null, 'xpath') != null) {
                $this->exts->two_factor_notif_msg_en = "";
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector, null, 'xpath')[0]->getAttribute('innerText');
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' Pls copy that link then paste here';
                $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
                $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            }
            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }

            $this->exts->notification_uid = '';
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Open url: ." . $two_factor_code);
                $this->exts->openUrl($two_factor_code);
                sleep(25);
                $this->exts->capture("after-open-url-two-factor");
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }


    // -------------------- GOOGLE login
    public $google_username_selector = 'input[name="identifier"]:not([aria-hidden="true"])';
    public $google_submit_username_selector = '#identifierNext, input#submit, input#next';
    public $google_password_selector = 'input[name="password"], input[name="Passwd"]';
    public $google_submit_password_selector = '#passwordNext, #gaia_loginform input#signIn, #passwordNext button, input#submit';
    private function loginGoogleIfRequired()
    {
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

            if ($this->exts->exists('#smsauth-interstitial-remindbutton')) {
                $this->exts->moveToElementAndClick('#smsauth-interstitial-remindbutton');
                sleep(10);
            }
            if ($this->exts->exists('#tos_form input#accept')) {
                $this->exts->moveToElementAndClick('#tos_form input#accept');
                sleep(10);
            }
            if ($this->exts->exists('[wizard-step-uid="RecoveryOptionsCollectionWizard:starter"] div:last-child > [role="button"]')) {
                $this->exts->moveToElementAndClick('[wizard-step-uid="RecoveryOptionsCollectionWizard:starter"] div:last-child > [role="button"]');
                sleep(10);
            }
            if ($this->exts->exists('.action-button.signin-button + a.setup-button[href*="/two-step-verification/"]')) {
                // SKIP setup 2FA
                $this->exts->moveToElementAndClick('.action-button.signin-button');
                sleep(10);
            }
            if ($this->exts->exists('a.setup-button[href*="two-step-verification/enroll"] + button.signin-button')) {
                // SKIP setup 2FA
                $this->exts->moveToElementAndClick('a.setup-button[href*="two-step-verification/enroll"] + button.signin-button');
                sleep(10);
            }
            if ($this->exts->exists('[action="/signin/newfeatures/save"] #optionsButton ~ [role="button"]')) {
                $this->exts->moveToElementAndClick('[action="/signin/newfeatures/save"] #optionsButton ~ [role="button"]');
                sleep(10);
            }
            if ($this->exts->exists('input[name="later"]') && $this->exts->urlContains('/AddressNoLongerAvailable')) {
                $this->exts->moveToElementAndClick('input[name="later"]');
                sleep(7);
            }
            if ($this->exts->exists('#editLanguageAndContactForm a[href*="/adsense/app"]')) {
                $this->exts->moveToElementAndClick('#editLanguageAndContactForm a[href*="/adsense/app"]');
                sleep(7);
            }
            if ($this->exts->exists('[data-view-instance-id="/web/chip-V0"] [role="button"]:first-child [jsslot]')) {
                $this->exts->moveToElementAndClick('[data-view-instance-id="/web/chip-V0"] [role="button"]:first-child [jsslot]');
                sleep(10);
            }

            if ($this->exts->exists('#submit_approve_access')) {
                $this->exts->moveToElementAndClick('#submit_approve_access');
                sleep(10);
            } else if ($this->exts->exists('form #approve_button[name="submit_true"]')) {
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
        if ($this->exts->exists('form ul li [role="link"][data-identifier]')) {
            $this->exts->moveToElementAndClick('form ul li [role="link"][data-identifier]');
            sleep(5);
        }

        if ($this->exts->urlContainsAny(['/ServiceLogin/identifier', '/ServiceLogin/webreauth']) && $this->exts->exists($this->google_submit_username_selector) && !$this->exts->exists($this->google_username_selector)) {
            $this->exts->capture("google-verify-it-you");
            // To help keep your account secure, Google needs to verify it’s you. Please sign in again to continue to Google Ads
            $this->exts->moveToElementAndClick($this->google_submit_username_selector);
            sleep(5);
        }

        $this->exts->capture("2-google-login-page");
        if ($this->exts->exists($this->google_username_selector)) {
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->google_username_selector, $this->username);
            sleep(1);
            $this->exts->moveToElementAndClick($this->google_submit_username_selector);
            sleep(5);
            if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists($this->google_password_selector) && $this->exts->exists($this->google_username_selector)) {
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                $this->exts->moveToElementAndClick($this->google_submit_username_selector);
                sleep(5);
                if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists($this->google_password_selector) && $this->exts->exists($this->google_username_selector)) {
                    $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                    $this->exts->moveToElementAndClick($this->google_submit_username_selector);
                    sleep(5);
                }
                if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists($this->google_password_selector) && $this->exts->exists($this->google_username_selector)) {
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
            if ($this->exts->exists('form[action*="/lookup"] button.account-chooser-button')) {
                $this->exts->moveToElementAndClick('form[action*="/lookup"] button.account-chooser-button');
                sleep(5);
            }
            if ($this->exts->exists('[data-view-id="prbTle"] form [role="link"][data-profileindex]')) {
                $this->exts->moveToElementAndClick('[data-view-id="prbTle"] form [role="link"][data-profileindex]');
                sleep(5);
            }
        }

        if ($this->exts->exists($this->google_password_selector)) {
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
            sleep(1);
            if ($this->exts->exists('#captchaimg[src]')) {
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
            }

            $this->exts->capture("2-google-login-page-filled");
            $this->exts->moveToElementAndClick($this->google_submit_password_selector);
            sleep(5);
            if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists('input[name="password"][aria-invalid="true"]') && $this->exts->exists($this->google_password_selector)) {
                $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
                sleep(1);
                if ($this->exts->exists('#captchaimg[src]')) {
                    $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                }
                $this->exts->moveToElementAndClick($this->google_submit_password_selector);
                sleep(5);
                if ($this->exts->exists('#captchaimg[src]') && $this->exts->exists($this->google_password_selector)) {
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
        if ($this->exts->exists('#assistActionId') && $this->exts->exists('[data-illustration="securityKeyLaptopAnim"]')) {
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
        } else if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
            // (updated special case 09-May-2020): If Notification method showed immediately, This method often make user confused
            // So, We try to click 'Choose another option' in order to select easier method
            $this->exts->moveToElementAndClick('[data-view-id] > div > div:nth-child(2)  div:nth-child(2) > [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button, button#assistiveActionOutOfQuota');
            sleep(7);
            $this->exts->capture("2.0-backed-methods-list-google");
        } else if ($this->exts->exists('input[name="ootpPin"]')) {
            // (updated special case 11-Jun-2020): If "Verify by offline device" immediately, This method often make user confused and maybe they don't have device on hand
            // So, We try to click 'Choose another option' in order to select easier method
            $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
            sleep(7);
            $this->exts->capture("2.0-backed-methods-list-google");
        }

        // STEP 1: Check if list of two factor methods showed, select first
        if ($this->exts->exists('li [data-challengetype]:not([data-challengeunavailable="true"])')) {
            // We most RECOMMEND confirm security phone or email, then other method
            if ($this->exts->exists('li [data-challengetype="13"]:not([data-challengeunavailable="true"])') && isset($this->security_phone_number) && $this->security_phone_number != '') {
                $this->exts->moveToElementAndClick('li [data-challengetype="13"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype="12"]:not([data-challengeunavailable="true"])') && isset($this->recovery_email) && $this->recovery_email != '') {
                $this->exts->moveToElementAndClick('li [data-challengetype="12"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype="6"]:not([data-challengeunavailable="true"])')) {
                // We RECOMMEND method type = 6 is get code from Google Authenticator
                $this->exts->moveToElementAndClick('li [data-challengetype="6"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])')) {
                // We second RECOMMEND method type = 9 is get code from SMS
                $this->exts->moveToElementAndClick('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"]:not([data-challengeunavailable="true"])')) {
                // We recommend method type = 4 and [data-sendauthzenprompt="true"] is  Tap YES on your smartphone or tablet
                $this->exts->moveToElementAndClick('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype="5"]:not([data-challengeunavailable="true"])')) {
                // Use a smartphone or tablet to receive a security code (even when offline)
                $this->exts->moveToElementAndClick('li [data-challengetype="5"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype]:not([data-challengetype="4"]):not([data-challengetype="2"]):not([data-challengeunavailable="true"])')) {
                // We DONT recommend method is QR code OR is Security USB, we can not solve this type of 2FA
                $this->exts->moveToElementAndClick('li [data-challengetype]:not([data-challengetype="4"]):not([data-challengetype="2"]):not([data-challengeunavailable="true"])');
            } else {
                $this->exts->moveToElementAndClick('li [data-challengetype]:not([data-challengeunavailable="true"])');
            }
            sleep(10);
        } else if ($this->exts->exists('#smsButton, [data-illustration="accountRecoverySmsPin"]')) {
            // Sometime user must confirm before google send sms
            $this->exts->moveToElementAndClick('#smsButton, div:first-child > [role="button"], [data-secondary-action-label] > div > div:nth-child(1) button');
            sleep(10);
        } else if ($this->exts->exists('#authzenNext') && $this->exts->exists('[data-view-id*="authzenView"], [data-illustration*="authzen"]')) {
            $this->exts->moveToElementAndClick('[data-view-id] #authzenNext');
            sleep(10);
        } else if ($this->exts->exists('#idvpreregisteredemailNext') && !$this->exts->exists('form input:not([type="hidden"])')) {
            $this->exts->moveToElementAndClick('#idvpreregisteredemailNext');
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
                $this->exts->type_key_by_xdotool("Return");
                sleep(7);
            }
            if ($this->exts->exists($input_selector)) {
                $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
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
                $this->exts->type_key_by_xdotool("Return");
                sleep(5);
            }
            if ($this->exts->exists($input_selector)) {
                $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
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
                $this->exts->type_key_by_xdotool("Return");
                sleep(7);
            }
            if ($this->exts->exists($input_selector)) {
                $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
            }
        } else if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
            // Check your smartphone. Google has sent a notification to your smartphone. Tap Yes in the notification, then tap 91 on your smartphone to continue
            $this->exts->two_factor_attempts = 3;
            $message_selector = '[data-view-id*="authzenView"] form, [data-view-id] form[method="post"]';
            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Enter \"OK\" after confirmation on device";
            $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->googleFillTwoFactor(null, null, '');
            sleep(5);
        } else if ($this->exts->exists('[data-view-id*="securityKeyWebAuthnView"], [data-view-id*="securityKeyView"]')) {
            // Method: insert your security key and touch it
            $this->exts->two_factor_attempts = 3;
            $this->exts->two_factor_notif_msg_en = 'Use chrome, login then insert your security key and touch it' . "\n>>>Enter \"OK\" after confirmation on device";
            $this->exts->two_factor_notif_msg_de = '[Chrome] Stecken Sie den Sicherheitsschlussel in den USB-Anschluss Ihres Computers ein. Wenn er eine Taste hat, tippen Sie darauf.' . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->googleFillTwoFactor(null, null, '');
            sleep(5);
            // choose another option: #assistActionId
        }

        // STEP 3: (Optional)  After choose method and confirm email or phone or.., google may asked confirm one more time before send code
        if ($this->exts->exists('#smsButton, [data-illustration="accountRecoverySmsPin"]')) {
            // Sometime user must confirm before google send sms
            $this->exts->moveToElementAndClick('#smsButton, div:first-child > [role="button"], [data-secondary-action-label] > div > div:nth-child(1) button');
            sleep(10);
        } else if ($this->exts->exists('#authzenNext') && $this->exts->exists('[data-view-id*="authzenView"], [data-illustration*="authzen"]')) {
            $this->exts->moveToElementAndClick('[data-view-id] #authzenNext');
            sleep(10);
        } else if ($this->exts->exists('#idvpreregisteredemailNext') && !$this->exts->exists('form input:not([type="hidden"])')) {
            $this->exts->moveToElementAndClick('#idvpreregisteredemailNext');
            sleep(10);
        } else if (count($this->exts->getElements('li [data-challengetype]:not([data-challengeunavailable="true"]):not([data-challengetype="undefined"])')) > 0) {
            $this->exts->moveToElementAndClick('li [data-challengetype]:not([data-challengeunavailable="true"]):not([data-challengetype="undefined"])');
            sleep(7);
        }


        // STEP 4: input code
        if ($this->exts->exists('form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin')) {
            $input_selector = 'form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin';
            $message_selector = 'form > span > section > div > div > div:first-child';
            $submit_selector = '#idvPreregisteredPhoneNext, #idvpreregisteredemailNext, #totpNext, #idvanyphoneverifyNext, #backupCodeNext, #view_container div.pwWryf.bxPAYd div.zQJV3 div.qhFLie > div > div > button';
            $this->exts->two_factor_attempts = 0;
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector);
        } else if ($this->exts->exists('input[name="ootpPin"], input#securityKeyOtpInputId')) {
            $input_selector = 'input[name="ootpPin"], input#securityKeyOtpInputId';
            $message_selector = 'form > span > section > div > div > div:first-child';
            $submit_selector = '';
            $this->exts->two_factor_attempts = 0;
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
        } else if ($this->exts->exists('input[name="Pin"]')) {
            $input_selector = 'input[name="Pin"]';
            $message_selector = 'form > span > section > div > div > div:first-child';
            $submit_selector = '';
            $this->exts->two_factor_attempts = 0;
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
        } else if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
            // Check your smartphone. Google has sent a notification to your smartphone. Tap Yes in the notification, then tap 91 on your smartphone to continue
            $this->exts->two_factor_attempts = 3;
            $message_selector = '[data-view-id*="authzenView"] form, [data-view-id] form[method="post"]';
            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Enter \"OK\" after confirmation on device";
            $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->googleFillTwoFactor(null, null, '');
            sleep(5);
        } else if ($this->exts->exists('input[name="secretQuestionResponse"]')) {
            $input_selector = 'input[name="secretQuestionResponse"]';
            $message_selector = 'form > span > section > div > div > div:first-child';
            $submit_selector = '[data-secondary-action-label] > div > div:nth-child(1) button';
            $this->exts->two_factor_attempts = 0;
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector);
        }
        if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
            // Check your smartphone. Google has sent a notification to your smartphone. Tap Yes in the notification, then tap 91 on your smartphone to continue
            $this->exts->two_factor_attempts = 3;
            $message_selector = '[data-view-id*="authzenView"] form, [data-view-id] form[method="post"]';
            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Enter \"OK\" after confirmation on device";
            $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->googleFillTwoFactor(null, null, '');
            sleep(5);
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

                if ($this->exts->exists($submit_selector)) {
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
        if ($this->exts->exists($recaptcha_iframe_selector)) {
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
                $recaptcha_textareas = $this->exts->getElements($recaptcha_textarea_selector);
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


    private function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            $this->waitFor($this->check_login_success_selector, 60);
            if ($this->exts->exists($this->check_login_success_selector)) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }

    private function invoicePage()
    {
        $this->exts->log("invoice Page");
        if ($this->exts->exists('div[class*="ModalPaneWithBuffer"] div[class*="CloseButton"], div[class*="ModalPaneWithBuffer"] div[class*="closeButton"]')) {
            $this->exts->click_element('div[class*="ModalPaneWithBuffer"] div[class*="CloseButton"], div[class*="ModalPaneWithBuffer"] div[class*="closeButton"]');
            sleep(1);
        }

        // New code 2022 for new design
        $this->exts->waitTillPresent('[class*="SettingsMenuButton"][role=button]', 30);
        if ($this->exts->exists('[class*="SettingsMenuButton"][role=button]')) {
            $this->exts->click_element('[class*="SettingsMenuButton"][role=button]');
            $this->exts->capture('seats-checking');
            sleep(5);
            $this->exts->waitTillPresent("div.GlobalTopbarUserMenu .Scrollable--withCompositingLayer .UserOrDomainSwitcherMenuItemDomain-nameAndIndicator", 10);
            $total_seats = count($this->exts->querySelectorAll('div.GlobalTopbarUserMenu .Scrollable--withCompositingLayer .UserOrDomainSwitcherMenuItemDomain-nameAndIndicator'));
            $this->exts->log('Total SEATS - ' . $total_seats);

            // $currentHandle = $this->exts->webdriver->getWindowHandle();

            // close browser tabs if multi tab is there
            // $this->close_all_tabs_w3c($currentHandle);

            if ($total_seats > 1) {
                for ($s = 0; $s < $total_seats; $s++) {
                    if (!$this->exts->getElements('.Scrollable--withCompositingLayer .UserOrDomainSwitcherMenuItemDomain-nameAndIndicator')) {
                        $this->exts->click_element('[class*="SettingsMenuButton"][role=button]');
                        sleep(3);
                    }
                    $this->exts->waitTillPresent('div.GlobalTopbarUserMenu .Scrollable--withCompositingLayer .UserOrDomainSwitcherMenuItemDomain-nameAndIndicator');
                    if (
                        $this->exts->exists("div.GlobalTopbarUserMenu .Scrollable--withCompositingLayer .UserOrDomainSwitcherMenuItemDomain-nameAndIndicator")
                        && $s > 0
                    ) {
                        $target_seat = $this->exts->getElements('div.GlobalTopbarUserMenu .Scrollable--withCompositingLayer .UserOrDomainSwitcherMenuItemDomain-nameAndIndicator')[$s];
                        $this->exts->startTrakingTabsChange();
                        $this->exts->click_element($target_seat);
                        sleep(3);
                        // $this->switch_to_new_tab_w3c($windowHandlesBefore);
                        $this->exts->switchToNewestActiveTab();

                        $this->exts->click_element('[class*="SettingsMenuButton"][role=button]');
                        $this->exts->capture('seats-switched-' . $s);
                        sleep(3);
                    } elseif ($s > 0) {
                        $target_seat = $this->exts->getElements('div.DomainsMenuSection-v2Scrollable div.SingleSelectMenuItem')[$s];
                        // $windowHandlesBefore = $this->exts->webdriver->getWindowHandles();
                        $this->exts->startTrakingTabsChange();
                        $this->exts->click_element($target_seat);
                        sleep(10);
                        // $this->switch_to_new_tab_w3c($windowHandlesBefore);
                        $this->exts->switchToNewestActiveTab();

                        $this->exts->click_element('[class*="SettingsMenuButton"][role=button]');
                        sleep(5);
                        $this->exts->capture('seats-switched-' . $s);
                    }

                    if ($this->exts->exists('div.ActionMenu a[href*="admin"]')) {
                        $billingUrl = 'https://app.asana.com/' . $this->exts->extract('div.ActionMenu a[href*="admin"]', null, 'href') . '/billing';
                        // $this->open_new_tab_w3c();
                        // $this->exts->openUrl($billingUrl);
                        $this->exts->openNewTab($billingUrl, false, true);
                        $this->downloadConsoleInvoice();
                    } elseif ($this->exts->exists('div.GlobalTopbarUserMenu div[class*="admin"]')) {
                        $this->exts->openNewTab($this->invoicePageUrl, false, true);
                        $this->downloadConsoleInvoice();
                    } else {
                        $this->exts->capture("seats_$s-no-console-admin.");
                    }

                    $this->exts->capture("seats_$s-console-admin.");
                    // close new tab too avoid too much tabs
                    // $this->close_all_tabs_w3c($currentHandle);
                    $this->exts->switchToInitTab();
                    $this->exts->closeAllTabsButThis();
                    $this->invoiceCount = 0;
                    $this->terminateLoop = false;
                }
            } else {
                $this->exts->openUrl($this->invoicePageUrl);
                sleep(3);

                $this->downloadConsoleInvoice();
            }
        }

        // Final, check no invoice
        if ($this->isNoInvoice) {
            $this->exts->no_invoice();
        }
    }

    private function downloadInvoice($team_id = '')
    {
        $this->exts->capture("invoices-page");
        if ($this->exts->getElement('#billing-tab-body a#invoice_pdf') != null) {
            $invoiceDate = trim(end(explode(' am ', $this->exts->extract('tr.request-invoice td.field-value', null, 'text'))));
            $invoiceName = trim($team_id . '_' . preg_replace("/[^\w]/", '', $invoiceDate), '_');
            $invoiceAmount = '';
            $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : "";

            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoiceName);
            $this->exts->log('invoiceDate: ' . $invoiceDate);
            $this->exts->log('invoiceAmount: ' . $invoiceAmount);
            $invoiceDate = $this->exts->parse_date($invoiceDate, 'n/j/Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoiceDate);

            $downloaded_file = $this->exts->click_and_download('#billing-tab-body a#invoice_pdf', 'pdf', $invoiceFileName);

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
            $this->isNoInvoice = false;
        }

        $this->exts->moveToElementAndClick('.dialogView2-closeX');
        sleep(5);
    }

    private function downloadConsoleInvoice()
    {
        // Restriction config
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)$this->exts->config_array["restrictPages"] : 3;
        $restrictDate = $restrictPages == 0 ? date('Y-m-d', strtotime('-2 years')) : date('Y-m-d', strtotime('-3 months'));
        $maxInvoices = 20;

        $this->invoiceCount = 0;
        $this->terminateLoop = false;

        $this->exts->log("Restrict Pages: $restrictPages");
        $this->exts->log("Restrict Date: $restrictDate");
        $this->exts->log("Max Invoices: $maxInvoices");

        // Wait and open invoice popup
        $this->waitFor('.BillingMaintenanceLatestInvoice li:nth-child(2) [role="button"]', 40);
        $this->exts->click_element('.BillingMaintenanceLatestInvoice li:nth-child(2) [role="button"]');
        $this->waitFor('.InvoiceHistoryDialog-gridCell > a[href*="/invoices/"]', 10);
        $this->exts->capture("4-invoices-page");

        $invoice_links = $this->exts->getElements('.InvoiceHistoryDialog-gridCell > a[href*="/invoices/"]');
        $this->exts->log("Total Invoices Found: " . count($invoice_links));

        foreach ($invoice_links as $invoice_link) {
            if ($this->terminateLoop) break;

            $invoice_url = $invoice_link->getAttribute('href');
            $invoiceName = trim($this->exts->extract('./../following-sibling::span[position()=1]', $invoice_link, 'innerText'));
            $invoiceAmount = trim($this->exts->extract('./../following-sibling::span[position()=2]', $invoice_link, 'innerText'));
            $invoiceDateRaw = trim($invoice_link->getText());
            $invoiceDate = $this->exts->parse_date($invoiceDateRaw, 'M. d Y', 'Y-m-d', 'de');
            $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : "";

            $this->exts->log('--------------------------');
            $this->exts->log("invoiceName: $invoiceName");
            $this->exts->log("invoiceDate: $invoiceDate");
            $this->exts->log("invoiceAmount: $invoiceAmount");

            if (!empty($invoiceDate) && $invoiceDate <= $restrictDate) {
                $this->exts->log("Invoice too old: $invoiceName ($invoiceDate). Stopping.");
                break; // sorted descending  no need to continue
            }
            if ($this->exts->invoice_exists($invoiceName)) {
                $this->exts->log("Invoice already exists: $invoiceName");
                continue;
            }

            $this->isNoInvoice = false;
            $downloaded_file = $this->exts->direct_download($invoice_url, 'pdf', $invoiceFileName);
            if (trim($downloaded_file) !== '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $downloaded_file);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }

            $this->invoiceCount++;
            if ($this->invoiceCount >= $maxInvoices) {
                $this->exts->log("Max invoice count ($maxInvoices) reached. Stopping.");
                $this->terminateLoop = true;
                break;
            }
        }
        $this->exts->log("Total Invoices Processed: $this->invoiceCount");
    }

    private function waitFor($selector, $seconds = 30)
    {
        for ($i = 1; $i <= $seconds && $this->exts->querySelector($selector) == null; $i++) {
            $this->exts->log('Waiting for Selector (' . $i . '): ' . $selector);
            sleep(1);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
