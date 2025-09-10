<?php // handle empty invoicesName case

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

    // Server-Portal-ID: 146161 - Last modified: 23.07.2025 06:43:20 UTC - User: 1

    public $baseUrl = "https://my.ecwid.com/cp/";
    public $loginUrl = "https://my.ecwid.com/cp/";
    public $username_selector = '.login-main .block-view-on input[name="email"]';
    public $password_selector = '.login-main .block-view-on input[name="password"]';
    public $submit_button_selector = '.login-main .block-view-on  button.btn-login-main';
    public $login_tryout = 0;
    public $restrictPages = 3;
    public $download_lang = 'en';
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        $this->download_lang = isset($this->exts->config_array["download_lang"]) ? trim($this->exts->config_array["download_lang"]) : $this->download_lang;

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
            $this->fillForm(0);
            sleep(10);

            if ($this->checkLogin()) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $this->exts->capture("LoginSuccess");
                $this->invoicePage();
            } else {
                if (filter_var($this->username, FILTER_VALIDATE_EMAIL) === false) {
                    $this->exts->loginFailure(1);
                }
                if (strpos(strtolower($this->exts->extract('div.bubble-error, div.bubble--error')), 'passwor') !== false) {
                    $this->exts->loginFailure(1);
                } else if (
                    strpos(strtolower($this->exts->extract('.bubble__container .bubble__text')), 'no accounts associated') !== false ||
                    strpos(strtolower($this->exts->extract('.bubble__container .bubble__text')), 'pas de compte associÃ© avec ce courriel') !== false ||
                    strpos(strtolower($this->exts->extract('.bubble__container .bubble__text')), 'unter dieser e-mail-adresse ist kein konto registriert') !== false
                ) {
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

    function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {
            sleep(1);
            if ($this->exts->getElement($this->username_selector) != null && $this->exts->getElement($this->password_selector) != null) {
                sleep(1);
                $this->login_tryout = (int)$this->login_tryout + 1;
                $this->exts->capture("1-pre-login");

                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(4);
                if (!$this->isValidEmail($this->username)) {
                    $this->exts->loginFailure(1);
                }

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);

                $this->exts->moveToElementAndClick('input[name="remember"]');
                $this->check_solve_blocked_page();
                $this->exts->capture("2-login-page-filled");
                $this->exts->moveToElementAndClick($this->submit_button_selector);
                sleep(5);
            }

            sleep(10);
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    function isValidEmail($username)
    {
        $emailPattern = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
        if (preg_match($emailPattern, $username)) {
            return 'email';
        }
        return false;
    }

    function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            if ($this->exts->getElement('.menu li[id="EcwidTab:My_Profile"] , .menu li[id="EcwidTab:Sales"], a[href*="logoff"]') != null) {
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

        $currentUrl = $this->exts->getUrl();
        $this->exts->log("print url :--->" . $currentUrl);
        if (trim($this->download_lang) == 'en') {
            $tempArr = explode("?", $currentUrl);
            if (count($tempArr) == 1) {
                $tempArr = explode("#", $currentUrl);
                $urlWithLang = trim($tempArr[0]) . '?lang=en#' . trim($tempArr[1]);
            } else {
                $urlWithLang = trim($tempArr[0]) . '?lang=en' . trim($tempArr[1]);
            }
            $this->exts->log('URL - ' . $urlWithLang);
            $this->exts->openUrl($urlWithLang);
            sleep(15);
        } else if (trim($this->download_lang) == 'de') {
            $tempArr = explode("?", $currentUrl);
            if (count($tempArr) == 1) {
                $tempArr = explode("#", $currentUrl);
                $urlWithLang = trim($tempArr[0]) . '?lang=de#' . trim($tempArr[1]);
            } else {
                $urlWithLang = trim($tempArr[0]) . '?lang=de' . trim($tempArr[1]);
            }
            $this->exts->log('URL - ' . $urlWithLang);
            $this->exts->openUrl($urlWithLang);
            sleep(15);
        } else if (trim($this->download_lang) == 'fr') {
            $tempArr = explode("?", $currentUrl);
            if (count($tempArr) == 1) {
                $tempArr = explode("#", $currentUrl);
                $urlWithLang = trim($tempArr[0]) . '?lang=fr#' . trim($tempArr[1]);
            } else {
                $urlWithLang = trim($tempArr[0]) . '?lang=fr' . trim($tempArr[1]);
            }
            $this->exts->log('URL - ' . $urlWithLang);
            $this->exts->openUrl($urlWithLang);
            sleep(15);
        }

        // $stores =  $this->exts->getElements('.my-stores-container a.my-store-link[href*="store"]');
        // new selector  
        $stores =  $this->exts->getElements('a.order-element__title-link[href^="#order:id="]');
        $stores_array = array();
        if (count($stores) >= 1) {
            foreach ($stores as $store) {
                $store_url = $store->getAttribute('href');
                $store_urls = array(
                    'store_url' => $store_url
                );

                array_push($stores_array, $store_urls);
            }

            foreach ($stores_array as $store) {
                $this->exts->openUrl($store['store_url']);
                sleep(15);
                $this->exts->moveToElementAndClick('.menu li[id="EcwidTab:My_Profile"]');
                sleep(5);
                // $this->exts->moveToElementAndClick('.plans-plate-container:not([style="display: none;"]) .block-billing-summary .block-payment-info .block-payment-details--history .payment-details-item-history a');
                $this->exts->click_element('.spacing--mt1 a.gwt-Anchor');
                sleep(5);

                $this->downloadPayment();
                sleep(3);

                $this->exts->moveToElementAndClick('.menu li[id="EcwidTab:Sales"]');
                sleep(5);

                $this->downloadInvoice();


                if ($this->exts->config_array['sales_invoice'] == '1') {
                    $this->downloadOrder();
                }
            }
        } else {
            $this->exts->moveToElementAndClick('.menu li[id="EcwidTab:My_Profile"]');
            sleep(5);
            // $this->exts->moveToElementAndClick('.plans-plate-container:not([style="display: none;"]) .block-billing-summary .block-payment-info .block-payment-details--history .payment-details-item-history a, div.plans-plate-container:not([style="display: none;"]) div.plan-billing-info__row-apps + div a');
            $this->exts->click_element('.spacing--mt1 a.gwt-Anchor');
            sleep(5);

            $this->downloadPayment();
            sleep(3);

            $this->exts->moveToElementAndClick('.menu li[id="EcwidTab:Sales"]');
            sleep(5);

            $this->downloadInvoice();


            if ($this->exts->config_array['sales_invoice'] == '1') {
                $this->downloadOrder();
            }
        }


        if ($this->totalFiles == 0) {
            $this->exts->log("No invoice !!! ");
            $this->exts->no_invoice();
        }
        $this->exts->success();
    }

    private function check_solve_blocked_page()
    {
        $unsolved_cloudflare_input_xpath = '//div[contains(@id, "sign_in")]//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) <= 0]';
        $solved_cloudflare_input_xpath = '//div[contains(@id, "sign_in")]//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) > 0]';
        $this->exts->capture("cloudflare-checking");
        if (
            !$this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath]) &&
            $this->exts->exists('#cf-please-wait > p:not([style*="display: none"]):not([style*="display:none"])')
        ) {
            for ($waiting = 0; $waiting < 10; $waiting++) {
                sleep(2);
                if ($this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath])) {
                    sleep(3);
                    break;
                }
            }
        }

        if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
            $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
            sleep(5);
            $this->exts->capture("cloudflare-clicked-1", true);
            sleep(3);
            if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
                $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
                sleep(5);
                $this->exts->capture("cloudflare-clicked-2", true);
                sleep(15);
            }
            if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
                $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
                sleep(5);
                $this->exts->capture("cloudflare-clicked-3", true);
                sleep(15);
            }
        }
    }

    /**
     *method to download incoice
     */
    public $totalFiles = 0;
    function downloadInvoice($count = 1, $pageCount = 1)
    {
        $this->exts->log("Begin download invoice");

        $this->exts->capture('4-order-page');

        try {
            if ($this->exts->getElement('div.ecwid-orders-list > .long-list > div.list-item') != null) {
                $receipts = $this->exts->getElements('div.ecwid-orders-list > .long-list > div.list-item');
                $invoices = array();
                foreach ($receipts as $i => $receipt) {
                    if ($this->exts->getElement('.list-item__button-wrapper:first-child button', $receipt) != null) {
                        $receiptDate = trim(reset(explode(' ', $this->exts->extract('.order__date', $receipt))));
                        $receiptUrl = 'div.ecwid-orders-list > .long-list > div.list-item:nth-child(' . ($i + 1) . ') .list-item__button-wrapper:first-child button';
                        $receiptName = trim($this->exts->extract('.order__number', $receipt));
                        $receiptAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('.order__price', $receipt))) . ' EUR';
                        $receiptFileName = !empty($receiptName) ? $receiptName . '.pdf': '';

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

                    $downloaded_file = $this->exts->click_and_print($invoice['receiptUrl'], $invoice['receiptFileName']);
                    $this->exts->log("Download file: " . $downloaded_file);

                    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoice['receiptName'], $invoiceDate, $invoice['receiptAmount'], $downloaded_file);
                        sleep(1);
                        $count++;
                    }
                }

                // next page
                if ($this->exts->getElement('.pagination button.pagination__nav--next:not([disabled])') != null) {
                    $pageCount++;
                    $this->exts->moveToElementAndClick('.pagination button.pagination__nav--next:not([disabled])');
                    sleep(10);
                    $this->downloadInvoice(1, $pageCount);
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception downloading invoice " . $exception->getMessage());
        }
    }

    function downloadPayment($count = 1, $pageCount = 1)
    {
        $this->exts->log("Begin download invoice");

        $this->exts->capture('4-billing-page');

        try {
            if ($this->exts->getElement('.content-wrapper-inner table.table > tbody > tr') != null) {
                $receipts = $this->exts->getElements('.content-wrapper-inner table.table > tbody > tr');
                $invoices = array();
                foreach ($receipts as $i => $receipt) {
                    $tags = $this->exts->getElements('td', $receipt);
                    if (count($tags) == 5 && $this->exts->getElement('td a[href*="/receipt"]', $receipt) != null) {
                        $receiptDate = trim($tags[0]->getAttribute('innerText'));
                        $receiptUrl = $this->exts->extract('td a[href*="/receipt"]', $receipt, 'href');
                        $receiptName = trim(reset(explode('&', end(explode('transactionId=', $receiptUrl)))));
                        $receiptAmount = trim($tags[4]->getAttribute('innerText'));
                        $receiptFileName = !empty($receiptName) ? $receiptName . '.pdf': '';

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

                    $downloaded_file = $this->exts->download_capture($invoice['receiptUrl'], $invoice['receiptFileName']);
                    $this->exts->log("Download file: " . $downloaded_file);

                    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoice['receiptName'], $invoiceDate, $invoice['receiptAmount'], $downloaded_file);
                        sleep(1);
                        $count++;
                    }
                }

                // next page
                if ($this->exts->getElement('button.PagingBar-btn:not([disabled]) > .icon-arr-right') != null) {
                    $pageCount++;
                    $this->exts->moveToElementAndClick('button.PagingBar-btn:not([disabled]) > .icon-arr-right');
                    sleep(5);
                    $this->downloadPayment(1, $pageCount);
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception downloading invoice " . $exception->getMessage());
        }
    }

    function downloadOrder($paging_count = 1)
    {
        sleep(25);
        $this->exts->capture("4-order-page");

        $rows = $this->exts->getElements('div.ecwid-orders-list > .long-list .list-element');
        foreach ($rows as $row) {
            if ($this->exts->getElement('.order-element__title-link[href*="order:id="]', $row) != null) {
                $this->totalFiles += 1;
                $order_link = $this->exts->getElement('.order-element__title-link[href*="order:id="]', $row);
                $invoiceName = $order_link->getAttribute('href');
                $invoiceName = explode('&', end(explode('order:id=', $invoiceName)))[0];
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf': '';
                $invoiceDate = '';
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('.list-element__actions .list-element__price', $row))) . ' EUR';

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $parsed_date = $this->exts->parse_date($invoiceDate, 'd/m/Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $parsed_date);

                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoiceName) || $this->exts->document_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                    continue;
                }
                $print_button = $this->exts->getElement('.list-element__actions .list-element__price + * .list-element__button-wrapper:first-child button', $row);
                try {
                    $this->exts->log('Click print button');
                    $print_button->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click print button by javascript');
                    $this->exts->execute_javascript("arguments[0].click()", [$print_button]);
                }
                sleep(5);
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }

                // close new tab too avoid too much tabs. Sometime download pdf create new login tab, close it
                // $handles = $this->exts->webdriver->getWindowHandles();
                // if(count($handles) > 1){
                //     $this->exts->webdriver->switchTo()->window(end($handles));
                //     $this->exts->webdriver->close();
                //     $handles = $this->exts->webdriver->getWindowHandles();
                //     $this->exts->webdriver->switchTo()->window($handles[0]);
                // }
            }
        }

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        if (
            $restrictPages == 0 &&
            $paging_count < 50 &&
            $this->exts->getElement('.pagination__nav--next:not([disabled="disabled"])') != null
        ) {
            $paging_count++;
            $this->exts->moveToElementAndClick('.pagination__nav--next:not([disabled="disabled"])');
            sleep(5);
            $this->downloadOrder($paging_count);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
