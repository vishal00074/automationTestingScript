<?php // udpated login button selector false $this->isNoInvoice value in case invoiceses found 

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

    // Server-Portal-ID: 77911 - Last modified: 23.07.2025 08:56:28 UTC - User: 1

    public $baseUrl = 'https://www.fonic.de/';
    public $loginUrl = 'https://mein.fonic.de/login';
    public $invoicePageUrl = 'https://www.fonic.de/selfcare/gespraechsuebersicht';

    public $username_selector = 'form[name=authForm] one-input';
    public $password_selector = 'form[name=authForm] one-input[type=password]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button[name="loginButton"]';

    public $check_login_failed_selector = 'div.alert.alert--error';
    public $check_login_success_selector = 'use[href*="logout"]';

    public $isNoInvoice = true;
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);

        $this->exts->execute_javascript('
			var shadow = document.querySelector("#usercentrics-root").shadowRoot;
			var button = shadow.querySelector(\'button[data-testid="uc-accept-all-button"]\')
			if(button){
				button.click();
			}
		');
         sleep(4);
        $this->exts->capture_by_chromedevtool('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if (!$this->checkLoggedIn()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->moveToElementAndClick('one-nav-bar-item[href*="auth/uebersicht"]:not(.hidden)');
            sleep(10);
            $this->checkFillLogin();
            sleep(15);
            if ($this->exts->exists('div.change_password')) {
                $this->exts->account_not_ready();
            }
        }

        // then check user logged in or not
        if ($this->checkLoggedIn()) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            $this->exts->waitTillPresent('a[href="/selfcare/gespraechsuebersicht"]');
            $this->exts->moveToElementAndClick('a[href="/selfcare/gespraechsuebersicht"]');

            $this->processInvoices();

            $this->exts->openUrl('https://www.fonic.de/mein-fonic/auth2/kostenuebersicht/#w=internal&p=flow');
            sleep(5);
            $this->processInvoicesNew();
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if (stripos($this->exts->extract($this->check_login_failed_selector, null, 'innerText'), 'Kennwort ist nicht korrekt.') !== false) {
                $this->exts->loginFailure(1);
            } elseif ($this->exts->exists('div.change_password')) {
                $this->exts->account_not_ready();
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        if ($this->exts->getElement($this->username_selector) != null) {
            $this->exts->log("Enter Username");
            $this->exts->click_by_xdotool($this->username_selector, 30, 40);
            sleep(1);
            $this->exts->type_text_by_xdotool($this->username);
            sleep(2);
            $this->exts->click_by_xdotool('form[name=authForm] one-button');
            sleep(5);
            $this->exts->log("Enter Password");
            $this->exts->click_by_xdotool($this->password_selector, 30, 40);
            sleep(1);
            $this->exts->type_text_by_xdotool($this->password);
            sleep(2);
            $this->exts->click_by_xdotool('form[name=authForm] one-button[data-type="main-action"]');
            sleep(2);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function checkLoggedIn()
    {
        $isLoggedIn = false;
        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            $isLoggedIn = true;
        }
        if ($this->exts->getElementByText('one-stack[class="full-width-on-mobile"]', ['Ausloggen', 'Log out'], null, false) != null) {
            $isLoggedIn = true;
        }

        return $isLoggedIn;
    }

    private function processInvoices()
    {
        $this->exts->waitTillPresent('div.selfcare__usage-invoices', 30);
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $rows = count($this->exts->querySelectorAll('div.selfcare__usage-invoices'));
        for ($i = 0; $i < $rows; $i++) {
            $row = $this->exts->querySelectorAll('div.selfcare__usage-invoices')[$i];
            $tags = $this->exts->querySelectorAll('div', $row);
            if (count($tags) >= 4 && $this->exts->querySelector('div.invoice-icon', $row) != null) {
                $this->isNoInvoice = false;
                $download_button = $this->exts->querySelector('div.invoice-icon', $row);
                $invoiceName = trim($tags[1]->getAttribute('innerText'));
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                $invoiceDate = trim(array_pop(explode('am', $tags[2]->getAttribute('innerText'))));
                $invoiceAmount = '';

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $parsed_date = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $parsed_date);

                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
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
                        $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }
            } else if (count($tags) >= 3 && $this->exts->querySelector('div.invoice__number', $row) != null) {
                $this->isNoInvoice = false;
                $download_button = $this->exts->querySelector('div.invoice__number', $row);
                $invoiceName = trim($tags[1]->getAttribute('innerText'));
                $invoiceFileName = $invoiceName . '.pdf';
                $invoiceDate = trim(array_pop(explode('am', $tags[2]->getAttribute('innerText'))));
                $invoiceAmount = '';

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $parsed_date = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $parsed_date);

                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
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
                        $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }
            }
        }
    }

    private function processInvoicesNew()
    {
        sleep(5);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $months = $this->exts->execute_javascript('
	    	var ele = document.querySelector("one-layout one-select").shadowRoot.querySelectorAll("select option")
			var arr=[]
			for(let i =0; i<12 && i<ele.length;i++){
			    arr.push(ele[i].value)
			}
			arr
	    ');
        $this->exts->log('----count: ' . count($months));

        if (is_array($months) && count($months) > 0) {
            foreach ($months as $key => $month) {
                $this->exts->log($month);
                $this->exts->moveToElementAndClick('one-layout one-select');
                sleep(2);
                $this->exts->type_key_by_xdotool("Home");
                sleep(2);
                for ($i = 0; $i < $key; $i++)
                    $this->exts->type_key_by_xdotool("Down");
                sleep(2);
                $this->exts->type_key_by_xdotool("Return");
                sleep(5);
                $this->exts->moveToElementAndClick('#mfe-cost-overview one-button-group>one-button');
                sleep(3);
                $invoiceName = $month;
                $invoiceDate = $month;
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceFileName: ' . $invoiceFileName);

                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $invoiceDate, "", $invoiceFileName);
                    sleep(1);
                    $this->isNoInvoice = false;
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");


$portal = new PortalScriptCDP("optimized-chrome-v2", 'Fonic', '2673336', 'MDE3Njk2NDk1Njg1', 'SnVsaWFIYW5rYTg0ODcjMTlmbw==');
$portal->run();
