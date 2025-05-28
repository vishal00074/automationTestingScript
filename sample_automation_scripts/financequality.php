<?php // I have migrated the selector and updated login failed Confirmed code

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

    // Server-Portal-ID: 58324 - Last modified: 27.05.2024 11:57:51 UTC - User: 15

    /*Define constants used in script*/
    public $baseUrl = "https://network.financequality.net/login";
    public $loginUrl = "https://network.financequality.net/login";
    public $homePageUrl = "https://network.financequality.net/login";
    public $username_selector = "input#login";
    public $password_selector = "input[name=\"pass\"], input#pass";
    public $submit_button_selector = "form[action=\"/login\"] button, .login-btn";
    public $check_login_failed_selector = ".wrong-password";
    public $login_tryout = 0;


    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->exts->openUrl($this->baseUrl);
        sleep(2);
        $this->exts->capture("Home-page-without-cookie");

        $isCookieLoginSuccess = false;
        if ($this->exts->loadCookiesFromFile()) {
            $this->exts->openUrl($this->homePageUrl);
            sleep(15);
            if ($this->checkLogin()) {
                $isCookieLoginSuccess = true;
            }
        }

        if (!$isCookieLoginSuccess) {
            $this->exts->capture("after-login-clicked");
            sleep(5);
            $this->fillForm(0);
            sleep(10);
            if ($this->checkLogin()) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $this->exts->capture("LoginSuccess");
                sleep(1);
                if ($this->exts->exists('#cmsModal .close')) {
                    $this->exts->moveToElementAndClick('#cmsModal .close');
                    sleep(2);
                }
                $this->invoicePage();

                $this->exts->success();
            } else {

                $error_text = strtolower($this->exts->extract($this->check_login_failed_selector));

                $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);

                if (stripos($error_text, strtolower('Usernamen oder ein falsches Passwort eingegeben')) !== false) {
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

            $this->exts->success();
        }
    }

    public function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {
            sleep(5);
            if ($this->exts->getElement($this->username_selector) != null) {
                sleep(2);
                $this->login_tryout = (int)$this->login_tryout + 1;
                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(2);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(5);

                $this->exts->moveToElementAndClick($this->submit_button_selector);

                sleep(10);
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
            if ($this->exts->getElement("a[href*='logout.do'], li.logout a[href*=\"/logout\"]") != null) {
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
        $this->exts->log("invoice Page");

        if ($this->exts->getElement("li.user-pro, a.account") != null) {
            $this->exts->getElement("li.user-pro, a.account")->click();
            sleep(15);
        }


        if ($this->exts->getElement("ul.nav.nav-second-level.collapse.in a[href*='user-billings.do'], a[href*=\"/account/credits/\"]") != null) {
            $this->exts->getElement("ul.nav.nav-second-level.collapse.in a[href*='user-billings.do'], a[href*=\"/account/credits/\"]")->click();
            sleep(15);
        }
        if ($this->exts->exists('#cmsModal .close')) {
            $this->exts->moveToElementAndClick('#cmsModal .close');
            sleep(2);
        }
        // $this->exts->openUrl("https://cloudinary.com/console/lui/settings/billing");
        // sleep(15);

        //$this->downloadInvoice();

        $this->downloadInvoiceNew();
    }

    /**
     *method to download incoice
     */
    public $j = 0;
    public $k = 0;
    //https://ebilling.dhl.com/customer/document/258402713/page/pdf/
    public function downloadInvoice()
    {
        $this->exts->log("Begin downlaod invoice 1");

        sleep(15);

        try {
            if ($this->exts->getElement("div#account_credits table tbody tr, div#content table tbody tr") != null) {
                $invoices = array();
                $receipts = $this->exts->getElements("div#account_credits table tbody, div#content table tbody tr");
                $this->exts->log(count($receipts));
                $count = 0;
                foreach ($receipts as $receipt) {
                    $this->exts->log("each record");
                    $this->exts->log($this->j);
                    $len_td = count($receipt->getElements("td"));
                    if ($this->j < count($receipts) && $len_td > 5) {
                        $receiptDate = $receipts[$this->j]->getElements("td:nth-child(1)")[0]->getText();
                        $this->exts->log($receiptDate);
                        $receiptUrl = $receipts[$this->j]->getElements("td:nth-child(5) a[href*='user-billings.do?document=']")[0]->getAttribute("href");
                        $receiptName = trim($receipts[$this->j]->getElements("td:nth-child(1)")[0]->getText());
                        $receiptName = html_entity_decode($receiptName);
                        $receiptName = preg_replace("/\s/", '', $receiptName);
                        $receiptName = str_ireplace(".pdf", "", $receiptName);
                        $receiptFileName = !empty($receiptName) ? $receiptName . '.pdf' : '';
                        $this->exts->log($receiptName);
                        $this->exts->log($receiptFileName);
                        $this->exts->log($receiptUrl);
                        $parsed_date = $this->exts->parse_date($receiptDate, 'd.m.Y', 'Y-m-d');
                        $this->exts->log($parsed_date);
                        $receiptAmount = $receipts[$this->j]->getElements("td:nth-child(4)")[0]->getText();
                        $receiptAmount = preg_replace('/[^\d\,\.]+/i', "", $receiptAmount) . ' EUR';
                        $this->exts->log($receiptAmount);
                        $invoice = array(
                            'receiptName' => $receiptName,
                            'parsed_date' => $parsed_date,
                            'receiptAmount' => $receiptAmount,
                            'receiptFileName' => $receiptFileName,
                            'receiptUrl' => $receiptUrl,
                        );

                        array_push($invoices, $invoice);
                        $this->j += 1;
                    }
                }

                $this->exts->log($this->j);
                foreach ($invoices as $invoice) {
                    $downloaded_file = $this->exts->direct_download($invoice['receiptUrl'], 'pdf', $invoice['receiptFileName']);
                    // $downloaded_file = $this->exts->download_current($receiptFileName);
                    $this->exts->log("downloaded file");
                    sleep(5);
                    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                        $this->exts->log("create file");
                        $this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $downloaded_file);
                        sleep(5);
                    }
                }
            } else {
                $this->exts->log("No invoice !!! ");
                $this->exts->no_invoice();
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception downlaoding invoice " . $exception->getMessage());
        }
    }




    function downloadInvoiceNew()
    {

        $this->exts->log("Begin downlaod invoice ");
        $this->exts->capture('4-List-invoice');
        sleep(15);
        $this->changeSelectbox('.row.ui-space:first-child select#offsetselector', 500);
        sleep(15);
        try {
            if ($this->exts->getElement('table tbody tr, div#account_credits table tbody tr') != null) {
                $receipts = $this->exts->getElements('table tbody tr, div#account_credits table tbody tr');
                $invoices = array();
                foreach ($receipts as $i => $receipt) {
                    $tags = $this->exts->getElements('td', $receipt);
                    if (count($tags) >= 3 && $this->exts->getElement('td:nth-child(7) a', $receipt) != null) {
                        $receiptUrl = $this->exts->extract('td:nth-child(7) a[href*="user-billings.do?document="]', $receipt, 'href');
                        $receiptDate = trim($tags[1]->getAttribute('innerText'));
                        $parsed_date = $this->exts->parse_date($receiptDate, 'd.m.Y', 'Y-m-d');
                        if ($parsed_date == '') {
                            $parsed_date = $this->exts->parse_date($receiptDate, 'd/m/Y', 'Y-m-d');
                        }

                        $receiptName = trim($tags[2]->getAttribute('innerText'));
                        $receiptName = html_entity_decode($receiptName);
                        $receiptName = preg_replace("/\s/", '', $receiptName);
                        $receiptName = str_ireplace(".pdf", "", $receiptName);
                        $receiptAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[5]->getAttribute('innerText'))) . ' EUR';
                        $receiptFileName = !empty($receiptName) ? $receiptName . '.pdf' : '';

                        // $receiptSelector = 'div.box > table.table--simple > tbody > tr:nth-child(' . ($i+1) . ') td a';
                        if (!$this->exts->invoice_exists($receiptName)) {
                            $this->exts->log("Invoice Date: " . $receiptDate);
                            $this->exts->log("parsed_date: " . $parsed_date);
                            $this->exts->log("Invoice Name: " . $receiptName);
                            $this->exts->log("Invoice Amount: " . $receiptAmount);
                            $this->exts->log("Invoice FileName: " . $receiptFileName);
                            $this->exts->log("Invoice Url: " . $receiptUrl);

                            $invoice = array(
                                'receiptDate' => $parsed_date,
                                'receiptName' => $receiptName,
                                'receiptAmount' => $receiptAmount,
                                'receiptUrl' => $receiptUrl,
                                // 'receiptSelector' => $receiptSelector,
                                'receiptFileName' => $receiptFileName
                            );
                            array_push($invoices, $invoice);
                        }
                    }
                }

                $this->exts->log("Invoice found: " . count($invoices));

                $this->totalFiles = count($invoices);
                foreach ($invoices as $invoice) {
                    $downloaded_file = $this->exts->direct_download($invoice['receiptUrl'], 'pdf', $invoice['receiptFileName']);
                    $this->exts->log("Download file: " . $downloaded_file);

                    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoice['receiptName'], $invoice['receiptDate'], $invoice['receiptAmount'], $downloaded_file);
                        sleep(1);
                    }
                }
            } else {
                $this->exts->log("No invoice !!! ");
                $this->exts->no_invoice();
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception downloading invoice " . $exception->getMessage());
        }
    }


    private function changeSelectbox($selector,  $value)
    {
        $this->exts->execute_javascript('
            let selectBox = document.querySelector(' . addslashes($selector) . ');
            selectBox.value = "' . addslashes($value) . '";
            selectBox.dispatchEvent(new Event("change"));
        ');
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
