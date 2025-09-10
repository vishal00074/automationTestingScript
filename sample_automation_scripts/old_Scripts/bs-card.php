<?php // migrated added code to accept cookies updated download code extract the 
// invoiceName, invoiceUrl, invoiceDate by using extract function

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

    // Server-Portal-ID: 22489 - Last modified: 02.08.2023 05:28:40 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = "https://portal.bs-card-service.com/login";
    public $loginUrl = "https://portal.bs-card-service.com/login";
    public $homePageUrl = "https://www.bs-service-portal.com/customer/default.aspx?Kanal=customer/Rech_Netz";
    public $contract_number_selector = "form#aspnetForm input[name=\"ctl00\$CC\$ctl00\$I\$ctl00\$ctl00\$T1\"]";
    public $username_selector = "form#aspnetForm input[name=\"ctl00\$CC\$ctl00\$I\$ctl00\$ctl01\$T1\"]";
    public $username_selector_1 = "form[action*=\"/login?\"] input[name=\"User\"]";
    public $password_selector = "form#aspnetForm input[name=\"ctl00\$CC\$ctl00\$I\$ctl00\$ctl02\$T1\"]";
    public $password_selector_1 = "form[action*=\"/login?\"] input[name=\"Password\"]";
    public $submit_button_selector = "form#aspnetForm input[type=\"submit\"]";
    public $submit_button_selector_1 = "form[action*=\"/login?\"] button[type=\"submit\"]";
    public $login_tryout = 0;
    public $contract_number = "";

    public $isNoInvoice = true;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->contract_number = isset($this->exts->config_array["contract_number"]) ? (int)@$this->exts->config_array["contract_number"] : "";

        $this->exts->log($this->contract_number);

        $this->exts->openUrl($this->baseUrl);
        sleep(7);
        $this->exts->capture("Home-page-without-cookie");

        $this->exts->execute_javascript('
                var shadow = document.querySelector("#usercentrics-root");
                if(shadow){
                    shadow.shadowRoot.querySelector(\'button[data-testid="uc-accept-all-button"]\').click();
                }
            ');

        $isCookieLoginSuccess = false;
        if ($this->exts->loadCookiesFromFile()) {
            $this->exts->openUrl($this->homePageUrl);
            sleep(10);

            $this->exts->execute_javascript('
                var shadow = document.querySelector("#usercentrics-root");
                if(shadow){
                    shadow.shadowRoot.querySelector(\'button[data-testid="uc-accept-all-button"]\').click();
                }
            ');
            if ($this->checkLogin()) {
                $isCookieLoginSuccess = true;
            } else {
                $this->exts->clearCookies();
                $this->exts->openUrl($this->loginUrl);
                sleep(7);
                $this->exts->execute_javascript('
                var shadow = document.querySelector("#usercentrics-root");
                if(shadow){
                    shadow.shadowRoot.querySelector(\'button[data-testid="uc-accept-all-button"]\').click();
                }
            ');
            }
        }

        if (!$isCookieLoginSuccess) {
            $this->exts->capture("after-login-clicked");

            $this->fillForm(0);
            sleep(10);

            $err_msg = "";
            if ($this->exts->getElement("div.wrong-login-data span.field-validation-error") != null) {
                $err_msg = trim($this->exts->getElements("div.wrong-login-data span.field-validation-error")[0]->getText());
            }

            if ($err_msg != "" && $err_msg != null) {
                $this->exts->log($err_msg);
                $this->exts->loginFailure(1);
            }

            if ($this->checkLogin()) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $this->exts->capture("LoginSuccess");
                $this->invoicePage();
            } else {
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure();
            }
        } else {
            sleep(10);
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->invoicePage();
        }
    }

    function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {
            sleep(5);
            if ($this->exts->getElement($this->username_selector) != null) {
                sleep(2);
                $this->login_tryout = (int)$this->login_tryout + 1;
                $this->exts->capture("1-pre-login");

                $this->exts->log("Contract Number");
                $this->exts->moveToElementAndType($this->contract_number_selector, $this->contract_number);
                sleep(2);

                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(2);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(5);

                $this->exts->moveToElementAndClick($this->submit_button_selector);

                sleep(10);
            } else if ($this->exts->getElement($this->username_selector_1) != null) {
                sleep(2);
                $this->login_tryout = (int)$this->login_tryout + 1;
                $this->exts->capture("1-pre-login");

                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector_1, $this->username);
                sleep(2);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector_1, $this->password);
                sleep(5);

                $this->exts->capture("2-login-page-filled");
                $this->exts->moveToElementAndClick($this->submit_button_selector_1);

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
    function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            if ($this->exts->getElement("a.mmpMenuLogout, div.user-menu a[href*=\"/Logout/\"]") != null) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }

    function invoicePage()
    {
        $this->exts->log("invoice Page");

        $this->exts->openUrl($this->homePageUrl);
        sleep(15);

        $this->downloadInvoice();
        // Final, check no invoice
        if ($this->isNoInvoice) {
            $this->exts->no_invoice();
        }
        $this->exts->success();
    }

    /**
     *method to download incoice
     */
    public $j = 0;
    public $k = 0;
    function downloadInvoice()
    {
        $this->exts->log("Begin downlaod invoice 1");
        $this->j = 0;
        sleep(15);

        try {
            if ($this->exts->getElement("tr > td[style*=\"background-image\"][title*=\".pdf\"]") != null) {
                $invoices = array();
                $receipts = $this->exts->getElements("table > tbody > tr");
                $this->exts->log(count($receipts));
                $count = 0;
                foreach ($receipts as $receipt) {
                    $this->exts->log("each record");
                    $this->exts->log($this->j);
                    if ($this->j < count($receipts) && $this->exts->getElement("table > tbody > tr:nth-child(" . ($this->j + 1) . ") td[style*=\"background-image\"][title*=\".pdf\"]") != null) {
                        $receiptDate = $receipts[$this->j]->getElements("td:nth-child(3)")[0]->getText();
                        $this->exts->log($receiptDate);
                        $receiptUrl = "table > tbody > tr:nth-child(" . ($this->j + 1) . ") td[style*=\"background-image\"][title*=\".pdf\"]";
                        $receiptName = $receipts[$this->j]->getElements("td:nth-child(1)")[0]->getText();
                        $receiptFileName = !empty($receiptName) ? $receiptName . '.pdf' : '';
                        $this->exts->log($receiptName);
                        $this->exts->log($receiptFileName);
                        $this->exts->log($receiptUrl);
                        $parsed_date = $this->exts->parse_date($receiptDate, 'd.m.Y', 'Y-m-d');
                        $this->exts->log($parsed_date);
                        $receiptAmount = $receipts[$this->j]->getElements("td:nth-child(5)")[0]->getText();
                        $receiptAmount = preg_replace('/[^\d\.,]/m', '', $receiptAmount) . 'EUR';
                        $this->exts->log($receiptAmount);
                        $invoice = array(
                            'receiptName' => $receiptName,
                            'parsed_date' => $parsed_date,
                            'receiptAmount' => $receiptAmount,
                            'receiptFileName' => $receiptFileName,
                            'receiptUrl' => $receiptUrl,
                        );

                        array_push($invoices, $invoice);
                        $this->isNoInvoice = false;
                    }

                    $this->j += 1;
                }

                $this->exts->log($this->j);
                foreach ($invoices as $invoice) {
                    $downloaded_file = $this->exts->click_and_download($invoice['receiptUrl'], 'pdf', $invoice['receiptFileName']);
                    $this->exts->log("downloaded file");
                    sleep(5);
                    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                        $this->exts->log("create file");
                        $this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $downloaded_file);
                        sleep(5);
                    }
                }
            } else {

                $this->exts->openUrl("https://portal.bs-card-service.com/cms/bssp/postfach/rechnungen/netzbetrieb");
                sleep(15);

                $this->slectAccount();
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception downlaoding invoice " . $exception->getMessage());
        }
    }

    public $totalFiles = 0;
    function slectAccount()
    {
        sleep(20);
        $this->exts->capture('before_select_account');
        $this->exts->openUrl("https://portal.bs-card-service.com/cms/bssp/postfach/rechnungen/netzbetrieb");
        sleep(15);
        $accounts = $this->exts->getElements('ul#vus_listbox li');
        foreach ($accounts as $account) {
            $account_id = $this->exts->getElement('div.vu-dropdown-item', $account)->getAttribute("innerText");
            if ($this->exts->getElement('div.vu-dropdown-item')->getAttribute("innerText") == $account_id && $account_id != '') {
                $this->exts->moveToElementAndClick('div.vu-dropdown-item');
                sleep(15);
                $selected_tag = $this->exts->getElement("span#vu-selector-arrow-down span.selected-value");
                if ($selected_tag != null) {
                    $accId = $this->exts->getElements("span#vu-selector-arrow-down span.selected-value")[0]->getText();
                    $this->exts->log("Account id: " . $accId);
                    $this->downloadInvoice1(trim($accId));
                }
            } else {
                $this->exts->log("Account id is not captured");
            }
        }
    }

    function downloadInvoice1($accId)
    {
        $this->exts->log("Begin downlaod invoice 1");

        sleep(15);

        try {
            if ($this->exts->getElement("div#bs-doc-grid-container table tbody tr") != null) {
                $invoices = array();
                $receipts = $this->exts->getElements("div#bs-doc-grid-container table tbody tr");
                $this->exts->log(count($receipts));
                $count = 0;
                foreach ($receipts as $receipt) {
                    $this->exts->log("each record");
                    $this->exts->log($this->j);
                    if ($this->j < count($receipts)) {
                        $receiptDate = $this->exts->extract('td:nth-child(4)', $receipt);
                        $this->exts->log($receiptDate);
                        $invoiceLink = $this->exts->getElement('td:nth-child(1) a', $receipt);

                        if ($invoiceLink != null) {
                            $receiptUrl = $invoiceLink->getAttribute("href");
                            $receiptName = $accId . "_" . $this->exts->extract('td:nth-child(1)', $receipt);
                            $receiptName = preg_replace("/[^\w]/", '', $receiptName);
                            $receiptFileName = !empty($receiptName) ? $receiptName . '.pdf' : '';
                            $this->exts->log($receiptName);
                            $this->exts->log($receiptFileName);
                            $this->exts->log($receiptUrl);
                            $parsed_date = $this->exts->parse_date($receiptDate, 'd.m.Y', 'Y-m-d');
                            $this->exts->log($parsed_date);
                            $receiptAmount = $this->exts->extract('td:nth-child(3)', $receipt);
                            $receiptAmount = preg_replace('/[^\d\.,]/m', '', $receiptAmount) . 'EUR';
                            $this->exts->log($receiptAmount);
                            $invoice = array(
                                'receiptName' => $receiptName,
                                'parsed_date' => $parsed_date,
                                'receiptAmount' => $receiptAmount,
                                'receiptFileName' => $receiptFileName,
                                'receiptUrl' => $receiptUrl,
                            );

                            array_push($invoices, $invoice);
                            $this->isNoInvoice = false;
                            $this->j += 1;
                        }
                    }
                }

                $this->exts->log($this->j);
                foreach ($invoices as $invoice) {
                    $downloaded_file = $this->exts->direct_download($invoice['receiptUrl'], 'pdf', $invoice['receiptFileName']);
                    // $downloaded_file = $this->exts->download_current($receiptFileName);
                    $this->exts->log("downloaded file");
                    sleep(5);
                    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                        $this->totalFiles += 1;
                        $this->exts->log("create file");
                        $this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $invoice['receiptFileName']);
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
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$portal = new PortalScriptCDP("optimized-chrome-v2", 'B+S Card Service Portal', '2673364', 'aW5mb0BpbGxpY2l0LmRl', 'aG03UkRDeVRjUzJr');
$portal->run();
