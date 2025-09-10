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

    // Server-Portal-ID: 1757 - Last modified: 17.06.2025 14:36:08 UTC - User: 1

    public $baseUrl = 'https://de.hotels.com/login?';
    public $username_selector = 'input#loginFormEmailInput';
    public $password_selector = 'input#loginFormPasswordInput, input#enterPasswordFormPasswordInput';
    public $submit_login_selector = 'form.sign-in button[type="submit"], button#loginFormSubmitButton, #enterPasswordFormSubmitButton';
    public $isNoInvoice = true;

    public $firmname = "";
    public $address1 = "";
    public $address2 = "";
    public $city = "";
    public $country = "";
    public $vat_number = "";
    public $restrictPages = '3';
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        $this->firmname = isset($this->exts->config_array["firmname"]) ? $this->exts->config_array["firmname"] : "";
        $this->address1 = isset($this->exts->config_array["address1"]) ? $this->exts->config_array["address1"] : "";
        $this->address2 = isset($this->exts->config_array["address2"]) ? $this->exts->config_array["address2"] : "";
        $this->city = isset($this->exts->config_array["city"]) ? $this->exts->config_array["city"] : "";
        $this->country = isset($this->exts->config_array["country"]) ? $this->exts->config_array["country"] : "";
        $this->vat_number = isset($this->exts->config_array["vat_number"]) ? $this->exts->config_array["vat_number"] : "";
        // Load cookies
        // $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture('0-init-page');
        // Funcatcha can be displayed as blocking page
        $this->processFunCaptcha();
        sleep(5);
        if ($this->exts->urlContains('botOrNot')) {
            $this->exts->openUrl('https://hotels.com');
            sleep(10);
            $this->processFunCaptcha();
        }
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if (!$this->isLoggedIn()) {
            $this->exts->log('NOT logged via cookie');
            // $this->exts->clearCookies();
            $this->exts->openUrl('https://de.hotels.com/login');
            sleep(10);
            $this->processFunCaptcha();
            sleep(5);
            $this->checkFillLogin();
            sleep(5);
            // Funcaptcha can be display after submit login
            $this->check_and_solve_challenge();
            sleep(20);
            $this->checkFillTwoFactor();
        }

        // then check user logged in or not
        if ($this->isLoggedIn()) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->moveToElementAndClick('#cookie-policy-banner-container .cookie-policy-banner-accept, [role="dialog"] button.osano-cm-accept-all');
            $this->exts->capture("3-login-success");

            // Open past booking and download receipts
            $this->exts->openUrl('https://de.hotels.com/trips/list/3');
            $this->downloadInvoice(0);

            // // Open up comming booking and download receipts
            // $this->exts->openUrl('https://de.hotels.com/trips/list/1');
            // $this->downloadInvoice(0);

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
            if ($this->exts->exists('.msg-error-icon a[href*="/forgot"]')) {
                $this->exts->loginFailure(1);
            } else if (strpos(strtolower($this->exts->extract('.uitk-error-summary-heading, [class*="banner-error"]')), 'passwort passt nicht zur eingegebenen') !== false) {
                $this->exts->loginFailure(1);
            } else if (strpos(strtolower($this->exts->extract('.uitk-banner, #loginFormEmailInput-error')), 'e-mail-adresse ein') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }
    private function checkFillLogin()
    {
        $this->exts->capture("2-login-page");

        if ($this->exts->exists($this->username_selector) && !$this->exts->exists($this->password_selector)) {
            sleep(3);
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(10);
            $this->exts->moveToElementAndClick('button#passwordButton');
            sleep(3);
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(10);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    public function switchToFrame($query_string)
    {
        $this->exts->log(__FUNCTION__ . " Begin with " . $query_string);
        $frame = null;
        if (is_string($query_string)) {
            $frame = $this->exts->queryElement($query_string);
        }

        if ($frame != null) {
            $frame_context = $this->exts->get_frame_excutable_context($frame);
            if ($frame_context != null) {
                $this->exts->current_context = $frame_context;
                return true;
            }
        } else {
            $this->exts->log(__FUNCTION__ . " Frame not found " . $query_string);
        }

        return false;
    }

    private function processFunCaptcha()
    {
        $this->exts->log("Begin solving fun captcha");
        $input = "input[name='fc-token']";
        $url = $this->exts->getUrl();
        if ($this->exts->exists('[data-e2e="enforcement-frame"].show')) {
            $this->exts->log("Found Fun Captcha, process now");
            $this->switchToFrame('[data-e2e="enforcement-frame"].show');

            $elem = $this->exts->querySelector($input);

            if ($elem == null) {
                $this->exts->log("No input token found, skip solving fun captcha");
                return;
            }

            $value = $elem->getAttribute("value");
            $params = explode("|", $value);

            $pkKey = null;
            $surl = null;

            foreach ($params as $i => $param) {
                $this->exts->log("Param: " . $param);
                if (strpos($param, "pk=") === 0)
                    $pkKey = explode("=", $param)[1];
                else if (strpos($param, "surl=") === 0)
                    $surl = explode("=", $param)[1];
            }

            $this->exts->log("Found value pk-key " . $pkKey . " and surl " . $surl);
            $response = $this->exts->processFunCaptcha("NoForm", ["input[name='fc-token']", "input[name='verification-token']"], $pkKey, 'https://expedia-api.arkoselabs.com', $url, false);
            if ($response == null) {
                $response = $this->exts->processFunCaptcha("NoForm", ["input[name='fc-token']", "input[name='verification-token']"], $pkKey, 'https://expedia-api.arkoselabs.com', $url, false);
            }
            if ($response == null) {
                $response = $this->exts->processFunCaptcha("NoForm", ["input[name='fc-token']", "input[name='verification-token']"], $pkKey, 'https://expedia-api.arkoselabs.com', $url, false);
            }
            $this->exts->switchToDefault();
            if ($response != null) {
                $this->exts->executeSafeScript('
			var tokenInput = document.createElement("textarea");
			tokenInput.setAttribute("name", "fc-token");
			tokenInput.innerHTML = "' . $response . '";
			if(document.querySelector(".sign-in") != null){
				document.querySelector(".sign-in").append(tokenInput);
				document.querySelector(".sign-in").submit();
			} else {
				document.querySelector("form[action*=botOrNot]").append(tokenInput);
				document.querySelector("form[action*=botOrNot]").submit();
			}
		');
                sleep(5);
            }
            return true;
        }
        return false;
    }
    private function check_and_solve_challenge()
    {
        // Check and solve Funcaptcha, It can require 1 pics, 2 or 5 pics. So check 5 times.
        $funcaptcha_displayed = $this->processFunCaptchaByClicking();
        if ($funcaptcha_displayed) {
            $this->processFunCaptchaByClicking();
            $this->processFunCaptchaByClicking();
            $this->processFunCaptchaByClicking();
            $this->processFunCaptchaByClicking();
            $funcaptcha_displayed = $this->processFunCaptchaByClicking();
        }

        // if Step above failed, try again
        if ($funcaptcha_displayed) {
            $this->processFunCaptchaByClicking();
            $this->processFunCaptchaByClicking();
            $this->processFunCaptchaByClicking();
            $this->processFunCaptchaByClicking();
            $this->processFunCaptchaByClicking();
            $this->processFunCaptchaByClicking();
        }
    }
    private function processFunCaptchaByClicking()
    {
        $this->exts->log("Checking Funcaptcha");
        if ($this->exts->exists('[data-e2e="enforcement-frame"].show')) {
            $language_code = $this->exts->extract('[lang]', null, "lang");
            $this->exts->capture("funcaptcha");
            $this->switchToFrame('[data-e2e="enforcement-frame"].show');

            if ($this->exts->exists('#fc-iframe-wrap')) {
                $this->switchToFrame('#fc-iframe-wrap');
            }

            if ($this->exts->exists('#CaptchaFrame')) {
                $this->switchToFrame('#CaptchaFrame');
                // Click button to show images challenge
                if ($this->exts->exists('#home_children_button')) {
                    $this->exts->moveToElementAndClick('#home_children_button');
                    sleep(2);
                } else if ($this->exts->exists('#wrong_children_button, a#wrongTimeout_children_button')) {
                    $this->exts->moveToElementAndClick('#wrong_children_button, a#wrongTimeout_children_button');
                    sleep(2);
                }
                $captcha_instruction = $this->exts->extract('#game #game_children_text');
                $this->exts->log('language_code: ' . $language_code . ' Instruction: ' . $captcha_instruction);
                $this->exts->switchToDefault();
                $this->switchToFrame('[data-e2e="enforcement-frame"].show');

                $captcha_wraper_selector = '#fc-iframe-wrap';
                $coordinates = $this->processClickCaptcha($captcha_wraper_selector, $captcha_instruction, $language_code, $json_result = true);
                if ($coordinates == '') {
                    $coordinates = $this->processClickCaptcha($captcha_wraper_selector, $captcha_instruction, $language_code, $json_result = true);
                }
                if ($coordinates != '') {
                    $this->exts->log('Clicking X/Y: ' . $coordinates[0]['x'] . '/' . $coordinates[0]['y']);
                    $this->exts->click_by_xdotool($coordinates[0]['x'], $coordinates[0]['y']);
                    sleep(3);
                }
            }
            $this->exts->switchToDefault();
            return true;
        }
        $this->exts->switchToDefault();
        return false;
    }

    private function processClickCaptcha(
        $captcha_image_selector,
        $instruction = '',
        $lang_code = '',
        $json_result = false,
        $image_dpi = 75
    ) {
        $this->exts->log("--GET Coordinates By 2CAPTCHA--");
        $response = '';
        $image_path = $this->exts->captureElement($this->exts->process_uid, $captcha_image_selector);
        $source_image = imagecreatefrompng($image_path);
        imagejpeg($source_image, $this->exts->screen_capture_location . $this->exts->process_uid . '.jpg', $image_dpi);

        $cmd = $this->exts->config_array['click_captcha_shell_script'] . " --PROCESS_UID::" . $this->exts->process_uid . " --CAPTCHA_INSTRUCTION::" . urlencode($instruction) . " --LANG_CODE::" . urlencode($lang_code) . " --JSON_RESULT::" . urlencode($json_result);
        $this->exts->log('Executing command : ' . $cmd);
        exec($cmd, $output, $return_var);
        $this->exts->log('Command Result : ' . print_r($output, true));

        if (!empty($output)) {
            $output = trim($output[0]);
            if ($json_result) {
                if (strpos($output, '"status":1') !== false) {
                    $response = json_decode($output, true);
                    $response = $response['request'];
                }
            } else {
                if (strpos($output, 'coordinates:') !== false) {
                    $array = explode("coordinates:", $output);
                    $response = trim(end($array));
                    $coordinates = [];
                    $pairs = explode(';', $response);
                    foreach ($pairs as $pair) {
                        preg_match('/x=(\d+),y=(\d+)/', $pair, $matches);
                        if (!empty($matches)) {
                            $coordinates[] = ['x' => (int)$matches[1], 'y' => (int)$matches[2]];
                        }
                    }
                    $this->exts->log('>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>');
                    $this->exts->log(print_r($coordinates, true));
                    return $coordinates;
                }
            }
        }

        if ($response == '') {
            $this->exts->log("Can not get result from API");
        }
        return $response;
    }

    private function isLoggedIn()
    {
        return $this->exts->exists('#hdr-signout, a[href*="signout"], a[href*="logout"]') && !$this->exts->exists($this->password_selector);
    }
    private function checkFillTwoFactor()
    {
        $this->exts->capture("2-2fa-checking");

        if ($this->exts->querySelector('input#verifyOtpFormCodeInput') != null && $this->exts->urlContains('/verify')) {
            $two_factor_selector = 'input#verifyOtpFormCodeInput';
            $two_factor_message_selector = 'form[name=verifyOtpForm] .uitk-text';
            $two_factor_submit_selector = 'button#verifyOtpFormSubmitButton';
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->querySelector($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->querySelectorAll($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->querySelectorAll($two_factor_message_selector)[$i]->getText() . "\n";
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

                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(10);

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

    private function downloadInvoice($counter = 1)
    {
        sleep(15);
        $this->exts->moveToElementAndClick('#cookie-policy-banner-container .cookie-policy-banner-accept, [role="dialog"] button.osano-cm-accept-all');
        sleep(3);
        $this->exts->capture("4-booking-page");
        if ($this->exts->querySelector('a[class*="card-link"]') != null) {
            $invoices = array();
            $receipts = $this->exts->querySelectorAll('a[class*="card-link"]');
            $this->exts->log(count($receipts));
            $count = 0;
            foreach ($receipts as $receipt) {
                $receiptUrl = $receipt->getAttribute("href");
                $receiptName = end(explode('/', $receiptUrl));
                $receiptName = str_replace('?filter=3', '', $receiptName);
                $invoice = array(
                    'receiptName' => $receiptName,
                    'parsed_date' => '',
                    'receiptAmount' => '',
                    'receiptUrl' => $receiptUrl,
                );
                $this->isNoInvoice = false;
                array_push($invoices, $invoice);
            }

            $this->exts->log("Number of invoices: " . count($invoices));
            foreach ($invoices as $invoice) {
                $this->exts->openUrl($invoice['receiptUrl']);
                $this->exts->waitTillPresent('div[class*="card-content-section-has-link"] a[href*="details"]');

                if ($this->exts->exists('div[class*="card-content-section-has-link"] a[href*="details"]')) {
                    $receiptUrls = $this->exts->querySelectorAll('div[class*="card-content-section-has-link"] a[href*="details"]');
                    foreach ($receiptUrls as $key => $receiptUrl) {
                        if ($key != 0) {
                            $receiptName = $invoice['receiptName'] . '_' . $key;
                        } else {
                            $receiptName = $invoice['receiptName'];
                        }

                        $receiptFileName = !empty($receiptName) ? $receiptName . '.pdf' : '';
                        $receiptUrl = $receiptUrl->getAttribute("href");
                        $receiptUrl = str_replace("?filter=3", "/trip-receipt.pdf", $receiptUrl);
                        $downloaded_file = $this->exts->direct_download($receiptUrl, 'pdf', $receiptFileName);
                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            $this->exts->new_invoice($receiptName, $invoice['parsed_date'], $invoice['receiptAmount'], $receiptFileName);
                            sleep(1);
                        } else {
                            $this->exts->log(__FUNCTION__ . '::No download ');
                        }
                    }
                }
                if ($this->exts->exists('[role=menu] a[href*="pdf"]')) {
                    $receiptUrl = $this->exts->extract('[role=menu] a[href*="pdf"]', null, 'href');

                    $receiptName = $invoice['receiptName'];
                    $receiptFileName = !empty($receiptName) ? $receiptName . '.pdf' : '';

                    $this->exts->log('---------------------------------------');
                    $this->exts->log('receiptUrl: ' . $receiptUrl);
                    $this->exts->log("receiptName: " . $receiptName);
                    $this->exts->log("receiptFileName: " . $receiptFileName);
                    $downloaded_file = $this->exts->direct_download($receiptUrl, 'pdf', $receiptFileName);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($receiptName, $invoice['parsed_date'], $invoice['receiptAmount'], $receiptFileName);
                        sleep(1);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ');
                    }
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
