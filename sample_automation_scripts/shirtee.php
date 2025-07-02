<?php // migrated

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

    // Server-Portal-ID: 104932 - Last modified: 22.01.2024 13:38:04 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = "https://www.shirtee.com/de/customer/account/login/";
    public $username_selector = '#login-form #email';
    public $password_selector = '#login-form #pass';
    public $submit_btn = "#login-form #send2";
    public $logout_btn = '[href*="/logout"]';
    public $wrong_credential_selector = "#login-form .error-msg li";

    public $isNoInvoice = true;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $isCookieLoaded = false;
        if ($this->exts->loadCookiesFromFile()) {
            sleep(1);
            $isCookieLoaded = true;
        }
        $this->exts->openUrl($this->baseUrl);
        sleep(12);

        if ($this->exts->querySelector('div.std span a[href*="shirtee"]')) {
            $this->exts->moveToElementAndClick('div.std span a[href*="shirtee"]');
            $this->clearChrome();
        }
        $this->exts->openUrl($this->baseUrl);
        sleep(10);

        if ($isCookieLoaded) {
            $this->exts->capture("Home-page-with-cookie");
        } else {
            $this->exts->capture("Home-page-without-cookie");
        }

        if (!$this->checkLogin() && !$this->isWrongCredential()) {
            if ($this->exts->exists('.cc-btn.cc-dismiss')) {
                $this->exts->moveToElementAndClick('.cc-btn.cc-dismiss');
                sleep(1);
            }
            if ($this->exts->exists('button#acceptAll')) {
                $this->exts->moveToElementAndClick('button#acceptAll');
                sleep(5);
            }
            $this->fillForm(0);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            $this->exts->openUrl('https://www.shirtee.com/en/customdashboard/order/history/');
            sleep(10);
            $this->downloadInvoice(0);
            sleep(5);
            $this->exts->moveToElementAndClick('[assigned_to="userhistory"]');
            sleep(5);
            $this->downloadInvoice(0);
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
        } else {
            $this->exts->capture("LoginFailed");
            if ($this->isWrongCredential()) {
                $this->exts->log($this->exts->extract($this->wrong_credential_selector, null));
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    public function isWrongCredential()
    {
        $tag = $this->exts->getElement($this->wrong_credential_selector);
        if ($tag != null) {
            return true;
        }
        return false;
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
        for ($i = 0; $i < 6; $i++) {
            $this->exts->type_key_by_xdotool('Tab');
        }
        $this->exts->type_key_by_xdotool('Return');
        sleep(15);
        $this->exts->capture("after-clear");
    }

    /**
     * Method to fill login form
     * @param Integer $count Number of times portal is retried.
     */
    function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {

            if ($this->exts->exists($this->username_selector)) {
                sleep(2);
                $this->exts->capture("1-pre-login");

                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username, 5);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password, 5);

                $this->exts->capture("1-pre-login-1");
                $this->checkFillRecaptcha(0);

                $this->exts->moveToElementAndClick($this->submit_btn);
                sleep(10);
                $this->checkFillRecaptcha(0);
            } else if ($this->exts->exists("iframe[src*=\"https://www.google.com/recaptcha/api2/anchor?\"]") && $this->exts->exists("textarea[name=\"g-recaptcha-response\"]")) {
                $this->checkFillRecaptcha(0);
                if ($count < 5) {
                    $this->fillForm($count + 1);
                } else {
                    $this->exts->log(__FUNCTION__ . " :: too many recaptcha attempts " . $count);
                    $this->exts->loginFailure();
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    function checkFillRecaptcha($count)
    {

        if (
            $this->exts->exists('iframe[src*="https://www.google.com/recaptcha/api2/anchor?"]') &&
            $this->exts->exists('textarea[name="g-recaptcha-response"]') &&
            $count < 3
        ) {

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
                $this->exts->execute_javascript("document.querySelector(\"#g-recaptcha-response\").value = '" . $this->exts->recaptcha_answer . "';");
                $this->exts->execute_javascript("document.querySelector(\"#g-recaptcha-response\").innerHTML = '" . $this->exts->recaptcha_answer . "';");
                sleep(5);
            } else {
                $this->exts->log("Captcha expired, retry...");
                $this->checkFillRecaptcha($count + 1);
            }
        } else if ($count >= 3) {
            $this->exts->log('Recaptcha exceeds 3 times');
        } else {
            $this->exts->log('There are no recaptcha');
        }
    }

    /**
     * Method to Check where user is logged in or not
     * return boolean true/false
     */
    public function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            if ($this->exts->exists($this->logout_btn)) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }

        return $isLoggedIn;
    }

    public function downloadInvoice($count = 1)
    {
        $this->exts->log("Begin download invoice - " . $count);
        $this->exts->capture("2-download-invoice-" . $count);
        try {
            if ($this->exts->exists('.new-account-table-orders > tbody > tr a[href*="print"]')) {

                $invoices = array();

                $receipts = $this->exts->getElements('.new-account-table-orders > tbody > tr');
                foreach ($receipts as $i => $receipt) {

                    try {
                        $receiptDate = trim($this->exts->extract('td:nth-child(3)', $receipt));
                    } catch (\Exception $exception) {
                        $receiptDate = null;
                    }

                    $this->exts->log($receiptDate);
                    if ($receiptDate != null && $this->exts->extract('a[href*="print"]', $receipt, 'href') != null) {
                        $this->exts->log($receiptDate);

                        $receiptName = trim($this->exts->extract('td:nth-child(1) span', $receipt));
                        $receiptName = trim(end(explode('#', $receiptName, 2)));

                        $this->exts->log($receiptName);
                        $parsed_date = $this->exts->parse_date($receiptDate, 'F d, Y', 'Y-m-d');
                        $this->exts->log($parsed_date);
                        $receiptFileName = !empty($receiptName) ? $receiptName . '.pdf' : '';
                        $this->exts->log($receiptFileName);

                        $receiptAmount = trim($this->exts->extract('td:nth-child(5)', $receipt));
                        $currency = $this->getCurrency($receiptAmount);
                        $receiptAmount = preg_replace('/[^\d\.,]/m', '', $receiptAmount);
                        $receiptAmount = trim($receiptAmount . ' ' . $currency);
                        $this->isNoInvoice = false;
                        $receiptUrl = $this->exts->extract('a[href*="downloadOrderInvoice"]', $receipt, 'href');
                        if ($receiptUrl == '') $receiptUrl = $this->exts->extract('a[href*="print"]', $receipt, 'href');
                        $invoice = array(
                            'receiptName' => $receiptName,
                            'receiptUrl' => $receiptUrl,
                            'parsed_date' => $parsed_date,
                            'receiptAmount' => $receiptAmount,
                            'receiptFileName' => $receiptFileName
                        );
                        array_push($invoices, $invoice);
                    }
                }

                foreach ($invoices as $invoice) {
                    try {
                        $this->exts->openUrl($invoice['receiptUrl']);
                        sleep(3);
                        // Wait for completion of file download
                        $this->exts->wait_and_check_download('pdf');

                        // find new saved file and return its path
                        $downloaded_file = $this->exts->find_saved_file('pdf', $invoice['receiptFileName']);

                        if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                            $this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $downloaded_file);
                        }
                        sleep(3);
                    } catch (\Exception $exception) {
                        $this->exts->log("Exception downloading invoice - " . $exception->getMessage());
                    }
                };
            } else {
                $invoices = [];

                $rows = $this->exts->getElements('.new-account-table-orders > tbody > tr');
                foreach ($rows as $row) {
                    $tags = $this->exts->getElements('td', $row);
                    if (count($tags) >= 9 && $this->exts->getElement('a[href*="downloadOrderInvoice"]', $tags[7]) != null) {
                        $invoiceUrl = $this->exts->getElement('a[href*="downloadOrderInvoice"]', $tags[7])->getAttribute("href");
                        $invoiceName = trim($tags[0]->getAttribute('innerText'));
                        $invoiceDate = trim($tags[3]->getAttribute('innerText'));
                        $invoiceAmount = '';

                        array_push($invoices, array(
                            'invoiceName' => $invoiceName,
                            'invoiceDate' => $invoiceDate,
                            'invoiceAmount' => $invoiceAmount,
                            'invoiceUrl' => $invoiceUrl
                        ));
                        $this->isNoInvoice = false;
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
                    $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'F j, Y', 'Y-m-d');
                    $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

                    $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $downloaded_file);
                        sleep(1);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }
                $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
                if (
                    $restrictPages == 0 &&
                    $count < 50 &&
                    $this->exts->getElement('a.next') != null
                ) {
                    $count++;
                    $this->exts->moveToElementAndClick('a.next');
                    sleep(5);
                    $this->downloadInvoice($count);
                } else if (
                    $count < 2 &&
                    $this->exts->getElement('a.next') != null
                ) {
                    $count++;
                    $this->exts->moveToElementAndClick('a.next');
                    sleep(5);
                    $this->downloadInvoice($count);
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception downloading invoice " . $exception->getMessage());
        }
    }

    /**
     * check if a element is exists
     * @param String $sel Css selector
     */
    public function getCurrency($amountString)
    {
        if (strpos($amountString, '$') !== false) {
            return 'USD';
        } else if (strpos($amountString, 'â‚¬') !== false) {
            return 'EUR';
        } else if (strpos($amountString, 'Â£') !== false) {
            return 'GBP';
        } else {
            return '';
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
