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
    // Server-Portal-ID: 678190 - Last modified: 20.09.2024 12:31:53 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = 'https://app.agicap.com/';
    public $loginUrl = 'https://app.agicap.com/app/account/login';
    public $invoicePageUrl = 'https://app.agicap.com/app/user/recuPaiement';

    public $username_selector = 'form input[data-test="login-input-email"]';
    public $password_selector = 'form input[data-test="login-input-password"]';
    public $submit_login_selector = 'form button[data-test="login-submit"]';

    public $check_login_failed_selector = 'div.toast-container.toast-bottom-right div.alert-toaster div.content:nth-child(02)  > p,.toast-error .description';
    public $check_login_success_selector = 'a[href*="/paid"]';

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


            if ($this->exts->querySelector('app-general-menu.general-menu a') != null) {
                $this->exts->moveToElementAndClick('app-general-menu.general-menu a');
                sleep(7);
            }

            if ($this->exts->querySelector('button[data-test-name="Subscription and invoicing"]') != null) {
                $this->exts->moveToElementAndClick('button[data-test-name="Subscription and invoicing"]');
                sleep(7);
            }

            $this->processInvoices();


            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
            if (
                strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'die von ihnen eingegebenen login-daten sind inkorrekt') !== false
                || strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'entered login information is incorrect') !== false
                || strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'user sso forced') !== false
            ) {
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

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(1);
            $this->exts->waitTillPresent('div.toast-error div.toast-message div, p.toast-message');
            if (
                stripos(strtolower($this->exts->extract('div.toast-error div.toast-message div, p.toast-message')), 'login-daten sind inkorrekt') !== false ||
                stripos(strtolower($this->exts->extract('div.toast-error div.toast-message div, p.toast-message')), 'login information is incorrect') !== false
                || stripos(strtolower($this->exts->extract('div.toast-error div.toast-message div, p.toast-message')), 'login information you entered is incorrect') !== false

            ) {
                $this->exts->log(__FUNCTION__ . "::>>>>>>>>>>>>>>>Found login failed screen!!!! ");
                $this->exts->loginFailure(1);
                sleep(5);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'div[data-test="twofactor-form"] input[data-test*="Digit"]';
        $two_factor_message_selector = 'div[data-test="twofactor-form"] div.message-2fa';
        $two_factor_submit_selector = 'div[data-test="twofactor-form"] button[data-test="signin-verify-2fa-button"]';

        if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getText() . "\n";
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
                $resultCodes = str_split($two_factor_code);
                $code_inputs = $this->exts->getElements($two_factor_selector);
                foreach ($code_inputs as $key => $code_input) {
                    if (array_key_exists($key, $resultCodes)) {
                        $this->exts->log(__FUNCTION__ . ': Entering apple key ' . $resultCodes[$key] . 'to input #' . $code_input->getAttribute('id'));

                        $this->exts->moveToElementAndType($code_input, $resultCodes[$key]);
                        $this->exts->capture("2.2-apple-two-factor-filled-" . $this->exts->two_factor_attempts);
                    } else {
                        $this->exts->log(__FUNCTION__ . ': Have no char for input #' . $code_input->getAttribute('id'));
                    }
                }


                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(15);

                if ($this->exts->getElement($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->exts->notification_uid = '';
                    $this->checkFillTwoFactor();
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        } else if ($this->exts->exists('app-two-factor-code input[id*="mat-input"]')) {
            $two_factor_selector = 'app-two-factor-code input[id*="mat-input"]';
            $two_factor_message_selector = 'mya-login-2fa header > div';
            $two_factor_submit_selector = 'button[data-test="signin-verify-2fa-button"]';

            if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
                $this->exts->log("Two factor page found.");
                $this->exts->capture("2.1-two-factor");

                if ($this->exts->getElement($two_factor_message_selector) != null) {
                    $this->exts->two_factor_notif_msg_en = "";
                    for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                        $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getText() . "\n";
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
                    $resultCodes = str_split($two_factor_code);
                    $code_inpsuts = $this->exts->getElements($two_factor_selector);

                    foreach ($code_inputs as $key => $code_input) {
                        if (array_key_exists($key, $resultCodes)) {
                            $this->exts->log(__FUNCTION__ . ': Entering apple key ' . $resultCodes[$key] . 'to input #' . $code_input->getAttribute('id'));

                            $this->exts->moveToElementAndType($code_input, $resultCodes[$key]);
                            $this->exts->capture("2.2-apple-two-factor-filled-" . $this->exts->two_factor_attempts);
                        } else {
                            $this->exts->log(__FUNCTION__ . ': Have no char for input #' . $code_input->getAttribute('id'));
                        }
                    }

                    $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                    sleep(3);
                    $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                    $this->exts->moveToElementAndClick($two_factor_submit_selector);
                    sleep(15);

                    if ($this->exts->getElement($two_factor_selector) == null) {
                        $this->exts->log("Two factor solved");
                    } else if ($this->exts->two_factor_attempts < 3) {
                        $this->exts->two_factor_attempts++;
                        $this->exts->notification_uid = '';
                        $this->checkFillTwoFactor();
                    } else {
                        $this->exts->log("Two factor can not solved");
                    }
                } else {
                    $this->exts->log("Not received two factor code");
                }
            }
        } else {
            $two_factor_selector = 'mya-two-factor-code input[data-test*="Digit"]';
            $two_factor_message_selector = 'mya-login-2fa header > div';
            $two_factor_submit_selector = 'button[data-test="signin-verify-2fa-button"]';

            if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
                $this->exts->log("Two factor page found.");
                $this->exts->capture("2.1-two-factor");

                if ($this->exts->getElement($two_factor_message_selector) != null) {
                    $this->exts->two_factor_notif_msg_en = "";
                    for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                        $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getText() . "\n";
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
                    $resultCodes = str_split($two_factor_code);
                    $code_inputs = $this->exts->getElements($two_factor_selector);
                    foreach ($code_inputs as $key => $code_input) {
                        if (array_key_exists($key, $resultCodes)) {
                            $this->exts->log(__FUNCTION__ . ': Entering apple key ' . $resultCodes[$key] . 'to input #' . $code_input->getAttribute('id'));

                            $this->exts->moveToElementAndType($code_input, $resultCodes[$key]);
                            $this->exts->capture("2.2-apple-two-factor-filled-" . $this->exts->two_factor_attempts);
                        } else {
                            $this->exts->log(__FUNCTION__ . ': Have no char for input #' . $code_input->getAttribute('id'));
                        }
                    }

                    $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                    sleep(3);
                    $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                    $this->exts->moveToElementAndClick($two_factor_submit_selector);
                    sleep(15);

                    if ($this->exts->getElement($two_factor_selector) == null) {
                        $this->exts->log("Two factor solved");
                    } else if ($this->exts->two_factor_attempts < 3) {
                        $this->exts->two_factor_attempts++;
                        $this->exts->notification_uid = '';
                        $this->checkFillTwoFactor();
                    } else {
                        $this->exts->log("Two factor can not solved");
                    }
                } else {
                    $this->exts->log("Not received two factor code");
                }
            }
        }
    }

    private function processInvoices($count = 0)
    {
        $this->exts->waitTillpresent('mat-table mat-row');
        $this->exts->capture("4-invoices-page");

        $rows = $this->exts->getElements('mat-table mat-row');
        foreach ($rows as $row) {
            $invoiceBtn = $this->exts->getElement('button[class="mdc-icon-button mat-mdc-icon-button mat-accent mat-mdc-button-base"]', $row);
            if ($invoiceBtn != null) {
                $invoiceUrl = '';
                $invoiceName = '';
                $invoiceDate = $this->exts->extract('mat-cell:nth-child(1) span:nth-child(2)', $row);
                $invoiceAmount = $this->exts->extract('mat-cell:nth-child(5)', $row);
                $this->isNoInvoice = false;

                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $invoiceFileName = '';

                try {
                    $invoiceBtn->click();
                } catch (\Exception $exception) {
                    $this->exts->execute_javascript('arguments[0].click();', [$invoiceBtn]);
                }
                sleep(5);
                $this->exts->wait_and_check_download('pdf');

                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $invoiceName = basename($downloaded_file, '.pdf');

                    $this->exts->log('invoiceName: ' . $invoiceName);

                    $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $downloaded_file);
                    sleep(5);
                } else {
                    $this->exts->log('Timeout when download ');
                }
            }
        }

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;


        $pagiantionSelector = 'mat-paginator button.mat-mdc-paginator-navigation-next';
        if ($restrictPages == 0) {
            if ($count < 50 && $this->exts->querySelector($pagiantionSelector) != null) {
                $this->exts->click_by_xdotool($pagiantionSelector);
                sleep(7);
                $count++;
                $this->processInvoices($count);
            }
        } else {
            if ($count < $restrictPages && $this->exts->querySelector($pagiantionSelector) != null) {
                $this->exts->click_by_xdotool($pagiantionSelector);
                sleep(7);
                $count++;
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
