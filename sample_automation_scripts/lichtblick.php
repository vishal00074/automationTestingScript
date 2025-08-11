<?php // update waittillpresent to waitFor and added the not empty condition on invoiceName .pdf

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

    // Server-Portal-ID: 25202 - Last modified: 24.07.2025 13:33:12 UTC - User: 1

    public $baseUrl = 'https://www.lichtblick.de/';
    public $loginUrl = 'https://www.lichtblick.de/';
    public $invoicePageUrl = 'https://mein.lichtblick.de/Privatkunden/Vertraege/Rechnungen';

    public $username_selector = 'input#Benutzername,input[name="email"],form[action="/Konto/Login"] input#Benutzername, input[id="email"]';
    public $password_selector = 'input[name="Passwort"],form[action="/Konto/Login"] input#Passwort, input#password';
    public $remember_me_selector = '';
    public $submit_login_selector = 'form[action="/Konto/Login"] button[type="submit"], div.m-login__action button, button#process-submit, button[form="localAccountForm"][type="submit"], form button';

    public $check_login_failed_selector = 'form[action="/Konto/Login"] div.alert-danger[style*="display: block"], div.m-login__action div.errors, form#localAccountForm div.error:not([style*="display: none"])';
    public $check_login_success_selector = 'button[data-testid="sub-nav-logout"],a[href*="Logout"],div#m_ver_menu';


    public $isNoInvoice = true;
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->baseUrl);
        sleep(1);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->clearChrome();
        $this->exts->openUrl($this->baseUrl);
        sleep(5);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->querySelector($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->openUrl($this->loginUrl);

            $this->waitFor('#uc-btn-accept-banner', 5);

            if ($this->exts->exists('#uc-btn-accept-banner')) {
                $this->exts->moveToElementAndClick('#uc-btn-accept-banner');
                sleep(2);
            }

            $this->waitFor('[id*=usercentrics-cmp-ui]', 5);

            if ($this->exts->exists("[id*=usercentrics-cmp-ui]")) {
                $this->exts->execute_javascript(
                    'document.querySelector("[id*=usercentrics-cmp-ui]").shadowRoot.querySelector(\'footer[data-testid="uc-footer"]\').querySelector(\'button[id="accept"]\').click();'
                );
            }
            $this->waitFor("a[href*='/konto/']", 5);
            if ($this->exts->exists("a[href*='/konto/']")) {
                $this->exts->click_element("a[href*='/konto/']");
            }
            $this->checkFillLogin();
            sleep(3);
            if (stripos(strtolower($this->exts->extract("div[class*='error itemLevel'][aria-hidden=false]")), 'valid') !== false) {
                $this->exts->openUrl('https://mein.lichtblick.de/Konto/Login');
                $this->checkFillLogin();
            }
        }
        sleep(15);
        $this->exts->waitTillAnyPresent(explode(',', $this->check_login_success_selector), 10);
        if ($this->exts->getElement($this->check_login_success_selector) != null && $this->exts->getElement($this->username_selector) == null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            $this->invoicePage();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $error_message = $this->exts->extract('[role="alert"].alert-danger', null, 'innerHTML');
            if ($error_message != null && (strpos($error_message, 'dieser benutzer ist gesperrt') != false
                || strpos($error_message, 'blocked') != false)) {
                $this->exts->account_not_ready();
            }
            $this->exts->log($this->exts->extract($this->check_login_failed_selector));
            if (strpos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'hast du die richtige e-mail-adresse und das richtige passwort') !== false || strpos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'Es gab ein Problem beim Abruf der Kundendaten. Bitte versuche es später erneut') !== false) {
                $this->exts->loginFailure(1);
            } elseif ($this->exts->getElementByText('div.validation-summary-errors', ['Das Feld "Benutzername" ist erforderlich'], null, false) != null) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        $this->waitFor($this->username_selector, 5);
        if ($this->exts->getElement($this->username_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);

            if ($this->exts->exists($this->submit_login_selector) && !$this->exts->exists($this->password_selector)) {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
            }

            $this->waitFor($this->password_selector, 5);

            $this->exts->log("Enter Password");
            if ($this->exts->exists($this->password_selector)) {
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
            }
            sleep(2);
            if ($this->remember_me_selector != '') {
                $this->exts->moveToElementAndClick($this->remember_me_selector);
                sleep(1);
            }

            $this->exts->capture("2-login-page-filled");
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
            }
            sleep(1);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function clearChrome()
    {
        $this->exts->log("Clearing browser history, cookie, cache");
        $this->exts->openUrl('chrome://settings/clearBrowserData');
        sleep(10);
        $this->exts->capture("clear-page");
        for ($i = 0; $i < 2; $i++) {
            $this->exts->type_key_by_xdotool('Tab');
        }
        $this->exts->type_key_by_xdotool('Tab');
        $this->exts->type_key_by_xdotool('Return');
        $this->exts->type_key_by_xdotool('a');
        sleep(1);
        $this->exts->type_key_by_xdotool('Return');
        sleep(3);
        $this->exts->capture("clear-page");
        for ($i = 0; $i < 5; $i++) {
            $this->exts->type_key_by_xdotool('Tab');
        }
        $this->exts->type_key_by_xdotool('Return');
        sleep(15);
        $this->exts->capture("after-clear");
    }

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }

    private function invoicePage()
    {
        $this->exts->log(__FUNCTION__);

        // private customer
        if (!$this->exts->urlContains('Geschaeftskunden')) {

            $this->exts->openUrl('https://www.lichtblick.de/konto/posteingang/?filter=rechnungen');
            $this->waitFor('a[href*="posteingang"]', 10);

            if ($this->exts->exists('[id*=usercentrics-cmp-ui]')) {
                $this->exts->execute_javascript("var shadow = document.querySelector('[id*=usercentrics-cmp-ui]').shadowRoot; shadow.querySelector('button[data-testid=\"uc-accept-all-button\"]').click();");
                sleep(2);
            }

            $this->processPrivateCustomerInvoice();
            sleep(5);
            $this->exts->switchToInitTab();
            $this->exts->closeAllTabsButThis();
            sleep(5);
        } else {
            // business customer
            $this->exts->openUrl('https://mein.lichtblick.de/Geschaeftskunden/Rechnungen');
            $this->waitFor('input#Benutzername', 5);

            if ($this->exts->exists('input#Benutzername')) {
                $this->exts->moveToElementAndType('input#Benutzername', $this->username);
                sleep(2);
                $this->exts->moveToElementAndClick($this->submit_login_selector);
                sleep(5);
                if ($this->exts->exists('[id*=usercentrics-cmp-ui]')) {
                    $this->exts->execute_javascript("var shadow = document.querySelector('[id*=usercentrics-cmp-ui]').shadowRoot; shadow.querySelector('button[data-testid=\"uc-accept-all-button\"]').click();");
                    sleep(2);
                }
                if ($this->exts->exists('button[onclick="ShowButtonLoadingAnimation(this)"]')) {
                    $this->exts->moveToElementAndClick('button[onclick="ShowButtonLoadingAnimation(this)"]');
                }
            }
            $this->provessBusinessCustomerInvoice();
            sleep(3);
        }
    }

    private function processPrivateCustomerInvoice()
    {
        $this->waitFor('section ul li', 10);
        $this->exts->log(__FUNCTION__);
        $this->exts->capture('4-private-customer-invoices');

        $invoices = [];
        $rows = $this->exts->querySelectorAll('section ul li');
        if ($this->exts->exists('section ul li')) {
            for ($i = 0; $i < count($rows); $i++) {
                $row = $this->exts->querySelectorAll('section ul li')[$i];
                $download_button = $this->exts->querySelector('button', $row);

                if ($this->exts->querySelector('p', $row) != null) {
                    $tags = explode('|', trim($this->exts->extract('p', $row, 'innerText')));
                    $invoiceDate = $tags[0];
                    $invoiceName = explode(': ', $tags[1]);
                    $invoiceName = end($invoiceName);
                    $invoiceAmount = '';
                } else {
                    $invoiceDate = $this->exts->extract('div[data-testid="message-item_box"] >div:nth-child(1) > span:nth-child(1)', $row);
                    $tags = explode('|', trim($this->exts->extract('div[data-testid="message-item_box"] >div:nth-child(1) > span:nth-child(3)', $row)));
                    $invoiceName = trim(str_replace('Vertrag:', '', $tags[0]));
                    $invoiceAmount = '';
                }


                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount
                ));
                $this->isNoInvoice = false;
            }

            // Download all invoices
            $this->exts->log('Invoices found: ' . count($invoices));
            foreach ($invoices as $invoice) {
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
                $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
                $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);

                $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
                $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . "_" . $invoice['invoiceDate'] . '.pdf' : '';
                $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

                try {
                    $this->exts->log('Click download button');
                    $download_button->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click download button by javascript');
                    $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
                }
                sleep(5);
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
        }
    }

    private function provessBusinessCustomerInvoice()
    {
        $this->waitFor('table#gk_rechnungen_table > tbody > tr', 10);
        $this->exts->log(__FUNCTION__);
        $this->exts->capture('4-Business-customer-invoices');

        if ($this->exts->exists('table#gk_rechnungen_table > tbody > tr')) {
            // sort invoice by date desc
            $theads = $this->exts->querySelectorAll('table#gk_rechnungen_table > thead > tr > th');
            for ($i = 0; $i < count($theads); $i++) {
                if (
                    strpos(strtolower($theads[$i]->getAttribute('innerText')), 'date') !== false
                    || strpos(strtolower($theads[$i]->getAttribute('innerText')), 'datum') !== false
                ) {
                    $this->exts->log($theads[$i]->getAttribute('class'));
                    while (strpos($theads[$i]->getAttribute('class'), 'sorting_desc') !== false) {
                        $this->exts->log($theads[$i]->getAttribute('class'));
                        $theads[$i]->click();
                        sleep(3);
                    }
                    break;
                }
            }
            if (isset($this->exts->config_array["restrictPages"]) && (int)$this->exts->config_array["restrictPages"] == 0) {
                // set maximum (defaulted) 100 invoices per page if full_download (restrictPages)
                //$this->exts->changeSelectbox('select[name*="gk_rechnungen_table_length"]', 100, 5);
                $this->exts->click_by_xdotool('select[name*="gk_rechnungen_table_length"]');   // Click to open the dropdown
                sleep(1);
                $this->exts->type_key_by_xdotool('Down');
                $this->exts->type_key_by_xdotool('Down');
                $this->exts->type_key_by_xdotool('Return');
                sleep(2);
            }
            sleep(3);

            $invoices = [];
            $rows = $this->exts->querySelectorAll('table#gk_rechnungen_table > tbody > tr');
            foreach ($rows as $row) {
                if ($this->exts->getElement('td > a[href*="/RechnungDownload?guid="]', $row) != null) {
                    // https://mein.lichtblick.de/Geschaeftskunden/RechnungDownload?guid=a1de7277-74a3-4400-bc2c-9ba563a7390d
                    $invoiceUrl = $this->exts->querySelector('td > a[href*="/RechnungDownload?guid="]', $row)->getAttribute("href");
                    $invoiceName = end(explode('/RechnungDownload?guid=', $invoiceUrl));
                    $invoiceDate = '';
                    $invoiceAmount = '';

                    array_push($invoices, array(
                        'invoiceName' => $invoiceName,
                        'invoiceDate' => $invoiceDate,
                        'invoiceAmount' => $invoiceAmount,
                        'invoiceUrl' => $invoiceUrl
                    ));
                    $this->isNoInvoice = false;
                }
            }

            // Download all invoices
            $this->exts->log('Invoices found: ' . count($invoices));
            foreach ($invoices as $invoice) {
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
                $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
                $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
                $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

                $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
                $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

                $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
