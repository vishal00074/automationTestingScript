<?php // migrated updated login and download code

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

    public $baseUrl = 'https://www.swk.de/privatkunden/kundenportal';
    public $loginUrl = 'https://www.swk.de/privatkunden/kundenportal';
    public $invoiceUrl = 'https://www.swk.de/privatkunden/de/kundenportal/postfach?documentType=BILL';
    public $check_login_success_selector = 'a[href*="/abmelden"], div[data-component-name="UserAvatar"]';
    public $submit_btn = "//button[normalize-space(text())='Einloggen']";
    public $username_selector = 'form input[name="username"] , #loginform input[type="text"]';
    public $password_selector = 'form input[name="password"] , #loginform input[type="password"]';
    public $logout_link = '#logout';
    public $restrictPages = 0;
    public $isNoInvoice = true;

    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        $this->exts->openUrl($this->baseUrl);

        sleep(4);
        $this->exts->capture("Home-page-without-cookie");
        $this->exts->clearCookies();
        $isCookieLoginSuccess = false;
        if ($this->exts->loadCookiesFromFile()) {
            sleep(1);
            $this->exts->openUrl($this->baseUrl);
            sleep(10);
            $this->exts->capture("Home-page-with-cookie");
            if ($this->checkLogin()) {
                $isCookieLoginSuccess = true;
            } else {
                $this->exts->clearCookies();
                $this->exts->log("initPortal::cookie is useless now. clear it");
            }
        }


        $this->exts->execute_javascript('
            var shadow = document.querySelector("#usercentrics-root");
            if(shadow){
                shadow.shadowRoot.querySelector(\'button[data-testid="uc-accept-all-button"]\').click();
            }
        ');

        if (!$isCookieLoginSuccess) {
            $this->exts->openUrl($this->loginUrl);
            sleep(2);
            $this->fillForm(0);
            sleep(10);
            $this->exts->execute_javascript('
            var shadow = document.querySelector("#usercentrics-root");
            if(shadow){
                shadow.shadowRoot.querySelector(\'button[data-testid="uc-accept-all-button"]\').click();
            }
        ');
            $this->exts->waitTillPresent("//button[.//span[normalize-space(text())='Kundenportal']]", 15);
            if ($this->exts->exists("//button[.//span[normalize-space(text())='Kundenportal']]")) {
                $this->exts->click_element("//button[.//span[normalize-space(text())='Kundenportal']]");
            }
            sleep(5);

            $this->exts->capture("after-login");
            if ($this->checkLogin()) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $this->exts->capture("LoginSuccess");

                $this->exts->openUrl($this->invoiceUrl);
                sleep(10);
                $this->processInvoices();

                if ($this->isNoInvoice) {
                    $this->exts->no_invoice();
                }

                $this->exts->success();
            } else {
                $this->exts->log(">>>>>>>>>>>>>> after-login check failed!!!!");
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure();
            }
        } else {

            $this->exts->log(">>>>>>>>>>>>>>>Login successful with cookie!!!!");
            $this->exts->capture("LoginSuccess");
            $this->exts->waitTillPresent('a[href*="/postfach?documentType=BILL"]', 15);
            if ($this->exts->exists('a[href*="/postfach?documentType=BILL"]')) {
                $this->exts->click_element('a[href*="/postfach?documentType=BILL"]');
            }
            sleep(10);
            $this->processInvoices();

            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        }
    }
    public function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->exts->capture("pre-fill-login");
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
            }

            if ($this->exts->querySelector($this->password_selector) != null) {
                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
            }
            sleep(5);
            $this->exts->capture("post-fill-login");
            $this->exts->moveToElementAndClick($this->submit_btn);
            sleep(10);
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    public function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            $this->exts->waitTillPresent($this->check_login_success_selector, 20);
            sleep(10);
            if ($this->exts->exists($this->check_login_success_selector)) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }

    private function processInvoices($count = 1)
    {
        $this->exts->waitTillPresent('ol[data-component-name="PostfachDokumenteListe"] li');
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $rows = $this->exts->querySelectorAll('ol[data-component-name="PostfachDokumenteListe"] li');
        foreach ($rows as $row) {
            if ($this->exts->querySelector('div a[href*="pdf"]:not([class*="disabled"])', $row) != null) {
                $invoiceUrl = $this->exts->querySelector('div a[href*="pdf"]:not([class*="disabled"])', $row)->getAttribute("href");
                // $invoiceName = explode('&',
                //   array_pop(explode('frame-', $invoiceUrl))
                // )[0];
                $filename = basename($invoiceUrl);
                $invoiceName = pathinfo($filename, PATHINFO_FILENAME);
                $invoiceDate = "";
                $invoiceAmount = "";

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
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : "";
            $this->exts->openUrl($invoice['invoiceUrl']);
            sleep(5);
            $this->exts->wait_and_check_download('pdf');
            $downloaded_file = $this->exts->find_saved_file('pdf');
            $invoiceFileName = basename($downloaded_file);

            $invoice['invoiceName'] = substr($invoiceFileName, 0, strrpos($invoiceFileName, '.'));

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        $count++;
        $pagiantionSelector = 'nav[data-component-name="ListPagination"] ul li:nth-child(' . $count . ')';
        if ($restrictPages == 0) {
            if ($count < 50 && $this->exts->querySelector($pagiantionSelector) != null) {
                $this->exts->click_by_xdotool($pagiantionSelector);
                sleep(7);

                $this->processInvoices($count);
            }
        } else {
            if ($count < $restrictPages && $this->exts->querySelector($pagiantionSelector) != null) {
                $this->exts->click_by_xdotool($pagiantionSelector);
                sleep(7);

                $this->processInvoices($count);
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
