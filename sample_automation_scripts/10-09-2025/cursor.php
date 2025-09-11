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

    // Server-Portal-ID: 4175515 - Last modified: 21.08.2025 14:06:01 UTC - User: 1

    /*Define constants used in script*/

    public $baseUrl = 'https://cursor.com/dashboard';
    public $loginUrl = 'https://authenticator.cursor.sh/';
    public $username_selector = 'input[type="email"]';
    public $password_selector = 'input[name="password"]';
    public $submit_login_selector = 'button[type="submit"]';
    public $check_login_failed_selector = 'P.rt-CalloutText';
    public $check_login_success_selector = "//button[contains(., 'Manage Subscription') or contains(., 'Billing')]";
    public $isNoInvoice = true;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */

    private function initPortal($count)
    {

        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->baseUrl);
        sleep(7);
        $this->check_solve_cloudflare_page();

        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            sleep(7);
            $this->check_solve_cloudflare_page();
            $this->checkFillLogin();
            sleep(5);
            $this->check_solve_blocked_page();
            sleep(5);
            $this->check_solve_cloudflare_page();
            sleep(18);
            $this->checkFillTwoFactor();
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            sleep(2);
            if ($this->exts->exists('div.cookie_notice button')) {
                $this->exts->click_element('div.cookie_notice button');
            }

            $this->exts->click_element('button[title*="Billing"], button[title*="Facture"], button[title*="Rechnung"]');
            sleep(5);
            $this->exts->click_element('//*[text()="Manage Subscription"]');
            sleep(1);
            $this->exts->switchToNewestActiveTab();

            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') !== false) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        $this->exts->capture("2-login-page");
        if ($this->exts->querySelector($this->username_selector) != null) {
            sleep(3);
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);
            $this->exts->capture("2-username-filled");
            $this->exts->moveToElementAndClick('[type="submit"]');
            sleep(7);
            $this->check_solve_cloudflare_page();
        }

        if ($this->exts->querySelector($this->password_selector) != null) {
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            $this->exts->capture("2-password-filled");
            $this->exts->moveToElementAndClick('button[value="password"]');
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-password-not-found");
        }
    }

    private function check_solve_cloudflare_page()
    {
        $unsolved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) <= 0]';
        $solved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) > 0]';
        $cloudflare_displayed_selector = '.rt-Box:not([aria-hidden="true"]) > .rt-BaseCard [id="cf-turnstile"]';

        $this->exts->capture("cloudflare-checking");

        if ($this->exts->exists($cloudflare_displayed_selector)) {
            $this->exts->click_by_xdotool($cloudflare_displayed_selector, 30, 28);
            sleep(5);
            $this->exts->capture("cloudflare-clicked-1", true);
            sleep(3);
            if ($this->exts->exists($cloudflare_displayed_selector)) {
                $this->exts->click_by_xdotool($cloudflare_displayed_selector, 30, 28);
                sleep(5);
                $this->exts->capture("cloudflare-clicked-2", true);
                sleep(15);
            }
            if ($this->exts->exists($cloudflare_displayed_selector)) {
                $this->exts->click_by_xdotool($cloudflare_displayed_selector, 30, 28);
                sleep(5);
                $this->exts->capture("cloudflare-clicked-3", true);
                sleep(15);
            }
        } else {
            if (
                $this->exts->queryXpath($solved_cloudflare_input_xpath) == null &&
                $this->exts->queryXpath($unsolved_cloudflare_input_xpath) == null &&
                $this->exts->exists('#cf-please-wait > p:not([style*="display: none"]):not([style*="display:none"])')
            ) {
                for ($waiting = 0; $waiting < 10; $waiting++) {
                    sleep(2);
                    if (
                        $this->exts->queryXpath($solved_cloudflare_input_xpath) != null ||
                        $this->exts->queryXpath($unsolved_cloudflare_input_xpath) != null
                    ) {
                        sleep(3);
                        break;
                    }
                }
            }

            if ($this->exts->queryXpath($unsolved_cloudflare_input_xpath) != null) {
                $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
                sleep(5);
                $this->exts->capture("cloudflare-clicked-1", true);
                sleep(3);
                if ($this->exts->queryXpath($unsolved_cloudflare_input_xpath) != null) {
                    $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
                    sleep(5);
                    $this->exts->capture("cloudflare-clicked-2", true);
                    sleep(15);
                }
                if ($this->exts->queryXpath($unsolved_cloudflare_input_xpath) != null) {
                    $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
                    sleep(5);
                    $this->exts->capture("cloudflare-clicked-3", true);
                    sleep(15);
                }
            }
        }
    }


    private function check_solve_blocked_page()
    {
        $this->exts->capture_by_chromedevtool("blocked-page-checking");

        for ($i = 0; $i < 5; $i++) {
            if ($this->exts->check_exist_by_chromedevtool('div[id="cf-turnstile"] > div ')) {
                $this->exts->capture_by_chromedevtool("blocked-by-cloudflare");
                // $this->exts->refresh();
                sleep(10);

                $this->exts->click_by_xdotool('div[id="cf-turnstile"] > div ', 30, 28);
                sleep(15);

                if (!$this->exts->check_exist_by_chromedevtool('div[id="cf-turnstile"] > div ')) {
                    break;
                }
            } else {
                break;
            }
        }
    }

    private function checkFillTwoFactor()
    {
        $two_factor_selector = '[data-test="otp-input"]';
        $two_factor_message_selector = './/p[contains(normalize-space(.), "Enter the code sent to")]//span';
        $two_factor_submit_selector = '';

        if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->getElement($two_factor_message_selector, null, 'xpath') != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector, null, 'xpath')); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector, null, 'xpath')[$i]->getText() . "\n";
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
                sleep(4);
                $resultCodes = str_split($two_factor_code);
                foreach ($resultCodes as $inputVal) {
                    $this->exts->log("inputVal: " . $inputVal);
                    sleep(2);
                    $this->exts->type_text_by_xdotool($inputVal);
                }

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
                sleep(12);

                if ($this->exts->querySelector($two_factor_selector) == null) {
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


    /**

     * Method to Check where user is logged in or not

     * return boolean true/false

     */
    private function checkLogin()
    {
        return $this->exts->queryXpath($this->check_login_success_selector) != null;
    }

    private function processInvoices()
    {
        sleep(10);
        $this->exts->capture("4-invoices-page");
        // Keep clicking more but maximum upto 10 times

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
        if ($restrictPages == 0) {
            $maxAttempts = 10;
        } else {
            $maxAttempts = $restrictPages;
        }
        for ($paging_count = 0; $paging_count < $maxAttempts; $paging_count++) {
            $this->exts->execute_javascript('
                var btn = document.querySelector("button[data-testid=\'view-more-button\']");
                if(btn){
                    btn.click();
                }
            ');
            sleep(8);
        }
        $invoices = [];

        $rows = $this->exts->querySelectorAll('a[href*="invoice.stripe.com"]');
        foreach ($rows as $row) {
            array_push($invoices, array(
                'invoiceUrl' => $row->getAttribute('href')
            ));
        }
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->openUrl($invoice['invoiceUrl']);
            sleep(5);
            if ($this->exts->querySelector('div.InvoiceDetailsRow-Container > button:nth-child(1)') != null) {
                $invoiceName = $this->exts->extract('table.InvoiceDetails-table tr:nth-child(1) > td:nth-child(2)');
                $invoiceDate = $this->exts->extract('table.InvoiceDetails-table tr:nth-child(2) > td:nth-child(2)');
                $invoiceAmount = $this->exts->extract('div[data-testid="invoice-summary-post-payment"] h1[data-testid="invoice-amount-post-payment"]');
                $downloadBtn = $this->exts->querySelector('div.InvoiceDetailsRow-Container > button:nth-child(1)');

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);
                $this->isNoInvoice = false;
            }

            $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : "";
            $invoice['invoiceDate'] = $this->exts->parse_date($invoiceDate, 'm.d.y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoiceDate);

            // $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            $downloaded_file = $this->exts->click_and_download($downloadBtn, 'pdf', $invoiceFileName);

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }
}
