<?php // replace waitTillPresent with custom js function waitFor and adjust sleep time

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

    // Server-Portal-ID: 1863256 - Last modified: 03.09.2025 23:15:34 UTC - User: 1

    /*Define constants used in script*/

    public $baseUrl = 'https://shop.pluxee.de';
    public $loginUrl = 'https://shop.pluxee.de/account/login';
    public $invoicePageUrl = 'https://shop.pluxee.de/account/order-documents';

    public $username_selector = 'input#loginMail';
    public $password_selector = 'input#loginpasswordfield';
    public $remember_me_selector = '';
    public $submit_login_selector = 'div.login-submit > button[type="submit"]';

    public $check_login_failed_selector = 'div.alert-danger';
    public $check_login_success_selector = 'button#accountWidget';

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
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            sleep(2);

            $this->exts->openUrl($this->invoicePageUrl);
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

    function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->waitFor($this->username_selector, 7);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(1);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(1);

                if ($this->exts->exists($this->remember_me_selector)) {
                    $this->exts->log("Remember Me");
                    $this->exts->moveToElementAndClick($this->remember_me_selector);
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
            $this->waitFor($this->check_login_success_selector, 5);
            $this->waitFor($this->check_login_success_selector, 5);
            if ($this->exts->exists($this->check_login_success_selector)) {

                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

                $isLoggedIn = true;
            }
        } catch (Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }

        return $isLoggedIn;
    }

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }

    private function processInvoices()
    {
        sleep(10);
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)$this->exts->config_array["restrictPages"] : 3;
        $pagingCount = 0;
        $this->exts->log('Restrict Pages: ' . $restrictPages);

        $restrictDate = $restrictPages == 0 ? date('Y-m-d', strtotime('-2 years')) : date('Y-m-d', strtotime('-3 months'));
        $dateRestriction = true; // (true) in case of date filter
        $this->exts->log('Restrict Date: ' . $restrictDate);

        $maxInvoices = 10;
        $invoiceCount = 0;

        $terminateLoop = false;


        $this->waitFor('div.order-documents table tbody tr td a[href*="download"]', 5);
        $this->waitFor('div.order-documents table tbody tr td a[href*="download"]', 5);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        do {

            $pagingCount++;

            $this->waitFor('div.order-documents table tbody tr td a[href*="download"]', 10);

            $rows = $this->exts->querySelectorAll('div.order-documents table tbody tr');
            $anchor = 'td a[href*="download"]';

            foreach ($rows as $row) {

                if ($this->exts->querySelector($anchor, $row) != null) {

                    $invoiceCount++;

                    $invoiceUrl = $this->exts->querySelector($anchor, $row)->getAttribute('href');
                    $invoiceName = $this->exts->extract('td:nth-child(2)', $row);
                    $invoiceAmount = $this->exts->extract('td:nth-child(5)', $row);
                    $invoiceDate = $this->exts->extract('td:nth-child(4)', $row);

                    $downloadBtn = $this->exts->querySelector($anchor, $row);

                    array_push($invoices, array(
                        'invoiceName' => $invoiceName,
                        'invoiceDate' => $invoiceDate,
                        'invoiceAmount' => $invoiceAmount,
                        'invoiceUrl' => $invoiceUrl,
                        'downloadBtn' => $downloadBtn
                    ));

                    $this->isNoInvoice = false;

                    $this->exts->log('--------------------------');
                    $this->exts->log('invoiceName: ' . $invoiceName);
                    $this->exts->log('invoiceDate: ' . $invoiceDate);
                    $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                    $this->exts->log('invoiceUrl: ' . $invoiceUrl);

                    $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                    $invoiceDate = $this->exts->parse_date(trim($invoiceDate), 'd.m.Y', 'Y-m-d');
                    $this->exts->log('Date parsed: ' . $invoiceDate);

                    $this->exts->execute_javascript("arguments[0].click();", [$downloadBtn]);

                    sleep(2);

                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf');
                    $invoiceFileName = basename($downloaded_file);

                    $invoiceName = substr($invoiceFileName, 0, strrpos($invoiceFileName, '.'));

                    $this->exts->log('invoiceName: ' . $invoiceName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                        sleep(1);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }

                    $this->exts->log(' ');
                    $this->exts->log('---------------------------INVOICE ITERATION END-------------------------');
                    $this->exts->log(' ');


                    $lastDate = !empty($invoiceDate) && $invoiceDate <= $restrictDate;

                    if ($restrictPages != 0 && ($invoiceCount == $maxInvoices || ($dateRestriction && $lastDate))) {
                        $terminateLoop = true;
                        break;
                    } elseif ($restrictPages == 0 && $dateRestriction && $lastDate) {
                        $terminateLoop = true;
                        break;
                    }
                }
            }


            if ($restrictPages != 0 && $pagingCount == $restrictPages) {
                break;
            } elseif ($terminateLoop) {
                break;
            }

            // pagination handle			
            if ($this->exts->exists('ul.pagination > li:nth-child(' . ($pagingCount + 1) . ') > button[data-page-no="' . ($pagingCount + 1) . '"]')) {
                $this->exts->log('Click Next Page in Pagination!');
                $this->exts->click_element('ul.pagination > li:nth-child(' . ($pagingCount + 1) . ') > button[data-page-no="' . ($pagingCount + 1) . '"]');
                sleep(5);
            } else {
                $this->exts->log('Last Page!');
                break;
            }
        } while (true);

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP("optimized-chrome-v2", 'Sodexo Shop', '2673591', 'YnVjaGhAbWFkZS12ZW51ZXMuY29t', 'cjd1NXlBciFtQlllOWdl');
$portal->run();
