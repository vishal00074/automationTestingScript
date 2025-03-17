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

    // Server-Portal-ID: 7336 - Last modified: 03.09.2024 11:56:31 UTC - User: 15

    /*Define constants used in script*/
    public $user_group = '';
    public $baseUrl = 'https://www.eurowings.com/';
    public $invoiceUrl = 'https://www.eurowings.com/de/ihre-vorteile/my-eurowings/my-eurowings/myflights.html';

    public $username_selector = 'form[action*="/login"] input#username, input[name="username"]';
    public $password_selector = 'form[action*="/login"] input#password, input[name="password"]';
    public $submit_login_selector = 'form[action*="/login"] button[class*="form__submit-button"], button[type="submit"]';

    public $check_login_failed_selector = 'form[action*="/login"] p[class*="o-notification__text--error"], p[class*="o-notification__text--error"]';
    public $check_login_success_selector = 'li.o-myew-header-sidenavigation__item a[href*="/myflights"]';

    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->baseUrl);

        $this->exts->send_websocket_event(
            $this->exts->current_context->webSocketDebuggerUrl,
            "Network.setUserAgentOverride",
            '',
            ["userAgent" => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36"]
        );
        // Load cookies
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->baseUrl);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        $this->exts->waitTillPresent($this->check_login_success_selector);
        if ($this->exts->querySelector($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->waitTillPresent('button[class*="cookie-consent--cta-accept"]');
            if ($this->exts->exists('button[class*="cookie-consent--cta-accept"]')) {
                $this->exts->click_by_xdotool('button[class*="cookie-consent--cta-accept"]');
                sleep(2);
            }
            $this->exts->click_by_xdotool('nav[aria-labelledby="header-navigation-meta"] a#header-nav-item-layer-account, button#navigation-login-desktop');

            $this->checkFillLogin();
            $this->exts->waitTillPresent($this->check_login_failed_selector, 30);
            $error_text = strtolower($this->exts->extract($this->check_login_failed_selector));
            if (
                stripos($error_text, 'falscher username oder passwort') !== false ||
                stripos($error_text, 'wrong username or password') !== false ||
                stripos($error_text, 'utilisateur ou mot de passe') !== false ||
                stripos($error_text, 'available for private customers') !== false ||
                stripos($error_text, 'nur für Privatkunden möglich') !== false
            ) {

                $this->exts->loginFailure(1);
            }
            $this->exts->capture('1-after-login-page');
            $this->exts->openUrl($this->invoiceUrl);

        }

        $this->exts->waitTillPresent($this->check_login_success_selector);
        if ($this->exts->querySelector($this->check_login_success_selector) != null) {
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open booking page and get receipt
            $this->exts->openUrl($this->invoiceUrl);
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());

            $error_text = strtolower($this->exts->extract($this->check_login_failed_selector));
            if (
                stripos($error_text, 'falscher username oder passwort') !== false ||
                stripos($error_text, 'wrong username or password') !== false ||
                stripos($error_text, 'utilisateur ou mot de passe') !== false ||
                stripos($error_text, 'available for private customers') !== false ||
                stripos($error_text, 'nur für privatkunden möglich') !== false
            ) {

                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        $this->exts->waitTillPresent($this->password_selector);
        if (isset($this->exts->config_array["user_group"])) {
            $this->user_group = trim($this->exts->config_array["user_group"]);
        } else if (isset($this->exts->config_array["usergroup"])) {
            $this->user_group = trim($this->exts->config_array["usergroup"]);
        }

        $this->exts->log('User Group: ' . $this->user_group);

        if (stripos($this->user_group, "TRAVEL_AGENCY") !== false) {
            $this->exts->click_by_xdotool('#usergroup-select, select[name="userGroupDropdown"]');
            sleep(2);
            $this->exts->click_by_xdotool('#usergroup-select option[value="TRAVEL_AGENCY"], select[name="userGroupDropdown"] option[value="TRAVEL_AGENCY"], [name="usergroup"] option[value="TRAVEL_AGENCY"]');
            sleep(2);
        } else if (stripos($this->user_group, "CORPORATE") !== false) {
            $this->exts->click_by_xdotool('select[name="userGroupDropdown"]');
            sleep(2);
            $this->exts->click_by_xdotool('#usergroup-select option[value="CORPORATE"], select[name="userGroupDropdown"] option[value="TRAVEL_AGENCY"], [name="usergroup"] option[value="CORPORATE"]');
            sleep(2);
        }

        if ($this->exts->querySelector($this->password_selector) != null) {
            $this->exts->capture("2-login-page");
            $this->exts->click_by_xdotool('select[name="usergroup"]');
            sleep(2);
            $this->exts->click_by_xdotool('#usergroup-select option[value="CUSTOMER"], select[name="userGroupDropdown"] option[value="CUSTOMER"], [name="usergroup"] option[value="CUSTOMER"]');
            sleep(2);
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            $this->exts->capture("2-login-page-filled");
            $this->exts->click_by_xdotool($this->submit_login_selector);
            sleep(15);
            if (stripos($this->exts->extract('p.o-notification__text'), 'Login is only available for') !== false) {
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(1);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(2);

                $this->exts->capture("2-login-page-filled");
                $this->exts->click_by_xdotool($this->submit_login_selector);
            }

            for ($i = 0; $i < 10; $i++) {
                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->click_by_xdotool($this->submit_login_selector);
                    sleep(10);
                } else {
                    break;
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function processInvoices()
    {
        $this->exts->waitTillPresent('div.o-box div.o-grid');
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('div.o-box div.o-grid');
        foreach ($rows as $row) {
            $booking_detail_link = $this->exts->querySelector('a[href*="/meine-reise/edititinerary"]', $row);
            if ($booking_detail_link != null) {
                $invoiceUrl = $booking_detail_link->getAttribute("href");
                $invoiceName = $this->exts->querySelector('div[class*="bookingCode"]', $row)->getAttribute('innerText');
                $invoiceName = end(explode(':', $invoiceName));
                $invoiceDate = '';
                $invoiceAmount = '';

                $this->exts->log('invoiceName: ' . $invoiceName);

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
            $invoiceFileName = $invoice['invoiceName'] . '.pdf';
            $this->exts->openUrl($invoice['invoiceUrl']);
            sleep(10);

            //Click Buchungsdetails
            $this->exts->click_by_xdotool('[id*="buchungs-highlighted"]');
            sleep(2);
            $this->exts->click_by_xdotool('div#billing-highlighted');
            sleep(2);
            $details_buttons = $this->exts->getElements('//*[contains(@class, "a-textlink")]/span[contains(text(),"Details anzeigen") or contains(text(),"Show details") or contains(text(),"Afficher les") or contains(text(),"Mostra dettagli")]', null, 'xpath');
            foreach ($details_buttons as $details_button) {
                $this->exts->click_element($details_button);
                sleep(2);
            }
            $this->exts->executeSafeScript('document.body.innerHTML = document.querySelector(".m-myew-billing-payment-details__content").innerHTML;');

            $downloaded_file = $this->exts->download_current($invoiceFileName, 3);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();