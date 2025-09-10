<?php //  updated login success selector added custom waitFor function to replace waitTillPresent added code for close popup
// updated download code
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

    // Server-Portal-ID: 8106 - Last modified: 31.03.2025 14:37:09 UTC - User: 1

    public $baseUrl = "https://service.smartmobil.de/";
    public $loginUrl = "https://service.smartmobil.de/";
    public $homePageUrl = "https://service.smartmobil.de/mytariff/invoice/showAll";
    public $username_selector = 'form#loginAction input[name="UserLoginType[alias]"]';
    public $password_selector = 'form#loginAction input[name="UserLoginType[password]"]';
    public $submit_button_selector = '#buttonLogin button.submitOnEnter, a.submitOnEnter[title="Login"]';
    public $login_tryout = 0;
    public $restrictPages = 3;
    public $totalFiles = 0;


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
            sleep(5);
            $this->exts->capture("Home-page-with-cookie");

            if ($this->checkLogin()) {
                $isCookieLoginSuccess = true;
            } else {
                $this->exts->clearCookies();
                $this->exts->openUrl($this->loginUrl);
                sleep(5);
            }
        }
        if ($this->exts->urlContains('mustChangeSecretQuestion')) {
            // This account need add SecretQuestion.
            $this->exts->click_by_xdotool('a.e-navi-link-logout');
            sleep(10);
            $this->exts->click_by_xdotool('a#logoutButton');
        }
        if (!$isCookieLoginSuccess) {
            $this->fillForm(0);

            $this->closePopup();
            $this->exts->capture("after-login-clicked");

            if ($this->checkLogin()) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $this->exts->capture("LoginSuccess");

                $this->processAfterLogin();

                if ($this->totalFiles == 0) {
                    $this->exts->log("No invoice !!! ");
                    $this->exts->no_invoice();
                }
                $this->exts->success();
            } else {
                $this->exts->capture("LoginFailed");

                if (strpos(strtolower($this->exts->extract('.c-form-error-block.error', null, 'innerText')), "nicht korrekt") !== false) {
                    $this->exts->loginFailure(1);
                } else {
                    $this->exts->loginFailure();
                }
            }
        } else {
            $this->closePopup();
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            $this->processAfterLogin();

            if ($this->totalFiles == 0) {
                $this->exts->log("No invoice !!! ");
                $this->exts->no_invoice();
            }
            $this->exts->success();
        }
    }

    public function closePopup()
    {
        $this->waitFor('div.containerPop button');
        if ($this->exts->querySelector('div.containerPop button') != null) {
            $this->exts->moveToElementAndClick('div.containerPop button');
            sleep(7);
        }

        if ($this->exts->querySelector('button[id="consent_wall_optout"]') != null) {
            $this->exts->moveToElementAndClick('button[id="consent_wall_optout"]');
            sleep(7);
        }

        if ($this->exts->querySelector('button#preferences_prompt_submit_all') != null) {
            $this->exts->moveToElementAndClick('button#preferences_prompt_submit_all');
            sleep(2);
        }

        if ($this->exts->querySelector('a.c-overlay-close-icon:nth-child(1)') != null) {
            $this->exts->moveToElementAndClick('a.c-overlay-close-icon:nth-child(1)');
            sleep(2);
        }
    }

    public function fillForm($count)
    {
        $this->exts->waitTillPresent($this->username_selector);
        $this->exts->log("Begin fillForm " . $count);
        try {
            sleep(2);
            if ($this->exts->querySelector($this->username_selector) != null || $this->exts->querySelector($this->password_selector) != null) {
                sleep(2);
                $this->login_tryout = (int) $this->login_tryout + 1;
                $this->exts->capture("1-pre-login");

                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(2);

                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(2);

                $this->exts->click_by_xdotool($this->submit_button_selector);

                $this->exts->waitTillPresent('div.ux-login div.error', 10);
                $err_msg = $this->exts->extract('div.ux-login div.error');
                if ($err_msg != "") {
                    $this->exts->log($err_msg);
                    $this->exts->loginFailure(1);
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    public function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            $this->exts->waitTillAnyPresent(['a#logoutLink', 'span#logoutLink', 'div[id="logoutLink"]']);
            if ($this->exts->querySelector('a#logoutLink, span#logoutLink, div[id="logoutLink"]') != null) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }

    public function waitFor($selector = null)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for login.....');
            sleep(10);
        }
    }

    public function processAfterLogin()
    {
        $this->exts->openUrl('https://service.smartmobil.de/start');
        $this->exts->waitTillAnyPresent(['select#SwitchUserType_currentSubscriberId', 'div.e-tile_navigation a[href="/mytariff/invoice/showAll"]']);

        if ($this->exts->exists('select#SwitchUserType_currentSubscriberId')) {
            $accountOptions = $this->exts->querySelectorAll('select#SwitchUserType_currentSubscriberId option');
            $this->exts->log('Accounts - ' . count($accountOptions));
            if (count($accountOptions) > 0) {
                foreach ($accountOptions as $key => $accountOption) {
                    if (!$this->exts->exists('select#SwitchUserType_currentSubscriberId')) {
                        $this->exts->openUrl('https://service.smartmobil.de/start');
                        sleep(25);
                    }
                    if ($this->exts->urlContains('mustChangeSecretQuestion')) {
                        // This account need add SecretQuestion.
                        $this->exts->click_by_xdotool('a.e-navi-link-logout');
                        sleep(10);
                        $this->exts->click_by_xdotool('a#logoutButton');
                        $this->fillForm(0);
                        sleep(10);
                        continue;
                    }
                    $accountOptions = $this->exts->querySelectorAll('select#SwitchUserType_currentSubscriberId option');
                    $optionValue = $accountOptions[$key]->getAttribute('value');
                    $this->changeSelectbox('select#SwitchUserType_currentSubscriberId', $optionValue);
                    sleep(10);

                    $this->exts->capture('account-changed-' . $optionValue);
                    if (stripos($this->exts->getUrl(), '/mytariff/invoice/') === false) {
                        $this->exts->click_by_xdotool('a[href="/mytariff/invoice/showAll"]');
                    }
                    $this->exts->capture('1-account-changed-' . $optionValue);
                    $this->downloadBills();
                }
            } else {
                $this->downloadBills();
            }
        } else {
            $this->exts->click_element('div.e-tile_navigation a[href="/mytariff/invoice/showAll"]');
            sleep(3);
            $this->downloadBills();
        }
    }

    private function changeSelectbox($select_box = '', $option_value = '')
    {
        $this->exts->waitTillPresent($select_box, 10);
        if ($this->exts->exists($select_box)) {
            $option = $select_box . ' option[value=' . $option_value . ']';
            $this->exts->click_element($select_box);
            sleep(1);
            if ($this->exts->exists($option)) {
                $this->exts->log('Select box Option exists');
                $this->exts->click_by_xdotool($option);
                sleep(3);
            } else {
                $this->exts->log('Select box Option does not exist');
            }
        } else {
            $this->exts->log('Select box does not exist');
        }
    }


    private function downloadBills()
    {
        $this->exts->log("Begin downloadBills");
        $this->exts->waitTillPresent('div[class*="group-wrapper"] div[data-name*="rechnungsjahr"]:not(.hide)');

        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->getElements('div[class*="group-wrapper"] div[data-name*="rechnungsjahr"]:not(.hide)');
        foreach ($rows as $row) {

            try {
                $row->click();
                sleep(5);
            } catch (\Exception $e) {
                $this->exts->log(':: Error In invoice opening:: ' . $e->getMessage());
            }

            if ($this->exts->getElement('p:nth-child(1) a', $row) != null) {
                $invoiceUrl = $this->exts->getElement('p:nth-child(1) a', $row)->getAttribute("href");
                $invoiceName = explode(
                    '&',
                    array_pop(explode('showPDF/', $invoiceUrl))
                )[0];
                $invoiceDate =  $this->exts->extract('summary', $row);
                $cleanedDate = str_replace("Rechnung vom ", "", $invoiceDate);
                $invoiceDate = $cleanedDate;
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
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';

            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
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
