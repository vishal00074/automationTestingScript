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

    // Server-Portal-ID: 27348 - Last modified: 02.04.2025 14:19:07 UTC - User: 1

    public $baseUrl = 'https://particuliers.engie.fr/espace-client/prive/mes-factures.html';

    public $username_selector = 'input[name="email"]';
    public $password_selector = 'input[type="password"]';
    public $remember_me_selector = 'input[type="checkbox"]:not(:checked) + label';
    public $submit_login_selector = 'form.k-oktaConnexion__form button.k-cta--primary';
    public $check_login_success_selector = 'button[data-e-modal-open="headerCel2User"], .authentification-liste-ec .c-businessProfile';

    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {

        $this->username = 'ESPACEPHONELVP@GMAIL.COM';
        $this->password = 'Liora1701!';

        $this->exts->log('Begin initPortal ' . $count);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);

        $this->exts->waitTillPresent('#popin_tc_privacy button.u-theme-bg', 10);
        if ($this->exts->exists('#popin_tc_privacy button.u-theme-bg')) {
            $this->exts->moveToElementAndClick('#popin_tc_privacy button.u-theme-bg');
            sleep(1);
        }
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->checkFillLogin();
        }

        $this->exts->waitTillPresent('button.k-cardBtn.k-cardBtn--rootTag', 10);
        if ($this->exts->getElement('button.k-cardBtn.k-cardBtn--rootTag') != null) {
            $this->exts->moveToElementAndClick('div:nth-child(1) > button.k-cardBtn.k-cardBtn--rootTag');

            $this->exts->waitTillPresent('button.k-cta--genBlue.k-cta--primary', 5);
            if ($this->exts->getElement('button.k-cta--genBlue.k-cta--primary') != null) {
                $this->exts->moveToElementAndClick('button.k-cta--genBlue.k-cta--primary');
                sleep(10);
            }
        }

        $this->exts->waitTillPresent('input.k-securityCodeField__field', 5);
        if ($this->exts->exists('input.k-securityCodeField__field')) {
            $this->checkFillTwoFactor();
        }

        // then check user logged in or not
        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');

            if ($this->exts->exists('#popin_tc_privacy button.u-theme-bg')) {
                $this->exts->moveToElementAndClick('#popin_tc_privacy button.u-theme-bg');
                sleep(1);
            }

            if ($this->exts->exists('.optly-modal.fade-in .optly-modal-close')) {
                $this->exts->moveToElementAndClick('.optly-modal.fade-in .optly-modal-close');
                sleep(1);
            }

            $this->exts->capture("3-login-success");
            if ($this->exts->urlContains('/espace-client/authentification.html')) {
                $addresses = $this->exts->getElementsAttribute('.authentification-liste-ec .c-businessProfile .list-cc-item[data-cc]', 'data-cc');
                foreach ($addresses as $address_id) {
                    $this->exts->openUrl('https://particuliers.engie.fr/espace-client/authentification.html');
                    sleep(10);
                    $this->exts->moveToElementAndClick('.authentification-liste-ec .c-businessProfile .list-cc-item[data-cc="' . $address_id . '"]');
                    sleep(10);

                    if ($this->exts->exists('.optly-modal.fade-in .optly-modal-close')) {
                        $this->exts->moveToElementAndClick('.optly-modal.fade-in .optly-modal-close');
                        sleep(1);
                    }
                    $this->exts->capture("Adress: $address_id");

                    if ($this->exts->exists('[data-testid="header-rubriques"] a[href*="/mes-factures.html"]')) {
                        $this->exts->moveToElementAndClick('[data-testid="header-rubriques"] a[href*="/mes-factures.html"]');
                        $this->download_invoices($address_id);
                    } else if ($this->exts->exists('#siteNav a[href*="espace-client/factures-"]')) {
                        $this->exts->moveToElementAndClick('#siteNav a[href*="espace-client/factures-"]');
                        $this->download_mirror_invoice();
                    }
                }
            } else {
                if ($this->exts->exists('[data-testid="header-rubriques"] a[href*="/mes-factures.html"]')) {
                    $this->exts->moveToElementAndClick('[data-testid="header-rubriques"] a[href*="/mes-factures.html"]');
                    $this->download_invoices();
                } else if ($this->exts->exists('#siteNav a[href*="espace-client/factures-"]')) {
                    $this->exts->moveToElementAndClick('#siteNav a[href*="espace-client/factures-"]');
                    $this->download_mirror_invoice();
                }
            }

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
            if (strpos(strtolower($this->exts->extract('p.k-oktaConnexion__globalError', null, 'innerText')), 'ou le mot de passe sont incorrects') !== false) {
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

            $this->exts->log("Enter Password");
            $this->exts->click_by_xdotool($this->password_selector);
            sleep(2);
            $this->exts->type_text_by_xdotool($this->password);
            // $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);


            if ($this->remember_me_selector != '')
                $this->exts->moveToElementAndClick($this->remember_me_selector);
            sleep(2);

            $this->exts->capture("2-login-page-filled");
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->click_by_xdotool($this->submit_login_selector);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'input.k-securityCodeField__field';
        $two_factor_message_selector = 'div.k-infoBlock3__content p';
        $two_factor_submit_selector = 'button.k-cta.k-cta--primary';

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
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(10);

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

    private function download_invoices($address_id = null)
    {

        $this->exts->waitTillPresent('.k-monHistorique__bills .k-billCard__main, .k-monHistorique__lastBill .k-billCard__main', 20);
        $this->exts->capture("invoices-page");

        $bill_cards = $this->exts->getElements('.k-monHistorique__bills .k-billCard__main, .k-monHistorique__lastBill .k-billCard__main');
        foreach ($bill_cards as $key => $bill_card) {
            $download_bill_button = $this->exts->getElement('.k-billCard__cta button[data-testid="callId"]', $bill_card);
            $download_latest_bill_button = $this->exts->getElement('.k-billCard__cta button.k-cta--primary', $bill_card);
            if ($download_bill_button != null) {
                $this->isNoInvoice = false;
                $bill_date = $this->exts->extract('p.k-billCard__title', $bill_card, 'innerText');
                $bill_type = $this->exts->extract('p.k-billCard__content2', $bill_card, 'innerText');
                $bill_type = explode("\n", $bill_type)[0];
                $bill_name = $bill_type . '_' . $bill_date;
                if ($address_id != null) {
                    $bill_name = $address_id . '_' . $bill_name;
                }
                $this->exts->log('bill_name: ' . $bill_name);
                if ($this->exts->invoice_exists($bill_name)) {
                    $this->exts->log('Invoice existed ' . $bill_name);
                } else {
                    try {
                        $this->exts->log('Click download button');
                        $download_bill_button->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click download button by javascript');
                        $this->exts->execute_javascript("arguments[0].click()", [$download_bill_button]);
                    }
                    sleep(5);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $bill_name . '.pdf');

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($bill_name, '', '', $downloaded_file);
                        sleep(1);
                    }
                }
            } else if ($download_latest_bill_button != null) {
                $this->isNoInvoice = false;
                $bill_date = trim($this->exts->extract('p.k-billCard__content2', $bill_card));
                $bill_type = trim($this->exts->extract('p.k-billCard__title', $bill_card));
                $bill_type = explode("\n", $bill_type)[0];
                $bill_name = $bill_type . '_' . $bill_date;
                if ($address_id != null) {
                    $bill_name = $address_id . '_' . $bill_name;
                }
                $this->exts->log('bill_name: ' . $bill_name);
                if ($this->exts->invoice_exists($bill_name)) {
                    $this->exts->log('Invoice existed ' . $bill_name);
                } else {
                    try {
                        $this->exts->log('Click download button');
                        $download_latest_bill_button->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click download button by javascript');
                        $this->exts->execute_javascript("arguments[0].click()", [$download_latest_bill_button]);
                    }
                    sleep(5);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $bill_name . '.pdf');

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($bill_name, '', '', $downloaded_file);
                        sleep(1);
                    }
                }
            }
        }
    }

    private function download_mirror_invoice()
    {
        // Some sub account was terminated, then website show invoice on old layout
        sleep(10);
        $this->exts->moveToElementAndClick('[role="tablist"] li a#panelLink_historique');
        sleep(5);
        $this->exts->capture("mirror-invoices-page");

        $rows_len = count($this->exts->getElements('#historique-facture .c-billSumList > li.c-billSum'));
        for ($i = 0; $i < $rows_len; $i++) {
            $row = $this->exts->getElements('#historique-facture .c-billSumList > li.c-billSum')[$i];
            $download_button = $this->exts->getElement('a.historique-facture-download', $row);
            if ($download_button != null) {
                $this->isNoInvoice = false;
                $invoiceName = trim($download_button->getAttribute('data-id'));
                $invoiceFileName = $invoiceName . '.pdf';
                $invoiceDate = '';
                $invoiceAmount = '';

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);

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
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $downloaded_file);
                        sleep(1);
                    }
                }

                $this->exts->switchToInitTab();
                sleep(1);
                $this->exts->closeAllTabsButThis();

                $this->exts->moveToElementAndClick('#siteNav a[href*="espace-client/factures-"]');
                sleep(15);

                $this->exts->moveToElementAndClick('[role="tablist"] li a#panelLink_historique');
                sleep(15);
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
