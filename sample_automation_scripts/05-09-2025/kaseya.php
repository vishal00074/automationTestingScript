<?php // replace waitTillPresent to custom js waitFor function and adjust sleep time

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

    // Server-Portal-ID: 100357 - Last modified: 04.09.2025 10:07:53 UTC - User: 1

    public $baseUrl = 'https://one.kaseya.com/home';
    public $loginUrl = 'https://one.kaseya.com/login';
    public $invoicePageUrl = 'https://myportal.kaseya.com/sca-dev-2021-2-0/my_account.ssp#transactionhistory';

    public $username_selector = 'input#username';
    public $company_selector = 'input#organizationName';
    public $password_selector = 'input#password';
    public $otp_description_selector = 'div.totp-mfa-verification__description';
    public $otp_selector = 'input#one-time-code,input[name="one-time-code"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button,button[data-test-id="totp-push-verification-button-verify-code"]';
    public $check_login_failed_selector = 'div.error-message';
    public $check_login_success_selector = 'div.profile__labels, div.profile-menu-old__labels,div.profile-menu__labels';

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
        sleep(10);
        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(2);
            $this->fillForm(0);
            sleep(10);
        }

        if ($this->checkLogin()) {
            $this->exts->startTrakingTabsChange();
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            sleep(10);

            if ($this->exts->exists(".//span[normalize-space(text())='Subscriptions & Billing']")) {
                $this->exts->click_element(".//span[normalize-space(text())='Subscriptions & Billing']");
            }
            if ($this->exts->exists('[data-test-id="left-navigation-menu-item-view-and-pay-invoices"]')) {
                $this->exts->click_element('[data-test-id="left-navigation-menu-item-view-and-pay-invoices"]');
            } elseif ($this->exts->exists(".//span[@class='navigation-link__item-text' and text()='View and Pay Invoices']")) {
                $this->exts->click_element(".//span[@class='navigation-link__item-text' and text()='View and Pay Invoices']");
            }

            sleep(30);
            $this->exts->switchToNewestActiveTab();
            $this->processInvoices();
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            if ($this->exts->exists($this->check_login_failed_selector)) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function processInvoices($paging_count = 1)
    {
        sleep(20);
        $this->waitFor('table.invoice-list-records > tbody > tr', 10);

        $rows = $this->exts->querySelectorAll('table.invoice-list-records > tbody > tr');
        $this->exts->log('Invoices found: ' . count($rows));
        if (count($rows) === 0) {
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(5);
            $this->exts->execute_javascript("
        var selectBox = document.querySelector('select[name=\"filter\"]');
        selectBox.value = 'CustInvc';
        selectBox.dispatchEvent(new Event('change', { bubbles: true }));
    ");
            sleep(5);
            $this->runInvoiceProcess();
        }

        foreach ($rows as $row) {
            $this->isNoInvoice = false;

            $invoiceAmount = trim($row->querySelectorAll('td')[4]->getText());

            $this->exts->log('invoice amount: ' . $invoiceAmount);

            $invoiceDate = trim($row->querySelectorAll('td')[3]->getText());

            $this->exts->log('invoice date: ' . $invoiceDate);

            $parsedDate = $this->exts->parse_date($invoiceDate, 'd/m/Y', 'Y-m-d');
            $this->exts->log('Parsed date: ' . $parsedDate);

            $row->querySelector('a')->click();
            sleep(1);

            $this->exts->wait_and_check_download('pdf');
            $downloaded_file = $this->exts->find_saved_file('pdf');
            $invoiceFileName = basename($downloaded_file);

            $invoiceName = substr($invoiceFileName, 0, strrpos($invoiceFileName, '.'));

            $this->exts->log('invoiceName: ' . $invoiceName);

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoiceName, $parsedDate, $invoiceAmount, $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }

    private function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->waitFor($this->username_selector, 10);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                $this->exts->log("Enter Company Name");
                $companyName = $this->exts->getConfig('company_name') ?? '';
                $this->exts->log("Company Name:" . $companyName);

                $this->exts->moveToElementAndType($this->company_selector, $companyName);
                $this->exts->click_element($this->submit_login_selector);

                $this->waitFor($this->password_selector, 10);
                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);

                $this->exts->capture("1-login-page-filled");
                sleep(1);
                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->click_element($this->submit_login_selector);
                    sleep(10);
                }

                $this->waitFor($this->otp_selector, 10);
                $this->checkFillTwoFactor();
            }
        } catch (\Exception $exception) {

            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    private function checkFillTwoFactor()
    {
        $two_factor_selector = $this->otp_selector;
        $two_factor_message_selector = $this->otp_description_selector;
        $two_factor_submit_selector = $this->submit_login_selector;

        $this->waitFor($two_factor_selector, 15);
        if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->querySelector($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($two_factor_message_selector));
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
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
                sleep(1);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(10);

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
    private function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            $this->waitFor($this->check_login_success_selector, 10);
            if ($this->exts->exists($this->check_login_success_selector)) {

                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

                $isLoggedIn = true;
            }
        } catch (Exception $exception) {

            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }

    public function runInvoiceProcess()
    {
        $invoices = [];
        $this->processInvoicesNew(1, $invoices);
        $this->exts->log("Finished invoice pagination, now downloading...");
        $this->downloadInvoices($invoices);
    }

    private function processInvoicesNew($paging_count = 1, &$invoices = [])
    {
        $this->waitFor('table tbody tr', 30);
        $this->exts->capture("4-invoices-page");

        $rows = $this->exts->querySelectorAll('table tbody tr');
        foreach ($rows as $row) {
            if ($this->exts->querySelector('td:nth-child(1)', $row) != null) {
                $invoiceId = $row->getHtmlAttribute('data-navigation-hashtag');
                $this->exts->log("invoiceId " . $invoiceId);
                $invoiceUrl = 'https://myportal.kaseya.com/sca-dev-2021-2-0/my_account.ssp#' . $invoiceId;
                $this->exts->log("invoiceUrl " . $invoiceUrl);

                array_push($invoices, array(
                    'invoiceName' => $this->exts->extract('td:nth-child(1) a >span', $row),
                    'invoiceDate' => $this->exts->extract('td:nth-child(3) span:last-child', $row),
                    'invoiceAmount' => $this->exts->extract('td:nth-child(4) span:last-child', $row),
                    'invoiceUrl' => $invoiceUrl,
                    'downloadBtn' => ''
                ));

                $this->isNoInvoice = false;
            }
        }

        sleep(5);
        $nextPageBtn = $this->exts->querySelector('global-views-pagination-next');
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        if ($restrictPages == 0 && $paging_count < 50 && $nextPageBtn != null) {
            $paging_count++;
            $this->exts->execute_javascript("arguments[0].click();", [$nextPageBtn]);
            sleep(5);
            $this->processInvoicesNew($paging_count, $invoices);
        } else if ($restrictPages > 0 && $paging_count < $restrictPages && $nextPageBtn != null) {
            $paging_count++;
            $this->exts->execute_javascript("arguments[0].click();", [$nextPageBtn]);
            sleep(5);
            $this->processInvoicesNew($paging_count, $invoices);
        }
    }

    private function downloadInvoices($invoices)
    {
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : "";
            $this->exts->openUrl($invoice['invoiceUrl']);
            sleep(10);

            $this->waitFor('a.invoice-details-button-download-as-pdf', 7);
            $downloadButton = $this->exts->querySelector('a.invoice-details-button-download-as-pdf');
            $this->exts->execute_javascript("arguments[0].click();", [$downloadButton]);
            $this->exts->wait_and_check_download('pdf');
            $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP("optimized-chrome-v2", 'Avaza', '2673526', 'bHVkd2lnQHN1cnZleWVuZ2luZS5jb20=', 'cHNDZms4Ny4=');
$portal->run();
