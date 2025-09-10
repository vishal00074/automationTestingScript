<?php //  optmize the scripts

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

    public $baseUrl = "https://biz.ikea.com/de/de/profile/transactions#";
    public $loginUrl = "https://www.ikea.com/de/de/profile/login";
    public $homePageUrl = "https://www.ikea.com/de/de/purchases";
    public $username_selector = 'form input#username';
    public $password_selector = 'form input#password';
    public $submit_button_selector = 'button[name="login"], form button[type="submit"]';
    public $check_login_success_selector = 'a[href*="/profile/login/"][data-tracking-label="profile"][class*="header__profile-link__neutral"]:not(.hnf-header__profile-link--hidden)';
    public $check_login_fail_selector = 'div.toast--show div.toast__body';
    public $login_tryout = 0;
    public $restrictPages = 3;
    public $isNoInvoice = true;
    public $tmpFlag = 0;
    public $moreBtn = true;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;

        $this->exts->openUrl($this->baseUrl);
        $this->exts->capture("Home-page-without-cookie");

        $isCookieLoginSuccess = false;
        if ($this->exts->loadCookiesFromFile()) {
            $this->exts->openUrl($this->homePageUrl);
            if ($this->checkLogin()) {
                $isCookieLoginSuccess = true;
            } else {
                $this->exts->clearCookies();
                $this->exts->openUrl($this->loginUrl);
            }
        }
        // for  testing 
        $isCookieLoginSuccess = false;


        for ($wait = 0; $wait < 4 && $this->exts->executeSafeScript('return !!document.querySelector("a[href*=\\"/#/login\\"]");') != 1; $wait++) {
            $this->exts->log('Waiting for login.....');
            sleep(10);
        }

        if ($this->isExists('a[href*="/#/login"]')) {
            $this->exts->moveToElementAndClick('a[href*="/#/login"]');
        }

        $this->waitFor('form a[href="#"]', 5);
        if ($this->isExists('form a[href="#"]')) {
            $this->exts->click_element('form a[href="#"]');
        }

        sleep(5);
        if (!$isCookieLoginSuccess) {
            $this->exts->capture("after-login-clicked");

            $this->fillForm(0);

            $this->waitFor('#onetrust-accept-btn-handler', 7);
            if ($this->isExists('#onetrust-accept-btn-handler')) {
                $this->exts->moveToElementAndClick('#onetrust-accept-btn-handler');
            }

            $err_msg = $this->exts->extract('div.loading-spinner .error-text');

            if ($this->checkLogin()) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $this->exts->capture("LoginSuccess");
                $this->invoicePage();
                sleep(10);

                if ($this->isNoInvoice) {
                    $this->exts->no_invoice();
                }
                $this->exts->success();
            } elseif (
                ($err_msg != "" && $err_msg != null && $this->isExists('div.loading-spinner .error-text'))
                || $this->isExists($this->check_login_fail_selector)
            ) {
                $this->exts->log($err_msg);
                $this->exts->loginFailure(1);
            } elseif ($this->isExists('form[name*="verifyPhone"]')) {
                $this->exts->account_not_ready();
            } else {
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure();
            }
        } else {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->invoicePage();

            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        }
    }

    private function fillForm($count)
    {
        $this->waitFor($this->username_selector, 10);

        if ($this->isExists($this->username_selector)) {
            $this->exts->capture("2-login-page");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);
            if (!$this->isExists($this->password_selector)) {
                //click "Sign in with your password"
                $this->exts->moveToElementAndClick('button[type="submit"]');
                $this->waitFor("div.toast--show div.toast__body", 50);
                if ($this->isExists("div.toast--show div.toast__body")) {
                    $this->exts->loginFailure(1);
                }
            }

            sleep(7);
            if ($this->isExists('form[name="OTPVerification"]')) {
                $this->checkFillTwoFactor();
            }

            $this->waitFor($this->password_selector, 10);
            if ($this->isExists($this->password_selector)) {
                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(5);
                $this->exts->capture("2-login-page-filled");
                if ($this->waitFor($this->check_login_fail_selector, 10)) {
                    $this->exts->log("Login Failure : " . $this->exts->extract($this->check_login_fail_selector));
                    if (
                        strpos(strtolower($this->exts->extract($this->check_login_fail_selector)), 'passwor') !== false ||
                        strpos(strtolower($this->exts->extract($this->check_login_fail_selector)), 'wir haben dein konto aufgrund zu vieler fehlgeschlagener anmeldeversuche gesperrt') !== false ||
                        strpos(strtolower($this->exts->extract($this->check_login_fail_selector)), 'anmeldung scheint nicht zu') !== false ||
                        strpos(strtolower($this->exts->extract($this->check_login_fail_selector)), 'unser system hat deine aktivität leider als bot-aktion gekennzeichnet.') !== false

                    ) {
                        $this->exts->loginFailure(1);
                    }
                }
            }

            if ($this->isExists($this->submit_button_selector)) {
                $this->exts->moveToElementAndClick($this->submit_button_selector);

                for ($wait = 0; $wait < 15 && $this->exts->executeSafeScript("return !!document.querySelector('div.toast--show div.toast__body');") != 1; $wait++) {
                    $this->exts->log('Waiting for login.....');
                    sleep(2);
                }
                // $this->waitFor("div.toast--show div.toast__body",50);
                if ($this->isExists("div.toast--show div.toast__body")) {
                    $this->exts->loginFailure(1);
                }
            }

            sleep(10);
            if ($this->isExists('form[name="OTPVerification"]')) {
                $this->checkFillTwoFactor();
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }


    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'form[name="OTPVerification"] input';
        $two_factor_message_selector = 'form[name="OTPVerification"] .form-field__content .form-field__message';
        $two_factor_submit_selector = 'form[name="OTPVerification"] button';

        if ($this->exts->getElement($two_factor_selector) != null) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                $total_message_selectors = count($this->exts->getElements($two_factor_message_selector));
                for ($i = 0; $i < $total_message_selectors; $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getText() . "\n";
                }
                $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            }
            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }
            // clear input
            $this->exts->click_by_xdotool($two_factor_selector);
            $this->exts->type_key_by_xdotool("ctrl+a");
            $this->exts->type_key_by_xdotool("Delete");

            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code. " . $two_factor_code);
                // $this->exts->moveToElementAndClick($two_factor_selector);
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
                $this->exts->type_key_by_xdotool("Tab");

                if ($this->isExists('input#trust_device_checkbox, [class*="Checkbox"] label [type="checkbox"]:not(:checked)')) {
                    $this->exts->moveToElementAndClick('input#trust_device_checkbox, [class*="Checkbox"] label [type="checkbox"]:not(:checked)');
                }
                // sleep(1);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(10);

                if ($this->exts->getElement($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->checkFillTwoFactor();
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
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
        $this->waitFor($this->check_login_success_selector, 10);
        try {
            if (($this->isExists('a[href*="/logout"], div#greeting button') && !$this->isExists($this->password_selector)) || $this->isExists($this->check_login_success_selector)) {
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
        $this->exts->log(__FUNCTION__);
        sleep(10);

        // $this->exts->openUrl('https://www.ikea.com/de/de/purchases/');
        //sleep(10);
        /* // go to profile overview page
		 $this->exts->execute_javascript('location.href = "https://www.ikea.com/de/de/local-apps/business-formulare/#/portal/overview";');
		 sleep(5);
		 $this->exts->capture('profile_overview');
		 // click Purchases nav link (open link)
		 $this->exts->execute_javascript('location.href = "https://biz.ikea.com/de/de/profile/transactions";');
		 sleep(5);
		 $this->exts->capture('transactions_page'); */
        $this->exts->openUrl("https://www.ikea.com/de/de/business-profile/transactions/");
        sleep(10);
        $this->exts->capture('purchases_page');

        //$this->handleCompanayDataQuestion();
        $this->processInvoices();

        $this->exts->openUrl('https://www.ikea.com/de/de/purchases/');
        sleep(10);

        $this->processInvoicesNew();

        if ($this->isNoInvoice) {
            $this->exts->log("No invoice !!! ");
            $this->exts->no_invoice();
        }
    }

    public function loadMoreInvoices()
    {
        $this->exts->moveToElementAndClick('[id="history-list-completed"] +div >div > button');
        sleep(2);

        if ($this->isExists('button[aria-label="Mehr Käufe ansehen"]')) {
            $this->exts->moveToElementAndClick('button[aria-label="Mehr Käufe ansehen"]');
        }
    }

    private function isExists($selector = '')
    {
        $safeSelector = addslashes($selector);
        $this->exts->log('Element:: ' . $safeSelector);
        $isElement = $this->exts->execute_javascript('!!document.querySelector("' . $safeSelector . '")');
        if ($isElement) {
            $this->exts->log('Element Found');
            return true;
        } else {
            $this->exts->log('Element not Found');
            return false;
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
    private function processInvoicesNew()
    {
        $this->waitFor('ul[id*="list-completed"] > li ,ul[id="ph_list-completed"] > li', 15);

        $this->exts->capture("4-invoices-page");

        $stop = 0;
        while ($this->moreBtn && $stop < 20) {
            // Check if the button exists
            $this->waitFor('[id="history-list-completed"] +div >div > button', 15);
            if ($this->isExists('[id="history-list-completed"] +div >div > button, button[aria-label="Mehr Käufe ansehen"]')) {
                $this->loadMoreInvoices();
                sleep(2);
            } else {
                // Exit the loop if the button does not exist
                $this->moreBtn = false;
            }
            $stop++;
        }

        $rows = count($this->exts->getElements('ul[id*="list-completed"] > li ,ul[id="ph_list-completed"] > li'));
        $this->exts->log("order count: " . $rows);

        //ul[id="manage-action-inline-list"] button[data-testid="invoice-item"]

        $url_array = array();

        for ($index = 0; $index < $rows; $index++) {
            $row = $this->exts->getElements('ul[id*="list-completed"] > li')[$index];
            //$show_document_button = $this->exts->getElement('button[class*="PurchasesList_button"]', $row);
            // Try to find the anchor element within the current row
            $anchorElement = $this->exts->getElement('a[href*="/purchases/"]', $row);

            // Check if the element is found
            if ($anchorElement !== null) {
                $receiptUrl = $anchorElement->getAttribute('href');
                sleep(2);
                $this->exts->log("link: " . 'https://www.ikea.com' . $receiptUrl);
                $url_array[] = $receiptUrl;
            } else {
                // Log an error or handle the case when the element is not found
                $this->exts->log("Error: Could not locate element - a[href*='/purchases/']");
            }
        }

        $this->exts->log("url_array count: " . count($url_array));

        foreach ($url_array as $link_value) {
            $this->exts->openUrl($link_value);
            sleep(35);
            //$this->exts->moveToElementAndClick('ul[id="manage-action-inline-list"] button[data-testid="invoice-item"]');

            if ($this->isExists('ul[id="manage-action-inline-list"] button[data-testid="invoice-item"],ul[id="manage-action-inline-list"] button[data-testid="viewReceipt-item"]')) {
                $this->isNoInvoice = false;

                $element = $this->exts->getElements('div[class="sc-dveHSx kEwoBh"]');
                $download_button = $this->exts->getElements('ul[id="manage-action-inline-list"] button[data-testid="invoice-item"]');
                // $this->exts->execute_javascript("arguments[0].focus();", [$element]);
                sleep(15);

                $invoiceName = $this->exts->extract('ul[id="purchase-details"] li:nth-child(1) span[class="list-view-item__description"],ul[id="Purchase-details"] li:nth-child(1) span[class="list-view-item__description"]');
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                $invoice_amount = $this->exts->extract('ul[id="PaymentsList"] li:nth-child(1) span[class="list-view-item__description"]');
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $invoice_amount)) . ' EUR';
                $invoiceDate = '';
                $this->exts->log("Invoice Name: " . $invoiceName);
                $this->exts->log("Invoice FileName: " . $invoiceFileName);
                $this->exts->log("Invoice Amount: " . $invoiceAmount);

                if ($this->exts->invoice_exists($invoiceName) || $this->exts->document_exists($invoiceFileName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    try {
                        $this->exts->log('Click download button');
                        $this->exts->moveToElementAndClick('ul[id="manage-action-inline-list"] button[data-testid="invoice-item"]');
                        // $download_button->click();		
                    } catch (\Exception $exception) {
                        $this->exts->log('Click download button by javascript');
                        $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
                    }

                    if ($this->isExists('ul[id="manage-action-inline-list"] button[data-testid="viewReceipt-item"]')) {

                        $this->exts->click_element('ul[id="manage-action-inline-list"] button[data-testid="viewReceipt-item"]');

                        sleep(5);
                    }


                    $this->waitFor('button[data-testid="download-receipt-invoice-button"]', 15);
                    if ($this->isExists('button[data-testid="download-receipt-invoice-button"]')) {

                        $this->exts->click_element('button[data-testid="download-receipt-invoice-button"]');
                    }
                    sleep(10);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {

                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }
                sleep(1);
            }
        }
    }

    private function processInvoices($paging_count = 1)
    {
        $this->waitFor('div[class*="TransactionsList_transactionsList"],table tbody tr', 15);

        $this->exts->capture("4-invoices-page");

        // Click a specific element if necessary (uncomment and modify if required)
        $this->exts->moveToElementAndClick('.experience-satisfaction-animation-container[aria-label="3"]');
        sleep(3);

        $rows = count($this->exts->getElements('div[class*="TransactionsList_transactionsList"],table tbody tr'));
        for ($index = 0; $index < $rows; $index++) {
            $row = $this->exts->getElements('div[class*="TransactionsList_transactionsList"],table tbody tr')[$index];
            $show_document_button = $this->exts->getElement('button[class*="PurchasesList_button"],button[class*="PurchasesList-module_button"]', $row);

            if ($show_document_button != null) {
                $this->isNoInvoice = false;
                $invoice_name = trim($this->exts->getElements('td', $row)[0]->getAttribute('innerText'));
                $invoice_file_name = !empty($invoice_name) ? $invoice_name . '.pdf' : '';
                $invoice_amount = $this->exts->getElements('td', $row)[3]->getAttribute('innerText');
                $invoice_amount = trim(preg_replace('/[^\d\.\,]/', '', $invoice_amount)) . ' EUR';

                $this->exts->log("Invoice Name: " . $invoice_name);
                $this->exts->log("Invoice FileName: " . $invoice_file_name);
                $this->exts->log("Invoice Amount: " . $invoice_amount);

                if ($this->exts->invoice_exists($invoice_name) || $this->exts->document_exists($invoice_file_name)) {
                    $this->exts->log("Invoice existed: " . $invoice_name);
                } else {
                    $this->click_element($show_document_button);
                    sleep(5);

                    if ($this->isExists('div[class*="ReceiptInvoiceModal"] a[href],button[aria-label="Rechnung herunterladen"]')) {
                        try {
                            $this->exts->log('Click download button');
                            $this->exts->moveToElementAndClick('div[class*="ReceiptInvoiceModal"] a[href],button[aria-label="Rechnung herunterladen"]');
                        } catch (\Exception $exception) {
                            $this->exts->log('Click download button by javascript');
                            $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
                        }

                        $this->exts->wait_and_check_download('pdf');
                        $downloaded_file = $this->exts->find_saved_file('pdf', $invoice_file_name);
                        sleep(5);

                        if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                            $this->exts->new_invoice($invoice_name, '', $invoice_amount, $downloaded_file);
                        } else {
                            $this->exts->capture("failed_download_" . $invoice_name);
                        }
                    }

                    // Click to close modal
                    $this->exts->moveToElementAndClick('button.modal-header__close');
                    sleep(1);
                }
            }
        }


        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
        $pageCount = $this->exts->getElements('div[class*="ResponsiveTableWithPagination-module_showMoreContainer"] button');

        if ($restrictPages == 0 && $paging_count < 50 && $pageCount != null) {
            if (count($pageCount) > 1) {
                $this->tmpFlag = 1;
                $this->exts->moveToElementAndClick('div[class*="ResponsiveTableWithPagination-module_showMoreContainer"] button:nth-child(2)');
                $paging_count++;
                sleep(5);
                $this->processInvoices($paging_count);
            } else if (count($pageCount) == 1 && $this->tmpFlag == 0) {
                $this->exts->moveToElementAndClick('div[class*="ResponsiveTableWithPagination-module_showMoreContainer"] button');
                $paging_count++;
                sleep(5);
                $this->processInvoices($paging_count);
            }
        }
    }


    /**
     * Helper Method to click element
     * @param ? $selector_or_object received both css selector, xpath or element object
     */
    public function handleCompanayDataQuestion()
    {
        $this->exts->log('Begin handleCompanayDataQuestion');
        // sometime a modal is showing to confirm your data is upto date.
        // if such a modal is found, click yes button
        if ($this->isExists('#companyDataVerificationQuestion___BV_modal_content_')) {
            $this->exts->moveToElementAndClick('#companyDataVerificationQuestion___BV_modal_footer_ .btn-primary');
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
