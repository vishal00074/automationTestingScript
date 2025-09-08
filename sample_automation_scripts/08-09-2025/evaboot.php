<?php // change exists with custom js function isExists updated billing button xpath in invoice code use best approcah 
// adust sleep time and optimize the script performance updated login failed selector
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

    // Server-Portal-ID: 823854 - Last modified: 17.07.2025 13:36:07 UTC - User: 1

    public $baseUrl = 'https://app.evaboot.com/?page=export';
    public $loginUrl = 'https://app.evaboot.com/access?p=login';
    public $invoicePageUrl = 'https://app.evaboot.com/?page=account';

    public $username_selector = 'input#email';
    public $password_selector = 'input#signup-email';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button.clickable-element:nth-child(4)';

    public $check_login_failed_selector = './/div[normalize-space(.)="Please check your email and password combination or log in using Google/Microsoft"]';
    public $check_login_success_selector = './/div[contains(text(),"Logout")]';

    public $isNoInvoice = true;

    /**<input type="password" name="password" autocomplete="current-password" class="textinput textInput" required id="id_password">

     * Entry Method thats called for a portal

     * @param Integer $count Number of times portal is retried.

     */
    private function initPortal($count)
    {

        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->baseUrl);

        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);

            $this->fillForm(0);
            sleep(30);

            if ($this->isExists('button [href*="fa-close"]')) {
                $this->exts->moveToElementAndClick('button [href*="fa-close"]');
                sleep(2);
            }
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            sleep(2);
            if ($this->isExists('button [href*="fa-close"]')) {
                $this->exts->moveToElementAndClick('button [href*="fa-close"]');
                sleep(2);
            }

            $this->exts->openUrl($this->invoicePageUrl);
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {
            if (count($this->exts->getElements($this->check_login_failed_selector)) != 0) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        // $this->exts->waitTillPresent($this->username_selector, 15);
        for ($i = 0; $i < 10 && $this->exts->getElement($this->username_selector) == null; $i++) {
            sleep(2);
        }
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(1);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(1);

                if ($this->isExists($this->remember_me_selector)) {
                    $this->exts->log("Remember Me");
                    $this->exts->moveToElementAndClick($this->remember_me_selector);
                    sleep(1);
                }

                $this->exts->capture("1-login-page-filled");
                sleep(5);

                if ($this->isExists($this->submit_login_selector)) {
                    $this->exts->click_by_xdotool($this->submit_login_selector);
                }
                sleep(10);
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    /**

     * Method to Check where user is logged in or not

     * return boolean true/false

     */
    function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            // $this->exts->waitTillPresent($this->check_login_success_selector, 20);
            for ($i = 0; $i < 10 && $this->exts->getElement($this->check_login_success_selector) == null && $this->exts->getElement($this->username_selector) == null; $i++) {
                sleep(2);
            }
            if ($this->exts->queryXpath('.//div[contains(text(),"Account")]') != null && $this->exts->getElement($this->check_login_success_selector) == null) {
                $this->exts->moveToElementAndClick('.//div[contains(text(),"Account")]');
                sleep(2);
            }
            if ($this->exts->getElement($this->check_login_success_selector)) {

                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

                $isLoggedIn = true;
            }
        } catch (Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }

        return $isLoggedIn;
    }

    private function isExists($selector = '')
    {
        $safeSelector = addslashes($selector);
        $this->exts->log('Element:: ' . $safeSelector);
        $isElement = $this->exts->execute_javascript('!!document.querySelector("' . $safeSelector . '")');
        if ($isElement) {
            $this->exts->log('Element Found');
            return true;
        } else {
            $this->exts->log('Element not Found');
            return false;
        }
    }

    private function processInvoices($paging_count = 1)
    {
        // In case of date filter:
        // If $restrictPages == 0, then download upto 3 years of invoices.
        // If $restrictPages != 0, then download upto 3 months of invoices with maximum 100 invoices.

        // In case of pagination and no date filter:
        // If $restrictPages == 0, then download all available invoices on all pages.
        // If $restrictPages != 0, then download upto pages in $restrictPages with maximum 100 invoices.

        // In case of no date filter and no pagination:
        // If $restrictPages == 0, then download all available invoices.
        // If $restrictPages != 0, then download upto 100 invoices.


        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)$this->exts->config_array["restrictPages"] : 3;
        $pagingCount = 0;
        $this->exts->log('Restrict Pages: ' . $restrictPages);

        $restrictDate = $restrictPages == 0 ? date('Y-m-d', strtotime('-2 years')) : date('Y-m-d', strtotime('-3 months'));
        $dateRestriction = true; // (true) in case of date filter
        $this->exts->log('Restrict Date: ' . $restrictDate);

        $maxInvoices = 10;
        $invoiceCount = 0;
        if ($this->isExists('button [href*="fa-close"]')) {
            $this->exts->moveToElementAndClick('button [href*="fa-close"]');
            sleep(2);
        }

        $this->exts->startTrakingTabsChange();

        for ($i = 0; $i < 10 && $this->exts->getElement('.//button[contains(text(),"Open Billing Portal")]') == null; $i++) {
            sleep(2);
        }
        if ($this->exts->queryXpath('.//button[contains(text(),"Open Billing Portal")]') != null) {
            $this->exts->click_element('.//button[contains(text(),"Open Billing Portal")]');
            sleep(5);
        }

        $this->exts->switchToNewestActiveTab();

        for ($i = 0; $i < 15 && $this->exts->getElement('a[href*="https://invoice.stripe.com/"]') == null; $i++) {
            sleep(2);
        }
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $loadMoreBtn = 'button[data-testid="view-more-button"]';
        for ($i = 0; $i < 15 && $this->exts->getElement($loadMoreBtn) == null; $i++) {
            sleep(2);
        }
        if ($this->isExists($loadMoreBtn)) {
            do {
                $this->exts->click_element($loadMoreBtn);
                sleep(5);
                for ($j = 0; $j < 15 && $this->exts->getElement($loadMoreBtn) == null; $j++) {
                    sleep(2);
                }
                if (!$this->isExists($loadMoreBtn)) {
                    break;
                }
            } while (true);
        }

        $rows = $this->exts->querySelectorAll('a[href*="https://invoice.stripe.com/"]');
        $this->invoicePageUrl = $this->exts->getUrl();
        $pagingCount++;
        foreach ($rows as $row) {
            if ($this->exts->querySelector('div', $row) != null) {
                $invoiceCount++;
                $invoiceUrl = $row->getAttribute('href');
                $invoiceName = '';
                $invoiceAmount = $this->exts->extract('div > div:nth-child(2) > span', $row);

                $invoiceDate = $this->exts->extract('div > div:first-child > span', $row);

                $formattedDate = date("Y-m-d", strtotime($invoiceDate));

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl
                ));

                $this->isNoInvoice = false;

                $lastDate = !empty($formattedDate) && $formattedDate <= $restrictDate;

                if ($restrictPages != 0 && ($invoiceCount == $maxInvoices || ($dateRestriction && $lastDate))) {
                    break;
                } else if ($restrictPages == 0 && $dateRestriction && $lastDate) {
                    break;
                }
            }
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));

        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $this->exts->openUrl($invoice['invoiceUrl']);
            for ($j = 0; $j < 10 && $this->exts->getElement('div.InvoiceDetailsRow-Container button') == null; $j++) {
                sleep(2);
            }
            $downloadBtn = $this->exts->getElement('.//button[contains(@class, "Button--primary")]//span[contains(text(),"Rechnung herunterladen") or contains(text(),"Download invoice")]');

            $this->exts->execute_javascript("arguments[0].click();", [$downloadBtn]);

            sleep(2);

            $this->exts->wait_and_check_download('pdf');
            $downloaded_file = $this->exts->find_saved_file('pdf');
            $invoiceFileName = basename($downloaded_file);

            $invoice['invoiceName'] = substr($invoiceFileName, 0, strrpos($invoiceFileName, '.'));

            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }

            $this->exts->log(' ');
            $this->exts->log('---------------------------INVOICE ITERATION END-------------------------');
            $this->exts->log(' ');
        }

        $this->exts->openUrl($this->invoicePageUrl);
    }
}
