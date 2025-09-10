<?php // migrated the script and updated login code.

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
    // Server-Portal-ID: 69471 - Last modified: 30.10.2024 13:34:42 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = "https://de.ingrammicro.com/cep/app/my/dashboard";
    public $invoicePageUrls = "https://de.ingrammicro.com/cep/app/invoice/InvoiceList/Invoice";
    public $invoicePageUrl = "https://de.ingrammicro.com/cep/app/invoice/InvoiceList";
    public $homePageUrl = "https://de.ingrammicro.com";
    public $username_selector = "#ctl00_PlaceHolderMain_txtUserEmail, input[name*='username']";
    public $password_selector = "#ctl00_PlaceHolderMain_txtPassword, input[name*='password']";
    public $login_button_selector = "#ctl00_PlaceHolderMain_btnLogin, input[id*='submit']";
    public $login_confirm_selector = 'button[data-testid="header-avatarBtn"]';
    public $billingPageUrl = "https://my.t-mobile.com/billing/summary.html";
    public $remember_me = "input[name=\"remember_me\"]";
    public $submit_button_selector = "input[type='submit']";
    public $dropdown_selector = "#img_DropDownIcon";
    public $dropdown_item_selector = "#di_billCycleDropDown";
    public $more_bill_selector = ".view-more-bills-btn";
    public $login_tryout = 0;
    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $isCookieLoginSuccess = false;

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->homePageUrl);
        $this->exts->capture('1-init-page');

        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->waitTillPresent('button[data-testid="btn_TopCornerLogin"]');
            $this->exts->moveToElementAndClick('button[data-testid="btn_TopCornerLogin"]');
            sleep(2);
            $this->exts->capture("after-login-clicked");

            $this->fillForm(0);

            sleep(15);

            if ($this->exts->getElement('#ctl00_PlaceHolderMain_chkTermsOfSale') != null && $this->exts->getElement('#ctl00_PlaceHolderMain_btnSubmit') != null) {
                $this->exts->moveToElementAndClick('#ctl00_PlaceHolderMain_chkTermsOfSale');
                sleep(1);
                $this->exts->moveToElementAndClick('#ctl00_PlaceHolderMain_btnSubmit');
                sleep(10);
            }

            if ($this->isExists('form[data-se="factor-email"]')) {
                sleep(1);
                $this->exts->moveToElementAndClick('form[data-se="factor-email"] input[type="submit"]');
                sleep(10);
            }

            $this->checkFillTwoFactor();
            sleep(15);
        }

        if ($this->exts->urlContains('/terms-and-conditions')) {
            $this->exts->moveToElementAndClick('input.PrivateSwitchBase-input');
            sleep(1);
            $this->exts->moveToElementAndClick('button.MuiButton-contained');
        }

        if ($this->exts->urlContains('/welcome-video')) {
            $this->exts->openUrl($this->homePageUrl);
            sleep(5);
        }

        if ($this->isExists('div[id*="walkme-visual-design"]')) {
            $this->exts->moveToElementAndClick('div[id*="walkme-visual-design"] button > div > .wm-ignore-css-reset');
            sleep(3);
        }

        if ($this->isExists('.cc_btn_accept_all, #onetrust-accept-btn-handler')) {
            $this->exts->moveToElementAndClick('.cc_btn_accept_all, #onetrust-accept-btn-handler');
            sleep(3);
        }

        $this->exts->capture('1.1-before-check-login');

        if ($this->checkLogin()) {

            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            $this->exts->openUrl($this->invoicePageUrls);
            sleep(10);
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(10);

            if ($this->isExists('.cc_btn_accept_all, #onetrust-accept-btn-handler')) {
                $this->exts->moveToElementAndClick('.cc_btn_accept_all, #onetrust-accept-btn-handler');
                sleep(3);
            }

            $from_date = $this->exts->config_array["restrictPages"] == '0' ?  date('d.m.Y', strtotime('-2 years')) : date('d.m.Y', strtotime('-6 months'));
            $to_date = date('d.m.Y');
            $this->exts->executeSafeScript('
			document.querySelector("input#from_date").value = "' . $from_date . '";
			document.querySelector("input#from_date").dispatchEvent(new Event("change"));
			document.querySelector("input#to_date").value = "' . $to_date . '";
			document.querySelector("input#to_date").dispatchEvent(new Event("change"));
		');
            sleep(3);

            $this->exts->capture("filled-invoice-filter");
            if ($this->isExists('button#btnDateStatus')) {
                $this->exts->moveToElementAndClick('button#btnDateStatus');
            }
            $this->downloadInvoice();
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log("Login failed " . $this->exts->getUrl());
            if ($this->exts->getElement('input[name*="oldPassword"]') != null && $this->exts->getElement('input[name*="newPassword"] ') != null) {
                $this->exts->log(">>>>>>>>>>>>>>>account_not_ready***************!!!!");
                $this->exts->capture("account_not_ready");
                $this->exts->account_not_ready();
            }
            if ($this->exts->urlContains('/PasswordUpdate')) {
                $this->exts->account_not_ready();
            }

            if (strpos($this->exts->extract('.o-form-has-errors .infobox-error'), 'Ihr Benutzername und Ihr Passwort') !== false) {
                $this->exts->loginFailure(1);
            } else if ($this->exts->getElement('//*[contains(text(),"bitte versuchen Sie es mit einer anderen URL")]', null, 'xpath') != null) {
                $this->exts->loginFailure(1);
            } else if (stripos($this->exts->extract('.o-form-has-errors .infobox-error'), 'Authentifizierung fehlgeschlagen') !== false) {
                $this->exts->loginFailure(1);
            } else if (stripos($this->exts->extract('.o-form-has-errors .infobox-error'), 'User is not assigned to the client application.') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {
            $this->exts->waitTillPresent($this->username_selector);
            if ($this->exts->getElement($this->username_selector) != null) {
                sleep(2);

                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                $this->exts->capture("2-login-page-filled");
                $this->exts->moveToElementAndClick($this->login_button_selector);
                sleep(10);
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->login_confirm_selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for login.....');
            sleep(10);
        }
        $isloggedIn = false;
        if ($this->exts->querySelector($this->login_confirm_selector) != null && !$this->exts->urlContains('/PasswordUpdate')) {
            $isloggedIn = true;
        }

        return $isloggedIn;
    }


    private function isExists($selector = '')
    {
        $safeSelector = addslashes($selector);
        $this->exts->log('Element:: ' . $safeSelector);
        $isElement = $this->exts->execute_javascript('!!document.querySelector("' . $safeSelector . '")');
        if ($isElement) {
            $this->exts->log('Element Found');
            return true;
        } else {
            $this->exts->log('Element not Found');
            return false;
        }
    }

    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'form[data-se="factor-email"] input[name="answer"]';
        $two_factor_message_selector = 'form[data-se="factor-email"] .mfa-email-sent-content';
        $two_factor_submit_selector = 'form[data-se="factor-email"] [type="submit"]';

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
                if ($this->isExists('form[data-se="factor-email"] [data-se-for-name="rememberDevice"]')) {
                    $this->exts->moveToElementAndClick('form[data-se="factor-email"] [data-se-for-name="rememberDevice"]');
                    sleep(3);
                }
                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(15);

                if ($this->exts->getElement($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->notification_uid = "";
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

    function downloadInvoice($paging_count = 1)
    {
        $this->exts->log("Begin download invoice ");
        $this->exts->waitTillPresent('.MuiDataGrid-virtualScrollerContent > .MuiDataGrid-virtualScrollerRenderZone > div[class="MuiDataGrid-row"]');
        $invoices = 0;

        $rows = $this->exts->getElements('.MuiDataGrid-virtualScrollerContent > .MuiDataGrid-virtualScrollerRenderZone > div[class="MuiDataGrid-row"]');

        foreach ($rows as $row) {
            $tags = $this->exts->getElements('div[class*="MuiDataGrid-cell"]', $row);

            $this->exts->log('tag count : ' . count($tags));
            foreach ($tags as $index => $tag) {
                $this->exts->log("Tag $index innerText: " . trim($tag->getAttribute('data-field')));
            }
            try {
                if (count($tags) < 8) {
                    $this->exts->log('Error: Not enough cells in the row.');
                    continue;
                }


                $invoiceDueDate = trim($tags[1]->getText()); // Due date in the first cell
                $invoiceDate = trim($tags[2]->getText()); // Invoice date in the second cell
                $invoiceNumber = trim($tags[3]->getText()); // Invoice number in the third cell
                $customerOrderNumber = trim($tags[4]->getText()); // Customer order number in the fourth cell
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[5]->getText())) . ' EUR'; // Invoiced amount in the fifth cell
                $invoiceStatus = trim($tags[6]->getText()); // Invoice status in the sixth cell

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceDueDate: ' . $invoiceDueDate);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceNumber: ' . $invoiceNumber);
                $this->exts->log('customerOrderNumber: ' . $customerOrderNumber);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $this->exts->log('invoiceStatus: ' . $invoiceStatus);

                $invoiceFileName = !empty($invoiceNumber) ? $invoiceNumber . '.pdf' : '';
                $invoiceDate = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoiceDate);

                if (count($tags) <= 7) {
                    $this->exts->log('Error: Not enough cells to locate the download button.');
                    continue;
                }

                $download_button = $this->exts->getElement('button', $tags[8]);

                if ($download_button) {
                    try {
                        $this->exts->log("Clicking download button");
                        $download_button->click();
                    } catch (Exception $e) {
                        $this->exts->log("Error clicking download button, attempting JavaScript click: " . $e->getMessage());
                        $this->exts->executeSafeScript('arguments[0].click()', [$download_button]);
                    }
                } else {
                    $this->exts->log('Download button not found.');
                    continue;
                }

                sleep(5); // Wait for the download to complete

                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceNumber, $invoiceDate, $invoiceAmount, $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }

                $invoices++;
                $this->isNoInvoice = false;
            } catch (Exception $e) {
                $this->exts->log('Error extracting data: ' . $e->getMessage());
            }
        }

        $this->exts->log('Invoices found: ' . $invoices);

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        if (
            $restrictPages == 0 && $paging_count < 50 &&
            $this->exts->getElement('div[data-name="nextpage"]') != null && $this->exts->getElement('div[data-name="nextpage"].Mui-disabled') == null
        ) {
            $paging_count++;
            $this->exts->moveToElementAndClick('div[data-name="nextpage"]');
            sleep(5);
            $this->downloadInvoice($paging_count);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
