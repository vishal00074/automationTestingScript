<?php // updated login url and success selector and download code.

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

    // Server-Portal-ID: 94981 - Last modified: 07.07.2025 14:49:18 UTC - User: 1

    public $baseUrl = 'https://www.dazn.com/en-DE/myaccount';
    public $loginUrl = 'https://www.dazn.com/en-DE/myaccount';
    public $invoicePageUrl = 'https://www.dazn.com/en-DE/myaccount/payment-history';

    public $username_selector = '.emailFieldSec input, input[name="email"]';
    public $password_selector = '.pwdFieldSec input#idEmailPwd, input[name="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button[data-test-id="refined-button-signin"]';

    public $check_login_failed_selector = 'form[action*="/mylogin"] #myAlert a, div[class*="errorCode"], div[data-test-id="error-message-EMAIL-is-email"], div[data-test-id="PASSWORD_ERROR_MESSAGE"]';
    public $check_login_success_selector = 'div[class*="signOutContainer"]';

    public $isNoInvoice = true;
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->disable_unexpected_extensions();
        $this->exts->openUrl($this->baseUrl);
        sleep(5);
        if ($this->exts->getElement('//div[contains(text(),"ERR_CERT_COMMON_NAME_INVALID")]', null, 'xpath') != null) {
            $this->exts->moveToElementAndClick('button#details-button');
            sleep(1);
            $this->exts->moveToElementAndClick('a#proceed-link');
            sleep(15);
        }
        $this->check_solve_blocked_page();
        sleep(5);
        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(10);
        $this->checkAndLogin();
    }

    private function disable_unexpected_extensions()
    {
        $this->exts->openUrl('chrome://extensions/?id=cjpalhdlnbpafiamejdnhcphjbkeiagm'); // disable Block origin extension
        sleep(2);
        $this->exts->execute_javascript("
    		if(document.querySelector('extensions-manager') != null) {
			    if(document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view')  != null){
			        var disable_button = document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view').shadowRoot.querySelector('#enableToggle[checked]');
			        if(disable_button != null){
			            disable_button.click();
			        }
			    }
			}
    	");
        sleep(1);
        $this->exts->openUrl('chrome://extensions/?id=ifibfemgeogfhoebkmokieepdoobkbpo');
        sleep(1);
        $this->exts->execute_javascript("if (document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view').shadowRoot.querySelector('#enableToggle[checked]') != null) {
			    document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view').shadowRoot.querySelector('#enableToggle[checked]').click();
			}");
        sleep(2);
    }

    private function check_solve_blocked_page()
    {
        $this->exts->capture("blocked-page-checking");
        if ($this->exts->getElement('iframe[src*="challenges.cloudflare.com"]') != null) {
            $this->exts->capture("blocked-by-cloudflare");
            $this->exts->click_by_xdotool('iframe[src*="challenges.cloudflare.com"]', 30, 28, true);
            sleep(20);
            if ($this->exts->getElement('iframe[src*="challenges.cloudflare.com"]') != null) {
                $this->exts->click_by_xdotool('iframe[src*="challenges.cloudflare.com"]', 30, 28, true);
                sleep(25);
            }
            if ($this->exts->getElement('iframe[src*="challenges.cloudflare.com"]') != null) {
                $this->exts->click_by_xdotool('iframe[src*="challenges.cloudflare.com"]', 30, 28, true);
                sleep(25);
            }
            if ($this->exts->getElement('iframe[src*="challenges.cloudflare.com"]') != null) {
                $this->exts->click_by_xdotool('iframe[src*="challenges.cloudflare.com"]', 30, 28, true);
                sleep(25);
            }
        }
    }

    private function checkAndLogin()
    {
        sleep(5);
        if ($this->exts->getElement('button#onetrust-accept-btn-handler') != null) {
            $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
            sleep(2);
        }
        $this->exts->capture('1-init-page');
        $this->check_solve_blocked_page();
        // If user hase not logged in from cookie, open the login url and wait for login form
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            sleep(2);
            if ($this->exts->getElement('button#onetrust-accept-btn-handler') != null) {
                $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
                sleep(2);
            }
            sleep(7);
            $this->exts->capture('after-clear-cookies');
            $this->exts->openUrl($this->loginUrl);
            sleep(25);

            if ($this->exts->getElement('button#onetrust-accept-btn-handler') != null) {
                $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
                sleep(8);
            }
            $this->checkFillLogin();
            sleep(8);
        }

        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log('User logged in');
            $this->exts->capture("3-login-success");

            $this->exts->openUrl($this->invoicePageUrl);
            sleep(5);
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log('Timeout waitForLogin');
            if ($this->exts->urlContains('account/payment-plan') && $this->exts->getElement('[data-test-id="select-subscription__page"] [data-test-id="signUpStepsIndicator__step"]') != null) {
                $this->exts->account_not_ready();
            } else if ($this->exts->getElement($this->check_login_failed_selector) != null) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    public function checkFillLogin()
    {
        $this->exts->capture("login-page");
        if ($this->exts->getElement($this->username_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(3);
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(10);
            $this->exts->capture("2-username-filled");
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            if ($this->exts->getElement($this->remember_me_selector) != null)
                $this->exts->moveToElementAndClick($this->remember_me_selector);
            sleep(3);

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(15);
        } else {
            $this->exts->log('Login page not found');
            $this->exts->loginFailure();
        }
    }
    public $totalInvoices = 0;

    private function processInvoices()
    {
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        sleep(15);
        $rows_len = count($this->exts->getElements('table[class*="PaymentHistory___payments"] > tbody > tr[data-test-id="paymentHistory-paymentLine-row"]'));
        for ($i = 0; $i < $rows_len; $i++) {
            if ($this->totalInvoices >= 50) {
                return;
            }

            $row = $this->exts->getElements('table[class*="PaymentHistory___payments"] > tbody > tr[data-test-id="paymentHistory-paymentLine-row"]')[$i];
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 6 && $this->exts->getElement('button[data-test-id*="download-invoice"]', $row) != null) {
                $download_button = $this->exts->getElement('button[data-test-id*="download-invoice"]', $row);
                $invoiceName = '';
                $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[4]->getAttribute('innerText'))) . ' EUR';

                $this->isNoInvoice = false;

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);

                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                $date_parse = $this->exts->parse_date($invoiceDate, 'F d, Y', 'Y-m-d');
                if ($date_parse == '') {
                    $date_parse = $this->exts->parse_date($invoiceDate, 'd/m/Y', 'Y-m-d');
                }
                $this->exts->log('Date parsed: ' . $date_parse);

                try {
                    $this->exts->log('Click download button');
                    $download_button->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click download button by javascript');
                    $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
                }
                sleep(5);
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf');

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $invoiceFileName = basename($downloaded_file);
                    $invoiceName = explode('.pdf', $invoiceFileName)[0];
                    $invoiceName = explode('(', $invoiceName)[0];
                    $invoiceName = str_replace(' ', '', $invoiceName);
                    $this->exts->log('Final invoice name: ' . $invoiceName);
                    $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                    @rename($downloaded_file, $this->exts->config_array['download_folder'] . $invoiceFileName);

                    if ($this->exts->invoice_exists($invoiceName)) {
                        $this->exts->log('Invoice existed ' . $invoiceFileName);
                    } else {
                        $this->totalInvoices++;
                        $this->exts->new_invoice($invoiceName, $date_parse, $invoiceAmount, $invoiceFileName);
                        sleep(1);
                    }
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceName);
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
