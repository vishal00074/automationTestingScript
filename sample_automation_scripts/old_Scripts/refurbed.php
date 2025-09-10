<?php // update waittillpresent to waitFor and add loginfailed selector and added the restrictPages condition

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

    /*Define constants used in script*/
    // Server-Portal-ID: 2846863 - Last modified: 30.06.2025 15:56:57 UTC - User: 1

    public $baseUrl = 'https://www.refurbed.de/login/?redirect=%2Faccount%2Forders%2F';
    public $loginUrl = 'https://www.refurbed.de/login/?redirect=%2Faccount%2Forders%2F';
    public $username_selector = 'input[type="email"][name="email"]';
    public $password_selector = 'input[type="password"][name="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button[type="submit"][data-test="login-submit-btn"]';

    public $check_login_failed_selector = 'p[data-cy="login-error-message"],p[id="email-error"]';
    public $check_login_success_selector = 'a[href="/account/orders"]';

    public $isNoInvoice = true;

    /**

     * Entry Method thats called for a portal

     * @param Integer $count Number of times portal is retried.

     */
    private function initPortal($count)
    {

        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->loginUrl);
        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);

            if ($this->exts->exists('button#acceptAllCookiesBtn')) {
                $this->exts->click_element('button#acceptAllCookiesBtn');
                sleep(2);
            }
            $this->fillForm(0);

            sleep(9);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            sleep(2);
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

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }

    function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->waitFor($this->username_selector, 5);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(1);

                if ($this->exts->exists($this->remember_me_selector)) {
                    $this->exts->click_by_xdotool($this->remember_me_selector);
                    sleep(1);
                }
                $this->exts->capture("1-login-page-filled");
                sleep(5);
                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->click_by_xdotool($this->submit_login_selector);
                }
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
            $this->waitFor($this->check_login_success_selector, 20);
            if ($this->exts->exists($this->check_login_success_selector)) {

                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

                $isLoggedIn = true;
            }
        } catch (Exception $exception) {

            $this->exts->log("Exception checking loggedin " . $exception);
        }

        return $isLoggedIn;
    }

    public $totalInvoices = 0;
    private function processInvoices($paging_count = 1)
    {
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
        $this->exts->log("restrictPages:: " . $restrictPages);

        $this->waitFor('div[data-test="order-id"]', 15);
        $this->exts->capture("invoices-page");
        $invoices = [];

        // Select all invoice containers
        $invoiceContainers = $this->exts->querySelectorAll('div[data-test="order"]');

        foreach ($invoiceContainers as $invoiceContainer) {

            try {
                // Extract details from each invoice
                $invoiceName = $this->exts->extract('div[data-test="order-id"]', $invoiceContainer);
                $invoiceDateRaw = $this->exts->extract('div[data-test="order-date"]', $invoiceContainer);
                $invoiceAmountRaw = $this->exts->extract('div[data-test="order-total"]', $invoiceContainer);

                $invoiceName = str_replace('#', '', $invoiceName);
                // Clean up invoice data
                $invoiceAmount = preg_replace('/[^0-9,.]/', '', $invoiceAmountRaw);
                $invoiceDate = $this->exts->parse_date($invoiceDateRaw, 'd.m.Y', 'Y-m-d');

                // Extract the download URL
                $currentUrl = $this->exts->getUrl();
                $this->exts->log($currentUrl);
                $downloadUrl = $currentUrl . $invoiceName;

                if ($downloadUrl) {
                    $invoices[] = [
                        'invoiceName' => trim($invoiceName),
                        'invoiceDate' => trim($invoiceDate),
                        'invoiceAmount' => trim($invoiceAmount),
                        'downloadUrl' => $downloadUrl,
                    ];
                    $this->isNoInvoice = false;
                }
            } catch (\Exception $e) {
                $this->exts->log("Error extracting invoice data: " . $e->getMessage());
            }
        }

        // Log and process invoices
        $this->exts->log('Invoices found: ' . count($invoices));

        foreach ($invoices as $invoice) {
            if ($restrictPages != 0 && $this->totalInvoices >= 50) {
                return;
            }
            $this->exts->log('--------------------------');
            $this->exts->log('Invoice Name: ' . $invoice['invoiceName']);
            $this->exts->log('Invoice Date: ' . $invoice['invoiceDate']);
            $this->exts->log('Invoice Amount: ' . $invoice['invoiceAmount']);
            $this->exts->openUrl($invoice['downloadUrl']);
            sleep(2);
            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';

            try {
                // Download the file using the extracted URL
                $this->waitFor('button[document-type="order_confirmation"]', 15);

                sleep(5);

                $download_button = $this->exts->getElement('button[document-type="order_confirmation"]');
                if ($download_button != null) {
                    try {
                        $this->exts->log('Click download_button button');
                        // $download_button->click();
                        // $this->exts->click_element($download_button);
                        $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
                    } catch (\Exception $exception) {
                        $this->exts->log('Click download_button button by javascript');
                        $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
                    }
                    sleep(5);
                    $this->exts->wait_and_check_download('pdf');
                    $downloadedFile = $this->exts->find_saved_file('pdf', '');


                    if (trim($downloadedFile) !== '' && file_exists($downloadedFile)) {
                        $invoiceFileName = basename($downloadedFile);
                        $invoiceName = explode('.PDF', explode('.pdf', $invoiceFileName)[0])[0];
                        $this->exts->log('invoiceName: ' . $invoiceName);

                        if ($this->exts->invoice_exists($invoiceName)) {
                            $this->exts->log('Invoice existed ' . $invoiceFileName);
                        } else {
                            $this->exts->new_invoice(
                                $invoiceName,
                                $invoice['invoiceDate'],
                                $invoice['invoiceAmount'],
                                $invoiceFileName
                            );
                            sleep(1);
                            $this->totalInvoices++;
                        }
                        sleep(1);
                    } else {
                        $this->exts->log("No downloaded file found for invoice: " . $invoiceFileName);
                    }
                }
            } catch (\Exception $e) {
                $this->exts->log("Error downloading invoice file: " . $e->getMessage());
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
