<?php // updated login success selector and download code.

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

    // Server-Portal-ID: 107037 - Last modified: 12.02.2025 07:00:42 UTC - User: 1

    public $baseUrl = "https://www.westwingnow.de/customer/order/index/";
    public $loginUrl = "https://www.westwing.de/customer/account/";
    public $invoicePageUrl = "https://www.westwingnow.de/customer/order/index/";
    public $username_selector = 'input#loginEmail';
    public $password_selector = 'input#loginPassword';
    public $submit_button_selector = 'button[data-testid="login-button"]';

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
            sleep(10);
            if ($this->exts->getElement('button#onetrust-accept-btn-handler') != null) {
                $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
                sleep(5);
            }
            $this->fillForm(0);
            sleep(2);

            if ($this->checkLogin()) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $this->exts->capture("LoginSuccess");
                $this->invoicePage();
            } else {
                if (stripos(strtolower($this->exts->extract('div[data-testid="error-notification-undefined"]')), 'passwor') !== false) {
                    $this->exts->account_not_ready();
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
            sleep(1);
            if ($this->exts->exists($this->username_selector) && $this->exts->exists($this->password_selector)) {
                sleep(1);
                $this->login_tryout = (int)$this->login_tryout + 1;
                $this->exts->capture("1-pre-login");

                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);

                if (!$this->isValidEmail($this->username)) {
                    $this->exts->log('>>>>>>>>>>>>>>>>>>>Invalid email........');
                    $this->exts->loginFailure(1);
                }

                $this->exts->moveToElementAndClick($this->submit_button_selector);
                sleep(5);

                if (stripos(strtolower($this->exts->extract('div[data-testid="error-notification-undefined"]')), 'passwor') !== false) {
                    $this->exts->account_not_ready();
                } else if (stripos($this->exts->extract('div.qa-login-passwordField-error'), 'passwor') !== false) {
                    $this->exts->log($this->exts->extract('div.qa-login-passwordField-error'));
                    $this->exts->loginFailure(1);
                }
            }

            sleep(20);
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    public function isValidEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Method to Check where user is logged in or not
     * return boolean true/false
     */
    public function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $this->exts->capture('4-checkLogin');
        $isLoggedIn = false;
        try {
            if ($this->exts->exists('a.sel-menu-orders[href*="/order/"]')) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            } else if ($this->exts->exists('a[href="/customer/account/logout/"]')) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            } else {
                $this->exts->openUrl($this->invoicePageUrl);
                sleep(15);
                if ($this->exts->exists('iframe[data-testid="alice-orders-iframe"]')) {
                    $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                    $isLoggedIn = true;
                } else if ($this->exts->exists('a.sel-menu-orders[href*="/order/"]')) {
                    $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                    $isLoggedIn = true;
                }
            }
        } catch (Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }

    public function invoicePage()
    {
        $this->exts->log("Invoice page");
        $this->exts->openUrl($this->invoicePageUrl);
        sleep(15);

        if ($this->exts->exists($this->username_selector) && $this->exts->exists($this->password_selector)) {
            $this->fillForm(0);
            sleep(2);

            if (!$this->checkLogin()) {
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure();
            }

            $this->exts->openUrl($this->invoicePageUrl);
            sleep(15);
        }

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
    public function downloadInvoice($count = 1, $pageCount = 1)
    {
        $this->exts->log("Begin download invoice");

        $this->exts->capture('4-List-invoice');
        try {
            if ($this->exts->getElement('div.blockOrdersList > div') != null) {
                $receipts = $this->exts->getElements('div.blockOrdersList > div');
                $invoices = array();
                foreach ($receipts as $i => $receipt) {
                    $tags = $this->exts->getElements('div.blockOrdersCell', $receipt);
                    if (count($tags) == 5 && $this->exts->getElement('div.blockOrdersCell a[href*="/order/"]', $receipt) != null) {
                        $receiptDate = trim($this->getInnerTextByJS($tags[2]));
                        $receiptUrl = $this->exts->extract('.blockOrdersCell_number a[href*="/order/"]', $receipt, 'href');
                        $receiptName = trim(end(explode('#', $this->getInnerTextByJS($tags[0]))));
                        $receiptAmount = trim(str_replace('.-', '', $this->getInnerTextByJS($tags[3]))) . ' EUR';
                        $receiptFileName = !empty($receiptName) ? $receiptName . '.pdf' : '';

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

                $this->totalFiles = count($invoices);
                $count = 1;
                foreach ($invoices as $invoice) {
                    $invoiceDate = $this->exts->parse_date($invoice['receiptDate']);
                    if ($invoiceDate == '') {
                        $invoiceDate = $invoice['receiptDate'];
                    }
                    $this->exts->openUrl($invoice['receiptUrl']);
                    sleep(5);
                    if ($this->exts->getElement('a[href*="downloadinvoice"]') != null) {
                        $receiptUrl = rtrim(str_replace('/view/?order_nr=', '/downloadinvoice/?orderNr=', $invoice['receiptUrl']), "/");
                        $downloaded_file = $this->exts->direct_download($receiptUrl, 'pdf', $invoice['receiptFileName']);
                        $this->exts->log("Download file: " . $downloaded_file);

                        if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                            $this->exts->new_invoice($invoice['receiptName'], $invoiceDate, $invoice['receiptAmount'], $downloaded_file);
                            sleep(1);
                            $count++;
                        }
                    }
                }
            } else if ($this->exts->querySelector('iframe[data-testid="alice-orders-iframe"]') != null) {
                $invoice_iframe = $this->exts->makeFrameExecutable('iframe[data-testid="alice-orders-iframe"]');
                if ($invoice_iframe->getElement('div.blockOrdersList > div') != null) {
                    $receipts = $invoice_iframe->getElements('div.blockOrdersList > div');
                    $invoices = array();
                    foreach ($receipts as $i => $receipt) {
                        $tags = $invoice_iframe->getElements('div.blockOrdersCell', $receipt);
                        if (count($tags) == 5 && $invoice_iframe->getElement('div.blockOrdersCell a[href*="/order/"]', $receipt) != null) {
                            $receiptDate = trim($tags[2]->getAttribute('innerText'));
                            $receiptUrl = $invoice_iframe->getElement('.blockOrdersCell_number a[href*="/order/"]', $receipt)->getAttribute('href');
                            $receiptName = trim(end(explode('#', $tags[0]->getAttribute('innerText'))));
                            $receiptAmount = trim(str_replace('.-', '', $tags[3]->getAttribute('innerText'))) . ' EUR';
                            $receiptFileName = !empty($receiptName) ? $receiptName . '.pdf' : '';

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

                    $this->totalFiles = count($invoices);
                    $count = 1;
                    foreach ($invoices as $invoice) {
                        $invoiceDate = $this->exts->parse_date($invoice['receiptDate']);
                        if ($invoiceDate == '') {
                            $invoiceDate = $invoice['receiptDate'];
                        }
                        $this->exts->openUrl($invoice['receiptUrl']);
                        sleep(15);
                        if ($this->exts->getElement('a[href*="downloadinvoice"]') != null) {
                            $receiptUrl = rtrim(str_replace('/view/?order_nr=', '/downloadinvoice/?orderNr=', $invoice['receiptUrl']), "/");
                            if (strpos($receiptUrl, '%2F&layout=mobile&device=androidapp') !== false) {
                                $receiptUrl = str_replace('%2F&layout=mobile&device=androidapp', '', $receiptUrl);
                                $this->exts->log("receiptUrl: " . $receiptUrl);
                            }
                            $downloaded_file = $this->exts->direct_download($receiptUrl, 'pdf', $invoice['receiptFileName']);
                            $this->exts->log("Download file: " . $downloaded_file);

                            if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                                $this->exts->new_invoice($invoice['receiptName'], $invoiceDate, $invoice['receiptAmount'], $downloaded_file);
                                sleep(1);
                                $count++;
                            }
                        } else {
                            $receiptUrl = rtrim(str_replace('/view/?order_nr=', '/downloadinvoice/?orderNr=', $invoice['receiptUrl']), "/");
                            if (strpos($receiptUrl, '%2F&layout=mobile&device=androidapp') !== false) {
                                $receiptUrl = str_replace('%2F&layout=mobile&device=androidapp', '', $receiptUrl);
                                $this->exts->log("receiptUrl1: " . $receiptUrl);
                            }
                            $downloaded_file = $this->exts->direct_download($receiptUrl, 'pdf', $invoice['receiptFileName']);
                            $this->exts->log("Download file: " . $downloaded_file);

                            if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                                $this->exts->new_invoice($invoice['receiptName'], $invoiceDate, $invoice['receiptAmount'], $downloaded_file);
                                sleep(1);
                                $count++;
                            }
                        }
                    }
                }
            } else if ($this->exts->querySelector('div[data-testid="order-overview-item-list-wrapper"] div[data-testid="order-item-wrapper"]') != null) {
                $invoices = [];
                $rows = $this->exts->getElements('div[data-testid="order-overview-item-list-wrapper"] div[data-testid="order-item-wrapper"]');
                foreach ($rows as $key => $row) {
                    $invoiceLink = $this->exts->getElement('div[data-testid="order-item-view-order-button"] a', $row);
                    if ($invoiceLink != null) {
                        $invoiceUrl = $invoiceLink->getAttribute("href");

                        array_push($invoices, array(
                            'invoiceUrl' => $invoiceUrl,
                        ));
                    }
                }


                foreach ($invoices as $invoice) {
                    $this->exts->openUrl($invoice['invoiceUrl']);
                    
                    $this->waitFor('button[data-testid="getInvoiceBtn"]');

                    if ($this->exts->querySelector('button[data-testid="getInvoiceBtn"]') != null) {
                        $this->exts->moveToElementAndClick('button[data-testid="getInvoiceBtn"]');
                    }
                    $this->waitFor('div.p-overlayMobilePadding button.justify-between');

                    if ($this->exts->querySelector('div.p-overlayMobilePadding button.justify-between') != null) {
                        $this->exts->moveToElementAndClick('div.p-overlayMobilePadding button.justify-between');
                        sleep(5);
                    }

                    $rows = count($this->exts->getElements('a[download]'));

                    for ($i = 0; $i < $rows; $i++) {
                        $j = $i + 1;

                        $invoiceLink = $this->exts->getElement('a[download]:nth-child(' . $j . ')');
                        if ($invoiceLink != null) {
                            $invoiceUrl = $invoiceLink->getAttribute("href");

                            $invoiceName = $this->exts->extract('a[download]:nth-child(' . $j . ')');

                            $invoiceName = str_replace(".pdf", "", $invoiceName);

                            $this->exts->log('--------------------------');
                            $this->exts->log('invoiceName: ' . $invoiceName);
                            $this->exts->log('invoiceUrl: ' . $invoiceUrl);

                            $invoiceFileName = !empty($invoiceName) ?  $invoiceName . '.pdf' : '';

                            $downloaded_file = $this->exts->direct_download($invoiceUrl, 'pdf', $invoiceFileName);
                            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                                $this->exts->new_invoice($invoiceName, '', '', $invoiceFileName);
                                sleep(1);
                            } else {
                                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                            }
                        }
                    }
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception downloading invoice " . $exception->getMessage());
        }
    }
    public function getInnerTextByJS($selector_or_object, $parent = null)
    {
        if ($selector_or_object == null) {
            $this->exts->log(__FUNCTION__ . ' Can not get innerText of null');
            return;
        }
        $element = $selector_or_object;
        if (is_string($selector_or_object)) {
            $element = $this->exts->getElement($selector_or_object, $parent);
            if ($element == null) {
                $element = $this->exts->getElement($selector_or_object, $parent, 'xpath');
            }
            if ($element == null) {
                $this->exts->log(__FUNCTION__ . ':: Can not found element with selector/xpath: ' . $selector_or_object);
            }
        }
        if ($element != null) {
            return $this->exts->execute_javascript("return arguments[0].innerText", [$element]);
        }
    }

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
