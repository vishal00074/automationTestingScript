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

    // Server-Portal-ID: 26981 - Last modified: 18.03.2025 13:48:01 UTC - User: 1
    public $baseUrl = 'https://pro.free.fr/account/#/';
    public $loginUrl = 'https://pro.free.fr/espace-client/connexion/#/';
    public $invoicePageUrl = 'https://pro.free.fr/account/#/billing';

    public $username_selector = 'div.login_form input[placeholder*="e-mail"]';
    public $password_selector = 'div.login_form input[type="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'div.login_form button[type="submit"]';

    public $check_login_failed_selector = 'article.notification.is-danger';
    public $check_login_success_selector = '.account-link button.is-logout';

    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->baseUrl);
        sleep(1);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture('1-init-page');

        // accecpt cookies
        $this->exts->waitTillPresent('div.cookiesMgmt button.is-primary');
        $acceptBtn = $this->exts->querySelector('div.cookiesMgmt button.is-primary');
        if ($acceptBtn != null) {
            $this->exts->log('Click accept cookie...');
            $this->exts->execute_javascript("arguments[0].click();", [$acceptBtn]);
            sleep(5);
        }

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(10);
            $this->checkFillLogin();
            sleep(10);

            // accecpt cookies
            $this->exts->waitTillPresent('div.cookiesMgmt button.is-primary');
            $acceptBtn = $this->exts->querySelector('div.cookiesMgmt button.is-primary');
            if ($acceptBtn != null) {
                $this->exts->log('Click accept cookie...');
                $this->exts->execute_javascript("arguments[0].click();", [$acceptBtn]);
                sleep(5);
            }


            $isLoginError = $this->exts->execute_javascript('document.body.innerHTML.includes("Vous avez saisi un identifiant ou un mot de passe invalide. Veuillez les ressaisir.")');
            $this->exts->log('isLoginError:: '. $isLoginError);
            if ($isLoginError) {
                
                $this->exts->log(__FUNCTION__ . '::Use login failed');
                $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());

                $this->exts->loginFailure(1);
            }

            $this->exts->type_key_by_xdotool('Tab');
            sleep(1);
            $this->exts->type_key_by_xdotool('Return');
            sleep(5);
            $this->exts->openUrl($this->baseUrl);
            sleep(10);
        }

        // then check user logged in or not
        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // accecpt cookies
            $this->exts->waitTillPresent('div.cookiesMgmt button.is-primary');
            $acceptBtn = $this->exts->querySelector('div.cookiesMgmt button.is-primary');
            if ($acceptBtn != null) {
                $this->exts->log('Click accept cookie...');
                $this->exts->execute_javascript("arguments[0].click();", [$acceptBtn]);
                sleep(5);
            }

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');

            $isLoginError = $this->exts->execute_javascript('document.body.innerHTML.includes("Vous avez saisi un identifiant ou un mot de passe invalide. Veuillez les ressaisir.")');

            if (strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'ous avez saisi un identifiant ou un mot de passe invalide') !== false) {
                $this->exts->loginFailure(1);
            } else if ($isLoginError) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        if ($this->exts->getElement($this->password_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            if ($this->remember_me_selector != '')
                $this->exts->moveToElementAndClick($this->remember_me_selector);
            sleep(2);

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function processInvoices()
    {
        sleep(25);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->getElements('table > tbody > tr');
        foreach ($rows as $row) {
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 6 && $this->exts->getElement('a[href*="/invoice"]:not([href*="csv"]):not([href*="mobile"])', $tags[5]) != null) {
                $invoiceUrl = $this->exts->getElement('a[href*="/invoice"]:not([href*="csv"]):not([href*="mobile"])', $tags[5])->getAttribute("href");
                $invoiceName = trim($tags[0]->getAttribute('innerText'));
                $invoiceDate = trim($tags[1]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[3]->getAttribute('innerText'))) . ' EUR';

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
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd/n/Y', 'Y-m-d');
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

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
