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

    // Server-Portal-ID: 10759 - Last modified: 19.03.2025 12:57:47 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = "https://my.avid.com/account/orientation?websource=avid";
    public $loginUrl = "https://my.avid.com/account/orientation?websource=avid";
    public $homePageUrl = "https://my.avid.com/products/orderhistory";
    public $username_selector = 'input[name="Email"]';
    public $password_selector = 'input[name="Password"]';
    public $submit_button_selector = 'button#login';
    public $login_tryout = 0;
    public $restrictPages = 3;
    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        $this->exts->openUrl($this->baseUrl);
        sleep(2);
        $this->exts->capture("Home-page-without-cookie");

        $isCookieLoginSuccess = false;
        if ($this->exts->loadCookiesFromFile()) {
            $this->exts->openUrl($this->homePageUrl);
            sleep(15);

            if ($this->checkLogin()) {
                $isCookieLoginSuccess = true;
            } else {
                //$this->exts->clearCookies();
                $this->exts->openUrl($this->loginUrl);
            }
        }

        if ($this->exts->exists('#onetrust-group-container')) {
            $this->exts->moveToElementAndClick('#onetrust-accept-btn-container > button#onetrust-accept-btn-handler');
            sleep(5);
        }


        if (!$isCookieLoginSuccess) {
            $this->exts->capture("after-login-clicked");

            $this->fillForm(0);
            sleep(10);

            if ($this->checkLogin()) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $this->exts->capture("LoginSuccess");
                if ($this->exts->getElement('#onetrust-group-container') != null) {
                    $this->exts->moveToElementAndClick('#onetrust-accept-btn-container > button#onetrust-accept-btn-handler');
                }
                $this->invoicePage();
            } else {
                $err_msg = $this->exts->extract('span.notification-message');
                if ($err_msg != "" && $err_msg != null && $this->exts->exists($this->password_selector)) {
                    $this->exts->log($err_msg);
                    $this->exts->loginFailure(1);
                } else {
                    $this->exts->capture("LoginFailed");
                    $this->exts->loginFailure();
                }
            }
        } else {
            sleep(10);
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->invoicePage();
        }
    }

    public function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {
            sleep(5);
            if ($this->exts->exists($this->username_selector) || $this->exts->exists($this->password_selector)) {
                sleep(2);
                $this->login_tryout = (int)$this->login_tryout + 1;
                $this->exts->capture("1-pre-login");

                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);

                $this->exts->moveToElementAndClick($this->submit_button_selector);

                sleep(8);
            }

            sleep(10);
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
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
            if ($this->exts->exists('a[href*="/logout"]') && !$this->exts->exists($this->password_selector)) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }

    public function invoicePage()
    {
        $this->exts->log("Invoice page");

        $this->exts->moveToElementAndClick('div#avid-billing a[href*="/orderhistory"]');
        sleep(15);

        $years = $this->exts->getElements('select#Years option');
        $limit = count($years);
        if ($this->restrictPages != 0) {
            $limit = 2;
        }

        $years_array = array();

        foreach ($years as $key => $y) {
            if ($key < $limit) {
                $y_val = $y->getAttribute('value');
                array_push($years_array, $y_val);
            }
        }

        foreach ($years_array as $year) {
            $this->changeSelectbox('select#Years', $year);
            sleep(15);

            $this->downloadInvoices();
        }

        if ($this->isNoInvoice) {
            $this->exts->log("No invoice !!! ");
            $this->exts->no_invoice();
        }
        $this->exts->success();
    }

    private function changeSelectbox($select_box = '', $option_value = '')
    {
        $this->exts->waitTillPresent($select_box, 10);
        if ($this->exts->exists($select_box)) {
            $option = $option_value;
            $this->exts->click_by_xdotool($select_box);
            sleep(2);
            $optionIndex = $this->exts->executeSafeScript('
        const selectBox = document.querySelector("' . $select_box . '");
        const targetValue = "' . $option_value . '";
        const optionIndex = [...selectBox.options].findIndex(option => option.value === targetValue);
        return optionIndex;
    ');
            $this->exts->log($optionIndex);
            sleep(1);
            for ($i = 0; $i < $optionIndex; $i++) {
                $this->exts->log('>>>>>>>>>>>>>>>>>> Down');
                // Simulate pressing the down arrow key
                $this->exts->type_key_by_xdotool('Down');
                sleep(1);
            }
            $this->exts->type_key_by_xdotool('Return');
        } else {
            $this->exts->log('Select box does not exist');
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
     *method to download incoice
     */

    private function downloadInvoices($count = 1)
    {
        $this->exts->log(__FUNCTION__);

        $this->exts->waitTillPresent('table.table-view tbody tr');
        $this->exts->capture("4-invoices-classic");

        $invoices = [];
        $rows = $this->exts->getElements('table.table-view tbody tr');
        foreach ($rows as $key => $row) {
            $invoiceLink = $this->exts->getElement('td a', $row);
            if ($invoiceLink != null) {
                // In case invoice name not found 
                $invoiceName = time();
                sleep(1);
                $invoiceUrl = $invoiceLink->getAttribute("href");
                preg_match('/orderId=([^&]+)/', $invoiceUrl, $matches);

                if (isset($matches[1])) {
                    $invoiceName = $matches[1];
                }

                $invoiceDate = $this->exts->extract('td a', $row);
                $invoiceAmount = $this->exts->extract('td:nth-child(6)', $row);

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl,
                ));
                $this->isNoInvoice = false;
            }
        }

        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $key => $invoice) {

            if ($this->exts->invoice_exists($invoice['invoiceName'])) {
                $this->exts->log('Invoice existed ' . $invoice['invoiceName']);
            } else {
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
                $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
                $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
                $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

                $invoiceFileName = !empty($invoice['invoiceName']) ?  $invoice['invoiceName'] . '.pdf' : '';
                $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);


                $newTab = $this->exts->openNewTab($invoice['invoiceUrl']);

                $this->waitFor('a[class="downloadAndPrintIcon"]');

                $downloaded_file = $this->exts->click_and_download('a[class="downloadAndPrintIcon"]', 'pdf', $invoiceFileName);
                sleep(5);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
                $this->exts->closeTab($newTab);
                sleep(2);
            }
        }
        sleep(10);
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
