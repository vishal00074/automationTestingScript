<?php // replaced waitTillPresent with existed waitFor function to prevent client read timout error and handle empty invoicesname
// replaced sendKeys to moveToElementAndType checkFillLogin define config setup for download_all_documents 

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

    // Server-Portal-ID: 3587 - Last modified: 20.08.2025 14:26:43 UTC - User: 1

    /*Define constants used in script*/

    public $baseUrl = 'https://www.eon.de/de/meineon/meine-uebersicht.html';
    public $download_all_documents = 0;
    public $username_selector = 'login-form__text-input-email:not(.hidden) input#username, input#text_input';
    public $password_selector = 'input#pwdTxt, input#password';
    public $isNoInvoice = true;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->download_all_documents =  isset($this->exts->config_array["download_all_documents"]) ? (int)@$this->exts->config_array["download_all_documents"] : $this->download_all_documents;
        $this->exts->log('download_all_documents ' . $this->download_all_documents);

        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->baseUrl);
        sleep(10);

        $this->check_solve_blocked_page();

        $this->exts->capture('cloudeflare-check');

        $this->exts->execute_javascript('
			var cookieAccept = document.querySelector("#usercentrics-root").shadowRoot.querySelector("button[data-testid=uc-accept-all-button]")
			if(cookieAccept != null) cookieAccept.click();
		');
        sleep(2);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if (!$this->checkLoginSuccess()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->baseUrl);

            sleep(10);
            $this->waitFor('#usercentrics-root');

            $this->exts->execute_javascript('
				var cookieAccept = document.querySelector("#usercentrics-root").shadowRoot.querySelector("button[data-testid=uc-accept-all-button]")
				if(cookieAccept != null) cookieAccept.click();
			');

            if ($this->exts->exists("#usercentrics-root")) {
                $this->exts->executeSafeScript(
                    'document.querySelector("#usercentrics-root").shadowRoot.querySelector(\'div[data-testid="uc-footer"]\').querySelector(\'button[data-testid="uc-accept-all-button"]\').click();'
                );
                sleep(2);
            }

            $this->exts->capture('1-pre-login-page');

            $this->checkFillLogin();

            $this->check_solve_blocked_page();

            if ($this->exts->exists("#usercentrics-root")) {
                $this->exts->executeSafeScript(
                    'document.querySelector("#usercentrics-root").shadowRoot.querySelector(\'div[data-testid="uc-footer"]\').querySelector(\'button[data-testid="uc-accept-all-button"]\').click();'
                );
                sleep(5);
            }

            $this->checkFillTwoFactor();
        }
        $this->doAfterLogin();
    }

    private function checkLoginSuccess()
    {
        $this->waitFor('[aria-label="Logout-icon"]', 45);
        return $this->exts->exists('a[aria-label="Logout-icon"], a .eon-de-react-icon--logout-2, eon-ui-navigation-main-icon-link[icon=logout], eon-ui-website-navigation-main-link[icon="logout"], eon-ui-website-navigation-main-link[data-testid="main-link-/profile"]');
    }

    private function checkFillLogin()
    {
        $this->check_solve_blocked_page();
        $this->waitFor('.login-form__text-input-email:not(.hidden) input#username', 30);
        if ($this->exts->exists('.login-form__text-input-email:not(.hidden) input#username')) {
            $this->exts->capture("2-login-page");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);
            $this->exts->click_by_xdotool('button#login-button');
            sleep(5);
        }

        if ($this->exts->exists('input#text_input')) {
            sleep(3);
            $this->exts->capture("2-login-page");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);
        }

        if ($this->exts->exists('input#pwdTxt, input#password') != null) {
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);
            $this->exts->capture("2-login-page-filled");
            $this->exts->click_by_xdotool('[class*="logon"] button[type="submit"], form[action*="login/"] button[type="submit"]');
            sleep(2);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function checkFillTwoFactor()
    {
        $two_factor_selector = '[id*="MFAPage:pageForm"] #verifyTotpDiv [id*=totpCode-input], input[onkeydown="manageKeydown(this);"]';
        $two_factor_message_selector = '';
        $two_factor_content_selector = 'div[data-test="twofactor-form"] div.message-2fa,document.querySelector("eon-ui-rte-renderer").shadowRoot.querySelector(".eonui-renderer-content.eonui-rte-source-aem p")';
        $two_factor_submit_selector = 'button[onclick*=verifyTotpFunction]';
        $this->waitFor($two_factor_submit_selector, 20);
        if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {

            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->querySelector($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->querySelectorAll($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->querySelectorAll($two_factor_message_selector)[$i]->getText() . "\n";
                }
                $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
                $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            }

            $twoFaMessage = $this->exts->executeSafeScript('document.querySelector("eon-ui-rte-renderer").shadowRoot.querySelector(".eonui-renderer-content.eonui-rte-source-aem p").innerText');

            if ($twoFaMessage != null) {
                $this->exts->two_factor_notif_msg_en = $this->exts->extract($two_factor_content_selector, null, 'content');
                $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
                $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            }

            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }

            $two_factor_code = trim($this->exts->fetchTwoFactorCode());

            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
                $resultCodes = str_split($two_factor_code);
                $code_inputs = $this->exts->getElements($two_factor_selector);

                foreach ($code_inputs as $key => $code_input) {
                    if (array_key_exists($key, $resultCodes)) {
                        $this->exts->log('checkFillTwoFactor: Entering key ' . $resultCodes[$key] . 'to input #');
                        $this->exts->moveToElementAndType($code_input, $resultCodes[$key]);
                    } else {
                        $this->exts->log('checkFillTwoFactor: Have no char for input #');
                    }
                }

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(15);

                if ($this->exts->querySelector($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->exts->notification_uid = '';
                    $this->checkFillTwoFactor();
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }

    private function check_solve_blocked_page()
    {
        sleep(10);
        $this->waitFor('div#turnstile-wrapper', 30);
        $this->exts->capture("blocked-page-checking");
        if ($this->exts->exists('div#turnstile-wrapper')) {
            $this->exts->capture("blocked-by-cloudflare");
            $attempts = 5;
            $delay = 30;

            for ($i = 0; $i < $attempts; $i++) {
                $this->exts->click_by_xdotool('div#turnstile-wrapper', 35, 35);
                sleep($delay);
                if (!$this->exts->exists('div#turnstile-wrapper')) {
                    break;
                }
            }
        }
    }


    function doAfterLogin()
    {
        // then check user logged in or not
        if ($this->checkLoginSuccess()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->waitFor('.advertise-agreement__modal.in .close');
            if ($this->exts->exists('.advertise-agreement__modal.in .close')) {
                $this->exts->moveToElementAndClick('.advertise-agreement__modal.in .close');
            }
            $this->waitFor('eon-ui-modal');
            $this->exts->execute_javascript('
				var modal = document.querySelector("eon-ui-modal").shadowRoot.querySelector(".eonui-icon-closing-x")
				if(modal != null) modal.click();
			');
            $this->exts->capture("3-login-success");
            $this->waitFor('#usercentrics-root');
            $this->exts->execute_javascript('
				var cookieAccept = document.querySelector("#usercentrics-root").shadowRoot.querySelector("button[data-testid=uc-accept-all-button]")
				if(cookieAccept != null) cookieAccept.click();
			');

            if ($this->exts->urlContains('business-portal')) {
                $this->businessInvoicesDownloads();
            } else {
                $this->waitFor('#mein-eon-page-links a[href*="meine-rechnungen"]');
                // Open invoices page, check multi accounts first, then loop all accounts
                $this->exts->moveToElementAndClick('#mein-eon-page-links a[href*="meine-rechnungen"]');
                $this->waitFor('[aria-label="mein-eon-contract"] .crs-information__crs-details', 100);
                $this->waitFor('a[aria-label="change-contract-link"]');
                $this->exts->moveToElementAndClick('a[aria-label="change-contract-link"]');
                sleep(3);
                $this->waitFor('#switchCrs [data-qa="showAll"]');
                $this->exts->moveToElementAndClick('#switchCrs [data-qa="showAll"]');
                sleep(3);
                $this->exts->capture("2-accounts-checking");
                $this->waitFor('#switchCrs .table:not([class*="mobile"]) table tr td:first-child');
                $accounts = $this->exts->getElementsAttribute('#switchCrs .table:not([class*="mobile"]) table tr td:first-child', 'innerText');
                $this->exts->log('ACCOUNTS found: ' . count($accounts));
                $this->exts->moveToElementAndClick('#switchCrs .close');
                if (count($accounts) > 1) {
                    sleep(2);
                    foreach ($accounts as $key => $account) {
                        $account = trim($account);
                        $this->exts->log('SWITCH to account: ' . $account);
                        $this->exts->moveToElementAndClick('a[aria-label="change-contract-link"]');
                        sleep(5);
                        $this->exts->moveToElementAndClick('#switchCrs [data-qa="showAll"]');
                        sleep(5);
                        $this->exts->click_element('//*[@id="switchCrs"]//table//td[1]//*[contains(text(), "' . $account . '")]');
                        sleep(3);
                        if ($this->exts->exists('#switchCrs .close')) {
                            $this->exts->moveToElementAndClick('#switchCrs .close');
                        }

                        if (!$this->exts->urlContains('meine-rechnungen')) {
                            $this->exts->moveToElementAndClick('#mein-eon-page-links a[href*="meine-rechnungen"]');
                        }
                        $this->waitFor('[aria-label="mein-eon-contract"] .crs-information__crs-details', 100);
                        $this->downloadInvoice();
                        if ($this->isNoInvoice) {
                            $this->downloadInvoiceNew();
                            $this->downloadInvoiceLatest();
                        }

                        if ($this->download_all_documents == 1) {
                            $this->exts->moveToElementAndClick('#mein-eon-page-links a[href*="meine-postbox"]');
                            $this->waitFor('[aria-label="mein-eon-contract"] .crs-information__crs-details', 100);
                            $this->downloadMessages();
                        }
                        if ($key % 5 == 0) {
                            // update this to avoid script closed by system
                            $this->exts->update_process_lock();
                        }
                    }
                } else {
                    $this->waitFor('[aria-label="mein-eon-contract"] .crs-information__crs-details', 100);
                    $this->downloadInvoice();
                    if ($this->isNoInvoice) {
                        $this->downloadInvoiceNew();
                        $this->downloadInvoiceLatest();
                    }

                    if ($this->download_all_documents == 1) {
                        $this->exts->moveToElementAndClick('#mein-eon-page-links a[href*="meine-postbox"]');
                        $this->waitFor('[aria-label="mein-eon-contract"] .crs-information__crs-details', 100);
                        $this->downloadMessages();
                    }
                }
            }

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());

            if (
                strpos(strtolower($this->exts->extract('.logon__form-error #errorMsg')), 'passwort sind nicht korrekt') !== false ||
                strpos(strtolower($this->exts->extract('.logon__form-error #errorMsg')), 'bitte geben sie ihre login-daten ein') !== false
            ) {
                $this->exts->loginFailure(1);
            } else if ($this->exts->exists('input[name*="zip-input-"]')) {
                $this->exts->loginFailure(1);
            } else if ($this->exts->exists('div.forgot_password-labeltext, .modal.in input[name*="zip"]')) {
                $this->exts->loginFailure(1);
            } else if ($this->exts->urlContains('/maintenance/fehler.html')) {
                $this->exts->loginFailure(1);
            } else if ($this->exts->urlContains('force.com/_nc_external/identity/sso/ui/AuthorizationError')) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    public function downloadInvoice()
    {
        sleep(5);
        $this->waitFor('table.invoice__table tbody tr');
        $this->exts->log("Begin download invoice");
        $account_number = trim($this->exts->extract('[aria-label="mein-eon-contract"] .crs-information__crs-number'));
        $this->exts->capture('4-List-invoice');
        if ($this->exts->querySelector('table.invoice__table tbody tr') != null) {
            $receipts = $this->exts->querySelectorAll('table.invoice__table tbody tr');
            $invoices = array();
            foreach ($receipts as $receipt) {
                $tags = $this->exts->querySelectorAll('td', $receipt);
                if (count($tags) >= 6 && $this->exts->querySelector('td a.invoice__table__download-icon', $receipt) != null) {
                    $download_button = $this->exts->querySelector('a.invoice__table__download-icon', $receipt);
                    $receiptDate = trim($tags[0]->getAttribute('innerText'));
                    $receiptName = trim($this->exts->extract('td:nth-child(3)', $receipt));
                    $receiptFileName =  !empty($receiptName) ? $receiptName . '.pdf' : '';
                    $parsed_date = $this->exts->parse_date($receiptDate, 'd.m.Y', 'Y-m-d');
                    $receiptAmount = trim($tags[2]->getAttribute('innerText'));
                    $receiptAmount = preg_replace('/[^\d\.\,]/m', '', $receiptAmount) . ' EUR';
                    $this->exts->log("Invoice Date: " . $receiptDate);
                    $this->exts->log("Invoice Name: " . $receiptName);
                    $this->exts->log("Invoice FileName: " . $receiptFileName);
                    $this->exts->log("Invoice parsed_date: " . $parsed_date);
                    $this->exts->log("Invoice Amount: " . $receiptAmount);

                    $this->isNoInvoice = false;
                    // Download invoice if it not exisited
                    if ($this->exts->invoice_exists($receiptName)) {
                        $this->exts->log('Invoice existed ' . $receiptFileName);
                    } else {
                        try {
                            $this->exts->log('Click download button');
                            $download_button->click();
                        } catch (\Exception $exception) {
                            $this->exts->log('Click download button by javascript');
                            $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                        }
                        sleep(25);
                        $this->exts->wait_and_check_download('pdf');
                        $downloaded_file = $this->exts->find_saved_file('pdf', $receiptFileName);

                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            $this->exts->new_invoice($receiptName, $parsed_date, $receiptAmount, $downloaded_file);
                        } else {
                            $this->exts->log(__FUNCTION__ . '::No download ' . $receiptFileName);
                        }
                    }
                }
            }
        }
    }

    public function downloadMessages()
    {
        sleep(5);
        $this->waitFor('table.document-download.postbox-table tbody tr');
        $account_number = trim($this->exts->extract('[aria-label="mein-eon-contract"] .crs-information__crs-number'));
        $receipts = $this->exts->querySelectorAll('table.document-download.postbox-table tbody tr');
        foreach ($receipts as $receipt) {
            $tags = $this->exts->querySelectorAll('td', $receipt);
            if (count($tags) >= 2) {
                $receiptDate = $tags[0]->getText();
                $receiptName = $account_number . str_replace(' ', '', trim($tags[1]->getText()));
                $receiptFileName = !empty($receiptName) ? $receiptName . '.pdf' : '';
                $parsed_date = $this->exts->parse_date($receiptDate, 'd.m.Y', 'Y-m-d');
                if ($parsed_date == null || $parsed_date == "") {
                    $parsed_date = $this->exts->parse_date($receiptDate, 'd/m/Y', 'Y-m-d');
                }
                $this->exts->log("Message Date: " . $receiptDate);
                $this->exts->log("Message Name: " . $receiptName);
                $this->exts->log("Message FileName: " . $receiptFileName);
                $this->exts->log("Message parsed_date: " . $parsed_date);
                $this->isNoInvoice = false;
                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($receiptName)) {
                    $this->exts->log('Invoice existed ' . $receiptFileName);
                } else {
                    try {
                        $this->exts->log('Click download button');
                        $receipt->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click download button by javascript');
                        $this->exts->executeSafeScript("arguments[0].click()", [$receipt]);
                    }
                    sleep(30);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $receiptFileName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($receiptName, $parsed_date, '', $downloaded_file);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $receiptFileName);
                    }
                }
            }
        }
    }

    public function downloadInvoiceNew()
    {
        $this->exts->openUrl('https://www.eon.de/de/meineonda/meine-rechnungen.html');
        sleep(10);
        $this->waitFor('[name="My bill section"] > eon-ui-grid-control [class*=downloadWrapper] eon-ui-link');
        $this->exts->log("Begin download invoice");
        $this->exts->capture('4-List-invoice-new');
        if ($this->exts->querySelector('[name="My bill section"] > eon-ui-grid-control [class*=downloadWrapper] eon-ui-link') != null) {
            $elements = $this->exts->querySelectorAll('[name="My bill section"] login-invoice-app [class*=row]');
            $invoiceName = '';
            if (count($elements) >= 2) {
                $invoiceName = $this->exts->extract('strong', $elements[1], 'innerText');
                $invoiceFileName = !empty($invoiceName)  ? $invoiceName . '.pdf' : '';
                $this->isNoInvoice = false;
                $this->exts->log("InvoiceName: " . $invoiceName);
                $this->exts->log("InvoiceFileName: " . $invoiceFileName);
                $downloaded_file = $this->exts->click_and_download('[name="My bill section"] > eon-ui-grid-control [class*=downloadWrapper] eon-ui-link', 'pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, '', '', $invoiceFileName);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
        }
    }

    public function downloadInvoiceLatest()
    {
        $this->exts->openUrl('https://www.eon.de/de/meineon/meine-rechnungen.html');
        sleep(10);
        $this->exts->log("Begin download invoice");
        $this->exts->capture('4-List-invoice-new');
        $this->waitFor('eon-ui-link[text*="mehr anzeigen"]');
        $this->exts->moveToElementAndClick('eon-ui-link[text*="mehr anzeigen"]');

        sleep(5);
        $this->waitFor('div[class*="wrapper"]:nth-child(4) div[class*="row"] div[class*="col"]:nth-child(4) eon-ui-link[text="Rechnung herunterladen"]');
        if ($this->exts->querySelector('div[class*="wrapper"]:nth-child(4) div[class*="row"] div[class*="col"]:nth-child(4) eon-ui-link[text="Rechnung herunterladen"]') != null) {
            $elements = $this->exts->querySelectorAll('div[class*=wrapper]:nth-child(4) div[class*=row]');
            $invoiceName = '';

            foreach ($elements as $receipt) {


                $tags = $this->exts->querySelectorAll('div[class*="col"]', $receipt);


                $button = $this->exts->querySelector('eon-ui-link[text="Rechnung herunterladen"]', $tags[3]);

                if ($button) {
                    $invoiceName = $this->exts->extract('strong', $tags[2], 'innerText');
                    $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                    $this->isNoInvoice = false;
                    $this->exts->log("InvoiceName: " . $invoiceName);
                    $this->exts->log("InvoiceFileName: " . $invoiceFileName);

                    if ($this->exts->invoice_exists($invoiceName) || $this->exts->document_exists($invoiceFileName)) {
                        $this->exts->log('Invoice existed ' . $invoiceFileName);
                    } else {

                        try {
                            $this->exts->log('Address found, Click it');
                            $button->click();
                        } catch (\Exception $exception) {
                            $this->exts->log('Click  by javascript');
                            $this->exts->executeSafeScript("arguments[0].click()", [$button]);
                        }
                    }

                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, '', '', $invoiceFileName);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }
            }
        }
    }

    public function businessInvoicesDownloads()
    {
        $this->exts->openUrl('https://www.eon.de/business-portal/invoices');
        sleep(10);
        $this->waitFor('div[data-testid="PreviousInvoices-list-item"]', 30);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('div[data-testid="PreviousInvoices-list-item"]');

        foreach ($rows as $row) {
            if ($this->exts->querySelector('eon-ui-link', $row) != null) {

                $invoiceUrl = '';
                $invoiceName = $this->exts->extract('eo-ui-text:nth-child(2)', $row);

                $invoiceAmount = $this->exts->execute_javascript("
					(function(row) {
						var element = row.querySelector('eon-ui-rte-renderer:nth-child(1)');
						if (element && element.shadowRoot) {
							var element2 = element.shadowRoot.querySelector('div');
							if (element2) {
								return element2.innerText;
							}
						}
						return '';
					})(arguments[0]);
				", [$row]);

                $invoiceAmount = !empty($invoiceAmount) ? trim(explode(':', $invoiceAmount)[1]) : '';

                $invoiceDate = $this->exts->execute_javascript("
					(function(row) {
						var element = row.querySelector('eon-ui-rte-renderer:nth-child(2)');
						if (element && element.shadowRoot) {
							var element2 = element.shadowRoot.querySelector('div');
							if (element2) {
								return element2.innerText;
							}
						}
						return '';
					})(arguments[0]);
				", [$row]);

                $invoiceDate = !empty($invoiceDate) ? trim(explode(':', $invoiceDate)[1]) : '';

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl
                ));

                $this->isNoInvoice = false;

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $this->exts->log('invoiceUrl: ' . $invoiceUrl);

                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                $invoiceDate = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoiceDate);

                $this->exts->execute_javascript("
					var anchor = arguments[0].querySelector('eon-ui-link')?.shadowRoot?.querySelector('a') || null;
								
					if (anchor) {
						anchor.click();
					}
				", [$row]);

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
            }
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
    }

    private function waitFor($selector, $seconds = 30)
    {
        for ($i = 1; $i <= $seconds && $this->exts->querySelector($selector) == null; $i++) {
            $this->exts->log('Waiting for Selector (' . $i . '): ' . $selector);
            sleep(1);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP("optimized-chrome-v2", 'E.ON - Deutschland', '2673623', 'aW5mb0BkZW1vcy1pbnRlcm5hdGlvbmFsLmNvbQ==', 'SD8yQ00ueUk4eDMyaSU3Yjk=');
$portal->run();
