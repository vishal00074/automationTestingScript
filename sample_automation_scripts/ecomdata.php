<?php // migrated

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

    // Server-Portal-ID: 508958 - Last modified: 28.08.2024 14:48:30 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = 'https://my.ecomdata.de/clientarea.php';
    public $loginUrl = 'https://my.ecomdata.de/index.php?rp=/login';
    public $invoicePageUrl = 'https://my.ecomdata.de/clientarea.php?action=invoices';

    public $username_selector = 'form.login-form input#inputEmail';
    public $password_selector = 'form.login-form input#inputPassword';
    public $remember_me_selector = 'form.login-form input[name="rememberme"]';
    public $submit_login_selector = 'form.login-form input[type="submit"]';

    public $check_login_failed_selector = 'div.logincontainer div.alert-danger';
    public $check_login_success_selector = 'li.primary-action a[href*="/logout"]';

    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->disable_unexpected_extensions();
        $this->exts->openUrl($this->baseUrl);
        sleep(1);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            // $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            $this->checkFillLogin();
            sleep(20);
            $this->checkFillTwoFactor();
        }

        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(15);
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
            if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'login-daten falsch') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function disable_unexpected_extensions()
    {
        $this->exts->openUrl('chrome://extensions/?id=cjpalhdlnbpafiamejdnhcphjbkeiagm'); // disable Block origin extension
        sleep(2);
        $this->exts->executeSafeScript("
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
        $this->exts->executeSafeScript("if (document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view').shadowRoot.querySelector('#enableToggle[checked]') != null) {
		    document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view').shadowRoot.querySelector('#enableToggle[checked]').click();
		}");
        sleep(2);
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

            if ($this->remember_me_selector != '') {
                $this->exts->moveToElementAndClick($this->remember_me_selector);
            }
            sleep(2);

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);

            if (!$this->isEmailValid($this->username)) {
                $this->exts->log(__FUNCTION__ . '::Email not valid');
                $this->exts->loginFailure(1);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function isEmailValid($email)
    {
        // Check if the email has a valid format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        return true;
    }

    private function checkFillTwoFactor()
    {
        $two_factor_selector = '#frmTwoFactorChallenge input[name="key"]';
        $two_factor_message_selector = '//div[contains(text(), "Faktor")]';
        $two_factor_submit_selector = '#frmTwoFactorChallenge input#btnLogin';

        if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->getElement($two_factor_message_selector, null, 'xpath') != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector, null, 'xpath')); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector, null, 'xpath')[$i]->getText() . "\n";
                }
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
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(15);

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

    private function processInvoices($paging_count = 1)
    {
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $rows = $this->exts->getElements('table > tbody > tr');
        foreach ($rows as $row) {
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 6 && $this->exts->getElement('a[href*="viewinvoice"]', $tags[5]) != null) {
                $invoiceUrl = $this->exts->getElement('a[href*="viewinvoice"]', $tags[5])->getAttribute("href");
                $invoiceName = explode(
                    '/',
                    array_pop(explode('id=', $invoiceUrl))
                )[0];
                $invoiceUrl = 'https://my.ecomdata.de/dl.php?type=i&id=' . $invoiceName;
                $invoiceDate = trim($this->getInnerTextByJS($tags[2]));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->getInnerTextByJS($tags[3]))) . ' EUR';

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

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf': '';
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
        $paging_count++;
        if (
            $this->exts->config_array["restrictPages"] == '0' &&
            $paging_count < 50 &&
            $this->exts->getElement('li#tableInvoicesList_next:not(.disabled) a') != null
        ) {
            $this->exts->moveToElementAndClick('li#tableInvoicesList_next:not(.disabled) a');
            sleep(5);
            $this->processInvoices($paging_count);
        }
    }

    function getInnerTextByJS($selector_or_object, $parent = null)
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
            return $this->exts->executeSafeScript("return arguments[0].innerText", [$element]);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
