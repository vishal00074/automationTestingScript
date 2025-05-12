<?php // added clear chrome function and rtry login in case redirect back after submitting the form
// added logs to check config keys values
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

    // Server-Portal-ID: 428 - Last modified: 12.05.2025 09:22:33 UTC - User: 1

    public $baseUrl = 'https://upwork.com';
    public $username_selector = '[data-ng-show*="isActiveScreen"]:not(.ng-hide) input#login_username, form#username input[name="login[username]"], form#login input[name="login[username]"]';
    public $password_selector = '[data-ng-show*="isActiveScreen"]:not(.ng-hide) input#login_password, form#password input[name="login[password]"], form#login input[name="login[password]"]';
    public $remember_me_selector = '#login_rememberme:not(:checked) ~ span.checkbox-replacement-helper, form#password input[name="login[rememberme]"], .d-none-mobile-app #login_rememberme:not(:checked)';
    public $submit_login_selector = '[data-ng-show*="isActiveScreen"]:not(.ng-hide) button[data-ng-click="submit($event)"], [data-ng-show*="isActiveScreen"]:not(.ng-hide) button[data-ng-click="submitUsername($event)"], form#username button#username_password_continue, form#password button#password_control_continue, form#login button[button-role="continue"]';
    public $open_login_button = '#nav-main a[href*="/login"], .navbar-header a[href*="/login"], .navbar-fixed-subnav a[href*="/login"]';
    public $check_login_success_selector = '[data-cy="user-menu"] form[action*="/logout"], .dropdown-account form[action*="/logout"], #simpleCompanySelector form[action*="/Logout"], .oNavIconNotifications, [icon-name="notification"]';
    public $login_with_google = 0;
    public $login_with_apple = 0;
    public $statement_only = '0';
    public $only_invoice = '0';
    public $security_answer = '';
    public $earning_history = '0';
    public $isNoInvoice = true;
    public $restrictPages = 3;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        // $nav_driver = $this->exts->executeSafeScript('return navigator.webdriver;');
        $this->login_with_google = isset($this->exts->config_array["login_with_google"]) ? (int)$this->exts->config_array["login_with_google"] : $this->login_with_google;
        $this->login_with_apple = isset($this->exts->config_array["login_with_apple"]) ? (int)$this->exts->config_array["login_with_apple"] : $this->login_with_apple;
        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)$this->exts->config_array["restrictPages"] : 3;
        $this->statement_only = isset($this->exts->config_array["statement_only"]) ? (int)$this->exts->config_array["statement_only"] : 0;
        $this->earning_history = isset($this->exts->config_array["earning_history"]) ? (int)$this->exts->config_array["earning_history"] : 0;
        $this->only_invoice = isset($this->exts->config_array["only_invoice"]) ? (int)$this->exts->config_array["only_invoice"] : $this->only_invoice;


        $this->exts->log('login_with_google ' . $this->login_with_google);
        $this->exts->log('login_with_apple ' . $this->login_with_apple);

        $this->exts->log('restrictPages ' . $this->restrictPages);


        $this->exts->log('earning_history ' . $this->earning_history);
        $this->exts->log('only_invoice ' . $this->only_invoice);
        $this->exts->log('statement_only ' . $this->statement_only);


        $this->exts->openUrl('https://www.upwork.com');
        $this->exts->loadCookiesFromFile();
        sleep(5);
        $this->check_solve_blocked_page();
        // sleep(10);
        $this->exts->capture_by_chromedevtool('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if (!$this->exts->exists($this->check_login_success_selector)) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->openUrl('https://www.upwork.com/ab/account-security/login');

            $this->acceptCookies();
            $this->checkFillLogin();
            sleep(15);

            //retry login 2nd time
            if ($this->exts->urlContains('account-security/login')) {
                $this->clearChrome();
                $this->exts->openUrl('https://www.upwork.com/ab/account-security/login');
                $this->acceptCookies();
                $this->checkFillLogin();
                sleep(15);
            }


            // Maybe this site ask for security, then ask for 2FA code, So call checkFillExtraAuthentication twice.
            $this->checkFillExtraAuthentication();
            $this->checkFillExtraAuthentication();
            if ($this->exts->exists('#main-auth-card button#control_cancel')) {
                $this->exts->click_by_xdotool('#main-auth-card button#control_cancel');
                sleep(5);
            }
            sleep(20);
        }
        $this->check_solve_blocked_page();

        $this->doAfterLogin();
    }

    public function acceptCookies()
    {
        sleep(3);
        $this->check_solve_blocked_page();
        sleep(10);
        if ($this->exts->exists($this->open_login_button)) {
            //$this->exts->click_element($this->open_login_button);
            $this->exts->click_by_xdotool($this->open_login_button);
            sleep(10);
        }
        $this->check_solve_blocked_page();
        if ($this->exts->exists($this->open_login_button)) {
            //$this->exts->click_element($this->open_login_button);
            $this->exts->click_by_xdotool($this->open_login_button);
            sleep(10);
        }
        if ($this->exts->check_exist_by_chromedevtool('#onetrust-banner-sdk:not([style*="display: none"]) #onetrust-accept-btn-handler')) {
            $this->exts->click_by_xdotool('#onetrust-banner-sdk:not([style*="display: none"]) #onetrust-accept-btn-handler');
        }
    }

    private function clearChrome()
    {
        $this->exts->log("Clearing browser history, cookie, cache");
        $this->exts->openUrl('chrome://settings/clearBrowserData');
        sleep(10);
        $this->exts->capture("clear-page");
        for ($i = 0; $i < 2; $i++) {
            $this->exts->type_key_by_xdotool('Tab');
        }
        $this->exts->type_key_by_xdotool('Tab');
        $this->exts->type_key_by_xdotool('Return');
        $this->exts->type_key_by_xdotool('a');
        sleep(1);
        $this->exts->type_key_by_xdotool('Return');
        sleep(3);
        $this->exts->capture("clear-page");
        for ($i = 0; $i < 5; $i++) {
            $this->exts->type_key_by_xdotool('Tab');
        }
        $this->exts->type_key_by_xdotool('Return');
        sleep(15);
        $this->exts->capture("after-clear");
    }

    private function checkFillLogin()
    {
        $this->exts->log(__FUNCTION__);
        $this->exts->capture_by_chromedevtool("2-login-page");
        if ($this->login_with_google == 1) {
            $this->exts->click_by_xdotool('button#login_google_submit');
            sleep(5);

            $this->loginGoogleIfRequired();
        } elseif ($this->login_with_apple == 1) {
            $this->exts->click_by_xdotool('button#login_apple_submit');
            sleep(1);

            // $handles = $this->exts->webdriver->getWindowHandles();
            // if(count($handles) > 1){
            // 	$this->exts->webdriver->switchTo()->window(end($handles));
            // }

            $this->loginAppleIfRequired();
        } else {
            if ($this->exts->exists($this->username_selector)) {
                sleep(3);
                $this->exts->log("Enter Username");
                $this->exts->click_by_xdotool($this->username_selector);
                sleep(1);
                $this->exts->type_key_by_xdotool("Ctrl+a");
                $this->exts->type_key_by_xdotool("BackSpace");
                $this->exts->type_text_by_xdotool($this->username);
                sleep(1);
                $this->exts->capture_by_chromedevtool("2.1-username-filled");
                //Since now it is running in chrome we can use movetoelement 22-10-2020
                //$this->exts->click_element($this->submit_login_selector);
                $this->exts->click_by_xdotool($this->submit_login_selector);
                sleep(10);
                // Sometime, blocked page displayed after submitting username, solve it, re-enter user if if display one again
                $is_blocked_page = $this->check_solve_blocked_page();
                if ($is_blocked_page && $this->exts->exists($this->username_selector)) {
                    sleep(3);
                    $this->exts->log("Re - Enter Username");
                    $this->exts->click_by_xdotool($this->username_selector);
                    sleep(1);
                    $this->exts->type_key_by_xdotool("Ctrl+a");
                    $this->exts->type_key_by_xdotool("BackSpace");
                    $this->exts->type_text_by_xdotool($this->username);
                    sleep(1);
                    $this->exts->capture_by_chromedevtool("2.1-username-re-filled");
                    $this->exts->click_by_xdotool($this->submit_login_selector);
                    sleep(5);
                    $this->exts->capture_by_chromedevtool("2.1-username-submitted");

                    $is_blocked_page = $this->check_solve_blocked_page();
                    if ($is_blocked_page && $this->exts->exists($this->username_selector)) {
                        sleep(3);
                        $this->exts->log("Re - Enter Username");
                        $this->exts->click_by_xdotool($this->username_selector);
                        sleep(1);
                        $this->exts->type_key_by_xdotool("Ctrl+a");
                        $this->exts->type_key_by_xdotool("BackSpace");
                        $this->exts->type_text_by_xdotool($this->username);
                        sleep(1);
                        $this->exts->capture_by_chromedevtool("2.1-username-re-filled");
                        $this->exts->click_by_xdotool($this->submit_login_selector);
                        sleep(5);
                        $this->exts->capture_by_chromedevtool("2.1-username-submitted");
                        $is_blocked_page = $this->check_solve_blocked_page();
                    }
                }

                // UPDATE 20-Jul-2020, Check and solve technical problem error
                if (!$this->exts->exists($this->password_selector) && $this->exts->exists($this->username_selector) && strpos($this->exts->extract('.up-alert-danger'), 'technical difficulties') !== false) {
                    $this->exts->capture_by_chromedevtool("2.1-technical-issue");
                    $this->exts->clearCookies();

                    $this->exts->getUrl('https://www.upwork.com');
                    sleep(5);
                    $this->check_solve_blocked_page();
                    if ($this->exts->exists($this->open_login_button)) {
                        //$this->exts->click_element($this->open_login_button);
                        $this->exts->click_by_xdotool($this->open_login_button);
                        sleep(10);
                    }
                    $this->exts->log("Re-Enter Username");
                    $this->exts->click_by_xdotool($this->username_selector);
                    sleep(1);
                    $this->exts->type_key_by_xdotool("Ctrl+a");
                    $this->exts->type_key_by_xdotool("BackSpace");
                    $this->exts->type_text_by_xdotool($this->username);
                    sleep(1);
                    $this->exts->capture_by_chromedevtool("2.1-reenter-username");
                    //$this->exts->click_element($this->submit_login_selector);
                    $this->exts->click_by_xdotool($this->submit_login_selector);
                    sleep(10);
                }
            }

            if ($this->exts->exists($this->password_selector)) {
                $this->exts->log("Enter Password");
                $this->exts->click_by_xdotool($this->password_selector);
                sleep(1);
                $this->exts->type_key_by_xdotool("Ctrl+a");
                $this->exts->type_key_by_xdotool("BackSpace");
                $this->exts->type_text_by_xdotool($this->password);
                sleep(1);

                $this->exts->click_by_xdotool($this->remember_me_selector);
                sleep(2);

                $this->exts->capture_by_chromedevtool("2.2-password-page-filled");
                $this->checkFillRecaptcha();
                //$this->exts->click_element($this->submit_login_selector);
                $this->exts->click_by_xdotool($this->submit_login_selector);
            } else {
                $this->exts->log(__FUNCTION__ . '::Password page not found');
                $this->exts->capture("2-pasword-page-not-found");
                if (!$this->exts->exists($this->username_selector) && $this->exts->exists('form#google button#google_control_submit, button.gsso-button#login_control_submit, [name="login"] #login_google_submit')) {
                    $this->exts->click_element('form#google button#google_control_submit, button.gsso-button#login_control_submit, [name="login"] #login_google_submit');
                    sleep(3);
                    $this->exts->switchToNewestActiveTab();
                    sleep(2);
                    $this->loginGoogleIfRequired();
                    $this->exts->switchToInitTab();
                } else if (!$this->exts->exists($this->username_selector) && $this->exts->exists('[name="login"] button.apple-sso-button')) {
                    $this->exts->click_element('[name="login"] button.apple-sso-button');
                    sleep(3);
                    $this->exts->log('Clicked on apple login button');
                    // $handles = $this->exts->webdriver->getWindowHandles();
                    // if(count($handles) > 1){
                    // 	$this->exts->webdriver->switchTo()->window(end($handles));
                    // }

                    // $this->exts->switchToIfNewTabOpened(); 
                    $this->loginAppleIfRequired();
                    sleep(3);

                    $google_url = $this->exts->findTabMatchedUrl(['google']);
                    if ($google_url != null) {
                        $this->exts->log('Google Tab found');

                        $this->exts->switchToTab($google_url);
                    }

                    $this->loginGoogleIfRequired();
                }
            }
        }
    }
    private function checkFillExtraAuthentication()
    {
        $this->exts->log(__FUNCTION__);
        $this->exts->capture_by_chromedevtool("2.1-checking-two-factor");
        if ($this->exts->exists('.push-form[name="login"] #next_continue')) {
            // Push notification
            $this->exts->click_by_xdotool('.push-form[name="login"] #next_continue');
            sleep(5);
            $this->exts->capture_by_chromedevtool("2.1-push-notification");
            $this->checkFill2FAPushNotification();
        }
        if ($this->exts->exists('#phone[name="login"] #next_continue, #email[name="login"] #next_continue') && !$this->exts->exists('input#deviceAuthOtp_otp')) {
            $this->exts->capture_by_chromedevtool("2.0-send-sms-confirmation");
            $this->exts->click_by_xdotool('#phone[name="login"] #next_continue, #email[name="login"] #next_continue');
            sleep(10);
        }

        if ($this->exts->exists('[data-ng-if*="isActiveScreen"]:not(.ng-hide) input#login_deviceAuthorization_answer, input[name="login[deviceAuthorization][answer]"], .security-question input#login_answer, #login input#login_answer')) {
            $this->exts->log("Security question page found.");
            $two_factor_selector = '[data-ng-if*="isActiveScreen"]:not(.ng-hide) input#login_deviceAuthorization_answer, input[name="login[deviceAuthorization][answer]"], .security-question input#login_answer, #login input#login_answer';
            $two_factor_message_selector = '[data-ng-if*="isActiveScreen"]:not(.ng-hide) strong#security-question-text, [for="security-question_deviceAuthorization_answer"], [for="login_deviceAuthorization_answer"], [for="login_answer"]';
            $two_factor_submit_selector = '[data-ng-if*="isActiveScreen"]:not(.ng-hide) button[type="submit"], button#security-question_control_continue, button#login_control_continue, .security-question #login_control_continue';
            $this->security_answer = isset($this->exts->config_array["security_answer"]) ? trim(@$this->exts->config_array["security_answer"]) : "";
            $this->exts->log(__FUNCTION__ . "::Security answer in config: " . $this->security_answer);
            if ($this->security_answer == '') {
                $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($two_factor_message_selector, null, 'innerText'));
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
                $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);

                $this->exts->notification_uid = "";
                $this->security_answer = trim($this->exts->fetchTwoFactorCode());
            }
            $this->exts->log(__FUNCTION__ . "::Security answer: " . $this->security_answer);
            if ($this->security_answer != '') {
                $this->exts->click_by_xdotool($two_factor_selector);
                sleep(1);
                $this->exts->type_text_by_xdotool($this->security_answer);
                $this->exts->click_by_xdotool('input[name="login[deviceAuthorization][remember]"]:not(:checked) + *, #login_remember:not(:checked) + span');
                $this->exts->capture_by_chromedevtool("2.2-security-question-filled");
                $this->exts->click_by_xdotool($two_factor_submit_selector);
                sleep(15);

                if ($this->exts->exists('form[name="login"] input#login_otp') && strpos(strtolower($this->exts->extract('form[name="login"] p')), 'reset your security question') !== false) {
                    $this->exts->log("third");
                    // $this->exts->account_not_ready();
                    sleep(2);
                    $this->exts->type_key_by_xdotool('Return');
                    sleep(2);
                    $this->checkFillTwoFactor();
                }
            }
        } else if ($this->exts->exists('[data-ng-if*="isActiveScreen"]:not(.ng-hide) input[name*="[otp]"], input[name="login[phoneOtp][otp]"], input[name="login[sqEmailOtp][otp]"]')) {
            $this->exts->log("2FA code email/phone page found.");
            $two_factor_selector = '[data-ng-if*="isActiveScreen"]:not(.ng-hide) input[name*="[otp]"], input[name="login[phoneOtp][otp]"], input[name="login[sqEmailOtp][otp]"]';
            $two_factor_message_selector = '[data-ng-if*="isActiveScreen"]:not(.ng-hide) p span:not(.ng-hide), [data-ng-if*="isActiveScreen"]:not(.ng-hide) p + h3,[data-ng-if*="isActiveScreen"]:not(.ng-hide) h1 + p' .
                ', form[name="login"] .auth-growable-flex > div:nth-child(2), form[name="login"] .auth-growable-flex > h3, form[name="login"] .auth-growable-flex p' .
                ', [name="deviceAuthOtp"] > .align-items-center + div';
            $two_factor_submit_selector = '[data-ng-if*="isActiveScreen"]:not(.ng-hide) button[type="submit"], button#phone_control_continue, button#sq-email_control_continue, button#login_control_continue';

            $this->exts->two_factor_notif_msg_en = join("\n", $this->exts->getElementsAttribute($two_factor_message_selector, 'innerText'));
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);

            $this->exts->notification_uid = "";
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            $this->exts->log(__FUNCTION__ . "::2FA answer: " . $two_factor_code);
            $this->exts->click_by_xdotool($two_factor_selector);
            sleep(1);
            $this->exts->type_text_by_xdotool($two_factor_code);
            sleep(3);
            $this->exts->capture_by_chromedevtool("2.2-2FA-filled");
            $this->exts->click_by_xdotool($two_factor_submit_selector);
            sleep(15);
        } else if ($this->exts->exists('.totp-form input[name="deviceAuthOtp[otp]"], .phone-form input[name="deviceAuthOtp[otp]"]')) {
            $this->exts->log("2FA authenticator app/phone code found.");
            $two_factor_selector = '.totp-form input[name="deviceAuthOtp[otp]"], .phone-form input[name="deviceAuthOtp[otp]"]';
            $two_factor_message_selector = 'form > div.sr-only + div .mb-20';
            $two_factor_submit_selector = 'form #next_continue, button#next_continue';

            $this->exts->two_factor_notif_msg_en = join("\n", $this->exts->getElementsAttribute($two_factor_message_selector, 'innerText'));
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);

            $this->exts->notification_uid = "";
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            $this->exts->log(__FUNCTION__ . "::2FA answer: " . $two_factor_code);
            $this->exts->click_by_xdotool($two_factor_selector);
            sleep(1);
            $this->exts->type_text_by_xdotool($two_factor_code);
            $this->exts->click_by_xdotool(' #deviceAuthOtp_remember:not(:checked)');
            $this->exts->capture_by_chromedevtool("2.2-2FA-filled");
            $this->exts->click_by_xdotool($two_factor_submit_selector);
            sleep(15);
        } else if ($this->exts->exists('#email [name="deviceAuthOtp[otp]"]')) {
            $this->exts->log("2FA authenticator email found.");
            $two_factor_selector = '#email [name="deviceAuthOtp[otp]"]';
            $two_factor_message_selector = '#email div:not(.sr-only)> p';
            $two_factor_submit_selector = 'form #next_continue, button#next_continue';

            $this->exts->two_factor_notif_msg_en = join("\n", $this->exts->getElementsAttribute($two_factor_message_selector, 'innerText'));
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);

            $this->exts->notification_uid = "";
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            $this->exts->log(__FUNCTION__ . "::2FA answer: " . $two_factor_code);
            $this->exts->click_by_xdotool($two_factor_selector);
            sleep(1);
            $this->exts->type_text_by_xdotool($two_factor_code);
            $this->exts->click_by_xdotool('#deviceAuthOtp_remember:not(:checked)');
            $this->exts->capture_by_chromedevtool("2.2-2FA-filled");
            $this->exts->click_by_xdotool($two_factor_submit_selector);
            sleep(15);
        } else if ($this->exts->exists('[name="deviceAuthOtp"] input#login_deviceAuthOtp_otp') && $this->exts->exists('[name="deviceAuthOtp"] input[type="tel"]')) {
            $this->exts->log("2FA code phone split input.");
            $two_factor_message_selector = '[name="deviceAuthOtp"] > .align-items-center + div';
            $two_factor_submit_selector = 'button#login_control_continue';

            $this->exts->two_factor_notif_msg_en = join("\n", $this->exts->getElementsAttribute($two_factor_message_selector, 'innerText'));
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            $this->exts->notification_uid = "";
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            $this->exts->log(__FUNCTION__ . "::2FA answer: " . $two_factor_code);
            if (trim($two_factor_code) != '' && !empty($two_factor_code)) {
                $two_factor_inputs = $this->exts->getElements('[name="deviceAuthOtp"] input[type="tel"]');
                for ($d = 0; $d < strlen($two_factor_code) && $d < count($two_factor_inputs); $d++) {
                    $two_factor_inputs[$d]->clear();
                    $two_factor_inputs[$d]->sendKeys($two_factor_code[$d]);
                }
                $this->exts->capture_by_chromedevtool("2.2-2FA-filled");
                $this->exts->click_by_xdotool($two_factor_submit_selector);
                sleep(15);
            }
        } else if ($this->exts->exists('reenter-password-form input#sensitiveZone_password, input#reenterPassword_password')) {
            $this->exts->log("Reenter password page found.");
            $this->exts->moveToElementAndType('reenter-password-form input#sensitiveZone_password, input#reenterPassword_password', $this->password);
            $this->exts->capture("2.2-reenter-password-filled");
            $this->exts->click_element('[form-interface="reenterPasswordForm"] button[type="submit"], button#control_continue');
            sleep(10);
        }
    }

    private function checkFillTwoFactor($count = 1)
    {
        $this->exts->waitTillAnyPresent(['input[id="login_otp"]'], 10);
        if ($this->exts->exists('input[id="login_otp"]')) {
            $two_factor_selector = 'input[id="login_otp"]';
            $two_factor_message_selector = 'p[class*="font-weigh"]';
            $two_factor_submit_selector = 'button[id="login_control_continue"]';
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->querySelector($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->querySelectorAll($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->querySelectorAll($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
                }
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
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code. " . $two_factor_code);
                $this->exts->click_by_xdotool($two_factor_selector);
                $this->exts->type_text_by_xdotool($two_factor_code);
                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $count);

                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(15);

                if ($this->exts->querySelector($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        } else if ($this->exts->exists('input[id*="_OTP"]')) { // SMS or Email
            $two_factor_selector = 'input[id*="_OTP"]';
            $two_factor_message_selector = '[role="main"] h1#title, p#subtitle';
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor-" . $count);

            if ($this->exts->querySelector($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                $messages = $this->exts->extract($two_factor_message_selector);
                $this->exts->two_factor_notif_msg_en = $messages;
                $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            }
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);

            $this->exts->notification_uid = '';
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            $resultCodes = str_split($two_factor_code);
            $code_inputs = $this->exts->querySelectorAll($two_factor_selector);
            if (count($code_inputs) > 1) {
                foreach ($code_inputs as $key => $code_input) {
                    if (array_key_exists($key, $resultCodes)) {
                        $this->exts->log('"checkFillTwoFactor: Entering key ' . $resultCodes[$key] . 'to input #');
                        $this->exts->moveToElementAndType('div[data-baseweb="pin-code"] div:nth-child(' . ($key + 1) . ') input', $resultCodes[$key]);
                        // $code_input->sendKeys($resultCodes[$key]);
                    } else {
                        $this->exts->log('"checkFillTwoFactor: Have no char for input #');
                    }
                }
            } else {
                if (!empty($two_factor_code)) {
                    $this->exts->log("checkFillTwoFactor: Entering two_factor_code. " . $two_factor_code);
                    $this->exts->click_by_xdotool($two_factor_selector);
                    $this->exts->type_text_by_xdotool($two_factor_code);
                    $this->exts->capture("2.2-two-factor-filled-" . $count);
                    sleep(12);
                    $this->exts->capture("2.2-two-factor-submitted-" . $count);

                    if (stripos($this->exts->extract('[data-test="otp-error"]', null, 'innerText'), 'entered is incorrect') !== false) {
                        $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
                        $this->exts->log("Retry Message:\n" . $this->exts->two_factor_notif_msg_en);
                        $this->exts->notification_uid = '';
                        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
                        if (!empty($two_factor_code)) {
                            $this->exts->log("checkFillTwoFactor: Retry two_factor_code. " . $two_factor_code);
                            $this->exts->click_by_xdotool($two_factor_selector);
                            $this->exts->type_text_by_xdotool($two_factor_code);
                            $this->exts->capture("2.2-two-factor-retry-filled-" . $count);
                            sleep(12);
                            $this->exts->capture("2.2-two-factor-retry-submitted-" . $count);
                        }
                    }

                    if ($this->exts->querySelector($two_factor_selector) == null) {
                        $this->exts->log("Two factor solved");
                    }
                } else {
                    $this->exts->log("Not received two factor code");
                }
            }
        } else if ($this->exts->exists('input[id*="TOTP-0"]')) { // Authenticator app code
            $two_factor_selector = 'input[id*="TOTP-0"]';
            $two_factor_message_selector = 'h1#title';
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor-" . $count);

            if ($this->exts->querySelector($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                $message = $this->exts->extract($two_factor_message_selector);
                $this->exts->two_factor_notif_msg_en = $message;
                $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            }
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);

            $this->exts->notification_uid = '';
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code. " . $two_factor_code);
                $this->exts->click_by_xdotool($two_factor_selector);
                $this->exts->type_text_by_xdotool($two_factor_code);
                $this->exts->capture("2.2-two-factor-filled-" . $count);
                sleep(1);
                $this->exts->moveToElementAndClick('[screen-test="TOTP_VERIFICATION"] #forward-button');
                sleep(12);
                $this->exts->capture("2.2-two-factor-submitted-" . $count);

                if (stripos($this->exts->extract('[data-test="otp-error"]', null, 'innerText'), 'entered is incorrect') !== false) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                    $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
                    $this->exts->log("Retry Message:\n" . $this->exts->two_factor_notif_msg_en);
                    $this->exts->notification_uid = '';
                    $two_factor_code = trim($this->exts->fetchTwoFactorCode());
                    if (!empty($two_factor_code)) {
                        $this->exts->log("checkFillTwoFactor: Retry two_factor_code. " . $two_factor_code);
                        $this->exts->click_by_xdotool($two_factor_selector);
                        $this->exts->type_text_by_xdotool($two_factor_code);
                        $this->exts->capture("2.2-two-factor-retry-filled-" . $count);
                        sleep(12);
                        $this->exts->capture("2.2-two-factor-retry-submitted-" . $count);
                    }
                }

                if ($this->exts->querySelector($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }

    private function checkFill2FAPushNotification()
    {
        $two_factor_message_selector = '[name="deviceAuthOtp"] > .align-items-center + div';
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
                if ($this->exts->getElement('form.eui-form button[data-oevent*="bouton_relancer_mc"], div.mc-code-invite') == null) {
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


    private function check_solve_blocked_page()
    {
        $this->exts->capture_by_chromedevtool("blocked-page-checking");

        for ($i = 0; $i < 5; $i++) {
            if ($this->exts->check_exist_by_chromedevtool('div[style="display: grid;"] > div > div')) {
                $this->exts->capture_by_chromedevtool("blocked-by-cloudflare");
                $this->exts->refresh();
                sleep(10);

                $this->exts->click_by_xdotool('div[style="display: grid;"] > div > div', 30, 28);
                sleep(15);

                if (!$this->exts->check_exist_by_chromedevtool('div[style="display: grid;"] > div > div')) {
                    break;
                }
            } else {
                break;
            }
        }
    }



    private function checkFillRecaptcha($count = 1)
    {
        $this->exts->log(__FUNCTION__);
        $recaptcha_iframe_selector = 'iframe[src*="/recaptcha/enterprise"]';
        $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
        $this->exts->waitTillPresent($recaptcha_iframe_selector, 20);
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
                $recaptcha_textareas =  $this->exts->querySelectorAll($recaptcha_textarea_selector);
                for ($i = 0; $i < count($recaptcha_textareas); $i++) {
                    $this->exts->execute_javascript("arguments[0].innerHTML = '" . $this->exts->recaptcha_answer . "';", [$recaptcha_textareas[$i]]);
                }
                sleep(2);
                $this->exts->capture('recaptcha-filled');

                $gcallbackFunction = $this->exts->execute_javascript('
        (function() { 
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
        })();
    ');
                $this->exts->log('Callback function: ' . $gcallbackFunction);
                $this->exts->log('Callback function: ' . $this->exts->recaptcha_answer);
                if ($gcallbackFunction != null) {
                    $this->exts->execute_javascript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
                    sleep(10);
                }
            } else {
                // try again if recaptcha expired
                if ($count < 3) {
                    $count++;
                    $this->checkFillRecaptcha($count);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
        }
    }

    // Huy END block

    // private function hoverOnElement($selector_or_object){
    // 	$element = $selector_or_object;
    // 	if(is_string($selector_or_object)){
    // 		$this->exts->log(__FUNCTION__.'::Click selector: ' . $selector_or_object);
    // 		$element = $this->exts->getElement($selector_or_object);
    // 		if($element == null){
    // 			$element = $this->exts->getElement($selector_or_object, null, 'xpath');
    // 		}
    // 		if($element == null){
    // 			$this->exts->log(__FUNCTION__.':: Can not found element with selector/xpath: '. $selector_or_object);
    // 		}
    // 	}
    // 	if($element != null) {
    // 		try {
    // 			if($element->exts->exists()) {
    // 				$actions = $this->exts->webdriver->action();
    // 				$actions->moveToElement($element)->perform();
    // 			} else {
    // 				$this->exts->log(__FUNCTION__ . "::Can not find element by selector" . $selector);
    // 			}
    // 		} catch(\Exception $ex) {
    // 			$this->exts->log(__FUNCTION__ . "::Exception \n");
    // 		}
    // 		sleep(2);
    // 	}
    // }

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
        }
    }
    private function checkFillAppleLogin()
    {
        $this->switchToFrame('iframe[name="aid-auth-widget"]');
        $this->exts->capture("2-apple_login-page");
        if ($this->exts->getElement($this->apple_username_selector) != null) {
            sleep(1);
            $this->exts->log("Enter apple_ Username");
            // $this->exts->getElement($this->apple_username_selector)->clear();
            $this->exts->moveToElementAndClick($this->apple_username_selector);
            sleep(2);
            $this->exts->moveToElementAndType($this->apple_username_selector, $this->username);
            sleep(1);
            $this->exts->moveToElementAndClick($this->apple_submit_login_selector);
            sleep(7);
            $this->exts->click_if_existed('button#continue-password');
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
        } else {
            $this->exts->capture("2-apple_password-page-not-found");
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

            $this->exts->log("Current apple URL - " . $this->exts->getUrl());
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

            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log(__FUNCTION__ . ": Entering apple two_factor_code." . $two_factor_code);
                // $resultCodes = str_split($two_factor_code);
                // $code_inputs = $this->exts->getElements('input[id^="char"]');
                // foreach ($code_inputs as $key => $code_input) {
                //     if(array_key_exists($key, $resultCodes)){
                //         $this->exts->log(__FUNCTION__.': Entering apple key '. $resultCodes[$key] . 'to input #'.$code_input->getAttribute('id'));
                //         $code_input->sendKeys($resultCodes[$key]);
                //         $this->exts->capture("2.2-apple-two-factor-filled-".$this->exts->two_factor_attempts);
                //     } else {
                //         $this->exts->log(__FUNCTION__.': Have no char for input #'.$code_input->getAttribute('id'));
                //     }
                // }
                $this->exts->moveToElementAndClick('input[id^="char"]');

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
    // ==================================END LOGIN WITH APPLE==================================


    private function doAfterLogin()
    {
        if ($this->exts->urlContains('/create-profile/') && $this->exts->exists('li:not(.active) > a[href*="/signup/home?companyReference="]')) {
            $this->exts->click_by_xdotool('li:not(.active) > a[href*="/signup/home?companyReference="]');
            //$this->exts->waitTillPresent($this->check_login_success_selector);
            sleep(15);
        }
        // then check user logged in or not
        if ($this->exts->exists($this->check_login_success_selector) || $this->exts->urlContains('upwork.com/freelancers/~')) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            if ($this->exts->check_exist_by_chromedevtool('#onetrust-banner-sdk:not([style*="display: none"]) #onetrust-accept-btn-handler')) {
                $this->exts->click_by_xdotool('#onetrust-banner-sdk:not([style*="display: none"]) #onetrust-accept-btn-handler');
            }
            $this->exts->capture_by_chromedevtool("3-login-success");

            $period_strings = [];
            // RestricetPage == 0 back to 2 years		
            if ($this->restrictPages == 0) {
                array_push($period_strings, 'startDate=' . date('Y-m-d', strtotime('-6 months')) . '&endDate=' . date('Y-m-d'));
                array_push($period_strings, 'startDate=' . date('Y-m-d', strtotime('-12 months')) . '&endDate=' . date('Y-m-d', strtotime('-6 months')));
                array_push($period_strings, 'startDate=' . date('Y-m-d', strtotime('-18 months')) . '&endDate=' . date('Y-m-d', strtotime('-12 months')));
                array_push($period_strings, 'startDate=' . date('Y-m-d', strtotime('-24 months')) . '&endDate=' . date('Y-m-d', strtotime('-18 months')));
            } else {
                array_push($period_strings, 'startDate=' . date('Y-m-d', strtotime('-3 months')) . '&endDate=' . date('Y-m-d'));
            }
            // print_r($period_strings);

            $this->exts->log(__FUNCTION__ . 'period strings ' . $period_strings);

            // Check multi account
            $accountOpenSelector = '[data-cy="user-menu"], .oCompanyDropdown';
            $this->exts->click_element($accountOpenSelector); // open account dropdown
            sleep(3);
            if (!$this->exts->exists('.oCompanyDropdown li[data-reference], [data-cy="user-menu"] .nav-dropdown-list li[data-cy*="organization"]')) {
                $accountOpenSelector = '[data-cy="user-menu"] > [data-cy="menu-trigger"], .oCompanyDropdown';
                $this->exts->click_element('[data-cy="user-menu"] > [data-cy="menu-trigger"], .oCompanyDropdown'); // open account dropdown
                sleep(3);
            }
            $this->exts->capture("3-account-checking");
            $accounts = $this->exts->getElementsAttribute('.oCompanyDropdown li[data-reference]', 'data-reference');
            if (count($accounts) == 0) {
                $accounts = $this->exts->getElementsAttribute('[data-cy="user-menu"] .nav-dropdown-list li[data-cy*="organization"]', 'data-cy');
            }

            $this->exts->click_element('[data-cy="user-menu"], .oCompanyDropdown'); // close account dropdown
            sleep(3);
            $this->exts->log('ACCOUNTS found: ' . count($accounts));


            if ($accounts > 0) {
                foreach ($accounts as $account_reference) {
                    $account_reference = end(explode('-', $account_reference));
                    $this->exts->log('SWITCH account: ' . $account_reference);

                    if ($this->exts->exists($accountOpenSelector)) {
                        $this->exts->click_element($accountOpenSelector);
                        sleep(3);
                        $this->exts->click_element('.oCompanyDropdown li[data-reference*="' . $account_reference . '"] a, [data-cy="user-menu"] .nav-dropdown-list li[data-cy*="' . $account_reference . '"] a');
                    } else if ($this->exts->urlContains('/create-profile/') && $this->exts->exists('li:not(.active) > a[href*="/signup/home?companyReference="]')) {
                        $this->exts->moveToElementAndClick('li:not(.active) > a[href*="/signup/home?companyReference=' . $account_reference . '"]');
                        sleep(5);
                    }

                    sleep(7);
                    $this->check_solve_blocked_page();

                    // If CLIENT then download invoices
                    // If FREELANCER only download when earning_history = 1

                    //if($this->exts->exists('a[href*="/billing-history"]')){// 2021-10-13 This condition to check Client user is not correct anymore, So update as below
                    if (!$this->exts->exists('#nav-main a[href*="/find-work"]')) {
                        $this->exts->log('This account is Client. Open Client invoice page');
                        // $this->hoverOnElement('#nav-right a[href*="/reports"][data-cy="menu-trigger"]');
                        sleep(2);
                        if ($this->exts->exists('a[href*="/billing-history"], a[href*="/reports/transaction-history"]')) {
                            $this->exts->click_element('a[href*="/billing-history"], a[href*="/reports/transaction-history"]');
                            sleep(5);
                            $this->check_solve_blocked_page();
                            sleep(5);
                            $this->exts->capture("4-account-" . $account_reference);
                            // Select default date to trigger account number displayed on url
                            $this->exts->click_element('.date-period-picker');
                            sleep(3);
                            $this->exts->click_element('.date-period-picker .date-options .pre-select li');
                            sleep(5);
                            $current_url = $this->exts->getUrl();
                            $temp_paths = explode("?", $current_url);
                            foreach ($period_strings as $period_string) {
                                $transaction_url = $temp_paths[0] . '?' . $period_string;
                                $this->exts->log('Go to transaction_url: ' . $transaction_url);
                                $this->exts->openUrl($transaction_url);
                                sleep(5);
                                $this->check_solve_blocked_page();
                                $this->processInvoices();
                            }
                        } else {
                            $this->exts->capture("account-" . $account_reference . "no-transaction-menu");
                        }
                        // } else if($this->earning_history == '1' && $this->exts->exists('a[href*="/earnings-history"]')){
                    } else if ($this->earning_history == '1' && $this->exts->exists('#nav-main a[href*="/find-work"]')) {
                        $this->exts->log('This account is freelancer. Open freelancer invoice page');
                        // $this->hoverOnElement('#nav-right a[href*="/reports"][data-cy="menu-trigger"]');
                        sleep(2);
                        if ($this->exts->exists('a[href*="/earnings-history"], a[href*="/reports/transaction-history"]')) {
                            $this->exts->click_element('a[href*="/earnings-history"], a[href*="/reports/transaction-history"]');
                            sleep(5);
                            $this->check_solve_blocked_page();
                            sleep(5);
                            $this->exts->capture("4-account-" . $account_reference);
                            // Select default date to trigger account number displayed on url
                            $this->exts->click_element('.date-period-picker');
                            sleep(3);
                            $this->exts->click_element('.date-period-picker .date-options .pre-select li');
                            sleep(5);
                            $current_url = $this->exts->getUrl();
                            $temp_paths = explode("?", $current_url);
                            foreach ($period_strings as $period_string) {
                                $transaction_url = $temp_paths[0] . '?' . $period_string;
                                $this->exts->log('Go to transaction_url: ' . $transaction_url);
                                $this->exts->openUrl($transaction_url);
                                sleep(5);
                                $this->check_solve_blocked_page();
                                $this->processInvoices();
                            }
                        } else {
                            $this->exts->capture("account-" . $account_reference . "no-transaction-menu");
                        }
                    }
                }
            } else {
                $this->processInvoices();
            }

            // if earning_history = 0 and no invoice downloaded for CLIENT, try to download freelancer invoices
            if ($this->earning_history == '0' && $this->isNoInvoice) {
                foreach ($accounts as $account_reference) {
                    $this->exts->log('SWITCH account: ' . $account_reference);
                    $this->exts->click_element('[data-cy="user-menu"], .oCompanyDropdown');
                    sleep(3);
                    $this->exts->log('1 test');
                    $this->exts->click_element('.oCompanyDropdown li[data-reference="' . $account_reference . '"] a, [data-cy="user-menu"] .nav-dropdown-list li[data-cy="' . $account_reference . '"] a');
                    sleep(10);

                    // Only download Freelancer invoices in this loop
                    // if($this->exts->exists('a[href*="/earnings-history"]')){
                    if ($this->exts->exists('a[href*="/reports/transaction-history"], #nav-main a[href*="/find-work"]')) {
                        $this->exts->log('Open freelancer invoice page');
                        // $this->hoverOnElement('#nav-right a[href*="/reports"][data-cy="menu-trigger"]');
                        sleep(2);
                        if ($this->exts->exists('a[href*="/earnings-history"], a[href*="/reports/transaction-history"]')) {
                            $this->exts->click_element('a[href*="/earnings-history"], a[href*="/reports/transaction-history"]');
                            sleep(5);
                            $this->check_solve_blocked_page();
                            sleep(5);
                            $this->exts->capture("4-account-" . $account_reference);
                            // Select default date to trigger account number displayed on url
                            $this->exts->click_element('.date-period-picker');
                            sleep(3);
                            $this->exts->click_element('.date-period-picker .date-options .pre-select li');
                            sleep(5);
                            $current_url = $this->exts->getUrl();
                            $temp_paths = explode("?", $current_url);
                            foreach ($period_strings as $period_string) {
                                $transaction_url = $temp_paths[0] . '?' . $period_string;
                                $this->exts->log('Go to transaction_url: ' . $transaction_url);
                                $this->exts->openUrl($transaction_url);
                                sleep(5);
                                $this->check_solve_blocked_page();
                                $this->processInvoices();
                            }
                        } else {
                            $this->exts->capture("account-" . $account_reference . "no-transaction-menu");
                        }
                    }
                }
            }

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log('Login failed ' . $this->exts->getUrl());
            if ($this->exts->urlContains('/create-profile/') || $this->exts->urlContains('/account-recovery/')) {
                $this->exts->log("first acc not ready");
                $this->exts->account_not_ready();
            } else if (stripos($this->exts->extract('button.air3-btn-primary'), 'Create New Profile') !== false || $this->exts->exists('div[data-qa="create-profile-wizard"]')) {
                $this->exts->log("second acc not ready");
                $this->exts->account_not_ready();
            } else if (
                strpos(strtolower($this->exts->extract('.up-form-message-error')), 'passwor') !== false ||
                strpos(strtolower($this->exts->extract('#username-                                                                          ')), 'incorrect') !== false
            ) {
                $this->exts->loginFailure(1);
            } else if ($this->exts->exists("//*[contains(text(),'Verification failed. Please try again')]
")) {
                $this->exts->loginfailure(1);
            } else if ($this->exts->exists('//*[contains(text(), "incorrect")]')) {
                $this->exts->loginfailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }
    private function processInvoices()
    {
        sleep(10);
        $this->check_solve_blocked_page();
        $this->checkFillExtraAuthentication();
        $this->checkFillExtraAuthentication();
        sleep(10);
        if ($this->exts->exists('.onetrust-close-btn-handler')) {
            $this->exts->moveToElementAndClick('.onetrust-close-btn-handler');
        }
        $this->exts->capture("4-invoices-page");
        $this->exts->waitTillPresent("table tbody tr, .trx-list table tbody tr, .trx-list table tbody tr", 60);

        $rows = count($this->exts->getElements('table tbody tr, .trx-list table tbody tr, .trx-list table tbody tr'));
        for ($i = 0; $i < $rows; $i++) {
            $row = $this->exts->getElements('table tbody tr, .trx-list table tbody tr, .trx-list table tbody tr')[$i];
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 6 && ($this->exts->getElement('a', end($tags)) != null || $this->exts->getElement('button', end($tags)) != null) || $row) {
                if ($this->exts->getElement('a', end($tags)) != null) {
                    $ref_id_button = $this->exts->getElement('a', end($tags));
                } else {
                    $ref_id_button = $this->exts->getElement('button', end($tags));
                }

                $invoiceDate = $this->exts->extract('td[data-qa="trx-col-date"]', $row, 'innerText');
                $invoiceName = '';
                $invoiceAmount = explode("\n", trim($tags[count($tags) - 2]->getAttribute('innerText')))[0];
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $invoiceAmount)) . ' USD';

                if ($this->exts->exists('td[data-qa="trx-col-type"]')) {
                    if ($this->statement_only == '1' && preg_match('/PAYMENT|ZAHLUNG/i', $this->exts->extract('td[data-qa="trx-col-type"]', $row, 'innerText')) !== 1) {
                        // If statement_only == 1, only download payment invoice. Ignore the other
                        continue;
                    } else if ($this->only_invoice == '1' && preg_match('/PAYMENT|ZAHLUNG/i', $this->exts->extract('td[data-qa="trx-col-type"]', $row, 'innerText')) === 1) {
                        // If only_invoice == 1, only download invoice. Ignore the receipt
                        continue;
                    }
                }

                if ($ref_id_button) {
                    $this->exts->click_element($ref_id_button);
                } else {
                    sleep(5);
                    $this->exts->click_element($row);
                }

                sleep(3);
                if ($this->exts->exists('div[data-qa="invoices-table"] table button, button[data-ev-label="download_pdf_summary"], .up-modal-body a[href*=".pdf"], .air3-modal-content a[href*=".pdf"], button[data-qa*="download-invoice-pdf"]')) {
                    $this->isNoInvoice = false;
                    $this->exts->log('--------------------------');

                    $this->exts->log('invoiceDate: ' . $invoiceDate);
                    $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                    $parsed_date = $this->exts->parse_date($invoiceDate, 'M j# Y', 'Y-m-d');
                    $this->exts->log('Date parsed: ' . $parsed_date);

                    // $this->exts->waitTillPresent("(//button[@data-qa='download-pdf-summary-btn'])[1]", 50);
                    // $this->exts->click_element("(//button[@data-qa='download-pdf-summary-btn'])[1]");

                    $multiple_invoices = $this->exts->querySelectorAll('div[data-qa="invoices-table"] table button');
                    foreach ($multiple_invoices as $multiple_invoice) {
                        $this->exts->click_element($multiple_invoice);

                        sleep(5);
                        $downloaded_file = $this->exts->find_saved_file('pdf');
                        $this->exts->wait_and_check_download('pdf');

                        $invoiceFileName = basename($downloaded_file);
                        $this->exts->log('invoiceFileName: ' . $invoiceFileName);

                        $invoiceName = substr($invoiceFileName, 0, strrpos($invoiceFileName, '.'));
                        $this->exts->log('invoiceName: ' . $invoiceName);

                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                        } else {
                            $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                        }
                    }

                    $this->exts->waitTillPresent("button[data-qa='slider-close-btn']", 50);
                    $this->exts->click_element("button[data-qa='slider-close-btn']");
                }

                // close new tab too avoid too much tabs
                $this->exts->closeAllTabsButThis();

                $this->exts->click_element('.up-modal-dialog button.close,.up-modal-dialog button.up-modal-close, .air3-modal-content button.air3-modal-close');
                sleep(2);
            }

            // close new tab too avoid too much tabs
            $this->exts->closeAllTabsButThis();
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
