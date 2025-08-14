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
        $this->exts->screen_capture_location = '/var/www/vhosts/worker/httpdocs/fs/cdo/process/2/2673482/screens/';
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
                // load cookies from file for desktop app
                if (!empty($this->exts->config_array["without_password"])) {
                    $this->exts->loadCookiesFromFile();
                }
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

    // Server-Portal-ID: 137156 - Last modified: 07.08.2025 14:49:28 UTC - User: 1

    public $baseUrl = 'https://www.fedex.com/online/billing/cbs/invoice';
    public $loginUrl = 'https://www.fedex.com/en-us/billing-online.html';
    public $invoicePageUrl = 'https://www.fedex.com/online/billing/cbs/invoices';

    public $username_selector = 'input#username,input#userId';
    public $password_selector = 'input#password';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button#login_button,button#login-btn';

    public $check_login_failed_selector = '#invalidCredentials';
    public $check_login_success_selector = 'a[onclick*="Logout"]';

    public $account_numbers = '';
    public $restrictPages = 3;

    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
        $this->account_numbers = isset($this->exts->config_array["account_numbers"]) ? trim($this->exts->config_array["account_numbers"]) : $this->account_numbers;

        $this->exts->openUrl($this->baseUrl);
        sleep(5);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login

        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->clearChrome();

            for ($i = 0; $i <= 5; $i++) {
                $this->exts->openUrl($this->baseUrl);
                $this->waitFor('.js-modal-close');
                if ($this->exts->exists('.js-modal-close')) {
                    $this->exts->moveToElementAndClick('.js-modal-close');
                    sleep(5);
                }

                if ($this->exts->exists('button.fxg-gdpr__accept-all-btn')) {
                    $this->exts->moveToElementAndClick('button.fxg-gdpr__accept-all-btn');
                    sleep(5);
                }



                $this->exts->moveToElementAndClick('div#global-login-wrapper');
                $this->waitFor('a[href*="/secure-login"]');
                $this->exts->moveToElementAndClick('a[href*="/secure-login"]');
                $this->exts->log(__FUNCTION__ . '::Try login attempt: ' . ($i + 1));

                $this->checkFillLogin();

                $this->exts->log("initiate 2FA check");
                $this->checkFillTwoFactor();
                $this->waitFor('button#cancelBtn', 10);
                if ($this->exts->exists('button#cancelBtn')) {
                    $this->exts->moveToElementAndClick('button#cancelBtn');
                    sleep(10);
                }
                if ($this->exts->exists('button#retry-btn')) {
                    $this->exts->moveToElementAndClick('button#retry-btn');
                    sleep(10);
                }
                if ((!$this->exts->exists($this->password_selector) && $this->exts->exists($this->check_login_success_selector)) || $this->exts->exists($this->check_login_failed_selector)) {
                    break;
                }
            }
        }

        if ($this->exts->exists('button#cancelBtn')) {
            $this->exts->moveToElementAndClick('button#cancelBtn');
        }

        // then check user logged in or not
        if ($this->checkLogin()) {
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(15);
            if ($this->exts->urlContains('login')) {
                $this->exts->openUrl($this->invoicePageUrl);
                sleep(15);
            }
            if ($this->exts->exists('button[aria-label*="close"]')) {
                $this->exts->moveToElementAndClick('button[aria-label*="close"]');
                sleep(5);
            }
            $this->checkMultiAccounts();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if (stripos($this->exts->extract($this->check_login_failed_selector, null, 'innerText'), 'Login incorrect') !== false) {
                $this->exts->loginFailure(1);
            } else if ($this->exts->getElement('#invalidCredentials') != null) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        $this->waitFor($this->password_selector);
        if ($this->exts->getElement($this->password_selector) != null) {
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            if ($this->exts->getElement($this->remember_me_selector) != null) {
                $this->exts->moveToElementAndClick($this->remember_me_selector);
                sleep(5);
            }

            $this->exts->capture("2-login-page-filled");

            if ($this->exts->getElement($this->submit_login_selector) != null) {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
            }
            sleep(15);
            if ($this->exts->getElement('button[aria-label="close"]') != null) {
                $this->exts->moveToElementAndClick('button[aria-label="close"]');
                $this->exts->log('2FA popup closed');
                sleep(5);
            }
            if ($this->exts->getElement($this->password_selector) != null && !$this->exts->getElement($this->check_login_failed_selector) != null && $this->exts->extract($this->password_selector, null, 'value') != null) {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
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

    private function checkFillTwoFactor()
    {
        $this->exts->log("checkFillTwoFactor");

        $two_factor_content_selector = '';

        $two_factor_selector = 'div[class*="fdx-c-single-digits__item"]';
        $two_factor_selector_shadow = 'return document.querySelector("fdx-authenticate").shadowRoot.querySelector("div[class*=\'fdx-c-single-digits__item\']")';

        $two_factor_message_selector = 'h2[id="verifySubtitleCall-email"]';
        $two_factor_message_selector_shadow = 'return document.querySelector("fdx-authenticate").shadowRoot.querySelector("h2[id=\'verifySubtitleCall-email\']")';

        $two_factor_submit_selector = 'button[id="submit-btn"]';
        $two_factor_submit_selector_shadow = 'return  document.querySelector("fdx-authenticate").shadowRoot.querySelector("button[id=\'submit-btn\']")';

        $two_factor_resend_selector = 'a[id="requestCode-btn"]';
        $two_factor_resend_selector_shadow = 'return document.querySelector("fdx-authenticate").shadowRoot.querySelector("a[id=\'requestCode-btn\']")';

        $this->exts->executeSafeScript('fdx-authenticate');
        if ($this->exts->executeSafeScript($two_factor_selector_shadow) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->capture("2.1-two-factor");
            $this->exts->log("2.1-two-factor");

            $this->exts->two_factor_notif_msg_en = $this->exts->execute_javascript('document.querySelector("fdx-authenticate").shadowRoot.querySelector("h2[id=\'verifySubtitleCall-email\']").innerText');

            if ($this->exts->two_factor_notif_msg_en != null) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en;
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
                $resultCodes = str_split($two_factor_code);
                $two_factor_selector = 'document.querySelector("fdx-authenticate").shadowRoot.querySelector("div[class*=\'fdx-c-single-digits__item\']")';
                $this->exts->execute_javascript('
			var inputs = document.querySelector("fdx-authenticate").shadowRoot.querySelectorAll(".fdx-c-single-digits__item input");
			var resultCodes = "' . $two_factor_code . '";
			for (var i = 0; i < inputs.length; i++) {
				inputs[i].value = resultCodes[i] || ""; // If resultCodes[i] is undefined, set empty string
			}
		');

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                //$this->exts->moveToElementAndClick($two_factor_submit_selector);
                $this->exts->execute_javascript('document.querySelector("fdx-authenticate").shadowRoot.querySelector("button[id=\'submit-btn\']").click()');
                sleep(15);

                if ($this->exts->getElement($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->exts->notification_uid = '';
                    $this->checkFillTwoFactor();
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }

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
            if ($this->exts->exists($this->check_login_success_selector)) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (Exception $exception) {

            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }

    private function checkMultiAccounts()
    {
        $this->exts->log('process-multi-accounts');
        sleep(10);
        if (trim($this->account_numbers) != '' && !empty($this->account_numbers)) {
            $accountNumbers = explode(',', $this->account_numbers);
        } else {
            $accountNumbers = $this->exts->getElementsAttribute('select[id="account_dd"] option', 'value');
        }
        $this->exts->log(__FUNCTION__ . '::Account numbers: ' . count($accountNumbers));
        if (count($accountNumbers) > 0) {
            foreach ($accountNumbers as $key => $accountNumber) {
                $this->exts->log('PROCESSING account number: ' . $accountNumber);
                $this->exts->openUrl($this->invoicePageUrl);
                sleep(10);
                $this->exts->click_by_xdotool('select[id="account_dd"]');
                sleep(2);
                $this->exts->click_by_xdotool('select[id="account_dd"] option[value="' . $accountNumber . '"]');

                // JS Fallback 
                $this->exts->execute_javascript('
            const selectBox = document.querySelector(\'select[id="account_dd"]\');
            selectBox.value = document.querySelector(\'select[id="account_dd"] option[value="' . $accountNumber . '"]\').value;
            selectBox.dispatchEvent(new Event("change"));
        ');

                sleep(10);
                $this->invoicePage();
                sleep(2);
            }
        } else {
            $this->invoicePage();
        }

        // Final, check no invoice
        if ($this->isNoInvoice) {
            $this->exts->no_invoice();
        }
        $this->exts->success();
    }

    private function invoicePage()
    {
        $this->exts->log("Process-Invoice-page");

        $this->exts->moveToElementAndClick('div#tab-0');
        sleep(5);
        $this->exts->click_element('.//li//*[contains(text(), "Invoice Status")]');
        sleep(5);
        $this->exts->click_element('.//li//*[contains(text(), "Closed")]');
        sleep(5);
        $this->exts->click_element('.//button[normalize-space(text()) = "APPLY"]');

        sleep(15);
        $this->exts->capture("4-invoices-page");
        if ($this->exts->exists('td[data-label="invoiceNumber"]')) {
            $this->exts->moveToElementAndClick('td[data-label="invoiceNumber"]');
        }

        $this->processInvoices();
    }

    private function processInvoices($pageCount = 1)
    {
        $this->exts->log('Begin-Process-Invoices');
        sleep(10);
        $this->downloadEachInvoice();
        if ($this->exts->exists('div[class="fdx-c-dialog__main"]') && $this->exts->getElement('//h6[normalize-space(text()) = "There was an error processing your request, Please try again later."]') != null) {
            $this->exts->log("There was an error processing your request, Please try again later.");
            $this->exts->no_permission();
        }
        $fetchInvoicesCount = $this->exts->extract('div.navigation label:last-child');
        preg_match('/of\s+(\d+)/', $fetchInvoicesCount, $matches);
        $invoicesCount = $matches[1] ?? null;
        $this->exts->log('Total No of Invoices Count : ' . $invoicesCount);

        for ($i = 0; $i < $invoicesCount && $this->exts->exists('div.navigation > svg:nth-child(3):not(.cursor-not-allowed)'); $i++) {
            $this->exts->moveToElementAndClick('div.navigation > svg:nth-child(3):not(.cursor-not-allowed)');
            sleep(10);
            $this->downloadEachInvoice();
        }
    }

    private function downloadEachInvoice()
    {
        $this->exts->log('Begin-Process-download-Each-Invoice');
        $invoiceUrl = $this->exts->getUrl();
        $this->exts->log('Invoice URL ====> : ' . $invoiceUrl);
        $invoiceName = explode(
            '&',
            array_pop(explode('invoiceNumber=', $invoiceUrl))
        )[0];
        $this->exts->log('Invoice Name ====> : ' . $invoiceName);
        $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
        $this->exts->log('Invoice FileName ====> : ' . $invoiceFileName);
        sleep(5);

        if ($this->exts->getElement('.//button//*[contains(text(), "PDF")]') != null) {
            $this->exts->click_element('.//button//*[contains(text(), "PDF")]');
        }
        sleep(10);

        // Wait for completion of file download
        $this->exts->wait_and_check_download('pdf');

        // find new saved file and return its path
        $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
            $this->exts->new_invoice($invoiceName, '', '', $downloaded_file);
            sleep(1);
        } else {
            $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
        }
        $this->isNoInvoice = false;
    }
}

$portal = new PortalScriptCDP("optimized-chrome-v2", 'Immowelt Kundenportal', '2673482', 'aW5mb0B0b20taW1tb2JpbGllbi5jb20=', 'UXQlb3FrV2VAKExSYjI=');
$portal->run();
