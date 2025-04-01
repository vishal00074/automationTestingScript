<?php // added solve cloud flare login function  remove return from billing page function stop download invoice 
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

    // Server-Portal-ID: 6598 - Last modified: 16.09.2024 14:53:22 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = "https://cloud.digitalocean.com/login";
    public $loginUrl = "https://cloud.digitalocean.com/login";
    public $dashboardUrl = "https://cloud.digitalocean.com/droplets";
    public $form_selector = "form[autocomplete='none']";
    public $username_selector = "input#email";
    public $password_selector = "input#password";
    public $submit_button_selector = "button[type=\"submit\"]";
    public $twofa_form_selector =  "form input[id=\"code\"]";
    public $twofa_form_selector1 =  "form[class*=\"tfa-form\"] input[name=\"otp\"]";
    public $restrictPages = 3;
    public $login_tryout = 0;
    public $no_invoice = true;
    public $no_payment_receipt = 0;

    public $accounts_name_array = array();

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        $this->no_payment_receipt = isset($this->exts->config_array["no_payment_receipt"]) ? (int)@$this->exts->config_array["no_payment_receipt"] : $this->no_payment_receipt;

        $this->exts->openUrl($this->baseUrl);
        sleep(12);
        $this->exts->capture("Home-page-without-cookie");
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(15);
        $this->exts->capture("Home-page-with-cookie");

        if ($this->exts->exists("button#truste-consent-button")) {

            $this->exts->moveToElementAndClick("button#truste-consent-button");
        }
        sleep(5);


        if (!$this->checkLogin()) {
            $this->exts->openUrl($this->baseUrl);
            sleep(2);
            $this->exts->capture("after-login-clicked");
            sleep(15);
            if ($this->exts->exists('iframe[title="TrustArc Cookie Consent Manager"]')) {
                $this->switchToFrame('iframe[title="TrustArc Cookie Consent Manager"]');
                sleep(5);
                $this->exts->moveToElementAndClick("a.acceptAllButtonLower");
                sleep(10);
                if ($this->exts->exists("a#gwt-debug-close_id")) {
                    $this->exts->moveToElementAndClick("a#gwt-debug-close_id");
                }
            }
            sleep(5);

            $this->fillForm(0);
            sleep(15);


            if ($this->exts->urlContains('challenge=/login')) {
                $this->check_solve_blocked_page();
                $this->check_solve_cloudflare_login();
            }
            sleep(10);

            $this->fillForm(0);
            sleep(15);


            if ($this->exts->urlContains('challenge=/login')) {
                $this->check_solve_blocked_page();
                $this->check_solve_cloudflare_login();
            }
            sleep(10);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->check_solve_blocked_page();
            sleep(15);
            $this->exts->capture("LoginSuccess");

            $this->exts->openUrl($this->baseUrl);
            sleep(15);
            $mes_el = $this->exts->getElement('//img[contains(@src, "/account-settings/")]/../../../../../div/h3', null, 'xpath');
            $mes = 'mes';
            if ($mes_el != null) {
                $mes = strtolower($mes_el->getText());
            }
            $this->exts->log('mes: ' . $mes);
            if (strpos($mes, 'verify your identity') !== false) {
                $this->exts->account_not_ready();
            }

            $this->billingPage();

            // Final, check no invoice
            if ($this->no_invoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log('url login failed: ' . $this->exts->getUrl());
            $mes_el = $this->exts->getElement('//img[contains(@src, "/account-settings/")]/../../../../../div/h3', null, 'xpath');
            $mes = 'mes';
            if ($mes_el != null) {
                $mes = strtolower($mes_el->getText());
            }
            $this->exts->log('mes: ' . $mes);
            if (strpos($this->exts->extract('form div.is-error p'), 'passwor') !== false) {
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure(1);
            } else if (strpos($this->exts->extract('form div.is-error p'), 'an account with this email address was not found') !== false) {
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure(1);
            } else if (strpos($this->exts->extract('div[class*="ErrorBanner"] small'), 'incorrect email or password') !== false) {
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure(1);
            } else if (strpos($mes, 'verify your identity') !== false) {
                $this->exts->account_not_ready();
            } else {
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure();
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

    function getInnerTextByJS($selector_or_object, $parent = null)
    {
        if ($selector_or_object == null) {
            $this->exts->log(__FUNCTION__ . ' Can not get innerText of null');
            return;
        }
        $element = $selector_or_object;
        if (is_string($selector_or_object)) {
            $element = $this->exts->getElement($selector_or_object, $parent);
            if ($element == null) {
                $element = $this->exts->getElement($selector_or_object, $parent, 'xpath');
            }
            if ($element == null) {
                $this->exts->log(__FUNCTION__ . ':: Can not found element with selector/xpath: ' . $selector_or_object);
            }
        }
        if ($element != null) {
            return $this->exts->executeSafeScript("return arguments[0].innerText", [$element]);
        }
    }

    /**
     * Method to fill login form
     * @param Integer $count Number of times portal is retried.
     */
    function fillForm($count = 0)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {

            if ($this->exts->getElement($this->password_selector) != null || $this->exts->getElement($this->username_selector) != null) {
                sleep(2);
                $this->login_tryout = (int)$this->login_tryout + 1;
                $this->exts->capture("1-pre-login");

                if ($this->exts->exists($this->username_selector)) {
                    $this->exts->log("Enter Username");
                    $this->exts->moveToElementAndType($this->username_selector, $this->username);
                    sleep(2);
                }

                if ($this->exts->exists($this->password_selector)) {
                    $this->exts->log("Enter Password");
                    $this->exts->moveToElementAndType($this->password_selector, $this->password);
                    sleep(2);
                }
                $this->exts->moveToElementAndClick($this->submit_button_selector);
                sleep(15);


                if ($this->exts->urlContains('challenge=/login')) {
                    $this->check_solve_blocked_page();
                }
                sleep(10);
                $this->checkAndSolveHumanCaptcha();
                sleep(3);
                if (
                    $this->exts->getElement($this->password_selector) != null && $this->exts->getElement($this->username_selector) != null
                    && !$this->exts->exists('form div.is-error p') && (int)$this->login_tryout < 2
                ) {
                    $this->exts->clearCookies();
                    $this->exts->openUrl($this->baseUrl);
                    sleep(1);

                    $this->fillForm(0);
                }

                if ($this->exts->getElement($this->twofa_form_selector) != null) {
                    $this->checkFillTwoFactor($this->twofa_form_selector, 'h4 ~ p', $this->submit_button_selector);
                }
                sleep(5);
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    private function check_solve_cloudflare_login($refresh_page = false)
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
            $this->exts->click_by_xdotool('div:has(>input[name^="cf"][name$="response"])', 30, 28);
            sleep(5);
            $this->exts->capture("cloudflare-clicked-1", true);
            sleep(3);
            if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
                $this->exts->click_by_xdotool('div:has(>input[name^="cf"][name$="response"])', 30, 28);
                sleep(5);
                $this->exts->capture("cloudflare-clicked-2", true);
                sleep(15);
            }
            if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
                $this->exts->click_by_xdotool('div:has(>input[name^="cf"][name$="response"])', 30, 28);
                sleep(5);
                $this->exts->capture("cloudflare-clicked-3", true);
                sleep(15);
            }
        }
    }

    private function checkFillTwoFactor($two_factor_selector, $two_factor_message_selector, $two_factor_submit_selector)
    {
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
            } else {
                $two_factor_message_selector = '//input[@id="code"]/../../../preceding-sibling::p';
                if ($this->exts->getElement($two_factor_message_selector, null, 'xpath') != null) {
                    $this->exts->two_factor_notif_msg_en = "";

                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector, null, 'xpath')[0]->getText();

                    $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
                    $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
                    $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
                }
            }
            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }

            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
                $this->exts->moveToElementAndClick('input[name="trust_device"]');
                sleep(1);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(15);

                if ($this->exts->getElement($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->checkFillTwoFactor($two_factor_selector, $two_factor_message_selector, $two_factor_submit_selector);
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }

    function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            if ($this->exts->getElement('a[href*="/logout"], a[href*="/notification"]') != null) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            } else {
                $this->exts->moveToElementAndClick('div[aria-label="User Menu"]');
                sleep(2);
                if ($this->exts->exists('a[href*="/logout"], a[href*="/notification"]')) {
                    $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                    $isLoggedIn = true;
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception->getMessage());
        }

        return $isLoggedIn;
    }

    public function billingPage($account_index = 1)
    {
        $this->exts->log(__FUNCTION__);
        $this->exts->capture(__FUNCTION__);
        $this->check_solve_blocked_page();
        sleep(10);
        if (strpos(strtolower($this->exts->extract('div > h3', null, 'text')), 'add a payment method to your account') !== false) {
            $this->exts->log($this->exts->getUrl());
            $this->exts->capture('add-payment-method');
            $this->exts->no_invoice();
        }

        $account_list_selector = 'div.teams-dropdown div.menu-dropdown-teams ul.menu-dropdownItems div.context-list section li a';
        if (!$this->exts->exists($account_list_selector) && $this->exts->urlContains('/welcome')) {
            // click on avatar to show a list of accounts
            if (!$this->exts->exists('div.dropdown-transition-enter-done div[role="button"]')) {
                $this->exts->moveToElementAndClick('[aria-label="User Menu"], img[src*="/avatar"]');
                sleep(1);
            }

            // select another account
            $temp_account_names = $this->exts->getElementsAttribute('div.dropdown-transition-enter-done div[role="button"]', 'aria-label');
            $accounts_count = count($temp_account_names);
            for ($accountIndex = 0; $accountIndex < $accounts_count; $accountIndex++) {
                $account_name = trim($temp_account_names[$accountIndex]);
                if (!in_array($account_name, $this->accounts_name_array)) {
                    array_push($this->accounts_name_array, $account_name);
                    $this->exts->moveToElementAndClick('div.dropdown-transition-enter-done div[role="button"][aria-label="' . $account_name . '"]');
                    sleep(15);
                    $this->exts->capture('switching-account-' . $account_name);
                    $this->billingPage();
                    return;
                }
            }
        }

        $accounts = $this->exts->getElements($account_list_selector);
        if (count($accounts) == 0 && $this->exts->exists('div.account-dropdown div[role="button"]')) {
            $this->exts->moveToElementAndClick('div.account-dropdown div[role="button"]');
            sleep(1);
            $accounts = $this->exts->getElements($account_list_selector);
        }
        $accounts_count = count($accounts);
        $accounts_url_array = array();

        for ($acc = 0; $acc < $accounts_count; $acc++) {
            $account = $this->exts->getElements($account_list_selector)[$acc];
            if ($account == null) continue;
            $temp_url = trim($account->getAttribute('href'));
            if (!in_array($temp_url, $accounts_url_array)) {
                array_push($accounts_url_array, $temp_url);
                $this->exts->log($temp_url);
            }
        }

        // add default for single account.
        if (count($accounts_url_array) == 0) {
            array_push($accounts_url_array, 'https://cloud.digitalocean.com/account/billing');
        }

        $this->exts->log('Accounts Found: ' . count($accounts_url_array));

        foreach ($accounts_url_array as $key => $account_url) {
            $this->exts->log('==========Account URL to be opened:::::::: ' . $account_url);
            $this->exts->openUrl($account_url);
            sleep(15);
            if ($this->exts->exists('div.side-nav-section.account a[href*="/account/billing?"]')) {
                $this->exts->moveToElementAndClick('div.side-nav-section.account a[href*="/account/billing?"]');
            } else {
                if ($this->exts->exists('a[href*="/account/billing/history?"]')) {
                    $this->exts->moveToElementAndClick('a[href*="/account/billing/history?"]');
                } else {
                    $this->exts->openUrl("https://cloud.digitalocean.com/account/billing/history");
                }
            }

            sleep(10);
            $this->downloadInvoices();
        }

        if ($this->no_invoice) {
            $this->exts->no_invoice();
        }
        // don't call success() because we don't want to update cookies which is currently work
        // if cookies get updated; we have to solve 2FA again.
        // $this->exts->success();
    }

    public function downloadInvoices($pageCount = 1)
    {
        sleep(25);
        $this->exts->capture("invoices-page");
        $invoices = [];

        $rows = $this->exts->getElements('div[data-testid="billing-history-entry-row"]');
        foreach ($rows as $row) {
            if ($this->exts->getElement('div[data-testid*="dropdown"] div[role="button"]', $row) != null) {
                if ($this->exts->getElement('div[data-label="Invoice"] a[href*="/account/billing/"]', $row) != null || $this->no_payment_receipt == 0) {
                    $invoiceDate = trim($this->getInnerTextByJS('div[data-label="Date"]', $row));
                    $invoiceDate = $this->exts->parse_date($invoiceDate, 'F, d Y', 'Y-m-d');
                    $invoiceName = '';

                    $amountText = trim($this->getInnerTextByJS('div[data-label="Amount"]', $row));
                    $invoiceAmount = preg_replace('/[^\d\.\,]/', '', $amountText);
                    if (stripos($amountText, 'A$') !== false) {
                        $invoiceAmount = $invoiceAmount . ' AUD';
                    } else if (stripos($amountText, '$') !== false) {
                        $invoiceAmount = $invoiceAmount . ' USD';
                    } else if (stripos(urlencode($amountText), '%C2%A3') !== false) {
                        $invoiceAmount = $invoiceAmount . ' GBP';
                    } else {
                        $invoiceAmount = $invoiceAmount . ' EUR';
                    }

                    $menuBtn = $this->exts->getElement('div[data-testid*="dropdown"] div[role="button"]', $row);
                    try {
                        $menuBtn->click();
                    } catch (\Exception $exception) {
                        $this->exts->executeSafeScript('arguments[0].click();', [$menuBtn]);
                    }
                    sleep(1);
                    $downloadBtn = $this->exts->getElement('div[data-testid*="dropdown"] ul li:first-child', $row);
                    try {
                        $downloadBtn->click();
                    } catch (\Exception $exception) {
                        $this->exts->executeSafeScript('arguments[0].click();', [$downloadBtn]);
                    }
                    sleep(10);

                    $this->exts->log('--------------------------');
                    $this->exts->log('invoiceName: ' . $invoiceName);
                    $this->exts->log('invoiceDate: ' . $invoiceDate);
                    $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                    // $this->exts->log('invoiceUrl: '.$invoiceUrl);

                    $this->exts->wait_and_check_download('pdf');
                    // $filename = $invoiceName.'.pdf';
                    $downloaded_file = $this->exts->find_saved_file('pdf');
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $invoiceFileName = basename($downloaded_file);
                        $invoiceName = trim(array_pop(explode('#', explode('.pdf', $invoiceFileName)[0])));
                        $invoiceName = trim(array_pop(explode('(', explode(')', $invoiceName)[0])));
                        // $invoiceName = trim(explode('_', end(explode('obile_', $invoiceName)))[0]);
                        $this->exts->log('Final invoice name: ' . $invoiceName);
                        $invoiceFileName = $invoiceName . '.pdf';
                        @rename($downloaded_file, $this->exts->config_array['download_folder'] . $invoiceFileName);

                        if ($this->exts->invoice_exists($invoiceName)) {
                            $this->exts->log('Invoice existed ' . $invoiceFileName);
                        } else {
                            $this->no_invoice = false;
                            $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                            sleep(1);
                        }
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ');
                    }
                }
            }
        }


        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        if (
            $restrictPages == 0 &&
            $pageCount < 50 &&
            $this->exts->getElement('.Pagination  a.next:not(.is-disabled)') != null
        ) {
            $pageCount++;
            $this->exts->moveToElementAndClick('.Pagination  a.next:not(.is-disabled)');
            sleep(5);
            $this->downloadInvoices($pageCount);
        }
    }

    public function checkAndSolveHumanCaptcha()
    {
        $hcaptcha_form_selector = ".challenge-form";
        $hcaptcha_textarea_selector = "textarea[name='h-captcha-response']";
        $gcaptcha_textarea_selector = "textarea[name='g-recaptcha-response']";
        $hcaptcha_sitekey = "33f96e6a-38cd-421b-bb68-7806e1764460";
        $submit_captcha_button = "button#hcaptcha_submit";
        $solved = false;
        $count = 0;
        while (!$solved && $count < 3) {
            if ($this->exts->getElement($hcaptcha_form_selector)) {
                $this->exts->log("Try solving human captcha count " . $count);
                $token = $this->exts->processHumanCaptcha($hcaptcha_form_selector, $hcaptcha_sitekey, $this->exts->getUrl(), true);

                // $captchaScript = '
                // 	function submitToken(token) {
                // 		document.querySelector("[name=g-recaptcha-response]").innerText = token;
                // 		document.querySelector("[name=h-captcha-response]").innerText = token;
                // 		document.querySelector(".challenge-form").submit();
                // 	}
                // 	submitToken(arguments[0]);
                // ';

                // $this->exts->log($captchaScript);
                // $this->exts->executeSafeScript($captchaScript, array($token));
                sleep(5);
                $count++;
            } else {
                $this->exts->log("No captcha found!");
                $solved = true;
            }
        }

        $this->exts->log("Human captcha solved: " . var_export($solved, true));
    }

    // helper functions
    private function check_solve_blocked_page()
    {
        $this->exts->capture_by_chromedevtool("blocked-page-checking");
        if ($this->exts->exists('iframe[src*="challenges.cloudflare.com"]')) {
            $this->exts->capture_by_chromedevtool("blocked-by-cloudflare");
            // $this->exts->refresh();
            sleep(40);
            //  $this->exts->click_by_xdotool('iframe[src*="challenges.cloudflare.com"]');
            $this->exts->click_by_xdotool('iframe[src*="challenges.cloudflare.com"]', 30, 28, true);
            sleep(40);
            if ($this->exts->exists('iframe[src*="challenges.cloudflare.com"]')) {
                $this->exts->click_by_xdotool('iframe[src*="challenges.cloudflare.com"]', 30, 28, true);
                sleep(40);
            }
            if ($this->exts->exists('iframe[src*="challenges.cloudflare.com"]')) {
                $this->exts->click_by_xdotool('iframe[src*="challenges.cloudflare.com"]', 30, 28, true);
                sleep(40);
            }
            if ($this->exts->exists('iframe[src*="challenges.cloudflare.com"]')) {
                $this->exts->click_by_xdotool('iframe[src*="challenges.cloudflare.com"]', 30, 28, true);
                sleep(40);
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
