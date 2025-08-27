<?php // migrated udpated fillform function use click_by_ xdotool added cloudflare function to slove page and remove check_solve_blocked_page
 // updated login filling code
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

    // Server-Portal-ID: 51574 - Last modified: 27.08.2024 13:37:42 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = "https://www.algolia.com/users/edit#?tab=invoices";
    public $username_selector = '#user_email';
    public $password_selector = '#user_password';
    public $submit_btn = '#new_user [type=submit]';
    public $logout_btn = '[href="/users/sign_out"], .AccountHeader_link';

    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        sleep(1);
        $isCookieLoaded = false;
        if ($this->exts->loadCookiesFromFile()) {
            sleep(1);
            $isCookieLoaded = true;
        }

        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->check_solve_cloudflare_page();

        if ($isCookieLoaded) {
            $this->exts->capture("Home-page-with-cookie");
        } else {
            $this->exts->capture("Home-page-without-cookie");
        }

        if (!$this->checkLogin()) {
            $this->exts->capture("after-login-clicked");
            $this->exts->openUrl('https://www.algolia.com/users/sign_in');
            sleep(3);
            $this->fillForm(0);
            sleep(20);

            if (
                $this->exts->exists('div.welcome ul.sidebar__steps')
                || ($this->exts->exists('button#user-menu-button') && $this->exts->urlContains('signup/personal_information'))
            ) {
                $this->exts->account_not_ready();
            }
            if ($this->exts->exists('button#user-menu-button') && $this->exts->urlContains('signup/dashboard_setup')) {
                $this->exts->account_not_ready();
            }
            if ($this->exts->exists('form.new_user input#user_gauth_token')) {
                $this->process2FA('form.new_user input#user_gauth_token', 'form.new_user input[name="commit"]');
            }
        }
        sleep(15);
        $this->check_solve_cloudflare_page();
        sleep(20);
        $this->fillForm(2);
        sleep(40);

        if ($this->checkLogin()) {
            $this->exts->openUrl("https://www.algolia.com/dashboard");
            sleep(2);

            $this->exts->waitTillPresent("a[href$='/billing']");
            $this->exts->moveToElementAndClick('a[href$="/billing"]');
            sleep(2);
            $this->exts->waitTillPresent("a[href$='/billing/invoices']");
            $this->exts->moveToElementAndClick('a[href$="/billing/invoices"]');

            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->exts->moveToElementAndClick('.AccountHeader_link, button#user-menu-button');
            sleep(10);

            $this->exts->moveToElementAndClick('.SideBar_list [href*="/billing"], #satellite-portal [href*="/account"]');
            sleep(10);

            $this->exts->moveToElementAndClick('.TabBar_list [href*="/billing/invoices"], [href*="/billing/invoices"]');
            sleep(10);
            $this->exts->moveToElementAndClick('button.AppSideBar_appSelector');

            $accounts = $this->exts->getElements('a.AppSelector_item[href*="/dashboard"]');
            $accounts = $this->exts->getElementsAttribute('a.AppSelector_item[href*="/dashboard"]', 'href');
            $this->exts->log('ACCOUNTs found: ' . count($accounts));
            if (count($accounts) > 1) {
                foreach ($accounts as $key => $account) {
                    $this->exts->log('SWITCH account: ' . $account);
                    $this->exts->log('URL>>>>>>>>>>>>>>>>>>>>>>' . $account);
                    $account_id = explode(
                        '/',
                        end(explode('apps/', $account))
                    )[0];
                    $this->exts->openUrl('https://www.algolia.com/apps/' . $account_id . '/billing/invoices');
                    sleep(10);
                    $this->downloadInvoice();
                }
            } else {
                $this->downloadInvoice();
            }
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->capture("LoginFailed");
            if ($this->exts->getElement('//div[contains(text(), "Invalid Email or password")]', null, 'xpath')) {
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
    public function fillForm($count = 0)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {

            if ($this->exts->exists($this->username_selector)) {
                sleep(2);
                $this->exts->capture("1-pre-login");

                $this->exts->log("Enter Username");
                // $this->exts->moveToElementAndType($this->username_selector, $this->username, 5);
                $this->exts->click_by_xdotool($this->username_selector);
                $this->exts->type_key_by_xdotool("ctrl+a");
                $this->exts->type_key_by_xdotool("Delete");
                $this->exts->type_text_by_xdotool($this->username);

                $this->exts->log("Enter Password");
                // $this->exts->moveToElementAndType($this->password_selector, $this->password, 5);
                $this->exts->click_by_xdotool($this->password_selector);
                $this->exts->type_key_by_xdotool("ctrl+a");
                $this->exts->type_key_by_xdotool("Delete");
                $this->exts->type_text_by_xdotool($this->password);

                $this->exts->click_by_xdotool('#user_remember_me');

                $this->exts->capture("1-pre-login-1");
                $this->checkFillRecaptcha(0);

                $this->exts->click_by_xdotool($this->submit_btn);

                sleep(15);
                $this->check_solve_cloudflare_page();
            } else if ($this->exts->exists("iframe[src*=\"https://www.google.com/recaptcha/api2/anchor?\"]") && $this->exts->exists("textarea[name=\"g-recaptcha-response\"]") && $count < 3) {
                $this->checkFillRecaptcha(0);
                $count++;
                $this->fillForm($count);
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
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

    private function checkFillRecaptcha($counter)
    {

        if ($this->exts->exists('iframe[src*="https://www.google.com/recaptcha/api2/anchor?"]') && $this->exts->exists('textarea[name="g-recaptcha-response"]')) {

            if ($this->exts->exists("div.g-recaptcha[data-sitekey]")) {
                $data_siteKey = trim($this->exts->getElement("div.g-recaptcha")->getAttribute("data-sitekey"));
            } else {
                $iframeUrl = $this->exts->getElement("iframe[src*=\"https://www.google.com/recaptcha/api2/anchor?\"]")->getAttribute("src");
                $tempArr = explode("&k=", $iframeUrl);
                $tempArr = explode("&", $tempArr[count($tempArr) - 1]);

                $data_siteKey = trim($tempArr[0]);
                $this->exts->log("iframe url  - " . $iframeUrl);
            }
            $this->exts->log("SiteKey - " . $data_siteKey);

            $isCaptchaSolved = $this->exts->processRecaptcha($this->exts->getUrl(), $data_siteKey, false);
            $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);

            if ($isCaptchaSolved) {
                $this->exts->log("isCaptchaSolved");
                $this->exts->executeSafeScript("document.querySelector(\"#g-recaptcha-response\").value = '" . $this->exts->recaptcha_answer . "';");
                $this->exts->executeSafeScript("document.querySelector(\"#g-recaptcha-response\").innerHTML = '" . $this->exts->recaptcha_answer . "';");
                sleep(5);
                try {
                    $tag = $this->exts->getElement("[data-callback]");
                    if ($tag != null && trim($tag->getAttribute("data-callback")) != "") {
                        $func =  trim($tag->getAttribute("data-callback"));
                        $this->exts->executeSafeScript(
                            $func . "('" . $this->exts->recaptcha_answer . "');"
                        );
                    } else {

                        $this->exts->executeSafeScript(
                            "var a = ___grecaptcha_cfg.clients[0]; for(var p1 in a ) {for(var p2 in a[p1]) { for (var p3 in a[p1][p2]) { if (p3 === 'callback') var f = a[p1][p2][p3]; }}}; if (f in window) f= window[f]; if (f!=undefined) f('" . $this->exts->recaptcha_answer . "');"
                        );
                    }
                    sleep(10);
                } catch (\Exception $exception) {
                    $this->exts->log("Exception " . $exception->getMessage());
                }
            }
        }
    }

    private function process2FA($two_factor_selector, $submit_btn_selector)
    {
        $this->exts->log("Current URL - " . $this->exts->getUrl());

        if ($this->exts->getElement("form.new_user div.card-body p") != null) {
            $msg_2fa = $this->exts->extract('form.new_user div.card-body p');

            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_de = $msg_2fa;
        }

        if ($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . $this->exts->two_factor_notif_msg_retry_de;
        }

        $this->exts->log("The msg: " . $this->exts->two_factor_notif_msg_en);

        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (trim($two_factor_code) != "" && !empty($two_factor_code)) {
            try {
                $this->exts->log("SIGNIN_PAGE: Entering two_factor_code.");
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);

                $this->exts->log("SIGNIN_PAGE: Clicking the [SIGN_IN] button.");
                if ($this->exts->getElement($submit_btn_selector) != null) {
                    $this->exts->moveToElementAndClick($submit_btn_selector);
                    sleep(10);
                } else {
                    sleep(5);
                    $this->exts->moveToElementAndClick($submit_btn_selector);
                    sleep(10);
                }

                if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->exts->notification_uid = "";
                    $this->process2FA($two_factor_selector, $submit_btn_selector);
                }
            } catch (\Exception $exception) {
                $this->exts->log('processTwoFactorAuth::ERROR while taking snapshot');
            }
        }
    }

    /**
     * Method to Check where user is logged in or not
     * return boolean true/false
     */
    private function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            if ($this->exts->exists('button#user-menu-button')) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }

        return $isLoggedIn;
    }

    private function downloadInvoice($pageCount = 1)
    {
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = count($this->exts->getElements('table > tbody > tr'));
        for ($i = 0; $i < $rows; $i++) {
            $row = $this->exts->getElements('table > tbody > tr')[$i];
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 5 && $this->exts->getElement('button[type="button"]', $tags[4]) != null) {
                $this->isNoInvoice = false;
                $download_button = $this->exts->getElement('button[type="button"]', $tags[4]);
                $invoiceName = trim($tags[1]->getAttribute('innerText'));
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';

                $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                $amountText = trim($tags[3]->getAttribute('innerText'));
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
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $parsed_date = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                if ($parsed_date == '') {
                    $parsed_date = $this->exts->parse_date($invoiceDate, 'Y-m-d', 'Y-m-d');
                }
                $this->exts->log('Date parsed: ' . $parsed_date);

                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    try {
                        $this->exts->log('Click download button');
                        $download_button->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click download button by javascript');
                        $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                    }
                    sleep(5);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }
            }
        }
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        if (
            $restrictPages == 0 &&
            $pageCount < 50 &&
            $this->exts->getElement('div.Invoices_pagination button.stl-btn-primary + button:not([disabled])') != null
        ) {
            $pageCount++;
            $this->exts->moveToElementAndClick('div.Invoices_pagination button.stl-btn-primary + button:not([disabled])');
            sleep(5);
            $this->downloadInvoice($pageCount);
        }
    }
}

$portal = new PortalScriptCDP("optimized-chrome-v2", 'LR', '2673461', 'aW5mb0Bway1rb2VuaWcuZGU=', 'S2F0cmluMTgxMDM2');
$portal->run();
