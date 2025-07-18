<?php // updated login code
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

    // Server-Portal-ID: 29237 - Last modified: 06.05.2025 13:56:00 UTC - User: 1

    // start script

    public $baseUrl = "https://app.caya.com/app/folder/inbox";
    public $username_selector = 'input[name="username"]';
    public $password_selector = 'input[name="password"]';
    public $submit_button_selector = 'form button[type=submit]';
    public $login_tryout = 0;
    public $restrictPages = 3;
    public $last_state = array();
    public $current_state = array();
    public $portal_invoice = 0;
    public $portal_tags = "";
    public $check_all = 0;
    public $document_invoices_tags = array();
    public $save_storage = 0;
    public $inbox_fetch = 0;
    public $archive_fetch = 0;
    public $no_invoice = true;


    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */

    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        if ($this->exts->docker_restart_counter == 0) {
            $this->portal_invoice = isset($this->exts->config_array["postal_invoice"]) ? (int)$this->exts->config_array["postal_invoice"] : 0;
            $this->inbox_fetch = isset($this->exts->config_array["inbox_fetch"]) ? (int)$this->exts->config_array["inbox_fetch"] : 0;
            $this->archive_fetch = isset($this->exts->config_array["archive_fetch"]) ? (int)$this->exts->config_array["archive_fetch"] : 0;
            $this->portal_tags = isset($this->exts->config_array["postal_tags"]) ? trim($this->exts->config_array["postal_tags"]) : "";
            $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)$this->exts->config_array["restrictPages"] : 3;
            $this->check_all = isset($this->exts->config_array["check_all"]) ? (int)$this->exts->config_array["check_all"] : 0;
            $this->save_storage = isset($this->exts->config_array["save_storage"]) ? (int)$this->exts->config_array["save_storage"] : $this->save_storage;

            if (empty($this->portal_tags)) $this->portal_tags = "Rechnung";
            $this->portal_tags = strtolower($this->portal_tags);
            $this->exts->log('portal_tags ' . $this->portal_tags);

            $this->document_invoices_tags = explode(",", $this->portal_tags);
            $this->exts->log('document_invoices_tags ' . print_r($this->document_invoices_tags, true));
            if (!empty($this->document_invoices_tags)) {
                $this->portal_tags = '';
                foreach ($this->document_invoices_tags as $s) {
                    if ($this->portal_tags != '') $this->portal_tags .= '|';
                    $this->portal_tags .= preg_quote(trim($s), '/');
                }
            }
        } else {
            $this->last_state = $this->current_state;
        }
        $this->exts->log('portal_tags ' . $this->portal_tags);

        $this->exts->openUrl($this->baseUrl);
        sleep(5);
        $this->exts->capture("Home-page-without-cookie");
        $this->exts->loadCookiesFromFile();

        if (!$this->checkLogin()) {
            if ($this->exts->exists('div#cookiescript_accept')) {
                $this->exts->moveToElementAndClick('div#cookiescript_accept');
                sleep(5);
            }
            if ($this->exts->config_array['login_with_google'] == '1') {
                $this->exts->moveToElementAndClick('button[data-cy="sign-on-with-google-button"]');
                sleep(5);
                $handles = $this->exts->get_all_tabs();
                if (count($handles) > 1) {
                    $this->exts->switchToTab(end($handles));
                }
                sleep(5);
                $this->loginGoogleIfRequired();
                $handles = $this->exts->get_all_tabs();
                $this->exts->switchToTab($handles[0]);
            } else {
                $this->fillForm(0);
                sleep(10);
                if ($this->exts->exists('.ant-modal-wrap button.ant-btn') && $this->exts->exists('.ant-modal-wrap button.ant-btn')) {
                    $this->exts->moveToElementAndClick('.ant-modal-wrap button.ant-btn');
                    sleep(2);
                }
            }
        }


        if ($this->checkLogin()) {

            if ($this->exts->exists('.ant-modal-wrap button.ant-btn') && $this->exts->exists('.ant-modal-wrap button.ant-btn')) {
                $this->exts->moveToElementAndClick('.ant-modal-wrap button.ant-btn');
                sleep(2);
            }

            $this->exts->capture("LoginSuccess");

            // If portal script supports restart docker and resume portal execution
            // Enable this only after successfull login, otherwise no need to process restart
            // $this->support_restart = true;

            $this->processAfterLogin(0);
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $logged_in_failed_selector = $this->exts->getElementByText('div', ['password', 'Passwort'], null, false);
            if ($logged_in_failed_selector != null) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    function relocated_row($searching_text, $selector, $scroll_parent_selector)
    {
        $searching_element = null;
        $rows_after_refreshing = $this->exts->getElements($selector);
        foreach ($rows_after_refreshing as $row_looping) {
            if (strcmp($searching_text, $row_looping->getText()) == 0) {
                $this->exts->log('Current row is located again');
                $searching_element = $row_looping;
                break;
            }
        }
        if ($searching_element == null) {
            for ($finding_step = 1; $finding_step < 300 && $searching_element == null; $finding_step++) {
                $this->exts->log('Scroll down to find row.');
                $scroll_bar = $this->exts->getElement($scroll_parent_selector);
                $this->exts->executeSafeScript('
                var scrollBar = arguments[0];
                scrollBar.scrollTop = scrollBar.scrollTop + 3*46;
            ', [$scroll_bar]);
                sleep(1);
                $rows_after_refreshing = $this->exts->getElements($selector);
                foreach ($rows_after_refreshing as $row_looping) {
                    if (strcmp($searching_text, $row_looping->getText()) == 0) {
                        $this->exts->log('Current row is located again by scrolling down');
                        $searching_element = $row_looping;
                        break;
                    }
                }
            }
        }
        if ($searching_element == null) {
            $this->exts->log('ERORR: Cannot located current row');
            $this->exts->log($searching_text);
        }
        return $searching_element;
    }

    // function moveToElement($selector_or_object, $parent = null, $offset_x=null, $offset_y=null) {
    //     if($selector_or_object == null){
    //         $this->exts->log(__FUNCTION__.' Can not click null');
    //         return;
    //     }
    //     if(is_string($selector_or_object)){
    //         $selector_or_object = $this->exts->getElement($selector_or_object, $parent);
    //     }

    //     if($selector_or_object != null) {
    //         try {
    //             $this->exts->webdriver->getMouse()->mouseMove($selector_or_object->getCoordinates(), $offset_x, $offset_y);
    //         } catch(\Exception $ex) {
    //             $this->exts->log(__FUNCTION__ . "::Exception " . $ex);
    //         }
    //         sleep(1);
    //     }
    // }

    private function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {
            if ($this->exts->querySelector($this->password_selector) != null || $this->exts->querySelector($this->username_selector) != null) {
                $this->login_tryout = (int)$this->login_tryout + 1;
                $this->exts->capture("1-pre-login");

                if ($this->exts->querySelector($this->username_selector) != null && $this->exts->querySelector($this->username_selector)) {
                    $this->exts->log("Enter Username");
                    $this->exts->moveToElementAndType($this->username_selector, $this->username);
                    sleep(10);
                }

                if ($this->exts->querySelector($this->password_selector) != null) {
                    $this->exts->log("Enter Password");
                    $this->exts->moveToElementAndType($this->password_selector, $this->password);
                    sleep(10);
                }

                $this->exts->capture("2-filled-login");
                sleep(10);
                $this->exts->click_element($this->submit_button_selector);
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

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

    private function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            $this->exts->waitTillPresent('button[data-testid="user-account-menu"]');
            if ($this->exts->getElement('button[data-testid="user-account-menu"]') != null) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }

        return $isLoggedIn;
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
    function processAfterLogin($count)
    {
        $this->exts->log("Begin processAfterLogin " . $count);

        if ($this->exts->exists('.ant-modal-wrap button.ant-btn')) {
            $this->exts->moveToElementAndClick('.ant-modal-wrap button.ant-btn');
            sleep(2);
        }
        if ($this->exts->exists('div#cookiescript_accept')) {
            $this->exts->moveToElementAndClick('div#cookiescript_accept');
            sleep(2);
        }

        if ($this->portal_invoice == 1 || ($this->inbox_fetch == 0 && $this->portal_invoice == 0)) {
            $this->exts->openUrl("https://app.caya.com/app/settings/subscription");
            sleep(20);
            $this->processBilling();
        }

        if ($this->inbox_fetch == 1) {
            $this->exts->openUrl("https://app.caya.com/app/folder/inbox");
            sleep(5);
            $this->download_inbox_document();
        }

        if ($this->archive_fetch == 1) {
            $this->exts->openUrl("https://app.caya.com/app/archive");
            sleep(20);
            // Archive folder maybe contains tree of sub-folder, we dont loop through all sub-folder, we switch to search by tags and download from flat page instead.
            if ($this->exts->exists('#caya-archivescreen-table [class*="FolderRow__Container"]')) {
                $this->download_archive_by_searching_tags();
            } else {
                $this->download_archive_document();
            }
        }


        if ($this->no_invoice) {
            if ($this->inbox_fetch == 1) {
                $this->exts->openUrl("https://usecaya.com/app/folder/inbox");
                sleep(20);
            } else if ($this->portal_invoice == 1) {
                $this->exts->openUrl("https://app.caya.com/app/settings/subscription");
                sleep(20);
            }
            $this->exts->no_invoice();
        }
    }
    function getElementsInnerTextByJS($selector, $parent = null, $type = "css")
    {
        $elements = $this->exts->getElements($selector, $parent, $type);
        array_walk($elements, function (&$element) {
            $element = $this->exts->executeSafeScript("return arguments[0].innerText", [$element]);
        });
        return $elements;
    }
    function download_inbox_document()
    {
        sleep(20);
        $this->exts->capture('inbox-page');
        $this->exts->log('postal_tags:' . $this->portal_tags);
        $this->exts->executeSafeScript('
        var blocked_guis = document.querySelectorAll(\'#refiner-widget-wrapper[style*="display: block"], div[style*=" z-index: 999999"], div[class*="SupportCard"] \');
        for (var index = 0; index < blocked_guis.length; index++) {
            blocked_guis[index].remove();
        }
        return blocked_guis.length > 0;			
    ');
        sleep(5);
        if ($this->exts->exists('div.ant-modal-wrap[role="dialog"] .ant-modal-footer button[class*="Primary"]')) {
            $this->exts->moveToElementAndClick('div.ant-modal-wrap[role="dialog"] .ant-modal-footer button[class*="Primary"]');
            sleep(2);
        }
        if ($this->exts->exists('div[class*="InfoPopup__Content"] div[class*="InfoPopup__CloseButton"')) {
            $this->exts->moveToElementAndClick('div[class*="InfoPopup__Content"] div[class*="InfoPopup__CloseButton"]');
            sleep(2);
        }

        $this->exts->capture('inbox-page-1');
        if ($this->exts->exists('#caya-folderScreen-table [data-test-id="virtuoso-item-list"] > div')) {
            $next_row = $this->exts->getElements('#caya-folderScreen-table [data-test-id="virtuoso-item-list"] > div')[0];
            //loop using $step_count to avoid infinity loop if somehow, moving mail to archive doesn't work.
            for ($step_count = 1; $step_count < 500 && $next_row != null; $step_count++) {
                $this->exts->log('--------------------------');
                $this->exts->log('Finding Tags in row: ' . $step_count);
                $this->exts->executesafeScript("arguments[0].scrollIntoView(true);", [$next_row]);
                sleep(5);
                $current_row = $next_row;
                $this->exts->click_element('body', null, 1, 1);
                $current_text = $current_row->getText();
                $moved_to_archive = false;

                // Download document if it contains setting tags
                $mail_tags_array = $this->getElementsInnerTextByJS('[data-testid="foldertable-document-tags-cell"],[class*="TagsCell__Container"], [class*="Tag__Container"][class*="TagsCell"]', $current_row);
                $mail_tags = join(" ", $mail_tags_array);
                $this->exts->log('mail_tags: ' . $mail_tags);
                $tag_found = false;
                $tags_array = preg_split("/[\|\,\;]/", $this->portal_tags); // explode('|',$this->portal_tags);
                foreach ($tags_array as $tag) {
                    $tag = strtolower(trim($tag));
                    // If check_all == 1 then mail must contain all inputted tags, else just need to match at least one.
                    if ($this->check_all == 1) {
                        if (stripos($mail_tags, $tag) !== false) {
                            $this->exts->log('tag found: ' . $tag);
                            $tag_found = true;
                        } else {
                            $this->exts->log('tag not found: ' . $tag);
                            $tag_found = false;
                            break;
                        }
                    } else {
                        if (stripos($mail_tags, $tag) !== false) {
                            $this->exts->log('tag found: ' . $tag);
                            $tag_found = true;
                            break;
                        }
                    }
                }

                if ($tag_found) {
                    // remove any unexpected popup
                    $removed_gui_blocked = $this->exts->executeSafeScript('
                    var blocked_guis = document.querySelectorAll(\'#refiner-widget-wrapper[style*="display: block"], div[style*=" z-index: 999999"]\');
                    for (var index = 0; index < blocked_guis.length; index++) {
                        blocked_guis[index].remove();
                    }
                    return blocked_guis.length > 0;		
                ');
                    $this->exts->log('removed_gui_blocked:' . $removed_gui_blocked);
                    $this->exts->click_element('body', null, 1, 1);
                    sleep(1);
                    $this->exts->log('move to targeted row');
                    $this->exts->click_element($current_row);
                    sleep(3);
                    $this->exts->click_element($current_row); // move to row twice to avoid an exception when the row is out of view
                    // $context_menu = $this->exts->getElement('[class*="ContextMenu__DropdownControl"][class*="ant-dropdown-trigger"]', $current_row);
                    // $this->moveToElement($context_menu);
                    // Huy update this since it changed 2023-08
                    $context_menu = $this->exts->getElement('[class*="ActionsCell"] [class*="ant-dropdown-trigger"]', $current_row);
                    $this->exts->click_element($context_menu);
                    sleep(3);
                    // $download_button = $this->exts->getElement('[class*="ActionsMenu__Container"] [title="Download"], [class*="ActionsMenu__Container"] [title="Herunterladen"]', $current_row);
                    $download_button = $this->exts->getElement('[class*="ActionsCell"],.ant-dropdown-menu-item[data-menu-id$="-download"]');
                    if ($download_button != null) {
                        $this->no_invoice = false;
                        $data_testid = $this->exts->getElement('[class*="DocumentRow"][data-testid*="folder-table:document-row:"]', $current_row)->getAttribute('data-testid');
                        $temp_array = explode('-row:', $data_testid);
                        $invoice_name = end($temp_array);
                        if ($this->exts->invoice_exists($invoice_name)) {
                            $this->exts->log('Invoice Existed: ' . $invoice_name);
                            $this->exts->update_process_lock();
                        } else {
                            $this->exts->log('Downloading document');
                            $this->exts->executeSafeScript("arguments[0].click()", [$download_button]); // We should trigger download button by js because if move out of dropdown menu button, download button may disapper
                            sleep(5);
                            $download_button_new = $this->exts->getElement('[class*="ActionsCell"]  button:first-child');
                            $this->exts->executeSafeScript("arguments[0].click()", [$download_button_new]);
                            sleep(5);
                            $this->exts->wait_and_check_download('pdf');
                            $downloaded_file = $this->exts->find_saved_file('pdf', $invoice_name . '.pdf');
                            if (empty($downloaded_file)) {
                                $this->exts->log('Download failed, try again');
                                $this->exts->click_element('body', null, 1, 1);
                                sleep(2);
                                $current_row = $this->relocated_row($current_text, '#caya-folderScreen-table [data-test-id="virtuoso-item-list"] > div', "#caya-folderScreen-table");
                                $this->exts->log('move to targeted row');
                                $this->exts->click_element($current_row);
                                sleep(3);
                                $this->exts->click_element($current_row); // move to row twice to avoid an exception when the row is out of view
                                // $context_menu = $this->exts->getElement('[class*="ContextMenu__DropdownControl"][class*="ant-dropdown-trigger"]', $current_row);
                                // $this->moveToElement($context_menu);
                                // Huy update this since it changed 2023-08
                                $context_menu = $this->exts->getElement('[class*="ActionsCell"] [class*="ant-dropdown-trigger"]', $current_row);
                                $this->exts->click_element($context_menu);
                                sleep(3);
                                $this->exts->capture('inbox-page-before-download');
                                $download_button = $this->exts->getElement('.ant-dropdown-menu-item[data-menu-id$="-download"]');
                                if ($download_button != null) {
                                    $this->exts->executeSafeScript("arguments[0].click()", [$download_button]); // We should trigger download button by js because if move out of dropdown menu button, download button may disapper
                                    sleep(5);
                                    $download_button_new = $this->exts->getElement('[class*="ActionsCell"]  button:first-child');
                                    $this->exts->executeSafeScript("arguments[0].click()", [$download_button_new]);
                                    sleep(5);

                                    $this->exts->wait_and_check_download('pdf');
                                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoice_name . '.pdf');
                                }
                            }

                            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                                $this->exts->new_invoice($invoice_name, '', '', $downloaded_file);
                                sleep(1);
                            } else {
                                $this->exts->log(__FUNCTION__ . '::No download ');
                            }

                            $this->exts->click_element('body', null, 1, 1);
                            sleep(1);
                            $current_row = $this->relocated_row($current_text, '#caya-folderScreen-table [data-test-id="virtuoso-item-list"] > div', "#caya-folderScreen-table");
                            if ($current_row == null) {
                                $this->exts->capture('inbox-download-abnormal-error');
                                return;
                            }
                        }

                        // Move mail to Archive if save_storage enabled and pdf downloaded successfully
                        if ($this->save_storage == 1 && trim($downloaded_file) != '') { // maybe add condition invoice existed here
                            $this->exts->log('Moving mail to archive');
                            $this->exts->click_element($current_row);
                            sleep(1);
                            // $this->moveToElement('[class*="ContextMenu__DropdownControl"][class*="ant-dropdown-trigger"]', $current_row);
                            // sleep(3);
                            // $move_button = $this->exts->getElement('.ant-dropdown-menu-item[data-menu-id*="moveToFolder"]');
                            // Huy update this since it changed 2023-08
                            $context_menu = $this->exts->getElement('[class*="ActionsCell"] [class*="ant-dropdown-trigger"]', $current_row);
                            $this->exts->click_element($context_menu);
                            sleep(2);
                            $move_button = $this->exts->getElement('.ant-dropdown-menu-item[data-menu-id*="moveToArchiveRoot"]');
                            if ($move_button != null) {
                                $this->exts->executeSafeScript('arguments[0].click()', [$move_button]);
                                sleep(7);
                            } else {
                                $this->exts->capture('save-storage-error');
                            }

                            // Check to make sure current email removed
                            $this->exts->click_element('body', null, 1, 1);
                            sleep(1);
                            if (strcmp($current_text, $current_row->getText()) == 0) {
                                $this->exts->log('Seem Moving does not work.');
                                $this->exts->log('Move again');
                                $this->exts->click_element($current_row);
                                sleep(1);
                                // $this->moveToElement('[class*="ContextMenu__DropdownControl"][class*="ant-dropdown-trigger"]', $current_row);
                                // sleep(2);
                                // $move_button = $this->exts->getElement('.ant-dropdown-menu-item[data-menu-id*="moveToFolder"]');
                                // Huy update this since it changed 2023-08
                                $context_menu = $this->exts->getElement('[class*="ActionsCell"] [class*="ant-dropdown-trigger"]', $current_row);
                                $this->exts->click_element($context_menu);
                                sleep(2);
                                $move_button = $this->exts->getElement('.ant-dropdown-menu-item[data-menu-id*="moveToArchiveRoot"]');
                                if ($move_button != null) {
                                    $this->exts->executeSafeScript('arguments[0].click()', [$move_button]);
                                    sleep(7);
                                } else {
                                    $this->exts->capture('save-storage-error');
                                }
                            }

                            $this->exts->click_element('body', null, 1, 1);
                            sleep(1);
                            if (strcmp($current_text, $current_row->getText()) == 0) {
                                $this->exts->log('Seem Moving does not work.');
                            } else {
                                $this->exts->log('Moving completed.');
                                $moved_to_archive = true;
                            }
                        }
                    }
                    $this->exts->click_element('body', null, 1, 1);
                    sleep(3);
                }
                // check if have next mail row
                if ($moved_to_archive) {
                    $next_row = $current_row;
                    $this->exts->click_element('body', null, 1, 1);
                    sleep(1);
                } else {
                    $next_row = $this->exts->getElement('./following-sibling::div', $current_row, 'xpath');
                    if ($next_row == null) {
                        $this->exts->click_element('body', null, 1, 1); // Move mouse out of current row
                        sleep(1);
                        // If It don't have next row, try to scroll down with a height of 2 row, then it will load more row.
                        $this->exts->executeSafeScript('
                        var scrollBar = document.querySelector("#caya-folderScreen-table");
                        scrollBar.scrollTop = scrollBar.scrollTop + 2*46;
                    ');
                        sleep(3);
                        $current_row = $this->relocated_row($current_text, '#caya-folderScreen-table [data-test-id="virtuoso-item-list"] > div', "#caya-folderScreen-table");
                        $next_row = $this->exts->getElement('./following-sibling::div', $current_row, 'xpath');
                    }
                }
            }
        }
    }
    function download_archive_document()
    {
        sleep(15);
        $this->exts->capture('archive-page');
        $this->exts->log('postal_tags:' . $this->portal_tags);
        $this->exts->executeSafeScript('
        var blocked_guis = document.querySelectorAll(\'#refiner-widget-wrapper[style*="display: block"], div[style*=" z-index: 999999"], div[class*="SupportCard"] \');
        for (var index = 0; index < blocked_guis.length; index++) {
            blocked_guis[index].remove();
        }
        return blocked_guis.length > 0;
    ');
        sleep(5);
        if ($this->exts->exists('div.ant-modal-wrap[role="dialog"] .ant-modal-footer button[class*="Primary"]')) {
            $this->exts->moveToElementAndClick('div.ant-modal-wrap[role="dialog"] .ant-modal-footer button[class*="Primary"]');
            sleep(2);
        }
        if ($this->exts->exists('div[class*="InfoPopup__Content"] div[class*="InfoPopup__CloseButton"')) {
            $this->exts->moveToElementAndClick('div[class*="InfoPopup__Content"] div[class*="InfoPopup__CloseButton"]');
            sleep(2);
        }

        $this->exts->capture('archive-page-1');
        if ($this->exts->exists('#caya-archivescreen-table [data-test-id="virtuoso-item-list"] > div')) {
            $next_row = $this->exts->getElements('#caya-archivescreen-table [data-test-id="virtuoso-item-list"] > div')[0];
            //loop using $step_count to avoid infinity loop if somehow, the condition is wrong.
            for ($step_count = 0; $step_count < 500 && $next_row != null; $step_count++) {
                $this->exts->log('--------------------------');
                $this->exts->log('Finding Tags in row: ' . $step_count);
                $this->exts->executesafeScript("arguments[0].scrollIntoView(true);", [$next_row]);
                sleep(5);
                $current_row = $next_row;
                $current_text = $current_row->getText();
                $this->exts->log('Finding Tags in row: ' . $current_text);
                $mail_tags_array = $this->getElementsInnerTextByJS('[data-testid="foldertable-document-tags-cell"],[class*="TagsCell__Container"], [class*="Tag__Container"][class*="TagsCell"]', $current_row);
                $mail_tags = join(" ", $mail_tags_array);
                $this->exts->log('mail_tags: ' . $mail_tags);
                $tag_found = false;
                $tags_array = preg_split("/[\|\,\;]/", $this->portal_tags);
                foreach ($tags_array as $tag) {
                    // If check_all == 1 then mail must contain all inputted tags, else just need to match at least one.
                    if ($this->check_all == 1) {
                        if (stripos($mail_tags, $tag) !== false) {
                            $this->exts->log('tag found: ' . $tag);
                            $tag_found = true;
                        } else {
                            $this->exts->log('tag not found: ' . $tag);
                            $tag_found = false;
                            break;
                        }
                    } else {
                        if (stripos($mail_tags, $tag) !== false) {
                            $this->exts->log('tag found: ' . $tag);
                            $tag_found = true;
                            break;
                        }
                    }
                }
                if ($tag_found) {
                    // remove any unexpected popup
                    $removed_gui_blocked = $this->exts->executeSafeScript('
                    var blocked_guis = document.querySelectorAll(\'#refiner-widget-wrapper[style*="display: block"], div[style*=" z-index: 999999"]\');
                    for (var index = 0; index < blocked_guis.length; index++) {
                        blocked_guis[index].remove();
                    }
                    return blocked_guis.length > 0;		
                ');
                    $this->exts->log('removed_gui_blocked:' . $removed_gui_blocked);
                    $this->exts->click_element('body', null, 1, 1);
                    sleep(1);
                    $this->exts->log('move to targeted row');
                    $this->exts->click_element($current_row);
                    sleep(3);
                    $this->exts->click_element($current_row); // move to row twice to avoid an exception when the row is out of view
                    // $context_menu = $this->exts->getElement('[class*="ContextMenu__DropdownControl"][class*="ant-dropdown-trigger"]', $current_row);
                    // $this->moveToElement($context_menu);
                    $context_menu = $this->exts->getElement('[class*="ActionsCell"] [class*="ant-dropdown-trigger"]', $current_row);
                    $this->exts->click_element($context_menu);
                    sleep(3);

                    // $download_button = $this->exts->getElement('[class*="ActionsMenu__Container"] [title="Download"], [class*="ActionsMenu__Container"] [title="Herunterladen"]', $current_row);
                    $download_button = $this->exts->getElement('[class*="ActionsCell"],.ant-dropdown-menu-item[data-menu-id$="-download"]');
                    if ($download_button != null) {
                        $this->no_invoice = false;
                        $data_testid = $this->exts->getElement('[class*="DocumentRow"][data-testid*="folder-table:document-row:"]', $current_row)->getAttribute('data-testid');
                        $temp_array = explode('-row:', $data_testid);
                        $invoice_name = end($temp_array);
                        if ($this->exts->invoice_exists($invoice_name)) {
                            $this->exts->log('Invoice Existed: ' . $invoice_name);
                        } else {
                            $this->exts->log('Downloading document');
                            // try {
                            //     $download_button->click();
                            // } catch(\Exception $exception){
                            //     $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                            // }
                            $this->exts->executeSafeScript("arguments[0].click()", [$download_button]); // We should trigger download button by js because if move out of dropdown menu button, download button may disapper
                            sleep(5);

                            $download_button_new = $this->exts->getElement('[class*="ActionsCell"]  button:first-child');
                            $this->exts->executeSafeScript("arguments[0].click()", [$download_button_new]);
                            sleep(5);

                            $this->exts->wait_and_check_download('pdf');
                            $downloaded_file = $this->exts->find_saved_file('pdf', $invoice_name . '.pdf');
                            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                                $this->exts->new_invoice($invoice_name, '', '', $downloaded_file);
                                sleep(1);
                            } else {
                                $this->exts->log(__FUNCTION__ . '::No download ');
                            }
                        }
                    }
                }

                $this->exts->click_element('body', null, 1, 1);
                sleep(10);
                $current_row = $this->relocated_row($current_text, '#caya-archivescreen-table [data-test-id="virtuoso-item-list"] > div', "#caya-archivescreen-table");
                // check if have next mail row
                if ($current_row != null) {
                    $next_row = $this->exts->getElement('./following-sibling::div', $current_row, 'xpath');
                    if ($next_row == null) {
                        $this->exts->click_element('body', null, 1, 1);
                        $current_text = $current_row->getText();
                        // If It doesn't have next row, try to scroll down with a height of 2 row, then it will load more row.
                        $this->exts->executeSafeScript('
                        var scrollBar = document.querySelector("#caya-archivescreen-table");
                        scrollBar.scrollTop = scrollBar.scrollTop + 2*45;
                    ');
                        sleep(13);
                        $current_row = $this->relocated_row($current_text, '#caya-archivescreen-table [data-test-id="virtuoso-item-list"] > div', "#caya-archivescreen-table");
                        $next_row = $this->exts->getElement('./following-sibling::div', $current_row, 'xpath');
                    }
                } else {
                    $this->exts->capture('archive-download-abnormal-error');
                    $next_row = null;
                }
            }
        }
    }
    function download_archive_by_searching_tags()
    {
        sleep(15);
        $this->exts->capture('archive-page');
        $this->exts->log('postal_tags:' . $this->portal_tags);
        $tags_array = preg_split("/[\|\,\;]/", $this->portal_tags);
        $this->exts->executeSafeScript('
        var blocked_guis = document.querySelectorAll(\'#refiner-widget-wrapper[style*="display: block"], div[style*=" z-index: 999999"], div[class*="SupportCard"]\');
        for (var index = 0; index < blocked_guis.length; index++) {
            blocked_guis[index].remove();
        }
        return blocked_guis.length > 0;
    ');
        sleep(5);
        if ($this->exts->exists('div.ant-modal-wrap[role="dialog"] .ant-modal-footer button[class*="Primary"]')) {
            $this->exts->moveToElementAndClick('div.ant-modal-wrap[role="dialog"] .ant-modal-footer button[class*="Primary"]');
            sleep(2);
        }
        if ($this->exts->exists('div[class*="InfoPopup__Content"] div[class*="InfoPopup__CloseButton"')) {
            $this->exts->moveToElementAndClick('div[class*="InfoPopup__Content"] div[class*="InfoPopup__CloseButton"]');
            sleep(2);
        }
        $this->exts->capture('archive-page-1');

        // Search tags
        $search_text = join(' ', $tags_array);
        $this->exts->log('search_text:' . $search_text);
        $this->exts->moveToElementAndClick('[class*="SearchContainer__Container"] input[class*="SearchInput"]');
        $this->exts->moveToElementAndType('[class*="SearchContainer__Container"] input[class*="SearchInput"]', $search_text);
        sleep(1);
        $this->exts->capture('searching-filled');
        $this->exts->moveToElementAndClick('[class*="SearchContainer__Container"] button[class*="SubmitButton"]');
        sleep(20);

        if ($this->exts->exists('#caya-searchScreen-table [data-test-id="virtuoso-item-list"] > div , [data-test-id="virtuoso-item-list"] > div')) {
            $next_row = $this->exts->getElements('#caya-searchScreen-table [data-test-id="virtuoso-item-list"] > div, [data-test-id="virtuoso-item-list"] > div')[0];
            //loop using $step_count to avoid infinity loop if somehow, the condition is wrong.
            for ($step_count = 1; $step_count < 500 && $next_row != null; $step_count++) {
                $this->exts->log('--------------------------');
                $this->exts->log('Finding Tags in row: ' . $step_count);
                $this->exts->executesafeScript("arguments[0].scrollIntoView(true);", [$next_row]);
                sleep(5);
                $current_row = $next_row;
                $current_text = $current_row->getText();

                $mail_tags_array = $this->getElementsInnerTextByJS('[data-testid="foldertable-document-tags-cell"],[class*="TagsCell__Container"], [class*="Tag__Container"][class*="TagsCell"]', $current_row);
                $mail_tags = join(" ", $mail_tags_array);
                $this->exts->log('mail_tags: ' . $mail_tags);
                $tag_found = false;
                foreach ($tags_array as $tag) {
                    // If check_all == 1 then mail must contain all inputted tags, else just need to match at least one.
                    if ($this->check_all == 1) {
                        if (stripos($mail_tags, $tag) !== false) {
                            $this->exts->log('tag found: ' . $tag);
                            $tag_found = true;
                        } else {
                            $this->exts->log('tag not found: ' . $tag);
                            $tag_found = false;
                            break;
                        }
                    } else {
                        if (stripos($mail_tags, $tag) !== false) {
                            $this->exts->log('tag found: ' . $tag);
                            $tag_found = true;
                            break;
                        }
                    }
                }
                if ($tag_found) {
                    // remove any unexpected popup
                    $removed_gui_blocked = $this->exts->executeSafeScript('
                    var blocked_guis = document.querySelectorAll(\'#refiner-widget-wrapper[style*="display: block"], div[style*=" z-index: 999999"], div[class*="SupportCard"]\');
                    for (var index = 0; index < blocked_guis.length; index++) {
                        blocked_guis[index].remove();
                    }
                    return blocked_guis.length > 0;		
                ');
                    $this->exts->log('removed_gui_blocked:' . $removed_gui_blocked);
                    $this->exts->click_element('body', null, 1, 1);
                    sleep(1);
                    $this->exts->log('move to targeted row');
                    $this->exts->click_element($current_row);
                    sleep(3);
                    $this->exts->click_element($current_row); // move to row twice to avoid an exception when the row is out of view
                    $context_menu = $this->exts->getElement('[class*="ActionsCell"] [class*="ant-dropdown-trigger"],[class*="ContextMenu__DropdownControl"][class*="ant-dropdown-trigger"]', $current_row);


                    if ($context_menu != null) {
                        $this->exts->click_element($context_menu);
                        sleep(3);
                        // $download_button = $this->exts->getElement('[class*="ActionsMenu__Container"] [title="Download"], [class*="ActionsMenu__Container"] [title="Herunterladen"]', $current_row);
                        // $download_button = $this->exts->getElement('.ant-dropdown-menu-item[data-menu-id$="-download"]');
                        $download_button = $this->exts->getElement('[class*="ActionsCell"],.ant-dropdown-menu-item[data-menu-id$="-download"]');
                        if ($download_button != null) {
                            $this->no_invoice = false;
                            $this->exts->log('Downloading document');
                            $this->exts->executeSafeScript("arguments[0].click()", [$download_button]); // We should trigger download button by js because if move out of dropdown menu button, download button may disapper
                            sleep(5);
                            $this->exts->wait_and_check_download('pdf');
                            $downloaded_file = $this->exts->find_saved_file('pdf');
                            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                                $invoiceName = basename($downloaded_file, '.pdf');
                                $invoiceName = preg_replace('/[^\w]/', '', $invoiceName);
                                $new_file_name = $this->exts->config_array['download_folder'] . $invoiceName . '.pdf';
                                @rename($downloaded_file, $new_file_name);

                                if (file_exists($new_file_name)) {
                                    $downloaded_file = $new_file_name;
                                }
                                $this->exts->new_invoice($invoiceName, '', '', $downloaded_file);
                                sleep(1);
                            } else {
                                $this->exts->log(__FUNCTION__ . '::No download ');
                            }
                        }
                    } else {
                        $row_checkbox = $this->exts->getElement('div[class*="CheckboxCell__CheckboxCellContainer"],button [data-testid="checkbox"]', $current_row);
                        $this->exts->click_element($row_checkbox);
                        sleep(1);
                        if ($this->exts->exists('div[class*="SingleItemActions"] > button:first-child[class*="ActionIconButton_"],div[class*="MultiItemActionsMenu"] button svg [d="M7 10L12 15L17 10"]')) {
                            $this->no_invoice = false;
                            $this->exts->log('Downloading document');
                            //$this->click_element('//*[@d="M7 10L12 15L17 10"]/../..');
                            $this->exts->click_element('div[class*="SingleItemActions"] > button:first-child[class*="ActionIconButton_"]');

                            sleep(5);
                            $this->exts->wait_and_check_download('pdf');
                            $downloaded_file = $this->exts->find_saved_file('pdf');
                            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                                $invoiceName = basename($downloaded_file, '.pdf');
                                $invoiceName = preg_replace('/[^\w]/', '', $invoiceName);
                                $new_file_name = $this->exts->config_array['download_folder'] . $invoiceName . '.pdf';
                                @rename($downloaded_file, $new_file_name);

                                if (file_exists($new_file_name)) {
                                    $downloaded_file = $new_file_name;
                                }
                                $this->exts->new_invoice($invoiceName, '', '', $downloaded_file);
                                sleep(1);
                            } else {
                                $this->exts->log(__FUNCTION__ . '::No download ');
                            }

                            $row_checkbox = $this->exts->getElement('button [data-testid="checkbox"]', $current_row); // uncheck current row
                            $this->exts->click_element($row_checkbox);
                            sleep(1);
                        }

                        if ($this->exts->exists('div[class*="MultiItemActionsMenu"] button svg [d="M7 10L12 15L17 10"]')) {
                            $row_checkbox = $this->exts->getElement('button [data-testid="checkbox"]', $current_row); // uncheck current row
                            $this->exts->click_element($row_checkbox);
                            sleep(1);
                        }
                    }
                }

                $this->exts->click_element('body', null, 1, 1);
                sleep(1);
                $current_row = $this->relocated_row($current_text, '#caya-searchScreen-table [data-test-id="virtuoso-item-list"] > div', "#caya-searchScreen-table");
                // check if have next mail row
                if ($current_row != null) {
                    $next_row = $this->exts->getElement('./following-sibling::div', $current_row, 'xpath');
                    if ($next_row == null) {
                        $this->exts->click_element('body', null, 1, 1);
                        $current_text = $current_row->getText();
                        // If It doesn't have next row, try to scroll down with a height of 2 row, then it will load more row.
                        $this->exts->executeSafeScript('
                        var scrollBar = document.querySelector("#caya-searchScreen-table");
                        scrollBar.scrollTop = scrollBar.scrollTop + 2*45;
                    ');
                        sleep(3);
                        $current_row = $this->relocated_row($current_text, '#caya-searchScreen-table [data-test-id="virtuoso-item-list"] > div', "#caya-searchScreen-table");
                        $next_row = $this->exts->getElement('./following-sibling::div', $current_row, 'xpath');
                    }
                } else {
                    $this->exts->capture('searching-download-abnormal-error');
                    $next_row = null;
                }
            }
        }
    }
    function processBilling()
    {
        $this->exts->capture('subscription-billing');
        try {
            $this->exts->moveToElementAndClick('[data-testid="settings-subscription-plan-button-manage"]'); // Click Manager Subcription
            sleep(20);

            if ($this->exts->exists("iframe#cb-frame")) {
                $this->switchToFrame("iframe#cb-frame");
                sleep(10);

                if (!$this->exts->exists('div[data-cb-id="portal_billing_history"]')) {
                    sleep(45);
                }
                if ($this->exts->exists('div[data-cb-id="portal_billing_history"]')) {
                    $this->exts->moveToElementAndClick('div[data-cb-id="portal_billing_history"]');
                    sleep(15);

                    if (!$this->exts->exists('div.cb-history__list div.cb-invoice')) {
                        sleep(45);
                    }

                    if ($this->exts->exists('div.cb-history__list div.cb-invoice')) {
                        $rows = $this->exts->querySelectorAll('div.cb-history__list div.cb-invoice');
                        $total_rows = count($rows);
                        $this->exts->log("Total Subscription Invoice - " . $total_rows);
                        if ($total_rows > 0) {
                            $this->no_invoice = false;
                            foreach ($rows as $key => $row) {
                                $invoice_date = $this->exts->querySelector('.cb-invoice__text', $row);
                                $invoice_date = str_replace(".", "", trim($invoice_date->getText()));
                                $this->exts->log("invoice_date - " . $invoice_date);

                                $parsed_date = $this->exts->parse_date($invoice_date, 'd M Y', 'Y-m-d');
                                $this->exts->log("parsed invoice_date - " . $parsed_date);

                                $invoice_amount = $this->exts->querySelector('.cb-invoice__price', $row);
                                $invoice_amount = preg_replace('/[^\d\.,]/m', '', trim($invoice_amount->getText()));
                                $this->exts->log("invoice_amount - " . $invoice_amount);

                                $invoice_name = "";

                                $invoice_download = $this->exts->querySelector('.cb-invoice__link', $row);
                                $invoice_download->click();
                                sleep(10);

                                // Wait for completion of file download
                                $this->exts->wait_and_check_download("pdf");

                                $downloaded_file = $this->exts->find_saved_file("pdf", "");
                                if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                                    $filename = basename($downloaded_file);
                                    $invoice_name = basename($downloaded_file, ".pdf");
                                    $this->exts->log("invoice filename - " . $filename);
                                    $this->exts->new_invoice($invoice_name, $parsed_date, $invoice_amount, $filename);
                                } else {
                                    $this->exts->executeSafeScript("
                                    var rows = document.querySelectorAll('div.cb-history__list div.cb-invoice');
                                    rows[arguments[0]].querySelector('.cb-invoice__link').click();
                                ", array($key));

                                    // Wait for completion of file download
                                    $this->exts->wait_and_check_download("pdf");

                                    $downloaded_file = $this->exts->find_saved_file("pdf", "");
                                    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                                        $filename = basename($downloaded_file);
                                        $invoice_name = basename($downloaded_file, ".pdf");
                                        $this->exts->log("invoice filename - " . $filename);
                                        $this->exts->new_invoice($invoice_name, $parsed_date, $invoice_amount, $filename);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception  Billing documents - " . $exception->getMessage());
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
