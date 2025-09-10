<?php // updated download code

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

    // Server-Portal-ID: 778938 - Last modified: 04.03.2025 14:09:12 UTC - User: 1

    public $baseUrl = 'https://login.payjoe.de/login';
    public $loginUrl = 'https://login.payjoe.de/login';
    public $invoicePageUrl = 'https://login.payjoe.de/activities';

    public $username_selector = 'input[id="mat-input-0"]';
    public $password_selector = 'input[id="mat-input-1"]';
    public $remember_me_selector = 'label[class="mat-checkbox-layout"]';
    public $submit_login_selector = 'button[type="submit"]';

    public $check_login_failed_selector = 'div mat-error[id="mat-error-0"]';
    public $check_login_success_selector = 'a[href="/activities"]';

    public $isNoInvoice = true;

    /**

     * Entry Method thats called for a portal

     * @param Integer $count Number of times portal is retried.  a[href*="SignIn"]

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

            $this->fillForm(0);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            // comment due to there is not pdf format invoice found in this code.
            // $this->dateRange();

            // $this->exts->openUrl('https://login.payjoe.de/activities?year=2023&month=6');
            // $this->exts->waitTillPresent('table > tbody > tr');
            // $this->downloadInvoice();

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


    function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->exts->waitTillPresent($this->username_selector, 5);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);

                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->click_by_xdotool($this->submit_login_selector);
                }

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
            $this->exts->waitTillPresent($this->check_login_success_selector, 20);
            if ($this->exts->exists($this->check_login_success_selector)) {

                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

                $isLoggedIn = true;
            }
        } catch (Exception $exception) {

            $this->exts->log("Exception checking loggedin " . $exception);
        }

        return $isLoggedIn;
    }
    // comment due to there is not pdf format invoice found in this code.
    // private function dateRange()
    // {
    //     $selectDate = new DateTime();

    //     $currentYear = $selectDate->format('Y');
    //     $currentMonth = $selectDate->format('m');

    //     if ($this->restrictPages == 0) {
    //         // select date
    //         $selectDate->modify('-3 years');

    //         $day = $selectDate->format('d');
    //         $month = $selectDate->format('m');
    //         $year = $selectDate->format('Y');

    //         $this->exts->log('3 years previous date:: ' . $day . '-' . $month . '-' . $year);


    //         $this->exts->capture('date-range-3-years');
    //     } else {
    //         // select date
    //         $selectDate->modify('-3 months');

    //         $day = $selectDate->format('d');
    //         $month = $selectDate->format('m');
    //         $year = $selectDate->format('Y');

    //         $this->exts->log('3 months previous date:: ' . $day . '-' . $month . '-' . $year);

    //         $this->exts->capture('date-range-3-months');
    //     }
    //     for ($i = 1; $year <= $currentYear;) {
    //         $this->exts->log('year:: ' . $year);

    //         $this->processInvoice($year);
    //         $year++;
    //     }
    // }


    /**
     * download invoice of current year
     * 
     */
    // private function processInvoice($year)
    // {
    //     for ($i = 1; $i < 13; $i++) {
    //         $this->exts->log("Download Invoice year: " . $year . "month: " . $i);
    //         $openUrl = 'https://login.payjoe.de/activities?year=' . $year . '&month=' . $i;
    //         $this->exts->openUrl($openUrl);
    //         sleep(5);
    //         $this->exts->waitTillPresent('table > tbody > tr');
    //         $this->exts->capture("4-invoices-page-month " . $i);

    //         $this->downloadInvoice();
    //     }
    // }

    // private function downloadInvoice()
    // {
    //     $invoices = [];

    //     $rows = $this->exts->getElements('table > tbody > tr');
    //     $this->exts->log('No of rows: ' . count($rows));
    //     foreach ($rows as $row) {
    //         $tags = $this->exts->getElements('td', $row);

    //         $this->exts->log('No of columns: ' . count($tags));

    //         if ($this->exts->getElement('a[href*="/download"]', $tags[7]) != null) {
    //             $invoiceUrl = $this->exts->getElement('a[href*="/download"]', $tags[7])->getAttribute("href");
    //             $parts = explode('/', parse_url($invoiceUrl, PHP_URL_PATH));
    //             $invoiceName = $parts[4];
    //             $invoiceDate = trim($tags[1]->getAttribute('innerText'));
    //             $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[6]->getAttribute('innerText'))) . ' EUR';

    //             array_push($invoices, array(
    //                 'invoiceName' => $invoiceName,
    //                 'invoiceDate' => $invoiceDate,
    //                 'invoiceAmount' => $invoiceAmount,
    //                 'invoiceUrl' => $invoiceUrl
    //             ));
    //             $this->isNoInvoice = false;
    //         }
    //     }
    //     // Download all invoices
    //     $this->exts->log('Invoices found: ' . count($invoices));
    //     foreach ($invoices as $invoice) {
    //         $this->exts->log('--------------------------');
    //         $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
    //         $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
    //         $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
    //         $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

    //         $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
    //         $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'M d, Y', 'Y-m-d');
    //         $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

    //         $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
    //         sleep(4);
    //         if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
    //             $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
    //             sleep(1);
    //         } else {
    //             $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
    //         }
    //     }
    // }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
