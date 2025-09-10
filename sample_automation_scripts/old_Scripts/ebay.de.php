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
    // Server-Portal-ID: 328 - Last modified: 21.03.2025 15:49:49 UTC - User: 1

    // Script here
    public $base_url = "http://ebay.de";
    public $invoice_url = "http://my.ebay.de/ws/eBayISAPI.dll?MyEbay&CurrentPage=MyeBayMyAccounts";
    public $purchase_history = "https://www.ebay.de/myb/PurchaseHistory?MyEbay&gbh=1";
    public $signinLink = 'header div#gh-top ul li a[href*="signin.ebay.de"]';
    public $username_selector = 'input[name="userid"]';
    public $password_selector = 'input[name="pass"]';
    public $remember_selector = 'input[name="keepMeSignInOption2"]';
    public $login_submit_selector = "form#SignInForm button#sgnBt, form#signin-form div.password-box-wrapper ~ button#sgnBt";
    public $meet_recaptcha_completed_button = false;
    public $restrictPages = 3;
    public $login_with_google = '0';
    public $isNoInvoice = true;
    public $html_invoice = false;
    public $fetch_transaction = 0;
    public $monthly_statements = 0;
    public $download_sales_invoice = 0;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        $this->fetch_transaction = isset($this->exts->config_array["fetch_transaction"]) ? (int)@$this->exts->config_array["fetch_transaction"] : 0;
        $this->monthly_statements = isset($this->exts->config_array["monthly_statements"]) ? (int)@$this->exts->config_array["monthly_statements"] : 0;
        $this->download_sales_invoice = isset($this->exts->config_array["download_sales_invoice"]) ? (int)@$this->exts->config_array["download_sales_invoice"] : 0;

        $this->login_with_google = isset($this->exts->config_array["login_with_google"]) ? (int)@$this->exts->config_array["login_with_google"] : (isset($this->exts->config_array["LOGIN_WITH_GOOGLE"]) ? (int)@$this->exts->config_array["LOGIN_WITH_GOOGLE"] : 0);

        $this->exts->log('login_with_google: ' . $this->login_with_google);
        $this->exts->openUrl($this->base_url);
        sleep(12);
        $this->checkAndReloadUrl($this->base_url);
        $this->callRecaptcha();
        sleep(15);

        //Since We save profile in this portal check if we are logged in using that
        if (!$this->checkLogin()) {
            // Load cookies
            $this->exts->loadCookiesFromFile(true);
            sleep(1);
            $this->exts->openUrl($this->base_url);
            sleep(10);
            $this->exts->capture('1-init-page');
        } else {
            // Load cookies
            $this->exts->loadCookiesFromFile();
            sleep(1);
            $this->exts->openUrl($this->base_url);
            sleep(10);
            $this->exts->capture('1-init-page-1');
        }

        $this->exts->openUrl($this->invoice_url);
        $this->callRecaptcha();
        sleep(15);

        // If user hase not logged in from cookie & profile both, clear cookie, open the login url and do login
        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->checkAndReloadUrl($this->invoice_url);
            $this->callRecaptcha();
            sleep(15);
            $this->fillForm(0);
            sleep(5);

            $mesg = strtolower($this->exts->extract('form#signin-form p#signin-error-msg, p#errormsg', null, 'innerText'));
            $this->exts->log($mesg);
            if (strpos($mesg, 'bereinstimmung.') !== false || strpos($mesg, 'no agreement') !== false) {
                $this->exts->moveToElementAndClick('a#switch-account-anchor');
                sleep(5);
                $this->fillForm(1);
                sleep(20);
            }

            if ($this->exts->exists('span.uci__actionAskLaterBtn')) {
                $this->exts->moveToElementAndClick('span.uci__actionAskLaterBtn');
                sleep(12);
            }

            if ($this->exts->exists('div#continue-wrapper a')) {
                $this->exts->moveToElementAndClick('div#continue-wrapper a');
                sleep(12);
            }

            $this->checkAndReloadUrl($this->invoice_url);
            $this->callRecaptcha();
            sleep(15);

            $this->exts->moveToElementAndClick('form#contactInfoForm a#rmdLtr');
            sleep(12);

            // if ($this->exts->urlContains('ChangeSecretQuestion')) {
            // 	$this->exts->moveToElementAndClick('');
            // }

            if ($this->exts->exists('form#securityQuestionForm input[name="answer"]')) {
                $this->checkFillSecurityAnswer();
            }

            // relogin when aftrer submit the page return login page
            if ($this->exts->exists($this->username_selector)) {
                $this->reLogin($this->base_url);
                sleep(20);

                if ($this->exts->exists($this->username_selector)) {
                    $this->exts->loginFailure(1);
                }
            }

            if ($this->exts->exists('#signin-error-msg') && $this->exts->exists('input[name="userid"]:not([type="Hidden"])')) {
                $this->reLogin($this->base_url);
            }
            if ($this->meet_recaptcha_completed_button && $this->exts->exists($this->signinLink)) {
                $this->reLogin($this->base_url);
            }

            if ($this->exts->exists('input#userInfo')) {
                $this->exts->moveToElementAndType('input#userInfo', $this->username);
                sleep(2);
                $this->exts->moveToElementAndClick('button[name="submitBtn"]');
                sleep(15);
            }

            if ($this->exts->exists('form#securityQuestionForm input[name="answer"]')) {
                $this->checkFillSecurityAnswer();
            }

            // phone 2FA
            $this->check2FA();
            $this->check2FA();

            // click some button when finished 2FA
            if ($this->exts->getElement('form[name="contactInfoForm"] a#rmdLtr') != null) {
                $this->exts->moveToElementAndClick('form[name="contactInfoForm"] a#rmdLtr');
            } else if ($this->exts->getElement('#fullscale [name="submitBtn"]') != null) {
                $this->exts->moveToElementAndClick('#fullscale [name="submitBtn"]');
            } else if ($this->exts->getElement('.primsecbtns [value="text"]') != null) {
                $this->exts->moveToElementAndClick('.primsecbtns [value="text"]');
            } else if ($this->exts->getElement("a#continue-get") != null) {
                $this->exts->moveToElementAndClick("a#continue-get");
            }

            sleep(15);

            if ($this->exts->exists('form#securityQuestionForm input[name="answer"]')) {
                $this->checkFillSecurityAnswer();
            }

            if ($this->exts->exists($this->password_selector)) {
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(2);

                $this->exts->moveToElementAndClick($this->login_submit_selector);
                sleep(12);

                $this->checkAndReloadUrl($this->base_url);
                $this->callRecaptcha();
                sleep(15);

                $this->exts->capture('input-password-after-2FA');
            }

            $this->exts->capture('after-check-2FA-1');
            // $this->exts->openUrl($this->invoice_url);
            // sleep(15);

            // phone 2FA
            $this->check2FA();
            $this->check2FA();

            // click some button when finished 2FA
            if ($this->exts->getElement('form[name="contactInfoForm"] a#rmdLtr') != null) {
                $this->exts->moveToElementAndClick('form[name="contactInfoForm"] a#rmdLtr');
            } else if ($this->exts->getElement('#fullscale [name="submitBtn"]') != null) {
                $this->exts->moveToElementAndClick('#fullscale [name="submitBtn"]');
            } else if ($this->exts->getElement('.primsecbtns [value="text"]') != null) {
                $this->exts->moveToElementAndClick('.primsecbtns [value="text"]');
            } else if ($this->exts->getElement("a#continue-get") != null) {
                $this->exts->moveToElementAndClick("a#continue-get");
            }

            sleep(15);

            if ($this->exts->exists('form#securityQuestionForm input[name="answer"]')) {
                $this->checkFillSecurityAnswer();
            }

            if ($this->exts->exists($this->password_selector)) {
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(2);

                $this->exts->moveToElementAndClick($this->login_submit_selector);
                sleep(12);

                $this->checkAndReloadUrl($this->base_url);
                $this->callRecaptcha();
                sleep(15);

                $this->exts->capture('input-password-after-2FA');
            }

            $this->exts->capture('after-check-2FA-2');
            if ($this->exts->exists('div.device-trust-skipbtn-wrapper a#skip-for-now-link')) {
                $this->exts->moveToElementAndClick('div.device-trust-skipbtn-wrapper a#skip-for-now-link');
                sleep(10);
            }
            if ($this->exts->exists('div#continue-wrapper a')) {
                $this->exts->moveToElementAndClick('div#continue-wrapper a');
                sleep(10);
            }
        }

        if ($this->checkLogin()) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in with cookie');
            $this->exts->capture("3-login-success");

            // $this->exts->openUrl($this->invoice_url);
            // sleep(15);
            $this->processAfterLogin(0);
            $this->exts->success();
        } else {

            if ($this->exts->exists('div[id="mainContent"] div[class="info-header"] p[id="info-header-sub"]')) {

                $err_msg1 = $this->exts->extract('div[id="mainContent"] div[class="info-header"] p[id="info-header-sub"]');
                $lowercase_err_msg = strtolower($err_msg1);
                $substrings = array('bitte bestätigen sie ihre identität', 'anderen computer', 'anderen');
                foreach ($substrings as $substring) {
                    if (strpos($lowercase_err_msg, strtolower($substring)) !== false) {
                        $this->exts->log($err_msg1);
                        $this->exts->account_not_ready();
                        //$this->exts->loginFailure(1);
                        break;
                    }
                }
            }


            $msg = strtolower($this->exts->extract('form#signin-form div.need-help button', null, 'innerText'));
            $msg1 = strtolower($this->exts->extract('section.content input ~ p', null, 'innerText'));
            $msg2 = strtolower($this->exts->extract('div#uciEditForm .uci__title h1', null, 'innerText'));
            if ($this->exts->exists('span.mi-er span.sd-err, #errf')) {
                $this->exts->capture("Wrong credentials");
                $this->exts->loginFailure(1);
            } else if (strpos($msg1, 'an und nennen sie uns den sicherheitscode') !== false) {
                $this->exts->log($msg1);
                $this->exts->capture("Wrong credentials1");
                $this->exts->loginFailure(1);
            } else if (strpos($msg, 'passwort zur') !== false || strpos($msg2, 'helfen sie uns, ihr ebay-konto zu') !== false) {
                $this->exts->account_not_ready();
            } else if ($this->exts->exists('div.individual__section-phone') && $this->exts->urlContains('accountsettings.ebay.de')) {
                $this->exts->account_not_ready();
            } else {
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure();
            }
        }
    }
    function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {
            if ($this->login_with_google == 1) {
                if (!$this->exts->exists('button#signin_ggl_btn') && $this->exts->exists('a#switch-account-anchor')) {
                    $this->exts->moveToElementAndClick('a#switch-account-anchor');
                    sleep(14);
                }

                $this->exts->moveToElementAndClick('button#signin_ggl_btn');
                sleep(8);

                $google_login_tab = $this->exts->findTabMatchedUrl(['.google.com']);
                if ($google_login_tab != null) {
                    $this->exts->switchToTab($google_login_tab);
                }

                $this->checkAndReloadUrl($this->exts->getUrl());
                $this->callRecaptcha();
                sleep(15);

                $this->loginGoogleIfRequired();

                if ($google_login_tab != null) {
                    $this->exts->closeTab($google_login_tab);
                }
            } else if ($this->exts->getElement($this->password_selector) != null || $this->exts->getElement($this->username_selector) != null) {
                $this->exts->capture("1-pre-login");
                if ($this->exts->getElement($this->remember_selector) != null) {
                    $this->exts->moveToElementAndClick($this->remember_selector);
                }
                sleep(1);

                if ($this->exts->getElement($this->username_selector) != null && !$this->exts->exists('form#signin-form > div.hide input[name="userid"]')) {
                    $this->exts->log("Enter Username");
                    $this->exts->moveToElementAndType($this->username_selector, $this->username);
                    sleep(2);

                    $this->exts->moveToElementAndClick('button[name="signin-continue-btn"]');
                    sleep(15);
                }

                // $this->checkAndReloadUrl($this->exts->getUrl());
                // $this->callRecaptcha();
                // sleep(15);

                $mesg = strtolower($this->exts->extract('form#signin-form p#signin-error-msg, p#errormsg', null, 'innerText'));
                $this->exts->log($mesg);
                if (strpos($mesg, 'es gab ein problem') !== false) {
                    $body = $this->exts->extract('body', null, 'innerHTML');
                    // $this->exts->log('body: ' . $body);
                    $this->exts->capture('input-username-error-1');
                    $this->exts->refresh();
                    sleep(15);

                    $this->exts->capture('input-username-error-2');

                    if ($this->exts->exists($this->username_selector)) {
                        $this->exts->log("Enter Username");
                        $this->exts->moveToElementAndType($this->username_selector, $this->username);
                        sleep(2);
                        $this->exts->capture('reinput-username');

                        $this->exts->moveToElementAndClick('button[name="signin-continue-btn"]');
                        sleep(15);

                        $this->exts->capture('after-reinput-username');
                    }

                    // $this->checkAndReloadUrl($this->exts->getUrl());
                    // $this->callRecaptcha();
                    // sleep(15);
                }

                if (strpos($mesg, "eso no coincide") !== false) {
                    $this->exts->loginFailure(1);
                }

                if ($this->exts->getElement($this->password_selector) != null) {
                    $this->exts->log("Enter Password");
                    $this->exts->moveToElementAndType($this->password_selector, $this->password);
                    sleep(2);
                }
                $this->exts->capture("2-filled-login");
                $this->exts->moveToElementAndClick($this->login_submit_selector);
                sleep(18);
                $this->checkAndReloadUrl($this->exts->getUrl());
                $this->callRecaptcha();
                sleep(15);

                $this->exts->moveToElementAndClick('div#continue-wrapper a');
                sleep(15);

                // if ($this->exts->exists('#captchaFrame[src*="/distil_r_captcha"]') && !$this->exts->exists('iframe[src*="/recaptcha/api2/anchor?"]')) {
                // 	$this->exts->executeSafeScript('var myobj = document.getElementById("captchaFrame"); myobj.remove();');
                // 	$this->exts->capture('after-remove-error-recaptcha');
                // }

                $mesg = strtolower($this->exts->extract('p#signin-error-msg, p#errormsg', null, 'innerText'));
                $this->exts->log($mesg);
                if (strpos($mesg, 'es gab ein problem') !== false) {
                    $this->exts->capture('input-password-error-1');
                    $this->exts->refresh();
                    sleep(15);

                    $this->exts->capture('input-password-error-2');

                    if ($this->exts->exists($this->password_selector)) {
                        $this->exts->log("Enter password");
                        $this->exts->moveToElementAndType($this->password_selector, $this->password);
                        sleep(2);

                        $this->exts->capture('reinput-password');

                        if ($this->exts->exists($this->login_submit_selector)) {
                            $this->exts->moveToElementAndClick($this->login_submit_selector);
                            sleep(15);
                        }
                        $this->exts->capture('after-reinput-password');
                    }



                    $this->checkAndReloadUrl($this->exts->getUrl());
                    $this->callRecaptcha();
                    sleep(15);
                }

                if (strpos($mesg, "eso no coincide") !== false || (strpos($mesg, "keine") !== false && strpos($mesg, "bereinstimmung") !== false)) {
                    $this->exts->loginFailure(1);
                }

                // if (strpos($mesg, "keine") !== false && strpos($mesg, "bereinstimmung") !== false) {
                // 	$this->exts->loginFailure(1);
                // }

                if ($this->exts->exists('a#continue-get')) {
                    $this->exts->moveToElementAndClick('a#continue-get');
                    sleep(15);
                }
            }
            sleep(4);
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }
    function check2FA()
    {
        $this->exts->log('start check 2FA');
        if ($this->exts->exists('input[value="phone"]')) {
            $this->exts->moveToElementAndClick('input[value="phone"]:not(:checked)');
            sleep(2);

            $this->exts->moveToElementAndClick('button[name="submitBtn"]');
            sleep(15);

            // $this->exts->moveToElementAndClick('input#numSelected1:not(:checked)');
            // sleep(2);

            // $this->exts->moveToElementAndClick('#fullscale[action="/Phone"] button[value="text"]');
            // sleep(15);

            $pnumber_count = count($this->exts->getElements('form#fullscale[action="/Phone"] input[name="numSelected"]'));
            for ($i = 1; $i <= $pnumber_count; $i++) {
                $pnumber_sel = 'input#numSelected' . $i . ':not(:checked)';
                if ($this->exts->exists('form#fullscale[action="/Phone"] input[name="numSelected"]')) {
                    $input_val = trim($this->exts->extract($pnumber_sel, null, 'value'));

                    if ($input_val != '-1') {
                        $this->exts->moveToElementAndClick($pnumber_sel);
                        sleep(2);

                        $this->exts->moveToElementAndClick('#fullscale[action="/Phone"] button[value="text"]');
                        sleep(15);
                    } else {
                        $this->exts->moveToElementAndClick('form#fullscale[action="/Phone"] #logout a');
                        sleep(15);

                        if ($this->exts->exists('input[value="email"]')) {
                            // email 2FA
                            $this->exts->moveToElementAndClick('input[value="email"]:not(:checked)');
                            sleep(2);

                            $this->exts->moveToElementAndClick('button[name="submitBtn"]');
                            sleep(15);

                            // security question
                            if ($this->exts->exists('form#securityQuestionForm')) {
                                $this->exts->moveToElementAndClick('input#questionId1:not(:checked)');
                                sleep(2);

                                $two_factor_selector = '[name="answer"]';
                                $two_factor_submit_selector = '[name="submitBtn"]';
                                $two_factor_message_selector = '[for="questionId1"], form#securityQuestionForm p';
                                $this->checkFillTwoFactor($two_factor_selector, $two_factor_submit_selector, $two_factor_message_selector);
                                sleep(15);
                            } else if ($this->exts->exists('form#SignIn2FA input[name="pin"], #verifyCodeForm #code')) {
                                $two_factor_selector = 'form#SignIn2FA input[name="pin"], #verifyCodeForm #code';
                                $two_factor_submit_selector = 'form#SignIn2FA button#subBtn, [name="submitBtn"]';
                                $two_factor_message_selector = 'span#mfa_send_status, form#verifyCodeForm > h3, form#verifyCodeForm > p#subheader';
                                $this->checkFillTwoFactor($two_factor_selector, $two_factor_submit_selector, $two_factor_message_selector);
                                sleep(15);
                            }
                        }

                        if ($this->exts->exists('form#securityQuestionForm input[name="answer"]')) {
                            $this->checkFillSecurityAnswer();
                        }
                    }

                    if ($this->exts->exists('form#securityQuestionForm input[name="answer"]')) {
                        $this->checkFillSecurityAnswer();
                    }
                } else {
                    break;
                }
            }

            if ($this->exts->exists('a[href*="/DefaultPage"]')) {
                $this->exts->moveToElementAndClick('a[href*="/DefaultPage"]');
                sleep(15);

                $this->checkAndReloadUrl($this->exts->getUrl());
                $this->callRecaptcha();
                sleep(15);

                if ($this->exts->exists('input[value="email"]')) {
                    // email 2FA
                    $this->exts->moveToElementAndClick('input[value="email"]:not(:checked)');
                    sleep(2);

                    $this->exts->moveToElementAndClick('button[name="submitBtn"]');
                    sleep(15);

                    // security question
                    if ($this->exts->exists('form#securityQuestionForm')) {
                        $this->exts->moveToElementAndClick('input#questionId1:not(:checked)');
                        sleep(2);

                        $two_factor_selector = '[name="answer"]';
                        $two_factor_submit_selector = '[name="submitBtn"]';
                        $two_factor_message_selector = '[for="questionId1"], form#securityQuestionForm p';
                        $this->checkFillTwoFactor($two_factor_selector, $two_factor_submit_selector, $two_factor_message_selector);
                        sleep(15);
                    } else if ($this->exts->exists('form#SignIn2FA input[name="pin"], #verifyCodeForm #code')) {
                        $two_factor_selector = 'form#SignIn2FA input[name="pin"], #verifyCodeForm #code';
                        $two_factor_submit_selector = 'form#SignIn2FA button#subBtn, [name="submitBtn"]';
                        $two_factor_message_selector = 'span#mfa_send_status, form#verifyCodeForm > h3, form#verifyCodeForm > p#subheader';
                        $this->checkFillTwoFactor($two_factor_selector, $two_factor_submit_selector, $two_factor_message_selector);
                        sleep(15);
                    }
                }
            }

            if ($this->exts->exists('form#SignIn2FA input[name="pin"], #verifyCodeForm #code')) {
                $two_factor_selector = 'form#SignIn2FA input[name="pin"], #verifyCodeForm #code';
                $two_factor_submit_selector = 'form#SignIn2FA button#subBtn, [name="submitBtn"]';
                $two_factor_message_selector = 'span#mfa_send_status, form#verifyCodeForm > h3, form#verifyCodeForm > p#subheader';
                $this->checkFillTwoFactor($two_factor_selector, $two_factor_submit_selector, $two_factor_message_selector);
                sleep(15);
            }
        } else if ($this->exts->exists('input[value="email"]')) {
            // email 2FA
            $this->exts->moveToElementAndClick('input[value="email"]:not(:checked)');
            sleep(2);

            $this->exts->moveToElementAndClick('button[name="submitBtn"]');
            sleep(15);

            // security question
            if ($this->exts->exists('form#securityQuestionForm')) {
                $this->exts->moveToElementAndClick('input#questionId1:not(:checked)');
                sleep(2);

                $two_factor_selector = '[name="answer"]';
                $two_factor_submit_selector = '[name="submitBtn"]';
                $two_factor_message_selector = '[for="questionId1"], form#securityQuestionForm p';
                $this->checkFillTwoFactor($two_factor_selector, $two_factor_submit_selector, $two_factor_message_selector);
                sleep(15);
            } else if ($this->exts->exists('form#SignIn2FA input[name="pin"], #verifyCodeForm #code')) {
                $two_factor_selector = 'form#SignIn2FA input[name="pin"], #verifyCodeForm #code';
                $two_factor_submit_selector = 'form#SignIn2FA button#subBtn, [name="submitBtn"]';
                $two_factor_message_selector = 'span#mfa_send_status, form#verifyCodeForm > h3, form#verifyCodeForm > p#subheader';
                $this->checkFillTwoFactor($two_factor_selector, $two_factor_submit_selector, $two_factor_message_selector);
                sleep(15);
            }
        } else if ($this->exts->exists('div#verifyitsyou ~ div a[href*="/StartPhone/"]')) {
            $this->exts->moveToElementAndClick('div#verifyitsyou ~ div a[href*="/StartPhone/"]');
            sleep(15);

            $this->checkFillTwoFactor('input[name="code"]', 'button[name="submitBtn"]', 'div#verifyCodeContent p');
        } else if ($this->exts->exists('div#verifyitsyou ~ a[href*="/Email/"]')) {
            $this->exts->moveToElementAndClick('div#verifyitsyou ~ a[href*="/Email/"]');
            sleep(15);

            $this->checkFillTwoFactorWithEmail('div#email p');
        } else if ($this->exts->exists('div.mfa_contr div.push-2fa-main-container')) {
            $this->checkFillTwoFactorWithEmail('h2#push-main-header + div span.pushNoticeText');
        } else if ($this->exts->exists('form#SignIn2FA input[name="pin"], #verifyCodeForm #code')) {
            $two_factor_selector = 'form#SignIn2FA input[name="pin"], #verifyCodeForm #code';
            $two_factor_submit_selector = 'form#SignIn2FA button#subBtn, [name="submitBtn"]';
            $two_factor_message_selector = 'span#mfa_send_status, form#verifyCodeForm > h3, form#verifyCodeForm > p#subheader';
            $this->checkFillTwoFactor($two_factor_selector, $two_factor_submit_selector, $two_factor_message_selector);
            sleep(15);
        } else if ($this->exts->exists('form#fullscale[action="/Phone"]')) {
            $pnumber_count = count($this->exts->getElements('form#fullscale[action="/Phone"] input[name="numSelected"]'));
            for ($i = 1; $i <= $pnumber_count; $i++) {
                $pnumber_sel = 'input#numSelected' . $i . ':not(:checked)';
                if ($this->exts->exists('form#fullscale[action="/Phone"] input[name="numSelected"]')) {
                    $input_val = trim($this->exts->extract($pnumber_sel, null, 'value'));

                    if ($input_val != '-1') {
                        $this->exts->moveToElementAndClick($pnumber_sel);
                        sleep(2);

                        $this->exts->moveToElementAndClick('#fullscale[action="/Phone"] button[value="text"]');
                        sleep(15);
                    } else {
                        $this->exts->moveToElementAndClick('form#fullscale[action="/Phone"] #logout a');
                        sleep(15);

                        if ($this->exts->exists('input[value="email"]')) {
                            // email 2FA
                            $this->exts->moveToElementAndClick('input[value="email"]:not(:checked)');
                            sleep(2);

                            $this->exts->moveToElementAndClick('button[name="submitBtn"]');
                            sleep(15);

                            // security question
                            if ($this->exts->exists('form#securityQuestionForm')) {
                                $this->exts->moveToElementAndClick('input#questionId1:not(:checked)');
                                sleep(2);

                                $two_factor_selector = '[name="answer"]';
                                $two_factor_submit_selector = '[name="submitBtn"]';
                                $two_factor_message_selector = '[for="questionId1"], form#securityQuestionForm p';
                                $this->checkFillTwoFactor($two_factor_selector, $two_factor_submit_selector, $two_factor_message_selector);
                                sleep(15);
                            } else if ($this->exts->exists('form#SignIn2FA input[name="pin"], #verifyCodeForm #code')) {
                                $two_factor_selector = 'form#SignIn2FA input[name="pin"], #verifyCodeForm #code';
                                $two_factor_submit_selector = 'form#SignIn2FA button#subBtn, [name="submitBtn"]';
                                $two_factor_message_selector = 'span#mfa_send_status, form#verifyCodeForm > h3, form#verifyCodeForm > p#subheader';
                                $this->checkFillTwoFactor($two_factor_selector, $two_factor_submit_selector, $two_factor_message_selector);
                                sleep(15);
                            }
                        }

                        if ($this->exts->exists('form#securityQuestionForm input[name="answer"]')) {
                            $this->checkFillSecurityAnswer();
                        }
                    }

                    if ($this->exts->exists('form#securityQuestionForm input[name="answer"]')) {
                        $this->checkFillSecurityAnswer();
                    }
                } else {
                    break;
                }
            }

            if ($this->exts->exists('a[href*="/DefaultPage"]')) {
                $this->exts->moveToElementAndClick('a[href*="/DefaultPage"]');
                sleep(15);

                $this->checkAndReloadUrl($this->exts->getUrl());
                $this->callRecaptcha();
                sleep(15);

                if ($this->exts->exists('input[value="email"]')) {
                    // email 2FA
                    $this->exts->moveToElementAndClick('input[value="email"]:not(:checked)');
                    sleep(2);

                    $this->exts->moveToElementAndClick('button[name="submitBtn"]');
                    sleep(15);

                    // security question
                    if ($this->exts->exists('form#securityQuestionForm')) {
                        $this->exts->moveToElementAndClick('input#questionId1:not(:checked)');
                        sleep(2);

                        $two_factor_selector = '[name="answer"]';
                        $two_factor_submit_selector = '[name="submitBtn"]';
                        $two_factor_message_selector = '[for="questionId1"], form#securityQuestionForm p';
                        $this->checkFillTwoFactor($two_factor_selector, $two_factor_submit_selector, $two_factor_message_selector);
                        sleep(15);
                    } else if ($this->exts->exists('form#SignIn2FA input[name="pin"], #verifyCodeForm #code')) {
                        $two_factor_selector = 'form#SignIn2FA input[name="pin"], #verifyCodeForm #code';
                        $two_factor_submit_selector = 'form#SignIn2FA button#subBtn, [name="submitBtn"]';
                        $two_factor_message_selector = 'span#mfa_send_status, form#verifyCodeForm > h3, form#verifyCodeForm > p#subheader';
                        $this->checkFillTwoFactor($two_factor_selector, $two_factor_submit_selector, $two_factor_message_selector);
                        sleep(15);
                    }
                }
            }

            if ($this->exts->exists('form#SignIn2FA input[name="pin"], #verifyCodeForm #code')) {
                $two_factor_selector = 'form#SignIn2FA input[name="pin"], #verifyCodeForm #code';
                $two_factor_submit_selector = 'form#SignIn2FA button#subBtn, [name="submitBtn"]';
                $two_factor_message_selector = 'span#mfa_send_status, form#verifyCodeForm > h3, form#verifyCodeForm > p#subheader';
                $this->checkFillTwoFactor($two_factor_selector, $two_factor_submit_selector, $two_factor_message_selector);
                sleep(15);
            }
        } else if ($this->exts->exists('#smsWithCode-btn')) {
            $this->exts->log('inside : smsWithCode-btn');
            $this->exts->moveToElementAndClick('#smsWithCode-btn');
            sleep(13);
            $this->checkFillTwoFactor('div#pin-boxes input', 'button#verify-btn', 'p#info-header-sub');
        } else if ($this->exts->exists('#smsWithCode-radio-btn-label')) {
            $this->exts->log('inside : smsWithCode-radio-btn-label');
            $this->exts->moveToElementAndClick('#smsWithCode-radio-btn-label');
            sleep(5);
            $this->exts->moveToElementAndClick('button[id="send-button"]');
            sleep(13);
            $this->checkFillTwoFactor('div#pin-boxes input', 'button#verify-btn', 'p#info-header-sub');
        } else if ($this->exts->exists('#emailWithCode-btn')) {
            $this->exts->log('inside : emailWithCode-btn');
            $this->exts->moveToElementAndClick('#emailWithCode-btn');
            sleep(13);
            $this->checkFillTwoFactor('div#pin-boxes input', 'button#verify-btn', 'p#info-header-sub');
        } else if ($this->exts->exists('#emailWithCode-radio-btn-label')) {
            $this->exts->log('inside :emailWithCode-radio-btn-label');
            $this->exts->moveToElementAndClick('#emailWithCode-radio-btn-label');
            sleep(5);
            $this->exts->moveToElementAndClick('button[id="send-button"]');
            sleep(13);
            $this->checkFillTwoFactor('div#pin-boxes input', 'button#verify-btn', 'p#info-header-sub');
        } else if ($this->exts->exists('input[name="pin_otp"]')) {
            $this->checkFillTwoFactor('input[name="pin_otp"]', 'button#subBtn', '#mfa_send_status');
        }

        if ($this->exts->exists('div#pin-boxes input')) {
            sleep(13);
            $this->checkFillTwoFactor('div#pin-boxes input', '', 'p#info-header-sub');
        }

        if ($this->exts->exists('form#securityQuestionForm input[name="answer"]')) {
            $this->checkFillSecurityAnswer();
        }
    }
    private function checkFillRecaptcha($url)
    {
        $this->exts->log(__FUNCTION__);
        $recaptcha_iframe_selector = 'iframe[src*="/recaptcha/api2/anchor?"]';
        $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
        if ($this->exts->exists($recaptcha_iframe_selector)) {
            $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
            $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
            $this->exts->log("iframe url  - " . $iframeUrl);
            $this->exts->log("SiteKey - " . $data_siteKey);

            $isCaptchaSolved = $this->exts->processRecaptcha($url, $data_siteKey, false);
            $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);

            if ($isCaptchaSolved) {
                // Step 1 fill answer to textarea
                $this->exts->log(__FUNCTION__ . "::filling reCaptcha response..");
                $recaptcha_textareas =  $this->exts->getElements($recaptcha_textarea_selector);
                for ($i = 0; $i < count($recaptcha_textareas); $i++) {
                    $this->exts->executeSafeScript("arguments[0].innerHTML = '" . $this->exts->recaptcha_answer . "';", [$recaptcha_textareas[$i]]);
                }
                sleep(2);
                $this->exts->capture('recaptcha-filled');

                // Step 2, check if callback function need executed
                $gcallbackFunction = $this->exts->executeSafeScript('
					if(document.querySelector("[data-callback]") != null){
						return document.querySelector("[data-callback]").getAttribute("data-callback");
					}

					var result = ""; var found = false;
					function recurse (cur, prop, deep) {
					    if(deep > 5 || found){ return;}console.log(prop);
					    try {
					        if(cur == undefined || cur == null || cur instanceof Element || Object(cur) !== cur || Array.isArray(cur)){ return;}
					        if(prop.indexOf(".callback") > -1){result = prop; found = true; return;
					        } else { deep++;
					            for (var p in cur) { recurse(cur[p], prop ? prop + "." + p : p, deep);}
					        }
					    } catch(ex) { console.log("ERROR in function: " + ex); return; }
					}

					recurse(___grecaptcha_cfg.clients[0], "", 0);
					return found ? "___grecaptcha_cfg.clients[0]." + result : null;
				');
                $this->exts->log('Callback function: ' . $gcallbackFunction);
                if ($gcallbackFunction != null) {
                    $this->exts->executeSafeScript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
                    sleep(10);
                }
                if ($this->exts->exists('#distilCaptchaForm #dCF_input_complete')) {
                    $this->meet_recaptcha_completed_button = true;
                    $this->exts->moveToElementAndClick('#distilCaptchaForm #dCF_input_complete');
                    sleep(10);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
        }
    }
    private function checkFillTwoFactor($two_factor_selector, $two_factor_submit_selector, $two_factor_message_selector)
    {
        if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
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
                if ($two_factor_selector == 'div#pin-boxes input') {
                    $this->exts->click_by_xdotool($two_factor_selector);
                    $this->exts->type_text_by_xdotool($two_factor_code);
                } else {
                    $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
                    $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
                }

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                if ($this->exts->exists($two_factor_submit_selector)) {
                    $this->exts->moveToElementAndClick($two_factor_submit_selector);
                    sleep(15);
                }


                if ($this->exts->getElement($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->exts->notification_uid = '';
                    $this->checkFillTwoFactor($two_factor_selector, $two_factor_submit_selector, $two_factor_message_selector);
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }
    private function checkFillTwoFactorWithEmail($two_factor_message_selector)
    {
        if ($this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
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
                // $this->exts->sendKeys($two_factor_selector, $two_factor_code);

                // $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                // sleep(3);
                // $this->exts->capture("2.2-two-factor-filled-".$this->exts->two_factor_attempts);

                // $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(15);

                if ($this->exts->getElement($two_factor_message_selector) == null && !$this->exts->exists('div.mfa_contr div.push-2fa-main-container')) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->exts->notification_uid = '';
                    $this->checkFillTwoFactorWithEmail($two_factor_message_selector);
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }
    private function checkFillSecurityAnswer()
    {
        $two_factor_selector = 'form#securityQuestionForm input[name="answer"]';
        $two_factor_message_selector = 'form#securityQuestionForm h3, form#securityQuestionForm p';
        $two_factor_submit_selector = 'form#securityQuestionForm button[name="submitBtn"]';

        if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
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

                $mes_err = strtolower($this->exts->extract('div#myerr p', null, 'innerText'));
                if ($this->exts->getElement($two_factor_selector) == null && strpos($mes_err, 'sie haben zu viele falsche antworten eingegeben') === false) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->exts->notification_uid = '';
                    $this->checkFillSecurityAnswer();
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }
    private function checkAndReloadUrl($url)
    {
        $msg = trim(strtolower($this->exts->extract('div.pgHeading h1', null, 'innerText')));
        $this->exts->log('msg: ' . $msg);
        $msg1 = trim(strtolower($this->exts->extract('div#main-message h1', null, 'innerText')));
        $this->exts->log('msg: ' . $msg1);
        if (strpos($msg, 'unable to identify your browser') !== false || strpos($msg1, 'be reached') !== false) {
            $this->exts->refresh();
            sleep(15);
            $this->exts->openUrl($url);
            sleep(15);
        } else if (strpos($msg, 'browser konnte nicht erkannt werden') !== false) {
            $this->exts->openUrl($url);
            sleep(15);
        } else if (!$this->exts->exists('a[href="https://www.ebay.de"] img[id*="logo"]')) {
            $this->exts->refresh();
            sleep(15);
        }

        $msg = trim(strtolower($this->exts->extract('div#myerr p', null, 'innerText')));
        if (strpos($msg, 'technischer fehler aufgetreten') !== false) {
            $this->exts->moveToElementAndClick('a[href*="/DefaultPage"]');
            sleep(15);
        }
    }
    private function callRecaptcha()
    {
        for ($i = 0; $i <= 2; $i++) {
            if ($this->exts->exists('iframe[src*="/recaptcha/api2/anchor?"]')) {
                $current_url = $this->exts->getUrl();
                $this->checkFillRecaptcha($current_url);
            } elseif ($this->exts->exists('iframe[src*="hcaptcha"]')) {
                $current_url = $this->exts->getUrl();
                $this->checkFillHcaptcha();
                sleep(20);
                if ($this->exts->urlContains('/splashui/captcha_submit')) {
                    $this->exts->capture('h-captcha-failed');
                }
            } else if ($this->exts->exists('form#captcha_form') && !$this->exts->exists('iframe[src*="hcaptcha"]')) {
                sleep(65);
                if ($this->exts->exists('form#captcha_form') && !$this->exts->exists('iframe[src*="hcaptcha"]')) {
                    $this->exts->refresh();
                    sleep(25);
                    $this->exts->capture('refresh-to-show-hcaptcha');
                    $current_url = $this->exts->getUrl();
                    $this->checkFillHcaptcha();
                    sleep(20);
                    if ($this->exts->urlContains('/splashui/captcha_submit')) {
                        $this->exts->capture('h-captcha-failed');
                    }
                }
            } else {
                if ($this->exts->exists('iframe[src*="distil_r_captcha.html"]')) {
                    $iframe_src_url = $this->exts->extract('iframe[src*="distil_r_captcha.html"]', null, 'src');
                    $this->switchToFrame('iframe[src*="distil_r_captcha.html"]');
                    if ($this->exts->exists('iframe[src*="/recaptcha/api2/anchor?"]')) {
                        $this->checkFillRecaptcha($iframe_src_url);
                    } else {
                        $this->exts->switchToDefault();
                    }
                }
            }
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
    private function checkFillHcaptcha()
    {
        $hcaptcha_iframe_selector = '#captcha_form iframe[src*="hcaptcha"]';
        if ($this->exts->exists($hcaptcha_iframe_selector)) {
            $iframeUrl = $this->exts->extract($hcaptcha_iframe_selector, null, 'src');
            $query_string = parse_url($iframeUrl, PHP_URL_FRAGMENT);
            // Parse the query string into an array
            parse_str($query_string, $query_params);
            // Extract the sitekey
            $data_siteKey = isset($query_params['sitekey']) ? $query_params['sitekey'] : "Not Found";
            $this->exts->log($data_siteKey);
            $jsonRes = $this->exts->processHumanCaptcha($data_siteKey, 'https://www.ebay.de');
            $captchaScript = '
                function submitToken(token) {
                  document.querySelector("[name=g-recaptcha-response]").innerText = token;
                  document.querySelector("[name=h-captcha-response]").innerText = token;
                }
                submitToken(arguments[0]);
        	    ';
            $params = array($jsonRes);

            sleep(2);
            $guiId = $this->exts->extract('input[id*="captcha-data"]', null, 'value');
            $guiId = trim(explode('"', end(explode('"guid":"', $guiId)))[0]);
            $this->exts->log('guiId: ' . $guiId);
            $this->exts->executeSafeScript($captchaScript, $params);
            $str_command = 'var btn = document.createElement("INPUT");
		    					var att = document.createAttribute("type");
								att.value = "hidden";
								btn.setAttributeNode(att);
								var att = document.createAttribute("name");
								att.value = "captchaTokenInput";
								btn.setAttributeNode(att);
								var att = document.createAttribute("value");
								btn.setAttributeNode(att);
							  	form1 = document.querySelector("#captcha_form");
							  	form1.appendChild(btn);';
            $this->exts->executeSafeScript($str_command);
            sleep(2);
            $captchaScript = '
                function submitToken1(token) {
                  document.querySelector("[name=captchaTokenInput]").value = token;
                }
                submitToken1(arguments[0]);
        	    ';
            $captchaTokenInputValue = '%7B%22guid%22%3A%22' . $guiId . '%22%2C%22provider%22%3A%22' . 'hcaptcha' . '%22%2C%22appName%22%3A%22' . 'orch' . '%22%2C%22token%22%3A%22' . $jsonRes . '%22%7D';
            $params = array($captchaTokenInputValue);
            $this->exts->executeSafeScript($captchaScript, $params);

            $this->exts->log($this->exts->extract('input[name="captchaTokenInput"]', null, 'value'));
            sleep(2);
            $gcallbackFunction = 'captchaCallback';
            $this->exts->executeSafeScript($gcallbackFunction . '("' . $jsonRes . '");');

            $this->exts->switchToDefault();
            sleep(5);
        }
    }
    private function reLogin($current_url)
    {
        $this->exts->log('start relogin function');
        if ($this->exts->exists($this->password_selector) && $this->exts->exists('form#signin-form div.hide input#userId, form#signin-form div.hide input#userid') && $this->exts->exists('button#sgnBt')) {
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            $this->exts->moveToElementAndClick('button#sgnBt');
            sleep(15);

            if ($this->exts->exists($this->password_selector) && $this->exts->exists('form#signin-form div.hide input#userId, form#signin-form div.hide input#userid') && $this->exts->exists('button#sgnBt')) {
                $this->exts->openUrl($this->base_url);
                sleep(8);
                $this->exts->openUrl($this->invoice_url);
                sleep(15);
            }
        } else {
            $this->exts->openUrl($this->base_url);
            sleep(15);

            $this->exts->moveToElementAndClick($this->signinLink);
            sleep(15);

            $error_page = trim(strtolower($this->exts->extract('div.pgHeading h1', null, 'innerText')));
            if (strpos($error_page, 'browser konnte nicht erkannt werden') !== false) {
                // $this->exts->clearCookies();
                // sleep(1);

                $this->exts->openUrl($this->invoice_url);
                sleep(15);
            }

            $this->checkAndReloadUrl($this->exts->getUrl());
            $this->callRecaptcha();
            sleep(15);

            if ($this->exts->exists($this->password_selector) && $this->exts->exists('form#signin-form div.hide input#userId, form#signin-form div.hide input#userid') && $this->exts->exists('button#sgnBt')) {
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(2);

                $this->exts->moveToElementAndClick('button#sgnBt');
                sleep(15);
            } else {
                $this->fillForm(0);
                sleep(15);
            }
        }



        $this->checkAndReloadUrl($this->exts->getUrl());
        $this->callRecaptcha();
        sleep(15);

        if ($this->exts->exists('form#securityQuestionForm input[name="answer"]')) {
            $this->checkFillSecurityAnswer();
        }

        // phone 2FA
        $this->check2FA();
        $this->check2FA();

        // click some button when finished 2FA
        if ($this->exts->getElement('form[name="contactInfoForm"] a#rmdLtr') != null) {
            $this->exts->moveToElementAndClick('form[name="contactInfoForm"] a#rmdLtr');
        } else if ($this->exts->getElement('#fullscale [name="submitBtn"]') != null) {
            $this->exts->moveToElementAndClick('#fullscale [name="submitBtn"]');
        } else if ($this->exts->getElement('.primsecbtns [value="text"]') != null) {
            $this->exts->moveToElementAndClick('.primsecbtns [value="text"]');
        } else if ($this->exts->getElement("a#continue-get") != null) {
            $this->exts->moveToElementAndClick("a#continue-get");
        }

        sleep(15);

        if ($this->exts->exists('form#securityQuestionForm input[name="answer"]')) {
            $this->checkFillSecurityAnswer();
        }

        // Check login after finished 2FA
        if ($this->checkLogin()) {
            $this->exts->openUrl($this->invoice_url);
            sleep(15);

            $this->checkAndReloadUrl($this->invoice_url);
            $this->callRecaptcha();
            sleep(15);

            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            $this->exts->openUrl($current_url);
            sleep(15);
        }
        //  else {
        // 	if ($this->exts->exists('span.mi-er span.sd-err, #errf')) {
        // 		$this->exts->capture("Wrong credentials");
        // 		$this->exts->loginFailure(1);
        // 	} else {
        // 		$this->exts->capture("LoginFailed");
        // 		$this->exts->loginFailure();
        // 	}

        // }
    }

    private function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            $buttons_len = count($this->exts->querySelectorAll('div[role="navigation"] ul#gh-topl button'));
            $this->exts->log('button len: ' . $buttons_len);
            for ($i = 0; $i < $buttons_len; $i++) {
                $button = $this->exts->querySelectorAll('div[role="navigation"] ul#gh-topl button')[$i];
                $bt_text = trim(strtolower($button->getAttribute('innerText')));
                if (strpos($bt_text, 'hi ', 0) !== false || strpos($bt_text, 'hello ', 0) !== false) {
                    // try{
                    //  $this->exts->log('Click account button');
                    //  $button->click();
                    // } catch(\Exception $exception){
                    //  $this->exts->log('Click account button by javascript');
                    //  $this->exts->executeSafeScript("arguments[0].click()", [$button]);
                    // }
                    // sleep(5);
                    $isLoggedIn = true;
                    break;
                }
            }

            if ($this->exts->exists('a[href$="MyEbay&gbh=1"], form#secretQuesForm, form#contactInfoForm, select[name="invoiceMonthYear"], a#continue-get') && !$this->exts->exists($this->username_selector) && !$this->exts->exists($this->password_selector) && !$this->exts->exists('[class*="guest"] a[href*="SignIn"][href*="signin.ebay.co.uk"]') && !$this->exts->exists('div.signout-banner a#signin-link')) {
                $this->exts->log(">>>>>>>>>>>>>>>Login success!!!!");
                $isLoggedIn = true;
            }

            if ($this->exts->exists('span.gh-identity button')) {
                $this->exts->log(">>>>>>>>>>>>>>>Login success!!!!");
                $isLoggedIn = true;
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception->getMessage());
        }

        return $isLoggedIn;
    }

    // -------------------- GOOGLE login
    public $google_username_selector = 'input[name="identifier"]:not([aria-hidden="true"])';
    public $google_submit_username_selector = '#identifierNext, input#submit, input#next';
    public $google_password_selector = 'input[name="password"], input[name="Passwd"]';
    public $google_submit_password_selector = '#passwordNext, #gaia_loginform input#signIn, #passwordNext button, input#submit';
    private function loginGoogleIfRequired()
    {
        if ($this->exts->urlContains('google.')) {
            if ($this->exts->urlContains('/webreauth')) {
                $this->exts->moveToElementAndClick('#identifierNext');
                sleep(6);
            }
            $this->googleCheckFillLogin();
            sleep(5);
            if ($this->exts->querySelector('input[name="password"][aria-invalid="true"], input[name="identifier"][aria-invalid="true"], input[name="Passwd"][aria-invalid="true"]') != null) {
                $this->exts->loginFailure(1);
            }
            if ($this->exts->querySelector('form[action*="/signin/v2/challenge/password/"] input[name="password"][aria-invalid="true"], input[name="identifier"][aria-invalid="true"], span#passwordError, input[name="Passwd"][aria-invalid="true"]') != null) {
                $this->exts->loginFailure(1);
            }

            // Click next if confirm form showed
            $this->exts->moveToElementAndClick('div[data-ownership-changed-phone-number] div:nth-child(2) > [role="button"]');
            $this->googleCheckTwoFactorMethod();

            if ($this->exts->exists('#smsauth-interstitial-remindbutton')) {
                $this->exts->moveToElementAndClick('#smsauth-interstitial-remindbutton');
                sleep(10);
            }
            if ($this->exts->exists('#tos_form input#accept')) {
                $this->exts->moveToElementAndClick('#tos_form input#accept');
                sleep(10);
            }
            if ($this->exts->exists('[wizard-step-uid="RecoveryOptionsCollectionWizard:starter"] div:last-child > [role="button"]')) {
                $this->exts->moveToElementAndClick('[wizard-step-uid="RecoveryOptionsCollectionWizard:starter"] div:last-child > [role="button"]');
                sleep(10);
            }
            if ($this->exts->exists('.action-button.signin-button + a.setup-button[href*="/two-step-verification/"]')) {
                // SKIP setup 2FA
                $this->exts->moveToElementAndClick('.action-button.signin-button');
                sleep(10);
            }
            if ($this->exts->exists('a.setup-button[href*="two-step-verification/enroll"] + button.signin-button')) {
                // SKIP setup 2FA
                $this->exts->moveToElementAndClick('a.setup-button[href*="two-step-verification/enroll"] + button.signin-button');
                sleep(10);
            }
            if ($this->exts->exists('[action="/signin/newfeatures/save"] #optionsButton ~ [role="button"]')) {
                $this->exts->moveToElementAndClick('[action="/signin/newfeatures/save"] #optionsButton ~ [role="button"]');
                sleep(10);
            }
            if ($this->exts->exists('input[name="later"]') && $this->exts->urlContains('/AddressNoLongerAvailable')) {
                $this->exts->moveToElementAndClick('input[name="later"]');
                sleep(7);
            }
            if ($this->exts->exists('#editLanguageAndContactForm a[href*="/adsense/app"]')) {
                $this->exts->moveToElementAndClick('#editLanguageAndContactForm a[href*="/adsense/app"]');
                sleep(7);
            }
            if ($this->exts->exists('[data-view-instance-id="/web/chip-V0"] [role="button"]:first-child [jsslot]')) {
                $this->exts->moveToElementAndClick('[data-view-instance-id="/web/chip-V0"] [role="button"]:first-child [jsslot]');
                sleep(10);
            }

            if ($this->exts->exists('#submit_approve_access')) {
                $this->exts->moveToElementAndClick('#submit_approve_access');
                sleep(10);
            } else if ($this->exts->exists('form #approve_button[name="submit_true"]')) {
                // An application is requesting permission to access your Google Account.
                // Click allow
                $this->exts->moveToElementAndClick('form #approve_button[name="submit_true"]');
                sleep(10);
            }
            $this->exts->capture("3-google-before-back-to-main-tab");
        } else {
            $this->exts->log(__FUNCTION__ . '::Not required google login.');
            $this->exts->capture("3-no-google-required");
        }
    }
    private function googleCheckFillLogin()
    {
        if ($this->exts->exists('form ul li [role="link"][data-identifier]')) {
            $this->exts->moveToElementAndClick('form ul li [role="link"][data-identifier]');
            sleep(5);
        }

        if ($this->exts->urlContainsAny(['/ServiceLogin/identifier', '/ServiceLogin/webreauth']) && $this->exts->exists($this->google_submit_username_selector) && !$this->exts->exists($this->google_username_selector)) {
            $this->exts->capture("google-verify-it-you");
            // To help keep your account secure, Google needs to verify it’s you. Please sign in again to continue to Google Ads
            $this->exts->moveToElementAndClick($this->google_submit_username_selector);
            sleep(5);
        }

        $this->exts->capture("2-google-login-page");
        if ($this->exts->exists($this->google_username_selector)) {
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->google_username_selector, $this->username);
            sleep(1);
            $this->exts->moveToElementAndClick($this->google_submit_username_selector);
            sleep(5);
            if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists($this->google_password_selector) && $this->exts->exists($this->google_username_selector)) {
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                $this->exts->moveToElementAndClick($this->google_submit_username_selector);
                sleep(5);
                if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists($this->google_password_selector) && $this->exts->exists($this->google_username_selector)) {
                    $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                    $this->exts->moveToElementAndClick($this->google_submit_username_selector);
                    sleep(5);
                }
                if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists($this->google_password_selector) && $this->exts->exists($this->google_username_selector)) {
                    $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                    $this->exts->moveToElementAndClick($this->google_submit_username_selector);
                    sleep(5);
                }
            } else if ($this->exts->urlContains('/challenge/recaptcha')) {
                $this->googlecheckFillRecaptcha();
                $this->exts->moveToElementAndClick('[data-primary-action-label] > div > div:first-child button');
                sleep(5);
            }

            // Which account do you want to use?
            if ($this->exts->exists('form[action*="/lookup"] button.account-chooser-button')) {
                $this->exts->moveToElementAndClick('form[action*="/lookup"] button.account-chooser-button');
                sleep(5);
            }
            if ($this->exts->exists('[data-view-id="prbTle"] form [role="link"][data-profileindex]')) {
                $this->exts->moveToElementAndClick('[data-view-id="prbTle"] form [role="link"][data-profileindex]');
                sleep(5);
            }
        }

        if ($this->exts->exists($this->google_password_selector)) {
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
            sleep(1);
            if ($this->exts->exists('#captchaimg[src]')) {
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
            }

            $this->exts->capture("2-google-login-page-filled");
            $this->exts->moveToElementAndClick($this->google_submit_password_selector);
            sleep(5);
            if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists('input[name="password"][aria-invalid="true"]') && $this->exts->exists($this->google_password_selector)) {
                $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
                sleep(1);
                if ($this->exts->exists('#captchaimg[src]')) {
                    $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                }
                $this->exts->moveToElementAndClick($this->google_submit_password_selector);
                sleep(5);
                if ($this->exts->exists('#captchaimg[src]') && $this->exts->exists($this->google_password_selector)) {
                    $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
                    sleep(1);
                    $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                    $this->exts->capture("2-google-login-pageandcaptcha-filled");
                    $this->exts->moveToElementAndClick($this->google_submit_password_selector);
                }
            } else {
                $this->googlecheckFillRecaptcha();
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Google password page not found');
            $this->exts->capture("2-google-password-page-not-found");
        }
    }
    private function googleCheckTwoFactorMethod()
    {
        // Currently we met many two factor methods
        // - Confirm email account for account recovery
        // - Confirm telephone number for account recovery
        // - Call to your assigned phone number
        // - confirm sms code
        // - Solve the notification has sent to smart phone
        // - Use security key usb
        // - Use your phone or tablet to get a security code (EVEN IF IT'S OFFLINE)
        $this->exts->log(__FUNCTION__);
        sleep(5);
        $this->exts->capture("2.0-before-check-two-factor-google");
        // STEP 0 (updated special case 28-Mar-2020): If we meet a unsolvable, click to back to method choosen list
        if ($this->exts->exists('#assistActionId') && $this->exts->exists('[data-illustration="securityKeyLaptopAnim"]')) {
            $this->exts->moveToElementAndClick('#assistActionId');
            sleep(5);
        } else if ($this->exts->urlContains('/challenge/wa') && strpos($this->exts->extract('form header h2'), 'QR-Code') !== false) {
            // (updated special case 28-Mar-2020): If we meet QR-Code, click 'Choose another option' to back to method choosen list
            $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
            sleep(5);
            $this->exts->capture("2.0-backed-methods-list-google");
            if ($this->exts->urlContains('/challenge/wa') && strpos($this->exts->extract('form header h2'), 'QR-Code') !== false) {
                $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
                sleep(5);
            }
        } else if ($this->exts->urlContains('/sk/webauthn')) {
            $node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-" . $this->exts->process_uid;
            exec("sudo docker exec " . $node_name . " bash -c 'xdotool key Return'");
            sleep(3);
            $this->exts->capture("2.0-cancel-security-usb-google");
            $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
            sleep(5);
            $this->exts->capture("2.0-backed-methods-list-google");
        } else if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
            // (updated special case 09-May-2020): If Notification method showed immediately, This method often make user confused
            // So, We try to click 'Choose another option' in order to select easier method
            $this->exts->moveToElementAndClick('[data-view-id] > div > div:nth-child(2)  div:nth-child(2) > [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button, button#assistiveActionOutOfQuota');
            sleep(7);
            $this->exts->capture("2.0-backed-methods-list-google");
        } else if ($this->exts->exists('input[name="ootpPin"]')) {
            // (updated special case 11-Jun-2020): If "Verify by offline device" immediately, This method often make user confused and maybe they don't have device on hand
            // So, We try to click 'Choose another option' in order to select easier method
            $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
            sleep(7);
            $this->exts->capture("2.0-backed-methods-list-google");
        }

        // STEP 1: Check if list of two factor methods showed, select first
        if ($this->exts->exists('li [data-challengetype]:not([data-challengeunavailable="true"])')) {
            // We most RECOMMEND confirm security phone or email, then other method
            if ($this->exts->exists('li [data-challengetype="13"]:not([data-challengeunavailable="true"])') && isset($this->security_phone_number) && $this->security_phone_number != '') {
                $this->exts->moveToElementAndClick('li [data-challengetype="13"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype="12"]:not([data-challengeunavailable="true"])') && isset($this->recovery_email) && $this->recovery_email != '') {
                $this->exts->moveToElementAndClick('li [data-challengetype="12"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype="6"]:not([data-challengeunavailable="true"])')) {
                // We RECOMMEND method type = 6 is get code from Google Authenticator
                $this->exts->moveToElementAndClick('li [data-challengetype="6"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])')) {
                // We second RECOMMEND method type = 9 is get code from SMS
                $this->exts->moveToElementAndClick('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"]:not([data-challengeunavailable="true"])')) {
                // We recommend method type = 4 and [data-sendauthzenprompt="true"] is  Tap YES on your smartphone or tablet
                $this->exts->moveToElementAndClick('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype="5"]:not([data-challengeunavailable="true"])')) {
                // Use a smartphone or tablet to receive a security code (even when offline)
                $this->exts->moveToElementAndClick('li [data-challengetype="5"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype]:not([data-challengetype="4"]):not([data-challengetype="2"]):not([data-challengeunavailable="true"])')) {
                // We DONT recommend method is QR code OR is Security USB, we can not solve this type of 2FA
                $this->exts->moveToElementAndClick('li [data-challengetype]:not([data-challengetype="4"]):not([data-challengetype="2"]):not([data-challengeunavailable="true"])');
            } else {
                $this->exts->moveToElementAndClick('li [data-challengetype]:not([data-challengeunavailable="true"])');
            }
            sleep(10);
        } else if ($this->exts->exists('#smsButton, [data-illustration="accountRecoverySmsPin"]')) {
            // Sometime user must confirm before google send sms
            $this->exts->moveToElementAndClick('#smsButton, div:first-child > [role="button"], [data-secondary-action-label] > div > div:nth-child(1) button');
            sleep(10);
        } else if ($this->exts->exists('#authzenNext') && $this->exts->exists('[data-view-id*="authzenView"], [data-illustration*="authzen"]')) {
            $this->exts->moveToElementAndClick('[data-view-id] #authzenNext');
            sleep(10);
        } else if ($this->exts->exists('#idvpreregisteredemailNext') && !$this->exts->exists('form input:not([type="hidden"])')) {
            $this->exts->moveToElementAndClick('#idvpreregisteredemailNext');
            sleep(10);
        }

        // STEP 2: (Optional)
        if ($this->exts->exists('input#knowledge-preregistered-email-response, input[name="knowledgePreregisteredEmailResponse"]')) {
            // If methos is recovery email, send 2FA to ask for email
            $this->exts->two_factor_attempts = 2;
            $input_selector = 'input#knowledge-preregistered-email-response, input[name="knowledgePreregisteredEmailResponse"]';
            $message_selector = '[data-view-id] form section div > div[jsslot] > div:first-child';
            $submit_selector = '';
            if (isset($this->recovery_email) && $this->recovery_email != '') {
                $this->exts->moveToElementAndType($input_selector, $this->recovery_email);
                $this->exts->type_key_by_xdotool("Return");
                sleep(7);
            }
            if ($this->exts->exists($input_selector)) {
                $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
                sleep(5);
            }
        } else if ($this->exts->exists('[data-view-id*="knowledgePreregisteredPhoneView"] input[type="tel"]')) {
            // If methos confirm recovery phone number, send 2FA to ask
            $this->exts->two_factor_attempts = 3;
            $input_selector = '[data-view-id*="knowledgePreregisteredPhoneView"] input[type="tel"]';
            $message_selector = '[data-view-id] form section div > div[jsslot] > div:first-child';
            $submit_selector = '';
            if (isset($this->security_phone_number) && $this->security_phone_number != '') {
                $this->exts->moveToElementAndType($input_selector, $this->security_phone_number);
                $this->exts->type_key_by_xdotool("Return");
                sleep(5);
            }
            if ($this->exts->exists($input_selector)) {
                $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
                sleep(5);
            }
        } else if ($this->exts->exists('input#phoneNumberId')) {
            // Enter a phone number to receive an SMS with a confirmation code.
            $this->exts->two_factor_attempts = 3;
            $input_selector = 'input#phoneNumberId';
            $message_selector = '[data-view-id] form section > div > div > div:first-child';
            $submit_selector = '';
            if (isset($this->security_phone_number) && $this->security_phone_number != '') {
                $this->exts->moveToElementAndType($input_selector, $this->security_phone_number);
                $this->exts->type_key_by_xdotool("Return");
                sleep(7);
            }
            if ($this->exts->exists($input_selector)) {
                $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
            }
        } else if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
            // Check your smartphone. Google has sent a notification to your smartphone. Tap Yes in the notification, then tap 91 on your smartphone to continue
            $this->exts->two_factor_attempts = 3;
            $message_selector = '[data-view-id*="authzenView"] form, [data-view-id] form[method="post"]';
            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Enter \"OK\" after confirmation on device";
            $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->googleFillTwoFactor(null, null, '');
            sleep(5);
        } else if ($this->exts->exists('[data-view-id*="securityKeyWebAuthnView"], [data-view-id*="securityKeyView"]')) {
            // Method: insert your security key and touch it
            $this->exts->two_factor_attempts = 3;
            $this->exts->two_factor_notif_msg_en = 'Use chrome, login then insert your security key and touch it' . "\n>>>Enter \"OK\" after confirmation on device";
            $this->exts->two_factor_notif_msg_de = '[Chrome] Stecken Sie den Sicherheitsschlussel in den USB-Anschluss Ihres Computers ein. Wenn er eine Taste hat, tippen Sie darauf.' . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->googleFillTwoFactor(null, null, '');
            sleep(5);
            // choose another option: #assistActionId
        }

        // STEP 3: (Optional)  After choose method and confirm email or phone or.., google may asked confirm one more time before send code
        if ($this->exts->exists('#smsButton, [data-illustration="accountRecoverySmsPin"]')) {
            // Sometime user must confirm before google send sms
            $this->exts->moveToElementAndClick('#smsButton, div:first-child > [role="button"], [data-secondary-action-label] > div > div:nth-child(1) button');
            sleep(10);
        } else if ($this->exts->exists('#authzenNext') && $this->exts->exists('[data-view-id*="authzenView"], [data-illustration*="authzen"]')) {
            $this->exts->moveToElementAndClick('[data-view-id] #authzenNext');
            sleep(10);
        } else if ($this->exts->exists('#idvpreregisteredemailNext') && !$this->exts->exists('form input:not([type="hidden"])')) {
            $this->exts->moveToElementAndClick('#idvpreregisteredemailNext');
            sleep(10);
        } else if (count($this->exts->getElements('li [data-challengetype]:not([data-challengeunavailable="true"]):not([data-challengetype="undefined"])')) > 0) {
            $this->exts->moveToElementAndClick('li [data-challengetype]:not([data-challengeunavailable="true"]):not([data-challengetype="undefined"])');
            sleep(7);
        }


        // STEP 4: input code
        if ($this->exts->exists('form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin')) {
            $input_selector = 'form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin';
            $message_selector = 'form > span > section > div > div > div:first-child';
            $submit_selector = '#idvPreregisteredPhoneNext, #idvpreregisteredemailNext, #totpNext, #idvanyphoneverifyNext, #backupCodeNext, #view_container div.pwWryf.bxPAYd div.zQJV3 div.qhFLie > div > div > button';
            $this->exts->two_factor_attempts = 0;
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector);
        } else if ($this->exts->exists('input[name="ootpPin"], input#securityKeyOtpInputId')) {
            $input_selector = 'input[name="ootpPin"], input#securityKeyOtpInputId';
            $message_selector = 'form > span > section > div > div > div:first-child';
            $submit_selector = '';
            $this->exts->two_factor_attempts = 0;
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
        } else if ($this->exts->exists('input[name="Pin"]')) {
            $input_selector = 'input[name="Pin"]';
            $message_selector = 'form > span > section > div > div > div:first-child';
            $submit_selector = '';
            $this->exts->two_factor_attempts = 0;
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
        } else if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
            // Check your smartphone. Google has sent a notification to your smartphone. Tap Yes in the notification, then tap 91 on your smartphone to continue
            $this->exts->two_factor_attempts = 3;
            $message_selector = '[data-view-id*="authzenView"] form, [data-view-id] form[method="post"]';
            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Enter \"OK\" after confirmation on device";
            $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->googleFillTwoFactor(null, null, '');
            sleep(5);
        } else if ($this->exts->exists('input[name="secretQuestionResponse"]')) {
            $input_selector = 'input[name="secretQuestionResponse"]';
            $message_selector = 'form > span > section > div > div > div:first-child';
            $submit_selector = '[data-secondary-action-label] > div > div:nth-child(1) button';
            $this->exts->two_factor_attempts = 0;
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector);
        }
    }
    private function googleFillTwoFactor($input_selector, $message_selector, $submit_selector, $submit_by_enter = false)
    {
        $this->exts->log(__FUNCTION__);
        $this->exts->log("Google two factor page found.");
        $this->exts->capture("2.1-two-factor-google");

        if ($this->exts->querySelector($message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'innerText'));
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
        }

        $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        $this->exts->notification_uid = "";
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            if ($this->exts->querySelector($input_selector) != null) {
                if (substr(trim($two_factor_code), 0, 2) === 'G-') {
                    $two_factor_code = end(explode('G-', $two_factor_code));
                }
                if (substr(trim($two_factor_code), 0, 2) === 'g-') {
                    $two_factor_code = end(explode('g-', $two_factor_code));
                }
                $this->exts->log(__FUNCTION__ . ": Entering two_factor_code: " . $two_factor_code);
                $this->exts->moveToElementAndType($input_selector, '');
                $this->exts->moveToElementAndType($input_selector, $two_factor_code);
                sleep(1);
                if ($this->exts->allExists(['input[type="checkbox"]:not(:checked) + div', 'input[name="Pin"]'])) {
                    $this->exts->moveToElementAndClick('input[type="checkbox"]:not(:checked) + div');
                    sleep(1);
                }
                $this->exts->capture("2.2-google-two-factor-filled-" . $this->exts->two_factor_attempts);

                if ($this->exts->exists($submit_selector)) {
                    $this->exts->log(__FUNCTION__ . ": Clicking submit button.");
                    $this->exts->moveToElementAndClick($submit_selector);
                } else if ($submit_by_enter) {
                    $this->exts->type_key_by_xdotool("Return");
                }
                sleep(10);
                $this->exts->capture("2.2-google-two-factor-submitted-" . $this->exts->two_factor_attempts);
                if ($this->exts->querySelector($input_selector) == null) {
                    $this->exts->log("Google two factor solved");
                } else {
                    if ($this->exts->two_factor_attempts < 3) {
                        $this->exts->notification_uid = '';
                        $this->exts->two_factor_attempts++;
                        $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector);
                    } else {
                        $this->exts->log("Google Two factor can not solved");
                    }
                }
            } else {
                $this->exts->log("Google not found two factor input");
            }
        } else {
            $this->exts->log("Google not received two factor code");
            $this->exts->two_factor_attempts = 3;
        }
    }
    private function googlecheckFillRecaptcha()
    {
        $this->exts->log(__FUNCTION__);
        $recaptcha_iframe_selector = 'form iframe[src*="/recaptcha/"]';
        $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
        if ($this->exts->exists($recaptcha_iframe_selector)) {
            $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
            $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
            $this->exts->log("iframe url  - " . $iframeUrl);
            $this->exts->log("SiteKey - " . $data_siteKey);
            $url = reset(explode('?', $this->exts->getUrl()));
            $isCaptchaSolved = $this->exts->processRecaptcha($url, $data_siteKey, false);
            $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);

            if ($isCaptchaSolved) {
                // Step 1 fill answer to textarea
                $this->exts->log(__FUNCTION__ . "::filling reCaptcha response..");
                $recaptcha_textareas =  $this->exts->getElements($recaptcha_textarea_selector);
                for ($i = 0; $i < count($recaptcha_textareas); $i++) {
                    $this->exts->execute_javascript("arguments[0].innerHTML = '" . $this->exts->recaptcha_answer . "';", [$recaptcha_textareas[$i]]);
                }
                sleep(2);
                $this->exts->capture('recaptcha-filled');

                // Step 2, check if callback function need executed
                $gcallbackFunction = $this->exts->execute_javascript('
					if(document.querySelector("[data-callback]") != null){
						document.querySelector("[data-callback]").getAttribute("data-callback");
					} else {
						var result = ""; var found = false;
						function recurse (cur, prop, deep) {
						    if(deep > 5 || found){ return;}console.log(prop);
						    try {
						        if(prop.indexOf(".callback") > -1){result = prop; found = true; return;
						        } else { if(cur == undefined || cur == null || cur instanceof Element || Object(cur) !== cur || Array.isArray(cur)){ return;}deep++;
						            for (var p in cur) { recurse(cur[p], prop ? prop + "." + p : p, deep);}
						        }
						    } catch(ex) { console.log("ERROR in function: " + ex); return; }
						}

						recurse(___grecaptcha_cfg.clients[0], "", 0);
						found ? "___grecaptcha_cfg.clients[0]." + result : null;
					}
				');
                $this->exts->log('Callback function: ' . $gcallbackFunction);
                if ($gcallbackFunction != null) {
                    $this->exts->execute_javascript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
                    sleep(10);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
        }
    }
    // End GOOGLE login

    public $check_transaction_login = 0;
    private function processAfterLogin($count)
    {

        if (stripos($this->exts->getUrl(), "/eBayISAPI.dll?MyEbay&CurrentPage=MyeBayMyAccounts") !== false) {
            if ($this->exts->exists('div.cards-account-settings a[href*="MyeBaySellerAccounts"]')) {
                $this->exts->moveToElementAndClick('div.cards-account-settings a[href*="MyeBaySellerAccounts"]');
                sleep(15);

                $this->checkAndReloadUrl($this->exts->getUrl());
                $this->callRecaptcha();
                sleep(15);
                sleep(15);

                if ($this->exts->exists('input[name="userid"]:not([type="Hidden"])')) {
                    $this->reLogin($this->exts->getUrl());
                }
            }

            if ($this->exts->exists('div#LocalNavigation a[href*="MyeBaySellerAccounts"]')) {
                $this->exts->moveToElementAndClick('div#LocalNavigation a[href*="MyeBaySellerAccounts"]');
                sleep(15);

                $this->checkAndReloadUrl($this->exts->getUrl());
                $this->callRecaptcha();
                sleep(15);

                if ($this->exts->exists('input[name="userid"]:not([type="Hidden"])')) {
                    $this->reLogin($this->exts->getUrl());
                }
            }
            $this->processAccounts();
        } else {
            $this->exts->openUrl($this->invoice_url);
            sleep(15);

            if ($this->exts->exists('form#signin-form div:not(.hide) input#pass')) {
                $this->reLogin($this->invoice_url);
            }

            $this->checkAndReloadUrl($this->invoice_url);
            $this->callRecaptcha();
            sleep(15);

            if ($this->exts->urlContains('error?statuscode=500') || $this->exts->exists('a[href*="accountsettings.ebay.de/uas"]')) {
                $this->exts->capture('proceessafterlogincode500');
                $this->exts->openUrl('https://accountsettings.ebay.de/uas');
                sleep(17);
            }

            if ($this->exts->exists('div.cards-account-settings a[href*="MyeBaySellerAccounts"]')) {
                $this->exts->moveToElementAndClick('div.cards-account-settings a[href*="MyeBaySellerAccounts"]');
                sleep(15);

                $this->checkAndReloadUrl($this->exts->getUrl());
                $this->callRecaptcha();
                sleep(15);

                if ($this->exts->exists('input[name="userid"]:not([type="Hidden"])')) {
                    $this->reLogin($this->exts->getUrl());
                }

                if ($this->exts->exists('div#LocalNavigation a[href*="MyeBaySellerAccounts"]')) {
                    $this->exts->moveToElementAndClick('div#LocalNavigation a[href*="MyeBaySellerAccounts"]');
                    sleep(15);

                    $this->checkAndReloadUrl($this->exts->getUrl());
                    $this->callRecaptcha();
                    sleep(15);

                    if ($this->exts->exists('input[name="userid"]:not([type="Hidden"])')) {
                        $this->reLogin($this->exts->getUrl());
                    }
                }
            } else if ($this->exts->exists('a[href*="/billing/selleraccount"]')) {
                $this->exts->moveToElementAndClick('a[href*="/billing/selleraccount"]');
                sleep(15);

                $this->checkAndReloadUrl($this->exts->getUrl());
                $this->callRecaptcha();
                sleep(15);

                if ($this->exts->exists('input[name="userid"]:not([type="Hidden"])')) {
                    $this->reLogin($this->exts->getUrl());
                }

                $this->exts->openUrl('https://www.ebay.de/sh/fin/report/invoices');
                sleep(17);

                $this->checkAndReloadUrl($this->exts->getUrl());
                $this->callRecaptcha();
                sleep(15);

                if ($this->exts->exists('input[name="userid"]:not([type="Hidden"])')) {
                    $this->reLogin($this->exts->getUrl());
                }

                $this->download_report_invoices();
            } else if ($this->exts->exists('a[href*="sellerstandards"][href*="dashboard"]')) {
                $this->exts->moveToElementAndClick('a[href*="sellerstandards"][href*="dashboard"]');
                sleep(15);

                $this->checkAndReloadUrl($this->exts->getUrl());
                $this->callRecaptcha();
                sleep(15);

                if ($this->exts->exists('input[name="userid"]:not([type="Hidden"])')) {
                    $this->reLogin($this->exts->getUrl());
                }

                if ($this->exts->exists('ul.myaccount-menu a[href*="MyeBayNextSellerAccounts"]')) {
                    $this->exts->moveToElementAndClick('ul.myaccount-menu a[href*="MyeBayNextSellerAccounts"]');
                    sleep(15);

                    $this->checkAndReloadUrl($this->exts->getUrl());
                    $this->callRecaptcha();
                    sleep(15);

                    if ($this->exts->exists('input[name="userid"]:not([type="Hidden"])')) {
                        $this->reLogin($this->exts->getUrl());
                    }
                }
            } else {
                $this->exts->openUrl('https://www.ebay.de/sh/fin/report/invoices');
                sleep(17);

                $this->checkAndReloadUrl($this->exts->getUrl());
                $this->callRecaptcha();
                sleep(15);

                if ($this->exts->exists('input[name="userid"]:not([type="Hidden"])')) {
                    $this->reLogin($this->exts->getUrl());
                }

                $this->download_report_invoices();
            }

            $this->processAccounts(0);

            if ($this->isNoInvoice) {
                $this->exts->openUrl($this->invoice_url);
                sleep(15);

                if ($this->exts->exists('form#signin-form div:not(.hide) input#pass')) {
                    $this->reLogin($this->invoice_url);
                }

                $this->checkAndReloadUrl($this->invoice_url);
                $this->callRecaptcha();
                sleep(15);

                if ($this->exts->urlContains('error?statuscode=500')) {
                    $this->exts->capture('proceessafterlogincode500');
                    $this->exts->openUrl('https://accountsettings.ebay.de/uas');
                    sleep(17);
                }

                if ($this->exts->exists('a[href*="/billing/selleraccount"]')) {
                    $this->exts->moveToElementAndClick('a[href*="/billing/selleraccount"]');
                    sleep(15);

                    $this->checkAndReloadUrl($this->exts->getUrl());
                    $this->callRecaptcha();
                    sleep(15);

                    if ($this->exts->exists('input[name="userid"]:not([type="Hidden"])')) {
                        $this->reLogin($this->exts->getUrl());
                    }

                    $this->processAccounts(0);
                }
            }

            $this->exts->switchToInitTab();
            $this->exts->closeAllTabsButThis();
        }

        if ((int)@$this->monthly_statements == 1) {
            $this->exts->openUrl('https://www.ebay.de/sh/ovw');
            sleep(10);
            $this->exts->capture('monthly_statements_page');

            if (!$this->checkLogin()) {
                $this->reLogin('https://www.ebay.de/sh/ovw');
            }

            $this->checkAndReloadUrl('https://www.ebay.de/sh/ovw');
            $this->callRecaptcha();

            if (!$this->checkLogin()) {
                $this->reLogin($this->exts->getUrl());
            }

            $this->exts->moveToElementAndClick('a[href="/sh/fin"]');
            sleep(15);

            $this->exts->moveToElementAndClick('.left-nav a[href="/sh/fin/report"]');
            sleep(15);

            $this->exts->moveToElementAndClick('a[href="/sh/fin/report/statement"]');
            sleep(15);
            if (!$this->checkLogin()) {
                $this->reLogin($this->exts->getUrl());
            }

            $this->downloadMonthlyStatements();
        }

        if ((int)@$this->fetch_transaction == 1) {
            $this->check_transaction_login = 1;
            $this->exts->openUrl($this->purchase_history);
            sleep(10);
            $this->exts->capture('purchase_history_page');

            if ($this->exts->exists($this->password_selector)) {
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(2);

                $this->exts->moveToElementAndClick($this->login_submit_selector);
                sleep(15);

                $this->callRecaptcha();
                sleep(15);
            }

            if (!$this->checkLogin()) {
                $this->reLogin($this->purchase_history);
            }

            $this->checkAndReloadUrl($this->purchase_history);
            $this->callRecaptcha();

            if (!$this->checkLogin()) {
                $this->reLogin($this->exts->getUrl());
            }

            $this->selectYear();
            $this->processPurchasesContainer();
        }

        if ((int)@$this->download_sales_invoice == 1) {
            $this->exts->openUrl('https://www.ebay.de/sh/ord/?filter=status:PAID_SHIPPED');
            sleep(17);

            $this->checkAndReloadUrl($this->exts->getUrl());
            $this->callRecaptcha();
            sleep(15);

            if ($this->exts->exists('input[name="userid"]:not([type="Hidden"])')) {
                $this->reLogin($this->exts->getUrl());
            }

            if (!$this->exts->urlContains('filter=status:PAID_SHIPPED')) {
                $this->exts->openUrl('https://www.ebay.de/sh/ord/?filter=status:PAID_SHIPPED');
                sleep(17);
            }


            $this->processOrders();
        }

        $this->exts->openUrl('https://www.ebay.de/mye/myebay/v2/purchase');
        sleep(15);
        if ($this->exts->exists('div.m-container__header.border-header button')) {
            $this->exts->moveToElementAndClick('div.m-container__header.border-header button');
            sleep(1);
            $this->exts->moveToElementAndClick('div.m-container__header.border-header div.menu-button__item');
        }
        $this->processBuyerInvoices();

        if ($this->isNoInvoice) {
            $this->exts->no_invoice();
        }
    }
    private function processAccounts()
    {
        $this->exts->capture('processAccounts');
        try {
            $this->checkAndReloadUrl($this->exts->getUrl());
            $this->callRecaptcha();
            sleep(15);

            $current_url = $this->exts->getUrl();
            $this->exts->log('current_page: ' . $current_url);

            if ($this->exts->getElement('select[name="cid"] option') != null) {
                $selectAccountElements = $this->exts->getElements('select[name="cid"] option');
                if (count($selectAccountElements) > 0) {
                    $optionAccountSelectors = array();
                    foreach ($selectAccountElements as $selectAccountElement) {
                        $elementAccountValue = trim($selectAccountElement->getAttribute('value'));
                        $optionAccountSelectors[] = $elementAccountValue;
                    }

                    $this->exts->log("optionAccountSelectors " . count($optionAccountSelectors));
                    if (!empty($optionAccountSelectors)) {
                        foreach ($optionAccountSelectors as $key => $optionAccountSelector) {
                            $this->exts->log("Account-value  " . $optionAccountSelector);
                            if ($key > 0) {
                                $this->exts->openUrl($current_url);
                                sleep(15);

                                $this->checkAndReloadUrl($current_url);
                                $this->callRecaptcha();
                                sleep(15);
                            }
                            // Fill Account Select
                            $optionSelAccEle = 'select[name="cid"] option[value="' . $optionAccountSelector . '"]';
                            $this->exts->log("processing account element  " . $optionSelAccEle);
                            $this->exts->moveToElementAndClick($optionSelAccEle);
                            sleep(5);

                            $this->exts->capture("Account-Selected-" . $optionAccountSelector);

                            $this->processInvoices(0);
                        }
                    } else {
                        $this->processInvoices(0);
                    }
                } else {
                    $this->processInvoices(0);
                }
            } else {
                $this->processInvoices(0);
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception checking process accounts " . $exception->getMessage());
        }
    }
    private function processInvoices($count = 0)
    {
        try {
            $current_url = $this->exts->getUrl();
            if ($this->exts->getElement('select[name="invoiceMonthYear"] option') != null) {
                $invoiceMonthOptions = $this->exts->getElements('select[name="invoiceMonthYear"] option');
                if (count($invoiceMonthOptions) > 0) {
                    $optionInvoiceSelectors = array();
                    foreach ($invoiceMonthOptions as $invoiceMonthOption) {
                        if ((int)@$this->restrictPages > 0 && count($optionInvoiceSelectors) > 6) break;
                        $elementInvoiceValue = trim($invoiceMonthOption->getAttribute('value'));
                        if (stripos($elementInvoiceValue, ":") === false)  continue;
                        $elementAccountText = trim($invoiceMonthOption->getAttribute('innerText'));
                        $optionInvoiceSelectors[] = array($elementInvoiceValue, $elementAccountText);
                    }

                    $this->exts->log("optionInvoiceSelectors " . count($optionInvoiceSelectors));
                    $invoice_data_arr = array();
                    if (!empty($optionInvoiceSelectors)) {
                        $this->isNoInvoice = false;
                        foreach ($optionInvoiceSelectors as $optionInvoiceSelector) {
                            try {
                                $optionValue = $optionInvoiceSelector[0];
                                $tempArr = explode(":", $optionValue);

                                $invoice_name = trim($tempArr[2]);
                                $this->exts->log("invoice_name - " . $invoice_name);

                                if (!$this->exts->invoice_exists($invoice_name)) {
                                    $invoice_date = trim($tempArr[0]) . "-" . trim($tempArr[1]);
                                    $this->exts->log("invoice_date - " . $invoice_date);

                                    $parsed_date = $this->exts->parse_date($invoice_date, 'n-Y', 'Y-m-d'); // $this->exts->parse_date($invoice_date);
                                    if (trim($parsed_date) != "") $invoice_date = $parsed_date;
                                    $this->exts->log("invoice_date - " . $invoice_date);

                                    // $tempArr =   explode(" ", trim($optionInvoiceSelector[1]));
                                    $invoice_amount = trim(end(preg_split("/\s\d{4}\s/", trim($optionInvoiceSelector[1])))); // trim($tempArr[count($tempArr)-1])." GBP";
                                    $this->exts->log("invoice_amount - " . $invoice_amount);

                                    if ((int)@$this->restrictPages == 0) {
                                        $invoice_data_arr[] = array(
                                            "invoice_name" => $invoice_name,
                                            "invoice_date" => $invoice_date,
                                            "invoice_amount" => $invoice_amount,
                                            "option_value" => $optionInvoiceSelector[0],
                                            "option_text" => $optionInvoiceSelector[1]
                                        );
                                    } else {
                                        $invoice_data_arr[] = array(
                                            "invoice_name" => $invoice_name,
                                            "invoice_date" => $invoice_date,
                                            "invoice_amount" => $invoice_amount,
                                            "option_value" => $optionInvoiceSelector[0],
                                            "option_text" => $optionInvoiceSelector[1]
                                        );
                                        if (count($invoice_data_arr) > 6) break;
                                    }
                                } else {
                                    $this->exts->log('Invoice already exists - ' . $invoice_name);
                                }
                            } catch (\Exception $exception) {
                                $this->exts->log("Exception while extraction each invoice " . $optionInvoiceSelector . " - " . $exception->getMessage());
                            }
                        }
                        $base_handle = $this->exts->get_all_tabs();

                        if (count($invoice_data_arr) > 0) {
                            // Fill First Invoice Select
                            $optionSelAccEle = 'select[name="invoiceMonthYear"] option[value="' . $invoice_data_arr[0]["option_value"] . '"]';
                            $this->exts->log("processing account element  " . $optionSelAccEle);

                            $this->exts->execute_javascript('
								$("select[name=\'invoiceMonthYear\']").val("' . $invoice_data_arr[0]["option_value"] . '");
								$("select[name=\'invoiceMonthYear\']").change();');
                            sleep(5);

                            if ($this->exts->exists('form[name="AccountStatusForm"] input[type="submit"]')) {
                                $this->exts->moveToElementAndClick('form[name="AccountStatusForm"] input[type="submit"]');
                                sleep(5);
                            } else if ($this->exts->exists('input#InvButtonId')) {
                                $this->exts->moveToElementAndClick('input#InvButtonId');
                                sleep(15);
                            }

                            $handles = $this->exts->get_all_tabs();
                            if (count($handles) > 0) {
                                $switchedtab = $this->exts->switchToTab(end($handles));
                            }

                            $filename = !empty($invoice_data_arr[0]["invoice_name"]) ? $invoice_data_arr[0]["invoice_name"] . ".pdf" : '';
                            // Checking the PDF download file here because as we open the URL PDF file get downloaded so if we get PDF here only then no need to find the URL and do the download.
                            // Wait for completion of file download
                            $this->exts->wait_and_check_download('pdf');

                            // find new saved file and return its path
                            $downloaded_file = $this->exts->find_saved_file('pdf', $filename);
                            //$downloaded_file = $this->exts->download_current($filename, 5);
                            if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                                $this->exts->new_invoice($invoice_data_arr[0]['invoice_name'], $invoice_data_arr[0]['invoice_date'], $invoice_data_arr[0]['invoice_amount'], $filename);
                            } else {
                                if ($this->exts->getElement('form#ViewInvoice a[href*="/eBayISAPI.dll?DownloadInvoiceNex"]') != null) {
                                    $invoiceDownloadUrl = $this->exts->getElement('form#ViewInvoice a[href*="/eBayISAPI.dll?DownloadInvoiceNex"]')->getAttribute("href");
                                    try {
                                        $newTab = $this->exts->openNewTab();
                                        $this->exts->openUrl($invoiceDownloadUrl);
                                        sleep(5);

                                        $this->callRecaptcha();
                                        sleep(15);

                                        if ($this->exts->exists($this->password_selector)) {
                                            $this->exts->moveToElementAndType($this->password_selector, $this->password);
                                            sleep(2);

                                            $this->exts->moveToElementAndClick($this->login_submit_selector);
                                            sleep(15);
                                        }

                                        $this->callRecaptcha();
                                        sleep(15);

                                        if ($this->exts->exists('form#DownloadInvoice input[name="Submit_btn"]')) {

                                            if ($this->exts->exists('input[name="downloadFormat"][id*="pdf"]')) {
                                                $this->exts->moveToElementAndClick('input[name="downloadFormat"][id*="pdf"]:not(:checked)');
                                                sleep(2);

                                                $this->exts->moveToElementAndClick('form#DownloadInvoice input[name="Submit_btn"]');
                                                sleep(15);
                                            }

                                            // Wait for completion of file download
                                            $this->exts->wait_and_check_download('pdf');

                                            // find new saved file and return its path
                                            $downloaded_file = $this->exts->find_saved_file('pdf', $filename);
                                            if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                                                $this->exts->new_invoice($invoice_data_arr[0]['invoice_name'], $invoice_data_arr[0]['invoice_date'], $invoice_data_arr[0]['invoice_amount'], $filename);
                                            } else {
                                                $this->exts->executeSafeScript('window.history.back();');
                                                sleep(1);

                                                $this->print_download_document($invoice_data_arr[0], $filename);
                                            }
                                        }
                                        if (count($handles) > 1) {
                                            $switchedtab = $this->exts->switchToTab(end($handles));
                                            $this->exts->closeTab($switchedtab);
                                        }
                                    } catch (\Exception $exception) {
                                        $this->exts->log("Exception in click on print view " . $invoice_data_arr[0]['invoiceName'] . " - " . $exception->getMessage());
                                    }
                                } else {
                                    $this->print_download_document($invoice_data_arr[0], $filename);
                                }
                            }

                            $handles = $this->exts->get_all_tabs();
                            if (count($handles) > 1) {
                                $switchedtab = $this->exts->switchToTab(end($handles));
                                $this->exts->closeTab($switchedtab);
                            }

                            $this->exts->switchToTab($base_handle);
                            sleep(3);

                            $tempCurrUrl = $this->exts->getUrl();
                            if ($current_url != $tempCurrUrl) {
                                $this->exts->openUrl($current_url);
                                sleep(15);

                                $this->checkAndReloadUrl($current_url);
                                $this->callRecaptcha();
                                sleep(15);
                            }

                            if (count($invoice_data_arr) > 1) {
                                foreach ($invoice_data_arr as $key => $invoice_data) {
                                    if ($key == 0) continue;
                                    $optionSelAccEle = 'select[name="invoiceMonthYear"] option[value="' . $invoice_data["option_value"] . '"]';
                                    $this->exts->log("processing account element  " . $optionSelAccEle);
                                    $this->exts->execute_javascript('
										$("select[name=\'invoiceMonthYear\']").val("' . $invoice_data["option_value"] . '");
										$("select[name=\'invoiceMonthYear\']").change();');
                                    sleep(5);

                                    // $this->exts->getElement('form#invoiceForm button#invSubmit[type="submit"]');
                                    // sleep(5);
                                    if ($this->exts->exists('form[name="AccountStatusForm"] input[type="submit"]')) {
                                        $this->exts->moveToElementAndClick('form[name="AccountStatusForm"] input[type="submit"]');
                                        sleep(5);
                                    } else if ($this->exts->exists('input#InvButtonId')) {
                                        $this->exts->moveToElementAndClick('input#InvButtonId');
                                        sleep(15);
                                    } else if ($this->exts->exists('#invSubmit')) {
                                        $this->exts->moveToElementAndClick('#invSubmit');
                                        sleep(15);
                                    }

                                    $handles = $this->exts->get_all_tabs();
                                    if (count($handles) > 1) {
                                        $switchedtab = $this->exts->switchToTab(end($handles));
                                        $this->exts->closeTab($switchedtab);
                                    }

                                    $filename = !empty($invoice_data["invoice_name"]) ? $invoice_data["invoice_name"] . ".pdf" : '';
                                    if ($this->html_invoice) {
                                        $this->print_download_document($invoice_data, $filename);
                                    } else {
                                        // Checking the PDF download file here because as we open the URL PDF file get downloaded so if we get PDF here only then no need to find the URL and do the download.
                                        // Wait for completion of file download
                                        $this->exts->wait_and_check_download('pdf');

                                        // find new saved file and return its path
                                        $downloaded_file = $this->exts->find_saved_file('pdf', $filename);
                                        //$downloaded_file = $this->exts->download_current($filename, 5);
                                        if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                                            $this->exts->new_invoice($invoice_data_arr[0]['invoice_name'], $invoice_data_arr[0]['invoice_date'], $invoice_data_arr[0]['invoice_amount'], $filename);
                                        } else {
                                            if ($this->exts->getElement('form#ViewInvoice a[href*="/eBayISAPI.dll?DownloadInvoiceNex"]') != null) {
                                                $invoiceDownloadUrl = $this->exts->getElement('form#ViewInvoice a[href*="/eBayISAPI.dll?DownloadInvoiceNex"]')->getAttribute("href");
                                                try {
                                                    $this->exts->open_new_window();
                                                    $this->exts->openUrl($invoiceDownloadUrl);
                                                    sleep(5);

                                                    $this->callRecaptcha();
                                                    sleep(15);

                                                    if ($this->exts->exists($this->password_selector)) {
                                                        $this->exts->moveToElementAndType($this->password_selector, $this->password);
                                                        sleep(2);

                                                        $this->exts->moveToElementAndClick($this->login_submit_selector);
                                                        sleep(15);
                                                    }

                                                    $this->callRecaptcha();
                                                    sleep(15);

                                                    if ($this->exts->exists('form#DownloadInvoice input[name="Submit_btn"]')) {
                                                        if ($this->exts->exists('input[name="downloadFormat"][id*="pdf"]')) {
                                                            $this->exts->moveToElementAndClick('input[name="downloadFormat"][id*="pdf"]:not(:checked)');
                                                            sleep(2);

                                                            $this->exts->moveToElementAndClick('form#DownloadInvoice input[name="Submit_btn"]');
                                                            sleep(15);
                                                        }

                                                        $this->exts->moveToElementAndClick('form#DownloadInvoice input[name="Submit_btn"]');
                                                        sleep(15);

                                                        // Wait for completion of file download
                                                        $this->exts->wait_and_check_download('pdf');

                                                        // find new saved file and return its path
                                                        $downloaded_file = $this->exts->find_saved_file('pdf', $filename);
                                                        if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                                                            $this->exts->new_invoice($invoice_data['invoice_name'], $invoice_data['invoice_date'], $invoice_data['invoice_amount'], $filename);
                                                        } else {
                                                            $this->exts->executeSafeScript('window.history.back();');
                                                            sleep(1);

                                                            $this->print_download_document($invoice_data, $filename);
                                                        }
                                                    }

                                                    $handles = $this->exts->get_all_tabs();
                                                    if (count($handles) > 1) {
                                                        $switchedtab = $this->exts->switchToTab(end($handles));
                                                        $this->exts->closeTab($switchedtab);
                                                    }
                                                } catch (\Exception $exception) {
                                                    $this->exts->log("Exception in click on print view " . $invoice_data_arr[0]['invoiceName'] . " - " . $exception->getMessage());
                                                }
                                            } else {
                                                $this->print_download_document($invoice_data, $filename);
                                            }
                                        }
                                    }

                                    $handles = $this->exts->get_all_tabs();
                                    if (count($handles) > 1) {
                                        $switchedtab = $this->exts->switchToTab(end($handles));
                                        $this->exts->closeTab($switchedtab);
                                    }

                                    $this->exts->switchToTab($base_handle);
                                    sleep(3);

                                    $tempCurrUrl = $this->exts->getUrl();
                                    if ($current_url != $tempCurrUrl) {
                                        $this->exts->openUrl($current_url);
                                        sleep(15);

                                        $this->checkAndReloadUrl($current_url);
                                        $this->callRecaptcha();
                                        sleep(15);
                                    }
                                }
                            }
                        } else {
                            $this->exts->success();
                        }
                    }
                }
            } else if ($this->exts->exists('select[data-testid="selleraccount-invoices-dropdown-select"]')) {
                $last_value = end($this->exts->getElementsAttribute('select[data-testid="selleraccount-invoices-dropdown-select"] option', 'value'));
                $this->exts->log('last value: ' . $last_value);
                $this->exts->execute_javascript('
					$("select[data-testid=\'selleraccount-invoices-dropdown-select\']").val("' . $last_value . '");
					$("select[data-testid=\'selleraccount-invoices-dropdown-select\']").change();');
                sleep(3);
                $this->exts->moveToElementAndClick('button[data-testid="selleraccount-invoices-gobutton"]');
                sleep(14);

                $invoiceValues = $this->exts->getElementsAttribute('select[data-testid="page-invoicedropdown"] option', 'value');
                foreach ($invoiceValues as $idx => $invoice_value) {
                    $this->exts->execute_javascript('
						$("select[data-testid=\'page-invoicedropdown\']").val("' . $invoice_value . '");
						$("select[data-testid=\'page-invoicedropdown\']").change();');
                    sleep(8);

                    $invoiceUrl = $this->exts->extract('div[class*="download"] a', null, 'href');
                    $invoiceName = str_replace(':', '_', trim(explode(':false', $invoice_value)[0]));
                    $invoiceDate = $this->exts->extract('select[data-testid="page-invoicedropdown"] option[value="' . $invoice_value . '"]', null, 'innerText');
                    $invoiceAmount = '';

                    $this->exts->log('--------------------------');
                    $this->exts->log('invoiceUrl: ' . $invoiceUrl);
                    $this->exts->log('invoiceName: ' . $invoiceName);
                    $this->exts->log('invoiceDate: ' . $invoiceDate);

                    $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                    $invoiceDate = $this->exts->parse_date($invoiceDate, 'F Y', 'Y-m-d', 'de');
                    $this->exts->log('Date parsed: ' . $invoiceDate);

                    $this->isNoInvoice = false;

                    // $this->exts->moveToElementAndClick('div[class*="download"] a');
                    $downloaded_file = $this->exts->download_capture($invoiceUrl, $invoiceFileName);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $invoiceDate, '', $invoiceFileName);
                        sleep(1);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }

                    if (!$this->exts->exists('select[data-testid="page-invoicedropdown"]') && $this->exts->urlContains('ViewInvoiceIFrame')) {
                        $this->exts->executeSafeScript('history.back();');
                        sleep(10);
                    }

                    // $this->exts->wait_and_check_download('pdf');
                    // $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                    // if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
                    // 	if($this->exts->invoice_exists($invoiceName)){
                    // 		$this->exts->log('Invoice existed '.$invoiceFileName);
                    // 	} else {
                    // 		$this->exts->new_invoice($invoiceName, $invoiceDate, '', $invoiceFileName);
                    // 		sleep(1);
                    // 	}
                    // } else {
                    // 	$this->exts->log(__FUNCTION__.'::No download '.$invoiceName);
                    // }

                }
            } else if ($this->exts->exists('select[data-testid="page-invoicedropdown"] option')) {
                $invoiceValues = $this->exts->getElementsAttribute('select[data-testid="page-invoicedropdown"] option', 'value');
                foreach ($invoiceValues as $idx => $invoice_value) {
                    $this->exts->execute_javascript('
						$("select[data-testid=\'page-invoicedropdown\']").val("' . $invoice_value . '");
						$("select[data-testid=\'page-invoicedropdown\']").change();');
                    sleep(8);

                    $invoiceUrl = $this->exts->extract('div[class*="download"] a', null, 'href');
                    $invoiceName = str_replace(':', '_', trim(explode(':false', $invoice_value)[0]));
                    $invoiceDate = $this->exts->extract('select[data-testid="page-invoicedropdown"] option[value="' . $invoice_value . '"]', null, 'innerText');
                    $invoiceAmount = '';

                    $this->exts->log('--------------------------');
                    $this->exts->log('invoiceUrl: ' . $invoiceUrl);
                    $this->exts->log('invoiceName: ' . $invoiceName);
                    $this->exts->log('invoiceDate: ' . $invoiceDate);

                    $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                    $invoiceDate = $this->exts->parse_date($invoiceDate, 'F Y', 'Y-m-d', 'de');
                    $this->exts->log('Date parsed: ' . $invoiceDate);

                    $this->isNoInvoice = false;

                    // $this->exts->moveToElementAndClick('div[class*="download"] a');
                    $downloaded_file = $this->exts->direct_download($invoiceUrl, 'pdf', $invoiceFileName);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $invoiceDate, '', $invoiceFileName);
                        sleep(1);
                    } else {
                        $this->exts->openUrl($invoiceUrl);
                        sleep(15);
                        $downloaded_file = $this->exts->download_current($invoiceFileName);
                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            $this->exts->new_invoice($invoiceName, $invoiceDate, '', $invoiceFileName);
                            sleep(1);
                        } else {
                            $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                        }
                    }

                    if (!$this->exts->exists('select[data-testid="page-invoicedropdown"]') && $this->exts->urlContains('ViewInvoiceIFrame')) {
                        $this->exts->executeSafeScript('history.back();');
                        sleep(10);
                    }
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception checking process invoice " . $exception->getMessage());
        }
    }
    private function print_download_document($invoice_data, $filename)
    {
        $invoicePrintUrl = "";
        if ($this->exts->getElement('iframe[src*="/eBayISAPI.dll?ViewInvoice"]') != null) {
            $invoicePrintUrl = $this->exts->getElement('iframe[src*="/eBayISAPI.dll?ViewInvoice"]')->getAttribute("src");
        } else if ($this->exts->getElement('iframe[src*="/eBayISAPI.dll?DownloadInvoiceNex"]') != null) {
            $invoicePrintUrl = $this->exts->getElement('iframe[src*="/eBayISAPI.dll?DownloadInvoiceNex"]')->getAttribute("src");
        }
        $this->exts->log("invoicePrintUrl - " . $invoicePrintUrl);

        if (trim($invoicePrintUrl) != "") {
            $this->exts->openUrl($invoicePrintUrl);
            sleep(5);

            $this->callRecaptcha();
            sleep(15);

            if ($this->exts->exists($this->password_selector)) {
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(2);

                $this->exts->moveToElementAndClick($this->login_submit_selector);
                sleep(15);
            }

            $this->callRecaptcha();
            sleep(15);
            // Wait for completion of file download
            $this->exts->wait_and_check_download('pdf');

            // find new saved file and return its path
            $downloaded_file = $this->exts->find_saved_file('pdf', $filename);
            //$downloaded_file = $this->exts->direct_download($invoicePrintUrl, 'pdf', $filename);
            if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice_data['invoice_name'], $invoice_data['invoice_date'], $invoice_data['invoice_amount'], $filename);
            } else {
                if ($this->exts->exists('.default_table .bold.pad-high')) {
                    $downloaded_file = $this->exts->download_current($filename, 10);
                    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                        $this->html_invoice = true;
                        $this->exts->new_invoice($invoice_data['invoice_name'], $invoice_data['invoice_date'], $invoice_data['invoice_amount'], $filename);
                    }
                }
            }
        } else {
            try {
                $this->exts->moveToElementAndClick('form#ViewInvoice a[onclick*="window.open"]');
                sleep(5);

                $handles = $this->exts->get_all_tabs();
                if (count($handles) > 1) {
                    $switchedtab = $this->exts->switchToTab(end($handles));
                    $this->exts->closeTab($switchedtab);
                }

                // Wait for completion of file download
                $this->exts->wait_and_check_download('pdf');

                // find new saved file and return its path
                $downloaded_file = $this->exts->find_saved_file('pdf', $filename);
                //$downloaded_file = $this->exts->download_current($filename, 5);
                if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice_data['invoice_name'], $invoice_data['invoice_date'], $invoice_data['invoice_amount'], $filename);
                } else {
                    if ($this->exts->exists('.default_table .bold.pad-high')) {
                        $downloaded_file = $this->exts->download_current($filename, 10);
                        if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                            $this->html_invoice = true;
                            $this->exts->new_invoice($invoice_data['invoice_name'], $invoice_data['invoice_date'], $invoice_data['invoice_amount'], $filename);
                        }
                    }
                }

                $handles = $this->exts->get_all_tabs();
                if (count($handles) > 1) {
                    $switchedtab = $this->exts->switchToTab(end($handles));
                    $this->exts->closeTab($switchedtab);
                }
            } catch (\Exception $exception) {
                $this->exts->log("Exception in click on print view " . $invoice_data['invoiceName'] . " - " . $exception->getMessage());
            }
        }
    }
    private function selectYear()
    {
        if ($this->exts->exists('div.period-menu > a') || $this->exts->exists('div.period-menu > button')) {
            if ($this->exts->exists('div.period-menu > button')) {
                $this->exts->moveToElementAndClick('div.period-menu > button');
            } else {
                $this->exts->moveToElementAndClick('div.period-menu > a');
            }
            sleep(2);

            $years = $this->exts->getElements('div.period-menu > ul li a');
            $years_sel = array();
            foreach ($years as $year) {
                $data_url = $year->getAttribute('data-url');
                $this->exts->log($data_url);
                $year_sel = 'div.period-menu > ul li a[data-url="' . $data_url . '"]';
                array_push($years_sel, $year_sel);
            }

            if ($this->restrictPages != 0) {
                if (count($this->processOrderDetails(0)) > 0) {
                    return;
                }

                foreach ($years_sel as $year_sel) {
                    $this->exts->moveToElementAndClick('div.period-menu > a');
                    sleep(2);

                    $this->exts->moveToElementAndClick($year_sel);
                    sleep(15);

                    if (count($this->processOrderDetails(0)) > 0) {
                        break;
                    }
                }
            } else {
                $this->processOrderDetails(0);
                foreach ($years_sel as $year_sel) {
                    $this->exts->moveToElementAndClick('div.period-menu > a');
                    sleep(2);

                    $this->exts->moveToElementAndClick($year_sel);
                    sleep(15);

                    $this->processOrderDetails(0);
                }
            }
        } else {
            $this->processOrderDetails(0);
        }
    }
    private function processOrderDetails($count, $pageNumber = 1)
    {
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->getElements('div#purchase-history div.icard');
        if (count($rows) > 0) {
            foreach ($rows as $row) {
                if ($this->exts->getElement('div.icard__header div.icard__status-bar.delivered', $row) != null) {
                    $invoiceUrl = '';
                    $itemCardListEle = $this->exts->getElements("div.icard__items div.item-card", $row);
                    if (count($itemCardListEle) > 0) {
                        if ($this->exts->getElement("div.icard_items-actions div.dropdown.mactions button.secItemActions", $itemCardListEle[0]) != null) {
                            $wouldLike = $this->exts->getElement("div.icard_items-actions div.dropdown.mactions button.secItemActions", $itemCardListEle[0]);

                            try {
                                $this->exts->log('Click wouldLike button');
                                $wouldLike->click();
                            } catch (\Exception $exception) {
                                $this->exts->log('Click wouldLike button by javascript');
                                $this->exts->executeSafeScript("arguments[0].click()", [$wouldLike]);
                            }
                            sleep(5);

                            if ($this->exts->getElement('div.icard_items-actions div.dropdown.mactions ul.dropdown-menu li a[href*="/eBayISAPI.dll?ViewPaymentStatus"]', $itemCardListEle[0]) != null) {
                                $invoiceUrl = trim($this->exts->extract('div.icard_items-actions div.dropdown.mactions ul.dropdown-menu li a[href*="/eBayISAPI.dll?ViewPaymentStatus"]', $itemCardListEle[0], 'href'));
                            }
                        }
                    }
                    $invoiceName = $row->getAttribute('id');
                    if ($invoiceUrl != '') {
                        $invoiceDate = trim($this->exts->extract('div.icard__header div.icard__datefield label.date', $row));
                        $amountText = '';
                        if (count($itemCardListEle) > 0) {
                            $amountText = trim($this->exts->extract('div.item-details div.item-priceInfo label.item-price', $itemCardListEle[0]));
                        }

                        $invoiceAmount = preg_replace('/[^\d\.\,]/', '', $amountText);
                        if (stripos($amountText, 'A$') !== false) {
                            $invoiceAmount = $invoiceAmount . ' AUD';
                        } else if (stripos($amountText, '$') !== false) {
                            $invoiceAmount = $invoiceAmount . ' USD';
                        } else if (stripos(urlencode($amountText), '%C2%A3') !== false) {
                            $invoiceAmount = $invoiceAmount . ' GBP';
                        } else {
                            $invoiceAmount = $invoiceAmount . ' EUR';
                        }

                        array_push($invoices, array(
                            'invoiceName' => $invoiceName,
                            'invoiceDate' => $invoiceDate,
                            'invoiceAmount' => $invoiceAmount,
                            'invoiceUrl' => $invoiceUrl
                        ));
                    } else {
                        $this->exts->log('This order did not have url detail: ' . $invoiceName);
                    }
                }
            }
        } else {
            $rows = $this->exts->getElements('div#purchase-history div#orders div.order-r.item-list-all');
            if (count($rows) > 0) {
                foreach ($rows as $row) {
                    if ($this->exts->getElement('div.order-action-wrap a[href*="/eBayISAPI.dll?ViewPaymentStatus"]', $row) != null || $this->exts->getElement("div.order-action-wrap div.action-col div.dropdown.mactions a.dropdown-toggle", $row) != null || $this->exts->getElement("div.item-level-wrap div.action-col div.dropdown.mactions button.dropdown-toggle", $row) != null) {
                        $invoiceUrl = trim($this->exts->extract('div.order-action-wrap a[href*="/eBayISAPI.dll?ViewPaymentStatus"]', $row, 'href'));

                        if ($this->exts->getElement("div.item-level-wrap div.action-col div.dropdown.mactions button.dropdown-toggle", $row) != null) {
                            $moreActions = $this->exts->getElement("div.item-level-wrap div.action-col div.dropdown.mactions button.dropdown-toggle", $row);
                        } else {
                            $moreActions = $this->exts->getElement("div.order-action-wrap div.action-col div.dropdown.mactions a.dropdown-toggle", $row);
                        }

                        if ($moreActions != null) {
                            try {
                                $this->exts->log('Click more action button');
                                $moreActions->click();
                            } catch (\Exception $exception) {
                                $this->exts->log('Click more action button by javascript');
                                $this->exts->executeSafeScript("arguments[0].click()", [$moreActions]);
                            }
                        }

                        sleep(5);

                        $invoiceUrl = trim($this->exts->extract('div.order-action-wrap div.action-col div.dropdown.mactions ul.dropdown-menu li a[href*="/eBayISAPI.dll?ViewPaymentStatus"]', $row, 'href'));
                        if ($invoiceUrl == '') {
                            if ($this->exts->getElement('div.item-level-wrap div.action-col div.dropdown.mactions ul.dropdown-menu li a[href*="/eBayISAPI.dll?ViewPaymentStatus"]', $row) != null) {
                                $invoiceUrl = trim($this->exts->extract('div.item-level-wrap div.action-col div.dropdown.mactions ul.dropdown-menu li a[href*="/eBayISAPI.dll?ViewPaymentStatus"]', $row, 'href'));
                            } else {
                                $invoiceUrl = trim($this->exts->extract('a[href*="/FetchOrderDetails"]', $row, 'href'));
                            }
                        }

                        $invoiceName = trim($this->exts->extract('div.order-num div.row-value', $row));
                        if ($invoiceName == '') {
                            if ($this->exts->getElement('div.item-level-wrap div.action-col a[href*="feedback.ebay.co.uk/ws/eBayISAPI.dll?LeaveFeedbackShow"]', $row) != null) {
                                $invoiceName = trim($this->exts->extract('div.item-level-wrap div.action-col a[href*="feedback.ebay.co.uk/ws/eBayISAPI.dll?LeaveFeedbackShow"]', $row, 'aria-describedby'));
                            } else if ($this->exts->getElement('div.order-action-wrap a[href*="feedback.ebay.co.uk/ws/eBayISAPI.dll?LeaveFeedbackShow"]', $row) != null) {
                                $invoiceName = trim($this->exts->extract('div.order-action-wrap a[href*="feedback.ebay.co.uk/ws/eBayISAPI.dll?LeaveFeedbackShow"]', $row, 'aria-describedby'));
                            } else {
                                $invoiceName = trim($this->exts->extract('a[href*="feedback.ebay.co.uk/ws/eBayISAPI.dll?LeaveFeedbackShow"]', $row, 'aria-describedby'));
                            }
                        }

                        $invoiceDate = trim($this->exts->extract('div.order-date div.row-value', $row));
                        $amountText = trim($this->exts->extract('div.purchase-info span.cost-label', $row));
                        $invoiceAmount = preg_replace('/[^\d\.\,]/', '', $amountText);
                        if (stripos($amountText, 'A$') !== false) {
                            $invoiceAmount = $invoiceAmount . ' AUD';
                        } else if (stripos($amountText, '$') !== false) {
                            $invoiceAmount = $invoiceAmount . ' USD';
                        } else if (stripos(urlencode($amountText), '%C2%A3') !== false) {
                            $invoiceAmount = $invoiceAmount . ' GBP';
                        } else {
                            $invoiceAmount = $invoiceAmount . ' EUR';
                        }

                        if ($invoiceUrl != '') {
                            array_push($invoices, array(
                                'invoiceName' => $invoiceName,
                                'invoiceDate' => $invoiceDate,
                                'invoiceAmount' => $invoiceAmount,
                                'invoiceUrl' => $invoiceUrl
                            ));
                        } else {
                            $this->exts->log('This order did not have url detail: ' . $invoiceName);
                        }
                    }
                }
            }
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->isNoInvoice = false;
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
            $invoice['invoiceDate'] = $this->exts->parse_date(str_replace('.', '', $invoice['invoiceDate']), 'd M Y', 'Y-m-d', 'fr');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            if ($this->exts->document_exists($invoiceFileName)) {
                continue;
            }

            // Open New window To process Invoice
            $newTab = $this->exts->openNewTab();

            // Call Processing function to process current page invoices
            $this->exts->openUrl($invoice['invoiceUrl']);
            sleep(10);

            $this->callRecaptcha();
            sleep(15);

            if ($this->exts->exists($this->password_selector)) {
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(2);

                $this->exts->moveToElementAndClick($this->login_submit_selector);
                sleep(15);
            }

            $this->callRecaptcha();
            sleep(15);

            if ($this->exts->exists('button.btn[aria-controls="printerFriendlyContent"]')) {
                $this->exts->moveToElementAndClick('button.btn[aria-controls="printerFriendlyContent"]');
                sleep(1);

                $downloaded_file = $this->exts->download_current($invoiceFileName, 5);
                if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            } else {
                $this->exts->log('This order did not have printer button');
                $this->exts->capture('failed-button-printer');
            }

            // Close new window
            $this->exts->closeTab($newTab);
        }

        if ($this->restrictPages == 0 && $pageNumber < 50 && $this->exts->getElement('div.pagination div.pagn a.gspr.next') != null) {
            $pageNumber++;
            $this->exts->moveToElementAndClick('div.pagination div.pagn a.gspr.next');
            sleep(5);
            $this->processOrderDetails($count, $pageNumber);
        }

        return $invoices;
    }
    private function processPurchasesContainer()
    {
        $this->exts->capture("4-Purchases-container-page");
        $containers_len = count($this->exts->getElements('[type="PURCHASE_ACTIVITIES"]'));
        $order_container = null;
        for ($i = 0; $i < $containers_len; $i++) {
            $container = $this->exts->getElements('[type="PURCHASE_ACTIVITIES"]')[$i];
            $container_text = strtolower($this->exts->extract('h2', $container, 'innerText'));
            $this->exts->log($container_text);
            // if (strpos($container_text, 'bestellungen') !== false) {
            // 	$this->exts->log('have container');
            // 	$order_container = $container;
            // 	break;
            // }
            $this->processPurchases(1, $container);
        }
    }
    private function processPurchases($paging_count = 1, $order_container)
    {
        $current_url = $this->exts->getUrl();
        $this->exts->log('current purchase url: ' . $current_url);
        $this->exts->capture("4-Purchases-page");
        $invoices = [];

        // $containers_len = count($this->exts->getElements('[type="PURCHASE_ACTIVITIES"]'));
        // $order_container = null;
        // for ($i = 0; $i < $containers_len; $i++) {
        // 	$container = $this->exts->getElements('[type="PURCHASE_ACTIVITIES"]')[$i];
        // 	$container_text = strtolower($this->exts->extract('h2', $container, 'innerText'));
        // 	$this->exts->log($container_text);
        // 	if (strpos($container_text, 'bestellungen') !== false) {
        // 		$this->exts->log('have container');
        // 		$order_container = $container;
        // 		break;
        // 	}
        // }

        $rows = $this->exts->getElements('div.m-item-card', $order_container);
        foreach ($rows as $row) {
            if ($this->exts->getElement('a[href*="/FetchOrderDetails"]', $row) != null) {
                $invoiceUrl = $this->exts->getElement('a[href*="/FetchOrderDetails"]', $row)->getAttribute("href");
                $invoiceName = '';
                $invoiceDate = '';
                $invoiceAmount = '';

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
        // $this->exts->open_new_window();
        foreach ($invoices as $invoice) {
            $this->exts->openUrl($invoice['invoiceUrl']);
            sleep(10);
            $this->exts->capture('detail-invoice-page');

            $this->callRecaptcha();
            sleep(15);

            if ($this->exts->exists($this->password_selector)) {
                sleep(1);
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                $this->exts->log("sendKeypassword");
                $this->exts->executeSafeScript('document.getElementById("pass").value = "' . $this->password . '";');
                sleep(3);

                $this->exts->capture('detail-invoice-page-fillpassword');
                $this->exts->executeSafeScript('document.getElementById("sgnBt").removeAttribute("disabled");');
                sleep(3);
                $this->exts->capture('detail-invoice-page-removedisabled');
                $this->exts->moveToElementAndClick($this->login_submit_selector);
                sleep(15);

                $handles = $this->exts->get_all_tabs();
                if (count($handles) >= 3) {
                    $switchedtab = $this->exts->switchToTab(end($handles[1]));
                    $this->exts->closeTab($switchedtab);

                    $handles1 = $this->exts->get_all_tabs();
                    if (count($handles1) > 1) {
                        $switchedtab1 = $this->exts->switchToTab(end($handles1[1]));
                        $this->exts->closeTab($switchedtab1);
                    }
                }


                // sometime the website recall 2FA
                $this->check2FA();
                $this->check2FA();

                // click some button when finished 2FA
                if ($this->exts->getElement('form[name="contactInfoForm"] a#rmdLtr') != null) {
                    $this->exts->moveToElementAndClick('form[name="contactInfoForm"] a#rmdLtr');
                } else if ($this->exts->getElement('#fullscale [name="submitBtn"]') != null) {
                    $this->exts->moveToElementAndClick('#fullscale [name="submitBtn"]');
                } else if ($this->exts->getElement('.primsecbtns [value="text"]') != null) {
                    $this->exts->moveToElementAndClick('.primsecbtns [value="text"]');
                } else if ($this->exts->getElement("a#continue-get") != null) {
                    $this->exts->moveToElementAndClick("a#continue-get");
                }

                sleep(15);

                if ($this->exts->exists('form#securityQuestionForm input[name="answer"]')) {
                    $this->checkFillSecurityAnswer();
                }
            }

            $this->callRecaptcha();
            sleep(15);

            $this->exts->capture('detail-invoice-page-relogin');
            $this->checkAndReloadUrl($invoice['invoiceUrl']);

            if (strpos(strtolower($this->exts->extract('div.page-alert p', null, 'innerText')), 'order service temporarily unavailable') !== false || strpos(strtolower($this->exts->extract('div.page-alert p', null, 'innerText')), 'bestellservice ist derzeit nicht ver') !== false) {
                continue;
            }

            if (strpos(strtolower($this->exts->extract('body', null, 'innerText')), "you don't have permission to access") !== false) {
                continue;
            }

            if ($this->exts->exists('[data-test-id="orderId"] span')) {
                $invoiceName = trim($this->exts->extract('[data-test-id="orderId"] span', null, 'innerText'));
                $invoiceDate = '';
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('[data-src*="OrderCostTotal"]', null, 'innerText'))) . ' USD';



                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);

                $this->exts->moveToElementAndClick('[data-test-id="PrinterFriendlyPage"]');
                sleep(8);

                $this->exts->executeSafeScript("document.querySelectorAll(\"body\")[0].innerHTML = document.querySelectorAll(\"div#printerFriendlyContent\")[0].innerHTML;");
                sleep(5);


                if (trim($invoiceName) != '') {
                    $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                } else {
                    $invoiceFileName = '';
                }

                $downloaded_file = $this->exts->download_current($invoiceFileName, 5);
                $this->exts->log("downloaded_file " . $downloaded_file);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            } else {
                $invoiceNameEl = $this->exts->getElement('//div[@class="order-box"]//span[contains(text(), "nummer")]/..//../../following-sibling::dd', null, 'xpath');
                if ($invoiceNameEl != null)
                    $invoiceName = trim($invoiceNameEl->getAttribute('innerText'));

                $invoiceDateEl = $this->exts->getElement('//div[@class="order-box"]//span[contains(text(), "Bestellt am")]/..//../../following-sibling::dd', null, 'xpath');
                if ($invoiceDateEl != null)
                    $invoiceDate = trim(explode(',', $invoiceDateEl->getAttribute('innerText'))[0]);

                $invoiceAmountEl = $this->exts->getElement('//div[@class="order-box"]//span[contains(text(), "Gesamt")]/..//../../following-sibling::dd', null, 'xpath');
                if ($invoiceAmountEl != null) {
                    $invoiceAmount = trim(explode(',', $invoiceAmountEl->getAttribute('innerText'))[0]);
                    $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $invoiceAmount)) . ' USD';
                }

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);

                $this->exts->moveToElementAndClick('[data-test-id="PrinterFriendlyPage"], div.page-header-action-box button.printer-friendly-button');
                sleep(8);

                $this->exts->executeSafeScript("document.querySelectorAll(\"body\")[0].innerHTML = document.querySelectorAll(\"div.modal-overlay div.modal.desktop-modal\")[0].innerHTML;");
                sleep(5);
                if (trim($invoiceName) != '') {
                    $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                } else {
                    $invoiceFileName = '';
                }

                $downloaded_file = $this->exts->download_current($invoiceFileName, 5);
                $this->exts->log("downloaded_file " . $downloaded_file);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
        }

        $this->exts->openUrl($current_url);
        sleep(16);
        // next page
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        if (
            $restrictPages == 0 &&
            $paging_count < 50 &&
            $this->exts->getElement('button.pagination__next:not([aria-disabled="true"])', $order_container) != null
        ) {
            $paging_count++;
            $next_bt = $this->exts->getElement('button.pagination__next:not([aria-disabled="true"])', $order_container);
            try {
                $this->exts->log('Click download button');
                $next_bt->click();
            } catch (\Exception $exception) {
                $this->exts->log('Click download button by javascript');
                $this->exts->executeSafeScript("arguments[0].click()", [$next_bt]);
            }
            // $this->exts->moveToElementAndClick('button.pagination__next:not([aria-disabled="true"])');
            sleep(5);
            $this->processPurchases($paging_count, $order_container);
        }
    }
    private function processBuyerInvoices($paging_count = 1)
    {
        $this->exts->capture("4-Buyer-orders-page");
        $invoices = [];

        $rows = $this->exts->getElements('div.m-order-card');
        foreach ($rows as $row) {
            if ($this->exts->getElement('a[href*="/FetchOrderDetails"]', $row) != null) {
                $invoiceUrl = $this->exts->getElement('a[href*="/FetchOrderDetails"]', $row)->getAttribute("href");
                $invoiceName = explode(
                    '#',
                    array_pop(explode('orderId=', $invoiceUrl))
                )[0];
                $invoiceDate = '';
                $invoiceAmount = '';

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
        // $this->exts->open_new_window();
        foreach ($invoices as $invoice) {
            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
            if ($this->exts->invoice_exists($invoice['invoiceName'])) {
                $this->exts->log('Invoice existed ' . $invoiceFileName);
            } else {
                $this->exts->openUrl($invoice['invoiceUrl']);
                sleep(3);
                if ($this->exts->getElement('//button//*[contains(text(), "Rechnung ansehen")]', null, 'xpath') != null) {
                    $this->click_element('//button//*[contains(text(), "Rechnung ansehen")]');
                    sleep(2);
                    $this->exts->moveToElementAndClick('div.ReactModal__Content div.extra-icon button');
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoice['invoiceName'], '', '', $invoiceFileName);
                        sleep(1);
                    } else {
                        $this->exts->log('Timeout when download ' . $invoiceFileName);
                    }
                }
            }
        }
        // next page
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        if (
            $restrictPages == 0 &&
            $paging_count < 50 &&
            $this->exts->getElement('button.pagination__next:not([aria-disabled="true"])') != null
        ) {
            $paging_count++;
            $this->exts->moveToElementAndClick('button.pagination__next:not([aria-disabled="true"])');
            sleep(5);
            $this->processBuyerInvoices($paging_count);
        }
    }
    private function click_element($selector_or_object)
    {
        if ($selector_or_object == null) {
            $this->exts->log(__FUNCTION__ . ' Can not click null');
            return;
        }

        $element = $selector_or_object;
        if (is_string($selector_or_object)) {
            $this->exts->log(__FUNCTION__ . '::Click selector: ' . $selector_or_object);
            $element = $this->exts->getElement($selector_or_object);
            if ($element == null) {
                $element = $this->exts->getElement($selector_or_object, null, 'xpath');
            }
            if ($element == null) {
                $this->exts->log(__FUNCTION__ . ':: Can not found element with selector/xpath: ' . $selector_or_object);
            }
        }
        if ($element != null) {
            try {
                $this->exts->log(__FUNCTION__ . ' trigger click.');
                $element->click();
            } catch (\Exception $exception) {
                $this->exts->log(__FUNCTION__ . ' by javascript');
                $this->exts->executeSafeScript("arguments[0].click()", [$element]);
            }
        }
    }

    private function processMonthlyStatements()
    {
        if ($this->exts->querySelector('span.time-period-dropdown:nth-child(2) button[class="btn btn--form"]') != null) {
            $this->exts->moveToElementAndClick('span.time-period-dropdown:nth-child(2) button[class="btn btn--form"]');
            sleep(6);
        }

        $rows  = $this->exts->getElements('div.listbox-button__options div');
        foreach ($rows as $key => $row) {
            $row->click();
            sleep(5);
            $this->downloadMonthlyStatements($key);
        }
    }

    private function downloadMonthlyStatements($count = 0)
    {
        $this->exts->capture("4-statement-page-" . $count);

        $invoiceDate = '';
        $invoiceAmount = $this->exts->extract('div.statement-visualization-container div.statement-chart-fund-amount  span.BOLD');

        if ($this->exts->querySelector('button[class="btn menu-button__button btn--secondary"]') != null) {
            $this->exts->moveToElementAndClick('button[class="btn menu-button__button btn--secondary"]');
            sleep(6);
        }

        if ($this->exts->querySelector('div[class="menu-button__item menu__item"]:nth-child(2)') != null) {
            $this->exts->moveToElementAndClick('div[class="menu-button__item menu__item"]:nth-child(2)');
            sleep(10);
            // $this->exts->waitTillPresent('')
        }

        

        
    }

    private function downloadMonthlyStatementsOld()
    {
        $this->exts->capture("4-statement-page");

        $rows = $this->exts->getElements('.sh-main-content table tbody tr');
        $this->exts->log('Rows - ' . count($rows));
        foreach ($rows as $row) {
            $expand_button = $this->exts->getElement('.statement-download-dropdown .expand-btn', $row);
            if ($expand_button != null) {
                $this->isNoInvoice = false;
                try {
                    $this->exts->log('Click expand_button button');
                    $expand_button->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click expand_button button by javascript');
                    $this->exts->executeSafeScript("arguments[0].click()", [$expand_button]);
                }
                sleep(1);
                $download_button = $this->exts->getElement('.statement-download-dropdown [role="option"]:last-child', $row); // VollstÃ¤ndiger Abrechnungsbericht (PDF)
                if ($download_button != null) {
                    try {
                        $this->exts->log('Click download button');
                        $download_button->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click download button by javascript');
                        $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                    }
                    sleep(2);
                    $this->exts->moveToElementAndClick('div.statement-donwload-task a');
                    sleep(7);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf');

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $invoiceName = basename($downloaded_file, '.pdf');
                        $this->exts->new_invoice($invoiceName, '', '', $downloaded_file);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ');
                    }
                } else {
                    $this->exts->capture("statement-no-download");
                }
            }
        }
    }
    private function processOrders()
    {
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows_len = count($this->exts->getElements('tr[id*="order-info"]'));
        for ($i = 0; $i < $rows_len; $i++) {
            $row = $this->exts->getElements('tr[id*="order-info"]')[$i];
            $tags = $this->exts->getElements('td', $row);
            $menu_buttons = $this->exts->getElements('div.menu-actions ul li button', $row);
            $download_button = null;
            foreach ($menu_buttons as $key => $menu_button) {
                $button_txt = strtolower($menu_button->getAttribute('innerText'));
                $this->exts->log('button_txt: ' . $button_txt);
                if (strpos($button_txt, 'rechnungen drucken und mehr') !== false) {
                    $download_button = $menu_button;
                    break;
                }
            }
            if ($download_button != null) {
                $invoiceName = trim($this->exts->extract('div.order-details a', $row, 'innerText'));
                $invoiceDate = trim($tags[6]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[5]->getAttribute('innerText'))) . ' EUR';

                $this->isNoInvoice = false;

                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                $invoiceDate = $this->exts->parse_date($invoiceDate, 'j M Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoiceDate);

                $expand_button = $this->exts->getElement('div.menu-actions button.expand-btn, .fake-menu-button', $row);
                if ($expand_button != null) {
                    try {
                        $this->exts->log('Click expand_button button');
                        $expand_button->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click expand_button button by javascript');
                        $this->exts->executeSafeScript("arguments[0].click()", [$expand_button]);
                    }
                    sleep(5);

                    try {
                        $this->exts->log('Click download button');
                        $download_button->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click download button by javascript');
                        $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                    }
                    sleep(5);

                    $type_docs_checkboxs = $this->exts->getElements('input[name*="documentSelections"][data-testid="checkbox"]');
                    foreach ($type_docs_checkboxs as $key => $type_docs_checkbox) {
                        $type_docs_checkbox_name = strtolower($type_docs_checkbox->getAttribute('name'));
                        $type_docs_checkbox_name_or = $type_docs_checkbox->getAttribute('name');
                        $this->exts->log('type_docs_checkbox_name: ' . $type_docs_checkbox_name);
                        if (strpos($type_docs_checkbox_name, 'orderreceipt') !== false) {
                            try {
                                $this->exts->log('Click type_docs_checkbox button');
                                $type_docs_checkbox->click();
                            } catch (\Exception $exception) {
                                $this->exts->log('Click type_docs_checkbox button by javascript');
                                $this->exts->executeSafeScript("arguments[0].click()", [$type_docs_checkbox]);
                            }
                            sleep(3);
                        } else {
                            if ($this->exts->exists('input[name="' . $type_docs_checkbox_name_or . '"]:checked')) {
                                $this->exts->moveToElementAndClick('input[name="' . $type_docs_checkbox_name_or . '"]:checked');
                                sleep(3);
                            }
                        }
                    }

                    $this->exts->moveToElementAndClick('button.download-pdf-link, div.print-documents .print-body .print-preview-details button');

                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                        sleep(1);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceName);
                    }

                    $this->exts->moveToElementAndClick('div.print-documents button.lightbox-dialog__close');
                    sleep(5);
                }
            }
        }
    }
    private function download_report_invoices()
    {
        sleep(10);
        $this->exts->capture("report-invoices");

        $rows = $this->exts->getElements('#invoices-report table > tbody > tr');
        foreach ($rows as $row) {
            $download_button = $this->exts->getElement('a[data-action-name="SUMMARY"]', $row);
            if ($download_button == null) {
                $download_button = $this->exts->getElement('.//a//*[contains(text(), "Herunterladen")]/..', $row, 'xpath');
            }
            if ($download_button != null) {
                $this->isNoInvoice = false;
                $invoiceName = trim($this->exts->extract('tr td:first-child', $row, 'innerText'));
                $this->exts->log('--------------------------');
                $this->exts->log('invoice label: ' . $invoiceName);
                try {
                    $this->exts->log('Click download button');
                    $download_button->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click download button by javascript');
                    $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                }
                sleep(5);
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf');

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $invoiceName = basename($downloaded_file, '.pdf');
                    $this->exts->new_invoice($invoiceName, '', '', $downloaded_file);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceName);
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
