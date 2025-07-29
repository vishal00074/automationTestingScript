<?php // updated download code handle empty incvoice name added restrictiction to download only 50 invoices if $this->restrictPages != 0
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

    // Server-Portal-ID: 45749 - Last modified: 01.05.2025 14:08:59 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = "https://business.dpd.de/";
    public $loginUrl = "https://business.dpd.de/";
    public $invoicePageUrl = "https://business.dpd.de/profil/meinkonto/rechnung-archiv.aspx";
    public $username_selector = 'div.login_banner input#txtMasterLogin, div.div_login_formular input#txtUserLogin';
    public $password_selector = 'div.login_banner input#txtMasterPasswort, div.div_login_formular input#txtUserPassword';
    public $remember_selector = '';
    public $submit_button_selector = 'div.login_banner div > a.home_loginbutton#CPLContentSmall_btnMasterLogin, a#CPLContentLarge_lnkLogin';
    public $check_login_failed_selector = 'div.check_login_failed_selector';
    public $login_tryout = 0;
    public $restrictPages = 3;


    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;

        $this->exts->openUrl($this->baseUrl);
        sleep(2);
        $this->exts->capture("Home-page-without-cookie");

        $isCookieLoginSuccess = false;
        if ($this->exts->loadCookiesFromFile()) {
            $this->exts->openUrl($this->baseUrl);
            sleep(15);

            if ($this->checkLogin()) {
                $isCookieLoginSuccess = true;
            } else {
                $this->exts->clearCookies();
                $this->exts->openUrl($this->loginUrl);
            }
        } else {
            $this->exts->openUrl($this->loginUrl);
        }

        if (!$isCookieLoginSuccess) {
            if ($this->exts->exists('div#tc-privacy-wrapper #popin_tc_privacy_button')) {
                $this->exts->moveToElementAndClick('div#tc-privacy-wrapper #popin_tc_privacy_button');
                sleep(2);
            }
            sleep(10);
            $this->fillForm(0);
            sleep(2);

            if ($this->exts->exists('a#cphBody_btnLayerSME_OK')) {
                $this->exts->moveToElementAndClick('a#cphBody_btnLayerSME_OK');
                sleep(1);
            }

            if ($this->checkLogin()) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $this->exts->capture("LoginSuccess");

                if ($this->exts->exists('a#cphBody_btnLayerSME_OK')) {
                    $this->exts->moveToElementAndClick('a#cphBody_btnLayerSME_OK');
                    sleep(1);
                }

                $this->invoicePage();
            } else {
                if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') !== false) {
                    $this->exts->log("Wrong credential !!!!");
                    $this->exts->loginFailure(1);
                } else {
                    $this->exts->loginFailure();
                }
            }
        } else {
            sleep(10);
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            if ($this->exts->exists('a#cphBody_btnLayerSME_OK')) {
                $this->exts->moveToElementAndClick('a#cphBody_btnLayerSME_OK');
                sleep(1);
            }

            $this->invoicePage();
        }
    }

    private function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {
            sleep(1);
            if ($this->exts->exists($this->username_selector) && $this->exts->exists($this->password_selector)) {
                sleep(1);
                $this->login_tryout = (int) $this->login_tryout + 1;
                $this->exts->capture("1-pre-login");

                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);

                if ($this->remember_selector != '')
                    $this->exts->moveToElementAndClick($this->remember_selector);

                $this->exts->moveToElementAndClick($this->submit_button_selector);
                sleep(10);

                $err_msg = trim($this->exts->extract('span#CPLContentLarge_labLogin_Error'));
                if (stripos($err_msg, 'passwor') !== false) {
                    $this->exts->log($err_msg);
                    $this->exts->loginFailure(1);
                }
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
    private function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            if ($this->exts->exists('a.btnLogout')) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }

    private function invoicePage()
    {
        $this->exts->log("Invoice page");
        if ($this->exts->exists('#popin_tc_privacy_button')) {
            $this->exts->moveToElementAndClick('#popin_tc_privacy_button');
            sleep(2);
        }

        $this->exts->moveToElementAndClick('ul.ulMainNav li:nth-child(2) a');
        sleep(5);

        $this->exts->openUrl('https://business.dpd.de/profil/mein-konto.aspx');
        sleep(10);

        $this->exts->openUrl($this->invoicePageUrl);
        sleep(15);

        $this->downloadInvoice();

        if ($this->totalFiles == 0) {
            $this->exts->log("No invoice !!! ");
            $this->exts->no_invoice();
        }
        $this->exts->success();
    }

    /**
     *method to download incoice
     */
    public $totalFiles = 0;
    private function downloadInvoice($count = 1, $pageCount = 1)
    {
        $this->exts->log("Begin download invoice");

        $this->exts->capture('4-List-invoice');

        try {
            if ($this->exts->querySelector('table.dpdTable > tbody > tr, table.tbl_details_table > tbody > tr') != null) {
                $receipts = $this->exts->querySelectorAll('table.dpdTable > tbody > tr, table.tbl_details_table > tbody > tr');
                $invoices = array();
                foreach ($receipts as $i => $receipt) {
                    $tags = $this->exts->querySelectorAll('td', $receipt);
                    if (count($tags) == 3 && $this->exts->querySelector('td a[href*="btnDownload"]', $receipt) != null) {
                        $receiptDate = trim($tags[0]->getText());
                        $receiptName = trim($tags[1]->getText());
                        $receiptAmount = '';
                        $receiptFileName = !empty($receiptName) ? $receiptName . '.pdf' : '';
                        $receiptUrl = $this->exts->querySelector('td a[href*="btnDownload"]', $receipt)->getAttribute('href');

                        // $receiptUrl = $this->exts->querySelector('td a[href*="btnDownload"]', $receipt);
                        // $this->exts->webdriver->executeScript(
                        // 	'arguments[0].setAttribute("myId", "invoice" + arguments[1]);',
                        // 	array($receiptUrl, $i)
                        // );

                        // $receiptUrl = 'table.dpdTable > tbody > tr > td a[myId="invoice'.$i.'"]';

                        $this->exts->log("_____________________" . ($i + 1) . "___________________________________________");
                        $this->exts->log("Invoice Date: " . $receiptDate);
                        $this->exts->log("Invoice Name: " . $receiptName);
                        $this->exts->log("Invoice Amount: " . $receiptAmount);
                        $this->exts->log("Invoice Url: " . $receiptUrl);
                        $this->exts->log("Invoice FileName: " . $receiptFileName);
                        $this->exts->log("________________________________________________________________");

                        $invoice = array(
                            'receiptDate' => $receiptDate,
                            'receiptName' => $receiptName,
                            'receiptAmount' => $receiptAmount,
                            'receiptUrl' => $receiptUrl,
                            'receiptFileName' => $receiptFileName
                        );
                        array_push($invoices, $invoice);
                    }
                }

                $this->exts->log(">>>>>>>>>>>>>>>>>>>Invoice found: " . count($invoices));


                $count = 1;
                foreach ($invoices as $invoice) {

                    if ($this->restrictPages != 0 && $this->totalFiles >= 50) {
                        return;
                    }

                    $invoiceDate = $this->exts->parse_date($invoice['receiptDate']);
                    if ($invoiceDate == '') {
                        $invoiceDate = $invoice['receiptDate'];
                    }
                    $this->exts->log("Parse date: " . $invoiceDate);

                    $downloaded_file = $this->exts->direct_download($invoice['receiptUrl'], 'pdf', $invoice['receiptFileName']);
                    $this->exts->log("Download file: " . $downloaded_file);

                    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoice['receiptName'], $invoiceDate, $invoice['receiptAmount'], $downloaded_file);
                        sleep(1);
                        $count++;
                        sleep(5);
                        $this->totalFiles++;
                    }
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception downloading invoice " . $exception->getMessage());
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
