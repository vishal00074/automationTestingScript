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

    // Server-Portal-ID: 1172646 - Last modified: 04.03.2025 13:46:41 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = 'https://www.grover.com/business-de/for-business';
    public $loginUrl = 'https://www.grover.com/de-de/auth';
    public $paymentPageUrl = 'https://www.grover.com/business-de/your-payments?status=PAID';

    public $username_selector = 'input[name="email"]';
    public $password_selector = 'input[name="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button[type="submit"], form button.eUjiwK, form button[class*="clickable"]';

    public $check_login_success_selector = 'div[data-testid="header-dashboard-links"] a:nth-child(3), div[data-testid="account-menu-button"], a[href*="your-profile"]';

    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        // Load cookies
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->baseUrl);
        $this->exts->waitTillPresent('.snackbar-enter-done button[role="button"]');
        if ($this->exts->exists('.snackbar-enter-done button[role="button"]')) {
            $this->exts->click_by_xdotool('.snackbar-enter-done button[role="button"]');
            sleep(1);
        }
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElementByText($this->check_login_success_selector, ['Konto', 'Account'], null, true) == null || $this->exts->exists($this->check_login_success_selector)) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            $this->exts->waitTillPresent('svg > path[clip-rule="evenodd"][fill="#333333"]');
            if ($this->exts->exists('svg > path[clip-rule="evenodd"][fill="#333333"]')) {
                $this->exts->click_by_xdotool('svg > path[clip-rule="evenodd"][fill="#333333"]');
                sleep(3);
            }

            if ($this->exts->exists('div[data-testid="country_redirection_close_button"]')) {
                $this->exts->click_by_xdotool('div[data-testid="country_redirection_close_button"]');
                sleep(3);
            }
            sleep(10);
            if ($this->exts->exists('.snackbar-enter-done button[role="button"]')) {
                $this->exts->click_by_xdotool('.snackbar-enter-done button[role="button"]');
                sleep(1);
            }

            if ($this->exts->exists('div[data-testid="snackbar"] > div >div > button')) {
                $this->exts->click_by_xdotool('div[data-testid="snackbar"] > div >div > button');
                sleep(3);
            }
            sleep(10);
            if ($this->exts->exists('div[data-testid="country_redirection_close_button"]')) {
                $this->exts->click_by_xdotool('div[data-testid="country_redirection_close_button"]');
                sleep(3);
            }

            if ($this->exts->exists('div.CountryRedirectionContent .bMOKHR')) {
                $this->exts->click_by_xdotool('div.CountryRedirectionContent .bMOKHR');
                sleep(1);
            }
            $this->checkFillLogin();
            $this->checkFillTwoFactor();

            if (strpos($this->exts->getUrl(), '/auth') !== false && $this->exts->exists('div.step-content-enter-done')) {
                $mes_check_login = '';
                $mes_els = $this->exts->getElements('div.step-content-enter-done');
                foreach ($mes_els as $mes_el) {
                    $mes_check_login .= $mes_el->getAttribute('innerText');
                }
                $mes_check_login = strtolower($mes_check_login);
                $this->exts->log($mes_check_login);
                if (strpos($mes_check_login, 'passwort zur') !== false && strpos($mes_check_login, 'wie du dein neues passwort einrichten willst') !== false) {
                    $this->exts->account_not_ready();
                }
            }
            $this->exts->capture('1-afterlogin-page');
            if ($this->exts->getElement('div.CountryRedirectionContent') != null) {
                $this->exts->log(__FUNCTION__ . '::redirect to base');
                $this->exts->openUrl($this->baseUrl);
                sleep(15);
            }
            if ($this->exts->exists('button[data-testid="country_redirection_confirm_button"]')) {
                $this->exts->click_by_xdotool('button[data-testid="country_redirection_confirm_button"]');
                sleep(15);
            }
            $this->exts->waitTillPresent($this->check_login_success_selector);
        }
        // then check user logged in or not
        if ($this->exts->getElementByText($this->check_login_success_selector, ['Konto', 'Account'], null, true) != null || $this->exts->exists($this->check_login_success_selector)) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            if ($this->exts->exists('.snackbar-enter-done button[role="button"]')) {
                $this->exts->click_by_xdotool('.snackbar-enter-done button[role="button"]');
                sleep(1);
            }

            // Open and download payment
            $this->exts->openUrl($this->paymentPageUrl);
            $this->processPayments();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if ($this->exts->exists('div[id*="AUTH_FLOW"]')) {
                $this->exts->account_not_ready();
            }
            if (strpos($this->exts->extract('form'), 'Passwor') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }


    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'label[name="twoFactorAuthCode"] input';
        $two_factor_message_selector = 'form h5 > font, form div[dir="auto"] > font, form h5';
        //$two_factor_submit_selector = 'form button.btn-primary';
        $this->exts->waitTillPresent($two_factor_selector, 20);
        if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getAttribute("innerText") . "\n";
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

                //$this->exts->click_by_xdotool($two_factor_submit_selector);
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

    private function checkFillLogin()
    {
        $this->exts->waitTillPresent($this->password_selector);
        if ($this->exts->getElement($this->password_selector) != null) {
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            if ($this->remember_me_selector != '')
                $this->exts->click_by_xdotool($this->remember_me_selector);
            sleep(2);

            $this->exts->capture("2-login-page-filled");
            // $this->exts->click_by_xdotool($this->submit_login_selector);
            $tab_buttons = $this->exts->getElements('form button');
            $this->exts->log('Finding Completted trips button...');
            foreach ($tab_buttons as $key => $tab_button) {
                $tab_name = trim($tab_button->getAttribute('innerText'));
                if (stripos($tab_name, 'Einloggen') !== false) {
                    $this->exts->log('Completted trips button found');
                    try {
                        $this->exts->log('Click button');
                        $tab_button->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click button by javascript');
                        $this->exts->execute_javascript("arguments[0].click()", [$tab_button]);
                    }
                    break;
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }


    private function processPayments()
    {
        $this->exts->waitTillPresent('div > div[data-testid="your-payments-payment-card"]');
        $this->exts->capture("4-invoices-classic");

        // Scroll According to restrictPages count 
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        $this->exts->log("restrictPages count: " . $restrictPages);

        for ($i = 0; $i < $restrictPages; $i++) {
            $this->exts->executeSafeScript('window.scrollBy(0, 1000);');
            sleep(5);
        }

        $this->exts->capture("invoices-list");
        $this->exts->executeSafeScript("window.scrollTo({ top: 0, behavior: 'smooth' });");
        sleep(7);
        $rows = $this->exts->getElements('div > div[data-testid="your-payments-payment-card"]');
        foreach ($rows as $key => $row) {
            $downloadBtn = $this->exts->getElement('button', $row);
            if ($downloadBtn != null) {
                $invoiceUrl = '';
                $invoiceName = $this->exts->extract('div.flex:nth-child(1) span:nth-child(2)', $row);
                $invoiceDate = $this->exts->extract('div:nth-child(2) > span', $row);
                $invoiceAmount = $this->exts->extract('div:nth-child(4) > span:nth-child(1)', $row);
                $parse_date = $this->exts->parse_date($invoiceDate, 'd/m/Y', 'Y-m-d');

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $parse_date);
                $this->exts->log('invoiceAmount: ' .  $invoiceAmount);
                $this->exts->log('invoiceUrl: ' . $invoiceUrl);

                try {
                    $downloadBtn->click();
                } catch (\Exception $exception) {
                    $this->exts->log(__FUNCTION__ . ' by javascript' . $exception);
                    $this->exts->execute_javascript('arguments[0].click();', [$downloadBtn]);
                }
                sleep(10);

                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf');
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $invoiceFileName = basename($downloaded_file);
                    $invoiceName = trim(array_pop(explode('#', explode('.pdf', $invoiceFileName)[0])));
                    $invoiceName = trim(array_pop(explode('(', explode(')', $invoiceName)[0])));
                    $this->exts->log('Final invoice name: ' . $invoiceName);
                    $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                    @rename($downloaded_file, $this->exts->config_array['download_folder'] . $invoiceFileName);

                    if ($this->exts->invoice_exists($invoiceName)) {
                        $this->exts->log('Invoice existed ' . $invoiceFileName);
                    } else {
                        $this->isNoInvoice = false;
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                        sleep(1);
                    }
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ');
                }
            }
        }
    }

    private function processPaymentsOld()
    {
        $this->exts->waitTillPresent('div[data-testid="your-payments-payment-card"]', 30);
        //error500-page-light
        if ($this->exts->getElement('div[data-testid="error500-page-light"] button[type="button"]') != null) {
            $this->exts->click_by_xdotool('div[data-testid="error500-page-light"] button[type="button"]');
            $this->exts->waitTillPresent('div[data-testid="your-payments-payment-card"]', 30);
        }
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $currentPageHeight = 0;
        $this->exts->log('Trying to scroll to bottom');
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        $max_scroll_count = $restrictPages == 0 ? 30 : $restrictPages;
        $scroll_count = 1;
        $this->exts->update_process_lock();
        while ($currentPageHeight != $this->exts->getElement('body')->getAttribute("scrollHeight") && $scroll_count <= $max_scroll_count) {
            $currentPageHeight = $this->exts->getElement('body')->getAttribute("scrollHeight");
            $this->exts->execute_javascript('window.scrollTo(0,document.body.scrollHeight);');
            sleep(10);
            $scroll_count++;
        }
        $this->exts->update_process_lock();
        $this->exts->capture("4.1-invoices-page");

        $rows = $this->exts->getElements('div[data-testid="your-payments-payment-card"]');
        $this->exts->log('count row-' . $rows);
        foreach ($rows as $row) {
            // $row->getLocationOnScreenOnceScrolledIntoView();
            sleep(2);
            try {
                if ($this->exts->getElementByText('div', ['Bezahlt', 'Paid'], $row, false) !== null) {
                    $invoiceDate = '';
                    $invoiceAmount = '';
                    try {

                        // $this->exts->log('Row outerHTML: ' . $row->getAttribute('outerHTML'));
                        sleep(8);
                        $this->exts->execute_javascript("arguments[0].click();", [$row]);

                        // $isClickable = $this->exts->execute_javascript('return arguments[0].offsetParent !== null && getComputedStyle(arguments[0]).getPropertyValue("pointer-events") === "auto";',  [$row]);

                        // if ($isClickable) {
                        //     $this->exts->log('click row inside');
                        //     $row->click();
                        // }




                    } catch (\Exception  $exception) {
                        $this->exts->execute_javascript("arguments[0].click()", [$row]);
                    }

                    sleep(2);
                    if ($this->exts->exists('.modalOverlay a[href*="pdf"]')) {
                        $invoiceLink = $this->exts->getElement('.modalOverlay a[href*="pdf"]');
                        if ($invoiceLink != null) {
                            $invoiceUrl = $invoiceLink->getAttribute("href");
                            $this->exts->log('invoiceUrl: ' . $invoiceUrl);
                            $urlParts = explode('invoices/', $invoiceUrl);
                            $invoiceName = explode('/pdf', array_pop($urlParts))[0];
                            array_push($invoices, array(
                                'invoiceName' => $invoiceName,
                                'invoiceDate' => $invoiceDate,
                                'invoiceAmount' => $invoiceAmount,
                                'invoiceUrl' => $invoiceUrl
                            ));
                            $this->isNoInvoice = false;
                        }
                    }

                    $this->exts->log('Invoice Name: ' . $invoiceName);
                    $this->exts->execute_javascript('document.elementFromPoint(0,0).click();');
                    sleep(2);
                    $this->exts->update_process_lock();
                }
            } catch (\StaleElementReferenceException $e) {
                echo "An error occurred: " . $e->getMessage();
            }
        }

        $this->exts->update_process_lock();

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd. F Y', 'Y-m-d');
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
