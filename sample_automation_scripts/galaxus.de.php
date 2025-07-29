<?php // handle empty invoice  name updated load more button selector and increase time after open invoiceUrl

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

    // Server-Portal-ID: 170772 - Last modified: 23.07.2025 14:26:14 UTC - User: 1

    // Script here
    public $baseUrl = 'https://www.galaxus.de/';
    public $username_selector = 'form.login-form input#Username, input[name="emailOrUsername"]';
    public $password_selector = 'form.login-form input#Password, input#password';
    public $submit_login_selector = 'form.login-form button[name="login"], button[type="submit"]';

    public $check_login_failed_selector = 'form.login-form p.form-error, [class*="HelpText___hasError"]';

    public $isNoInvoice = true;

    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->baseUrl);

        $accecptAllBtn = 'div[aria-labelledby="cookieBannerTitle"] > div > div > div:nth-child(2) > button:first-child';
        $this->exts->waitTillPresent($accecptAllBtn, 15);
        if ($this->exts->exists($accecptAllBtn)) {
            $this->exts->click_element($accecptAllBtn);
        }

        sleep(1);
        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);

        $this->exts->waitTillPresent($accecptAllBtn, 15);
        if ($this->exts->exists($accecptAllBtn)) {
            $this->exts->click_element($accecptAllBtn);
        }

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if (!$this->isLoggedin()) {
            $this->exts->log('NOT logged via cookie');
            if ($this->exts->queryXpath('//div[@id="usermenu"]//button[contains(text(),"Anmelden") or contains(text(),"Sign in")]') != null) {
                $login_button = $this->exts->queryXpath('//div[@id="usermenu"]//button[contains(text(),"Anmelden") or contains(text(),"Sign in")]');
                try {
                    $this->exts->log(__FUNCTION__ . ' trigger click.');
                    $login_button->click();
                } catch (\Exception $exception) {
                    $this->exts->log(__FUNCTION__ . ' by javascript' . $exception);
                    $this->exts->execute_javascript("arguments[0].click()", [$login_button]);
                }
                sleep(5);
            }

            if ($this->exts->exists('button[data-test="allowAllCookiesButton"]')) {
                $this->exts->moveToElementAndClick('button[data-test="allowAllCookiesButton"]');
                sleep(2);
            }

            $this->checkFillLogin();
            sleep(7);
            $this->checkFillTwoFactor();
            sleep(7);

            if ($this->exts->exists('button[data-test="lightboxCloseButton"]')) {
                $this->exts->moveToElementAndClick('button[data-test="lightboxCloseButton"]');
                sleep(2);
            }

            if ($this->exts->exists('button#toggleCustomerAccountButton')) {
                $this->exts->moveToElementAndClick('button#toggleCustomerAccountButton');
                sleep(5);
            }

            $this->exts->capture("toggleCustomerAccountButton");

            if ($this->exts->urlContains('registration') || $this->exts->exists('input[name="firstName"]')) {
                $this->exts->account_not_ready();
            }
        }

        if ($this->isLoggedin()) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->openUrl('https://www.galaxus.de/order');
            sleep(10);
            if ($this->exts->exists($this->password_selector)) {
                $this->exts->capture('3-required-input-password');
                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                $this->exts->capture("2-login-page-filled");
                $this->exts->moveToElementAndClick($this->submit_login_selector);
                sleep(7);
                $this->checkFillTwoFactor();
                sleep(7);
            }
            $this->processOrders();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
            if ($this->isWrongCredential()) {
                $this->exts->loginFailure(1);
            } else if (stripos($this->exts->extract('[class*="HelpText___hasError"]', null, 'innerText'), 'e-mail-adresse oder gib deinen') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        if ($this->exts->getElement($this->username_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);
            $this->exts->moveToElementAndClick('button[type="submit"]');
            sleep(10);
            if ($this->exts->getElement($this->password_selector) != null && !$this->exts->exists('input[name="firstName"]')) {
                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                $this->exts->capture("2-login-page-filled");
                $this->exts->moveToElementAndClick($this->submit_login_selector);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function isWrongCredential()
    {
        $isWrongCredential = false;
        $error_text = strtolower($this->exts->extract($this->check_login_failed_selector));
        if (
            stripos($error_text, 'anmeldedaten sind nicht korrekt') !== false ||
            stripos($error_text, 'login details are incorrect') !== false ||
            stripos($error_text, 'de connexion sont erron') !== false ||
            stripos($error_text, 'dati per il login sono errati') !== false
        ) {
            $isWrongCredential = true;
        }
        return $isWrongCredential;
    }

    private function isLoggedin()
    {
        sleep(5);
        if ($this->exts->queryXpath('//button[contains(text(),"Abmelden") or contains(text(),"Sign out")]', null) !== null) {
            return true;
        } else {
            return $this->exts->exists('div#usermenu[data-test="loggedIn"]') && !$this->exts->exists($this->password_selector);
        }
    }

    private function checkFillTwoFactor()
    {
        $this->exts->capture("2-2fa-checking");

        if ($this->exts->getElement('input[name="otp-code"],form input#TwoFactorCode, input#OneTimePassword') != null) {
            $two_factor_selector = 'input[name="otp-code"],form input#TwoFactorCode, input#OneTimePassword';
            $two_factor_message_selector = 'main.container__main p.subtitle,h1[data-testid="title"], div[data-testid="content"]';
            $two_factor_submit_selector = 'form button.button.primary';
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
                }
                $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            }
            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            $this->exts->notification_uid = "";
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                // $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(10);

                if ($this->exts->getElement($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->notification_uid = '';
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

    private function processOrders()
    {

        for ($p = 0; $p < 5 && $this->exts->config_array["restrictPages"] == "0"; $p++) {
            $button_loadmore = $this->exts->queryXpath('//button[(contains(text(), "Mehr anzeigen") or contains(text(), "Show more") or contains(text(), "Afficher plus") or contains(text(), "Mostra di")) and not(@disabled)]', null);
            sleep(5);
            // Loadmore button just click by javascript
            if ($button_loadmore != null) {
                $this->exts->log('Click LOADMORE button by javascript');
                try {
                    $this->exts->execute_javascript("arguments[0].click()", [$button_loadmore]);
                } catch (\Exception $exception) {
                    $this->exts->log('Cannot LOADMORE button by javascript');
                }
            } else {
                break;
            }
        }

        $this->exts->capture("4-orders-page");
        $orders = [];
        $rows = $this->exts->getElements('main#pageContent > section > div');
        foreach ($rows as $row) {
            if ($this->exts->getElement('a[href*="/order"]', $row) != null) {
                $order_page_Url = $this->exts->getElement('a[href*="/order"]', $row)->getAttribute("href");
                $invoiceName = explode(
                    '&',
                    array_pop(explode('order/', $order_page_Url))
                )[0];
                $orderUrl = 'https://www.galaxus.de/orderdocuments/pdfproofofpurchase/' . $invoiceName;

                $invoiceDate = $this->exts->extract('header h2', $row);
                $invoiceDate = trim(end(explode(' ', $invoiceDate)));
                $invoiceAmount = $this->exts->extract('span[class*="totalPrice"]', $row);
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $invoiceAmount)) . ' EUR';

                array_push($orders, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'orderUrl' => $orderUrl
                ));
                $this->isNoInvoice = false;
            }
        }

        // Download all invoices
        $this->exts->log('orderUrl found: ' . count($orders));
        foreach ($orders as $order) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $order['invoiceName']);
            $this->exts->log('invoiceDate: ' . $order['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $order['invoiceAmount']);
            $this->exts->log('orderUrl: ' . $order['orderUrl']);

            $invoiceFileName = !empty($order['invoiceName']) ? $order['invoiceName'] . '.pdf': '';
            $order['invoiceDate'] = $this->exts->parse_date($order['invoiceDate'], 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $order['invoiceDate']);

            $downloaded_file = $this->exts->direct_download($order['orderUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($order['invoiceName'], $order['invoiceDate'], $order['invoiceAmount'], $invoiceFileName);
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
