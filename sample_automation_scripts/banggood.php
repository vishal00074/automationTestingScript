<?php

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

    // Server-Portal-ID: 37092 - Last modified: 19.10.2021 11:31:43 UTC - User: 15

    public $baseUrl = "https://www.banggood.com/login.html";
    public $loginUrl = "https://www.banggood.com/login.html";
    public $homePageUrl = "https://www.banggood.com/index.php?com=account&t=ordersList";
    public $username_selector = "input#login-email";
    public $password_selector = "input#login-pwd";
    public $submit_button_selector = "input#login-submit";
    public $login_tryout = 0;
    public $restrictPages = 3;

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
                $this->exts->clearCookies();
                $this->exts->openUrl($this->loginUrl);
                sleep(5);
            }
        }

        if (!$isCookieLoginSuccess) {
            $this->exts->capture("after-login-clicked");

            $this->fillForm(0);
            sleep(10);
            $error_text = strtolower($this->exts->extract('p.login-email-tip'));
            $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);
            if (stripos($error_text, strtolower('No match for E-Mail Address and/or Password')) !== false) {
                $this->exts->loginFailure(1);
            }

            if ($this->checkLogin()) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $this->exts->capture("LoginSuccess");
                $this->invoicePage();

                $this->exts->success();
            } else {
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure();
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
            if ($this->exts->getElement($this->username_selector) != null || $this->exts->getElement($this->password_selector) != null) {
                sleep(2);
                $this->login_tryout = (int)$this->login_tryout + 1;
                $this->exts->capture("1-pre-login");

                if ($this->exts->getElement($this->username_selector) != null) {
                    $this->exts->log("Enter Username");

                    $this->exts->moveToElementAndType($this->username_selector, $this->username);
                    sleep(2);
                }

                if ($this->exts->getElement($this->password_selector) != null) {
                    $this->exts->log("Enter Password");
                    $this->exts->moveToElementAndType($this->password_selector, $this->password);
                    sleep(5);
                }
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
    function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            if ($this->exts->getElement('a[href*="logout"]') != null) {
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
        $this->exts->log("Invoice page");

        $this->exts->moveToElementAndClick("div.user_info div.user_log a");
        sleep(15);

        $this->exts->moveToElementAndClick("a.my-order");
        sleep(15);

        $this->downloadInvoice();

        if ($this->totalFiles == 0) {
            $this->exts->log("No invoice !!! ");
            $this->exts->no_invoice();
        }
    }

    /**
     *method to download incoice
     */
    public $j = 0;
    public $totalFiles = 0;
    function downloadInvoice()
    {
        $this->exts->log("Begin downlaod invoice 1");
        sleep(15);
        try {
            if ($this->exts->getElement(".order-list li[id*='order-list']") != null) {
                $invoices = array();
                $receipts = $this->exts->getElements(".order-list li[id*='order-list']");
                $this->exts->log(count($receipts));
                $count = 0;
                foreach ($receipts as $receipt) {
                    $this->exts->log("each record");
                    if ($this->exts->getElements('a[href*="ordersDetail&ordersId="]', $receipt) != null) {
                        if ($this->exts->getElements("span.date", $receipt)[0]) {
                            $this->exts->log("check day");
                            $receiptDate = $this->exts->getElements("span.date", $receipt)[0]->getAttribute('innerText');
                            $receiptDate = trim(end(explode(':', $receiptDate)));
                        } else {
                            $receiptDate = "";
                        }
                        $this->exts->log("Invoice date: " . $receiptDate);
                        $receiptUrl = $this->exts->getElements('a[href*="ordersDetail&ordersId="]', $receipt)[0]->getAttribute('href');
                        $receiptName = $this->exts->getElements('a[href*="ordersDetail&ordersId="]', $receipt)[0]->getAttribute('innerText');
                        $receiptFileName = !empty($receiptName) ? $receiptName . '.pdf' : '';
                        $this->exts->log("Invoice name: " . $receiptName);
                        $this->exts->log("Invoice Filename: " . $receiptFileName);
                        $this->exts->log("Inovice URL: " . $receiptUrl);
                        $parsed_date = $this->exts->parse_date($receiptDate, 'M d Y', 'Y-m-d');
                        $this->exts->log("Invoice Parsed date: " . $parsed_date);
                        $receiptAmount = $this->exts->getElements("li.amount", $receipt)[0]->getAttribute('innerText');
                        $receiptAmount = trim(preg_replace('/[^\d\.\,]/', '', $receiptAmount)) . ' USD';
                        $this->exts->log("Inovice amount: " . $receiptAmount);
                        $invoice = array(
                            'receiptName' => $receiptName,
                            'parsed_date' => $parsed_date,
                            'receiptAmount' => $receiptAmount,
                            'receiptFileName' => $receiptFileName,
                            'receiptUrl' => $receiptUrl,
                        );

                        array_push($invoices, $invoice);
                    }

                    $this->j += 1;
                }

                $this->exts->log("Number of invoices: " . count($invoices));
                if (count($invoices) > 0) {
                    $newTab = $this->exts->openNewTab();
                    sleep(1);
                    foreach ($invoices as $invoice) {
                        $this->exts->openUrl($invoice['receiptUrl']);
                        sleep(5);

                        $downloaded_file = $this->exts->click_and_download('a[href*="ordersPrint"]', 'pdf', $invoice['receiptFileName']);
                        $this->exts->log("downloaded file");
                        sleep(5);
                        if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                            $this->exts->log("create file");
                            $this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $downloaded_file);
                            sleep(5);
                        } else {
                            $this->downloadAgain($invoice, 1);
                        }

                        $this->totalFiles += 1;
                    }
                    $this->exts->closeTab($newTab);
                    sleep(1);
                }

                if ($this->restrictPages == 0 && $this->exts->getElement("div.rate-paginator a.rate-page-btn.rate-page-next") != null && $this->exts->getElement("div.rate-paginator a.rate-page-btn.rate-page-next.disabled") == null) {
                    $this->exts->moveToElementAndClick("div.rate-paginator a.rate-page-btn.rate-page-next");
                    sleep(10);
                    $this->downloadInvoice();
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception downlaoding invoice " . $exception->getMessage());
        }
    }

    public function downloadAgain($invoice, $count)
    {
        $this->exts->log("Download again invoice: " . $invoice['receiptName']);

        $downloaded_file = $this->exts->click_and_download('a[href*="ordersPrint"]', 'pdf', $invoice['receiptFileName']);
        $this->exts->log("downloaded file");
        sleep(5);
        if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
            $this->exts->log("create file");
            $this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $downloaded_file);
            sleep(5);
        } else {
            $count += 1;
            if ($count < 10) {
                $this->downloadAgain($invoice, $count);
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
