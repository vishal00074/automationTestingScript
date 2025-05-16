<?php // updated login code and 2fa code request again in case wrong 2fa entered for single time   added condition to handle empty invoice name

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

    //Server-Portal-ID: 8846 - Last modified: 29.01.2025 13:53:11 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = "https://www.dell.com/identity/global/loginorregister";
    public $username_selector = '#frmSignIn input[name=EmailAddress]';
    public $password_selector = '#frmSignIn input[name=Password]';
    public $submit_btn = '#frmSignIn #sign-in-button';
    public $logout_btn = 'a[href*="/out/"], #user_name, a[href*="/signout"]';
    public $wrong_credential_selector = '#frmSignIn #validationSummaryText';

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $isCookieLoaded = false;
        if ($this->exts->loadCookiesFromFile()) {
            sleep(1);
            $isCookieLoaded = true;
        }
        $this->exts->openUrl('https://www.dell.com');
        sleep(10);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        if ($isCookieLoaded) {
            $this->exts->capture("Home-page-with-cookie");
        } else {
            $this->exts->capture("Home-page-without-cookie");
        }

        sleep(10);
        if (!$this->checkLogin() && !$this->isWrongCredential()) {
            sleep(10);
            $this->fillForm(0);
        }

        sleep(10);
        $this->fillForm(0);
        sleep(10);

        $isCaptcha = strtolower($this->exts->extract('#frmSignIn #validationSummaryText'));

        $this->exts->log('Captcha:: ' . $isCaptcha);

        if (stripos($isCaptcha, strtolower('Bitte geben Sie die im Bild angezeigten Zeichen ein, um fortzufahren.')) !== false) {
            sleep(10);
            $this->fillForm(0);
        }

        sleep(10);
        if ($this->exts->exists('input#OTP')) {
            $this->checkFillTwoFactor();
            sleep(10);
        }

        // request again in case wrong 2fa entered
        $isTwoError = strtolower($this->exts->extract('div#validationSummaryContainer'));

        $this->exts->log('isTwoError:: ' . $isTwoError);

        if (stripos($isTwoError, strtolower('Der einmalige Bestätigungscode ist falsch')) !== false) {

            $this->exts->moveToElementAndClick('a#send-verification-email-link');
            sleep(7);
            $this->checkFillTwoFactor();
            sleep(10);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            $this->exts->openUrl('https://www.dell.com/support/orders/de/de/dedhs1/order/list?lwp=rt');
            sleep(20);
            if ($this->exts->waitTillPresent('#ddlPeriod')) {
                $this->changeSelectbox('#ddlPeriod', '4');
                sleep(30);
            }
            if ($this->exts->exists('button#_evidon-accept-button')) {
                $this->exts->moveToElementAndClick('button#_evidon-accept-button');
                sleep(10);
            }
            $this->downloadInvoice(0);

            $this->exts->success();
        } else {
            $this->exts->capture("LoginFailed");

            $isTwoError = strtolower($this->exts->extract('div#validationSummaryContainer'));

            $this->exts->log('isTwoError:: ' . $isTwoError);

            if ($this->isWrongCredential()) {
                $this->exts->log($this->exts->extract($this->wrong_credential_selector, null));
                $this->exts->loginFailure(1);
            } else if (stripos($isTwoError, strtolower('Der einmalige Bestätigungscode ist falsch')) !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    /**
     * Method to fill login form
     * @param Integer $count Number of times portal is retried.
     */
    function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {

            if ($this->exts->exists($this->username_selector)) {
                sleep(2);
                $this->exts->capture("1-pre-login");

                sleep(10);
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username, 5);

                sleep(10);
                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password, 5);

                $this->exts->capture("1-pre-login-1");
                $this->checkFillRecaptcha(0);

                sleep(10);
                if ($this->exts->exists('[id*=captcha-image]')) {
                    $this->exts->processCaptcha('[id*=captcha-image]', '[name="ImageText"]');
                    sleep(2);
                }

                sleep(10);
                $this->exts->click_by_xdotool($this->submit_btn, 10);
            } else if ($this->exts->exists("iframe[src*=\"https://www.google.com/recaptcha/api2/anchor?\"]") && $this->exts->exists("textarea[name=\"g-recaptcha-response\"]")) {
                $this->checkFillRecaptcha(0);
                $this->fillForm($count + 1);
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling command " . $exception->getMessage());
        }
    }

    public function isWrongCredential()
    {
        $tag = false;
        $error_text = strtolower($this->exts->extract($this->wrong_credential_selector));

        if (stripos($error_text, strtolower('Passwort')) !== false) {
            $tag = true;
        }
        return $tag;
    }

    public function checkFillRecaptcha($counter)
    {

        if ($this->exts->exists('iframe[src*="https://www.google.com/recaptcha/api2/anchor?"]') && $this->exts->exists('textarea[name="g-recaptcha-response"]')) {

            if ($this->exts->exists("div.g-recaptcha[data-sitekey]")) {
                $data_siteKey = trim($this->exts->querySelector("div.g-recaptcha")->getAttribute("data-sitekey"));
            } else {
                $iframeUrl = $this->exts->querySelector("iframe[src*=\"https://www.google.com/recaptcha/api2/anchor?\"]")->getAttribute("src");
                $tempArr = explode("&k=", $iframeUrl);
                $tempArr = explode("&", $tempArr[count($tempArr) - 1]);

                $data_siteKey = trim($tempArr[0]);
                $this->exts->log("iframe url  - " . $iframeUrl);
            }
            $this->exts->log("SiteKey - " . $data_siteKey);

            $isCaptchaSolved = $this->exts->processRecaptcha($this->exts->getUrl(), $data_siteKey, false);
            $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);

            if ($isCaptchaSolved) {
                $this->exts->log("isCaptchaSolved");
                $this->exts->execute_javascript("document.querySelector(\"#g-recaptcha-response\").value = '" . $this->exts->recaptcha_answer . "';");
                sleep(5);
                try {
                    $tag = $this->exts->querySelector("[data-callback]");
                    if ($tag != null && trim($tag->getAttribute("data-callback")) != "") {
                        $func =  trim($tag->getAttribute("data-callback"));
                        $this->exts->execute_javascript(
                            $func . "('" . $this->exts->recaptcha_answer . "');"
                        );
                    } else {

                        $this->exts->execute_javascript(
                            "var a = ___grecaptcha_cfg.clients[0]; for(var p1 in a ) {for(var p2 in a[p1]) { for (var p3 in a[p1][p2]) { if (p3 === 'callback') var f = a[p1][p2][p3]; }}}; if (f in window) f= window[f]; if (f!=undefined) f('" . $this->exts->recaptcha_answer . "');"
                        );
                    }
                    sleep(10);
                } catch (\Exception $exception) {
                    $this->exts->log("Exception " . $exception->getMessage());
                }
            }
        }
    }

    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'input#OTP';
        $two_factor_message_selector = '#validateotp-section #description i';
        $two_factor_submit_selector = 'button#configure-OTPNo-submit-button';

        if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->querySelector($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->querySelectorAll($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->querySelectorAll($two_factor_message_selector)[$i]->getText() . "\n";
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

                if ($this->exts->querySelector($two_factor_selector) == null) {
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

    /**
     * Method to Check where user is logged in or not
     * return boolean true/false
     */
    public  function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            if ($this->exts->exists($this->logout_btn) && $this->exts->exists($this->username_selector) == false) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }

        return $isLoggedIn;
    }



    private function downloadInvoice($count)
    {
        $this->exts->log("Begin download invoice - " . $count);
        try {
            if ($this->exts->exists('#tblDataDisplay > tbody > tr')) {
                $this->exts->capture("2-download-invoice");

                $invoices = array();
                $receipts = $this->exts->querySelectorAll('#tblDataDisplay > tbody > tr');
                $this->exts->log("receipts count - " . count($receipts));

                $i = 0;
                foreach ($receipts as $receipt) {
                    $i++;
                    try {
                        $receiptDate = trim($this->exts->extract('.col-Date div', $receipt));
                    } catch (\Exception $exception) {
                        $receiptDate = null;
                    }

                    if ($receiptDate != null) {
                        $this->exts->log($receiptDate);

                        $receiptName = trim($this->exts->extract('.col-OrderNumber a', $receipt));
                        $this->exts->log($receiptName);
                        $parsed_date = $this->exts->parse_date($receiptDate, 'd.m.Y', 'Y-m-d');
                        $this->exts->log($parsed_date);
                        $receiptFileName = !empty($receiptName) ? $receiptName . '.pdf' : '';
                        $this->exts->log($receiptFileName);

                        $receiptAmount = "";
                        $receiptUrl = '#tblDataDisplay > tbody > tr:nth-child(' . $i . ') .col-OtherActions select';

                        $invoice = array(
                            'receiptName' => $receiptName,
                            'parsed_date' => $parsed_date,
                            'receiptAmount' => $receiptAmount,
                            'receiptFileName' => $receiptFileName,
                            'receiptUrl' => $receiptUrl
                        );


                        array_push($invoices, $invoice);
                    }
                }

                $this->exts->log("Invoices count - " . count($invoices));
                foreach ($invoices as $invoice) {
                    try {
                        $this->changeSelectbox($invoice['receiptUrl'], '2', 15);
                        $this->exts->wait_and_check_download('pdf');
                        $downloaded_file = $this->exts->find_saved_file('pdf', $invoice['receiptFileName']);
                        sleep(1);
                        if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                            $this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $downloaded_file);
                            sleep(2);
                        }
                        sleep(1);
                    } catch (\Exception $exception) {
                        $this->exts->log("Exception downloading invoice - " . $exception->getMessage());
                    }
                }
            } else if ($this->exts->exists('order-collection-list')) {
                $this->exts->capture("2.1-download-invoice");

                $invoices = array();
                $receipts = $this->exts->querySelectorAll('order-collection-list');
                $this->exts->log("receipts count - " . count($receipts));

                foreach ($receipts as $receipt) {
                    $cardHeader = $this->exts->querySelectorAll('app-card-header span', $receipt);
                    $cards = $this->exts->querySelectorAll('.card-body', $receipt);
                    if (count($cards) > 0 && count($cardHeader) > 0) {
                        $receiptDate = trim($cardHeader[1]->getText());
                        $this->exts->log($receiptDate);

                        $receiptName = trim($this->exts->querySelector('app-card-header span a', $receipt)->getText());
                        $this->exts->log($receiptName);
                        $parsed_date = $this->exts->parse_date($receiptDate);
                        $this->exts->log($parsed_date);
                        $receiptFileName = !empty($receiptName) ? $receiptName . '.pdf' : '';
                        $this->exts->log($receiptFileName);

                        $receiptAmount = "";

                        $downloadBtn = $this->exts->querySelectorAll('button.btn', $cards[0]);
                        try {
                            $downloadBtn[1]->click();
                        } catch (\Exception $exception) {
                            $this->exts->executeSafeScript('arguments[0].click();', [$downloadBtn[1]]);
                        }
                        sleep(15);

                        $this->exts->wait_and_check_download('pdf');

                        $downloaded_file = $this->exts->find_saved_file('pdf', $receiptFileName);
                        sleep(1);
                        if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                            $this->exts->new_invoice($receiptName, $parsed_date, $receiptAmount, $downloaded_file);
                            sleep(2);
                        }
                    }
                }
            } else {
                $this->exts->log("No invoice !!! ");
                $this->exts->no_invoice();
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception downloading invoice " . $exception->getMessage());
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
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
