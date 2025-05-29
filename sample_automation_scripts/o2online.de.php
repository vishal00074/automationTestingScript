<?php // updated 2fa code 

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

    // Server-Portal-ID: 332 - Last modified: 23.05.2025 15:16:19 UTC - User: 1

    // Script here
    public $baseUrl = 'https://o2online.de';
    public $dslLoginUrl = 'https://dsl.o2online.de/selfcare/content/segment/kundencenter/';
    public $mobileLoginUrl = 'https://www.o2online.de/ecare/';
    public $dsl_username_selector = 'input#username';
    public $dsl_password_selector = 'form#loginFormular input[name="password"]';
    public $dsl_submit_login_selector = 'form#loginFormular a[onclick*="submit"]';
    public $mobile_username_selector = '#idToken4_od , input#IDToken1';
    public $mobile_verification_number = 'input[data-test-id="login-uservalidation-input"]';
    public $mobile_password_selector = 'input#IDToken2';
    public $mobile_submit_login_selector = 'form[name="Login"] button[type="submit"]';
    public $password_selector = 'one-cluster one-input[type="password"]';
    public $check_login_success_selector = '.navigation-item-logged-in a[href*="auth/logout"] .glyphicon-user, a[href*="/logout"] span.logoutUser, li a[data-testid="menu-item-billing"], a[href*="/logout"]';
    public $check_reset_password_selector = 'a[href*="/auth/passwordForgotten"]';
    public $isNoInvoice = true;
    public $usernameTemp = '';
    public $user_mobile_number = '';

    /**
     * Entry Method thats called for a portal
     *
     * @param int $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->usernameTemp = $this->exts->config_array['usernametemp'] ?? $this->username;
        $this->user_mobile_number = $this->exts->config_array['user_mobile_number'] ?? $this->user_mobile_number;
        $this->user_mobile_number = trim(preg_replace('/[^\d]/', '', $this->user_mobile_number));
        $this->exts->log($this->user_mobile_number);

        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture('1-init-page');

        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            if (stripos($this->usernameTemp, 'my') !== false) {
                $this->exts->openUrl($this->dslLoginUrl);
                if (stripos($this->exts->extract('div[id="login"]'), 'Session ist abgelaufen') !== false) {
                    $this->exts->moveToElementAndClick('button[type="submit"]');
                }
                sleep(15);
                $this->checkFillDslLogin();
                sleep(20);
            } else {
                $this->exts->openUrl($this->mobileLoginUrl);
                sleep(15);
                if ($this->exts->exists('[role="dialog"] button#uc-btn-accept-banner')) {
                    $this->exts->moveToElementAndClick('[role="dialog"] button#uc-btn-accept-banner');
                }
                if (stripos($this->exts->extract('div[id="login"]'), 'Session ist abgelaufen') !== false) {
                    $this->exts->moveToElementAndClick('button[type="submit"]');
                }
                sleep(15);
                $this->checkCookieConfirm();
                $this->checkFillMobileLogin();
                sleep(20);
            }

            $this->exts->execute_javascript("document.querySelector('div#usercentrics-root').shadowRoot.querySelector('button[data-testid=\"uc-accept-all-button\"]').click()");
            sleep(5);

            if (count($this->exts->getElements('label[data-test-id*="mfa-msisdn"]')) > 1) {
                if ($this->user_mobile_number != '') {
                    $phone_label = $this->exts->getElement('//label[contains(text(),"' . $this->user_mobile_number . '") and contains(@data-test-id, "mfa")]', null, 'xpath');
                    if ($phone_label != null) {
                        try {
                            $this->exts->log(__FUNCTION__ . ' trigger click.');
                            $phone_label->click();
                        } catch (\Exception $exception) {
                            $this->exts->log(__FUNCTION__ . ' by javascript');
                            $this->exts->execute_javascript("arguments[0].click()", [$phone_label]);
                        }
                        sleep(2);
                    }
                }
            }

            if ($this->exts->exists('button[data-test-id="mfa-send-otp"]')) {
                $this->exts->moveToElementAndClick('button[data-test-id="mfa-send-otp"], one-button[onclick*="sendEmailNow"]');
                sleep(10);
            }

            $this->checkFillTwoFactor();

            if ($this->exts->exists('one-button[onclick*="sendEmailNow"]')) {
                $this->exts->moveToElementAndClick('one-button[onclick*="sendEmailNow"]');
                sleep(10);
            }
            $this->checkFillTwoFactorEmail();

            if ($this->exts->getElement('form[name="enterMsisdnForm"]') != null) {
                $this->exts->moveToElementAndClick('a[href="#/verwalten/uebersicht"]');
                sleep(5);
                $this->exts->moveToElementAndClick('a[href="/"]');
                sleep(20);
            }
        }



        // then check user logged in or not
        if ($this->exts->exists($this->check_login_success_selector)) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture('LoginSuccess');
            // .modal.in button.close
            if ($this->exts->exists('.ui-dialog a.ui-dialog-titlebar-close')) {
                $this->exts->moveToElementAndClick('.ui-dialog a.ui-dialog-titlebar-close');
                sleep(2);
            }
            $this->exts->capture('3-login-success');

            if (stripos($this->usernameTemp, 'my') !== false) {
                if (count($this->exts->getElements('table#multiContractsDisplayTable')) > 0) {
                    $this->processMultiContract();
                } else {
                    if ($this->exts->exists('#wrong_lounge_type_dialog a[href="/selfcare/content/segment/business/"]')) {
                        $this->exts->moveToElementAndClick('#wrong_lounge_type_dialog a[href="/selfcare/content/segment/business/"]');
                        sleep(10);

                        $this->exts->openUrl('https://dsl.o2online.de/selfcare/content/segment/business/daten/rechnung/rechnungen/');
                        sleep(10);
                        $this->processInvoiceBusiness();
                    } else {
                        $this->exts->openUrl('https://dsl.o2online.de/selfcare/content/segment/kundencenter/daten-vertraege/rechnung/monatsuebersicht/');
                        $this->getInvoices();
                    }
                }
            } else {
                if (stripos($this->exts->getUrl(), '/anderer-vertrag/') !== false) {
                    $this->exts->openUrl('https://dsl.o2online.de/selfcare/content/segment/kundencenter/');
                    sleep(10);
                    if (count($this->exts->getElements('table#multiContractsDisplayTable')) > 0) {
                        $this->processMultiContract();
                    } else {
                        $this->exts->openUrl('https://dsl.o2online.de/selfcare/content/segment/kundencenter/daten-vertraege/rechnung/monatsuebersicht/');
                        $this->getInvoices();
                    }
                } elseif (stripos($this->exts->getUrl(), '/mobilfunk-logout')) {
                    $this->checkProcessMultiAccounts();
                } else {
                    // do something here


                    $side_menus = $this->exts->getElements('ul.side-nav-items li a');
                    $url = '';
                    foreach ($side_menus as $side_menu) {
                        if ($side_menu->getAttribute('href') != null && stripos($side_menu->getAttribute('href'), 'billing') !== false) {
                            $url = $side_menu->getAttribute('href');
                            break;
                        }
                    }
                    if ($url == '') {
                        $url = 'https://www.o2online.de/ecareng/billing/uebersicht';
                    }
                    $this->exts->openUrl($url);
                    $this->inherit();
                }
            }

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');

            $isTwoFAFailed = $this->exts->execute_javascript('document.body.innerHTML.includes("Beachte, dass du die Eingaben nur 3 mal falsch tätigen darfst")');
            $this->exts->log('isErrorMessage:: ' . $isTwoFAFailed);
            if ($isTwoFAFailed) {
                $this->exts->capture('incorrect-twoFA-code');
                $this->exts->loginFailure(1);
            }

            if (stripos($this->usernameTemp, 'my') !== false) {
                if (strpos($this->exts->extract('#aliceLogin .feedbackPanel'), 'Passwort ist nicht korrekt') !== false) {
                    $this->exts->loginFailure(1);
                } else if (strpos($this->exts->extract('div[id="o2-login"]'), 'Leider konnten wir Ihre') !== false) {
                    $this->exts->loginFailure(1);
                } else {
                    $this->exts->loginFailure();
                }
            } else {
                if ($this->exts->urlContains('/meta/bereichswechsel/')) {
                    $this->exts->loginFailure(1);
                } elseif (strpos(strtolower($this->exts->extract('#login .alert-danger')), 'bitte geben sie ihre rufnummer ein') !== false) {
                    $this->exts->loginFailure(1);
                } else if (strpos($this->exts->extract('div[id="o2-login"]'), 'Leider konnten wir Ihre') !== false) {
                    $this->exts->loginFailure(1);
                } elseif (
                    strpos($this->exts->extract('#login .alert-danger'), 'Nutzername ist uns') !== false ||
                    strpos($this->exts->extract('#login .alert-danger'), 'Kennwort ist') !== false ||
                    strpos($this->exts->extract('#login .alert-danger'), 'Sie sind noch nicht f') !== false
                ) {
                    $this->exts->loginFailure(1);
                } elseif ($this->exts->urlContains('/meta/auth/logout/')) {
                    $this->exts->loginFailure(1);
                } else {
                    $this->exts->loginFailure();
                }
            }
        }
    }

    private function checkFillDslLogin()
    {
        if ($this->exts->getElement($this->dsl_password_selector) != null) {
            sleep(3);
            $this->exts->capture('2-login-page');

            $this->exts->log('Enter Username');
            $this->exts->moveToElementAndType($this->dsl_username_selector, $this->username);
            sleep(1);

            $this->exts->log('Enter Password');
            $this->exts->moveToElementAndType($this->dsl_password_selector, $this->password);
            sleep(1);

            $this->exts->capture('2-login-page-filled');
            $this->exts->moveToElementAndClick($this->dsl_submit_login_selector);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture('2-login-page-not-found');
        }
    }
    private function checkCookieConfirm()
    {
        sleep(3);
        $this->exts->execute_javascript('
	var cookie_popup = document.querySelector("#usercentrics-root");
	if (cookie_popup != null) {
	cookie_popup.shadowRoot.querySelector("[data-testid=\"uc-accept-all-button\"]").click();
	}
	');

        sleep(3);
    }

    private function checkFillMobileLogin()
    {
        $this->exts->log(__FUNCTION__);
        $this->exts->capture('2-login-page');
        if ($this->exts->getElement($this->mobile_username_selector) != null) {
            sleep(3);
            $this->exts->log('Enter MobUsername');
            $this->exts->log($this->username);
            // $this->exts->execute_javascript("
            //     (function() {
            //         var host1 = document.querySelector('#idToken4_od');
            //         if (host1 && host1.shadowRoot) {
            //             var input = host1.shadowRoot.querySelector('input#input-2');
            //             if (input) {
            //                 input.value = " . $this->username . ";
            //                 input.dispatchEvent(new Event('input', { bubbles: true }));
            //                 input.dispatchEvent(new Event('change', { bubbles: true }));
            //             }
            //         }
            //     })();
            // ");
            $this->exts->click_by_xdotool('one-input#idToken4_od');
            sleep(3);
            $this->exts->type_text_by_xdotool($this->username);

            sleep(2);
            $this->exts->execute_javascript('
	            var shadow = document.querySelector("one-button.loginLegacySubmitBtn");
	            if(shadow){
	                shadow.shadowRoot.querySelector(\'button[role="button"]\').click();
	            }
	        ');
            sleep(5);
        }

        if ($this->exts->getElement($this->password_selector) != null) {
            $this->exts->log('Enter Password 2');

            // $this->exts->execute_javascript("
            //     (function() {
            //         var host1 = document.querySelector('one-input[type=\"password\"]');
            //         if (host1 && host1.shadowRoot) {
            //             var input = host1.shadowRoot.querySelector('input[type=\"password\"]');
            //             if (input) {
            //                 input.value = '" . $this->password . "';
            //                 input.dispatchEvent(new Event('input', { bubbles: true }));
            //                 input.dispatchEvent(new Event('change', { bubbles: true }));
            //             }
            //         }
            //     })();
            // ");
            $this->exts->click_by_xdotool('one-input[type="password"]');
            sleep(3);
            $this->exts->type_text_by_xdotool($this->password);

            $this->exts->capture('2-login-page-filled');
            $this->exts->execute_javascript('
	            var shadow = document.querySelector("one-button[data-type=\'main-action\']");
	            if (shadow && shadow.shadowRoot) {
	                var button = shadow.shadowRoot.querySelector("button[role=\'button\']");
	                if (button) {
	                    button.click();
	                }
	            }
	        ');

            sleep(5);

            // if ($this->exts->exists($this->mobile_verification_number)) {
            if ($this->exts->exists('[id="select-group"] one-select')) {
                $this->exts->log('Enter Password 1');
                $this->exts->log($this->user_mobile_number);
                $this->exts->log('this is working -------------------->');
                $this->exts->execute_javascript('
	                var shadowHost = document.querySelector("#select-group one-select");
	                if (shadowHost && shadowHost.shadowRoot) {
	                    var select = shadowHost.shadowRoot.querySelector("select");
	                    if (select) {
	                        select.value = "1";
	                        select.dispatchEvent(new Event("change", { bubbles: true }));
	                    }
	                }

	                var buttonElement = document.querySelector("one-button[data-type=\'main-action\']");
	                if (buttonElement) {
	                    buttonElement.removeAttribute("disabled");

	                    if (buttonElement.shadowRoot) {
	                        var innerButton = buttonElement.shadowRoot.querySelector("button");
	                        if (innerButton) {
	                            innerButton.disabled = false;
	                            innerButton.removeAttribute("disabled");
	                            innerButton.click();
	                        }
	                    } else {
	                        buttonElement.click();
	                    }
	                }
	            ');

                $this->exts->capture('2-login-page-number');
                sleep(5);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture('2-login-page-not-found');
        }
    }

    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'one-pin-input[autocomplete="one-time-code"]';
        $two_factor_message_selector = 'one-text[role="presentation"] b';
        $two_factor_submit_selector = 'one-button-group one-button[data-type="main-action"]';
        $this->exts->waitTillPresent($two_factor_selector, 20);

        $errMsg = $this->exts->extract('div[data-test-id="unified-login-error-display"]');
        $invalidUsernameMsg_gm = 'Ihr Nutzername ist uns nicht bekannt. Bitte überprüfen Sie ihre Eingabe.';
        $invalidUsernameMsg_en = 'We do not know your username. Please check your entry.';
        $this->exts->log($errMsg);
        if ((!empty($errMsg) && trim($errMsg) != '') && ($errMsg == $invalidUsernameMsg_gm || $errMsg == $invalidUsernameMsg_en || stripos($errMsg, 'Ihr Kennwort ist ungültig') !== false) || stripos($this->exts->extract('div#login h1'), 'Neues Kennwort anfordern') !== false) {
            $this->exts->loginFailure(1);
        }
        sleep(5);
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
                $this->exts->execute_javascript("
					const host = document.querySelector('$two_factor_selector');
					if (!host || !host.shadowRoot) return 'NO_SHADOW';
					const inputs = host.shadowRoot.querySelectorAll('fieldset div[class*=\"pin-input\"] input');
					const code = arguments[0];
					if (inputs.length !== code.length) return 'OTP_LENGTH_MISMATCH';
					code.split('').forEach((digit, idx) => {
						inputs[idx].value = digit;
						inputs[idx].dispatchEvent(new Event('input', { bubbles: true }));
						inputs[idx].dispatchEvent(new Event('change', { bubbles: true }));
					});
					return 'SUCCESS';
				", [$two_factor_code]);
                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(5);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
                $this->exts->execute_javascript("
					const host = document.querySelector('$two_factor_submit_selector');
					if (host && host.shadowRoot) {
						const btn = host.shadowRoot.querySelector('button[role=\"button\"]');
						if (btn) btn.click();
					}
				");
                $this->exts->click_element('one-button[data-type="main-action"]');
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

    private function checkFillTwoFactorEmail(): void
    {
        $selector = 'one-pin-input[id*="idToken"]';
        $message_selector = 'one-text[id*="callback"]';
        $submit_selector = 'one-button[data-type="main-action"]';

        while ($this->exts->getElement($selector) !== null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            // Collect and log the 2FA instruction messages
            $this->exts->two_factor_notif_msg_en = "";
            $messages = $this->exts->getElements($message_selector);
            foreach ($messages as $msg) {
                $this->exts->two_factor_notif_msg_en .= $msg->getAttribute('innerText') . "\n";
            }

            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);

            // Add retry message if this is the final attempt
            if ($this->exts->two_factor_attempts === 2) {
                $this->exts->two_factor_notif_msg_en .= ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de .= ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }

            $code = trim($this->exts->fetchTwoFactorCode());
            if ($code === '') {
                $this->exts->log("2FA code not received");
                break;
            }

            $this->exts->log("checkFillTwoFactor: Entering 2FA code: " . $two_factor_code);
            $this->exts->click_by_xdotool($selector);
            sleep(1);
            $this->exts->type_key_by_xdotool("Tab");
            sleep(1);
            $this->exts->type_text_by_xdotool($code);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            $this->exts->moveToElementAndClick($submit_selector);
            sleep(5); // Added: Ensure time for 2FA processing

            if ($this->exts->getElement($selector) === null) {
                $this->exts->log("Two factor solved");
                break;
            }

            $this->exts->two_factor_attempts++;
        }

        if ($this->exts->two_factor_attempts >= 3) {
            $this->exts->log("Two factor could not be solved after 3 attempts");
        }
    }

    private function checkProcessMultiAccounts()
    {
        $this->exts->openUrl('https://dsl.o2online.de/selfcare/content/segment/kundencenter/');
        sleep(10);
        if (count($this->exts->getElements('table#multiContractsDisplayTable')) > 0) {
            $this->processMultiContract();
        } else {
            $this->exts->openUrl('https://dsl.o2online.de/selfcare/content/segment/kundencenter/daten-vertraege/rechnung/monatsuebersicht/');
            $this->getInvoices();
        }
    }

    private function processMultiContract()
    {
        $selectors = [];
        $trs = $this->exts->getElements('table#multiContractsDisplayTable tbody tr');
        foreach ($trs as $tr) {
            $tds = $this->exts->getElements('td', $tr);
            if (count($tds) >= 4 && $this->exts->getElement('a', $tds[3]) != null) {
                array_push($selectors, $this->exts->getElement('a', $tds[3])->getAttribute('href'));
            }
        }

        foreach ($selectors as $selector) {
            $this->exts->openUrl($selector);
            $this->processConractPage();
        }
    }

    private function processConractPage()
    {
        sleep(25);
        $this->exts->openUrl('https://dsl.o2online.de/selfcare/content/segment/kundencenter/meinerechnung/monatsuebersicht/');
        $this->getInvoices();
    }

    private function getInvoices()
    {
        sleep(25);
        $this->exts->capture('3-bill');
        if ($this->exts->getElement('a[href*="/segment/business/"]') != null) {
            $this->exts->openUrl('https://dsl.o2online.de/selfcare/content/segment/business/daten/rechnung/rechnungen/');
            $this->processInvoiceBusiness();
        } else {
            $restrictPages = isset($this->exts->config_array['restrictPages']) ? (int) @$this->exts->config_array['restrictPages'] : 3;
            if ($restrictPages == 0) {
                $pages = [];
                $links = $this->exts->getElements('div#hnaccordion h3.hn-accordion-header a.hn-accordion-header-link');
                foreach ($links as $link) {
                    $year = trim(array_pop(explode(' ', $link->getAttribute('innerText'))));
                    array_push($pages, 'https://dsl.o2online.de/selfcare/content/segment/kundencenter/meinerechnung/monatsuebersicht/?year=' . $year);
                }
                foreach ($pages as $page) {
                    $this->exts->openUrl($page);
                    sleep(10);
                    $this->downloadInvoices();
                }
            } else {
                $this->downloadInvoices();
            }
        }
    }

    private function processInvoiceBusiness()
    {
        $this->exts->capture('4-invoices-page');
        $invoices = [];

        $rows = $this->exts->getElements('table#info_weitereRechnungen tbody tr');
        foreach ($rows as $index => $row) {
            if ($this->exts->getElement('td a[href*=".pdf?view=asDownload"]', $row) != null) {
                $cols = $this->exts->getElements('td', $rows[$index]);
                $invoiceUrl = $this->exts->getElement('td a[href*=".pdf?view=asDownload"]', $rows[$index])->getAttribute('href');
                $invoiceName = trim($cols[1]->getAttribute('innerText'));
                $invoiceDate = trim($cols[0]->getAttribute('innerText'));
                $invoiceAmount = '';

                array_push($invoices, [
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl,
                ]);
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
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
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

    private function downloadInvoices()
    {
        $this->exts->capture('4-invoices-page');
        $invoices = [];

        $rows = $this->exts->getElements('div#hnaccordion div.infoblock.ui-accordion-content-active table tbody tr');
        foreach ($rows as $index => $row) {
            if ($this->exts->getElement('td.col_subcategory', $row) != null) {
                $invoiceUrl = $this->exts->getElement('td.col_docname a', $rows[$index + 1])->getAttribute('href');
                $invoiceName = explode(
                    ')',
                    array_pop(explode('(', $this->exts->getElement('td.col_docname a', $rows[$index + 1])->getAttribute('innerText')))
                )[0];
                $invoiceDate = trim(array_pop(explode(' ', $this->exts->getElement('td.col_subcategory', $row)->getAttribute('innerText'))));
                $invoiceAmount = '';

                array_push($invoices, [
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl,
                ]);
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
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
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

    private function inherit()
    {
        sleep(25);
        // for ($wait_count = 1; $wait_count <= 10 && $this->exts->getElement('table > tbody > tr a[href*="/downloadinvoice') == null; $wait_count++) {
        //  $this->exts->log('Waiting for invoice...');
        //  sleep(5);
        // }
        $this->exts->capture('3-download-page');
        $invoices = [];

        $acc_id = trim(end(explode(':', $this->exts->extract('[snippet*="account-id"]'))));

        $rows = $this->exts->getElements('invoice-panel div.panel-body');
        foreach ($rows as $index => $row) {
            if ($this->exts->getElement('a.btn[data-description="bill-download-link"]', $row) != null) {
                $invoiceSelector = $this->exts->getElement('a.btn[data-description="bill-download-link"]', $row);
                $this->exts->execute_javascript("arguments[0].setAttribute('id', 'custom-pdf-download-button-" . $index . "');", [$invoiceSelector]);
                $invoiceName = trim($this->exts->getElement('div.text', $row)->getAttribute('innerText'));
                $invoiceName = str_replace(' ', '_', $invoiceName);
                $invoiceName = str_replace('.', '_', $invoiceName);
                $invoiceDate = trim(array_pop(explode(' ', $this->exts->getElement('div.text', $row)->getAttribute('innerText'))));
                if ($invoiceDate != '') {
                    $invoiceName = str_replace('.', '', $invoiceDate);
                }
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->getElement('div.pricing', $row)->getAttribute('innerText'))) . ' EUR';

                $invoiceDate = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');

                $this->exts->log('invoiceName ' . $invoiceName);
                $this->exts->log('invoiceDate ' . $invoiceDate);
                $this->exts->log('invoiceAmount ' . $invoiceAmount);
                $this->isNoInvoice = false;
                if (stripos($invoiceName, 'letzte') !== false || stripos($invoiceName, 'last') !== false) {
                    $invoiceName = '';
                    $invoiceFileName = '';
                } else {
                    $invoiceName = trim($acc_id . '_' . $invoiceName, '_, ');
                    $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                }
                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    // click and download invoice
                    $this->exts->moveToElementAndClick('a#custom-pdf-download-button-' . $index);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        if (trim($invoiceName) == '') {
                            $invoiceName = basename($downloaded_file, '.pdf');
                        }
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $downloaded_file);
                        sleep(1);
                    } else {
                        $this->exts->log('Timeout when download ' . $invoiceFileName);
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
