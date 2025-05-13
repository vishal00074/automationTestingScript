<?php
// Server-Portal-ID: 21708 - Last modified: 22.01.2025 14:49:07 UTC - User: 1

public $baseUrl = "https://www.ebay.com/";
public $invoice_url = "http://my.ebay.com/ws/eBayISAPI.dll?MyEbay&CurrentPage=MyeBayMyAccounts";
public $purchase_history = "https://www.ebay.com/myb/PurchaseHistory#PurchaseHistoryOrdersContainer?GotoPage=1";
public $signinLink = 'header div#gh-top ul li a[href*="signin.ebay.com"]';
public $username_selector = 'form#SignInForm input[name="userid"], form#signin-form input[name="userid"]';
public $password_selector = 'form#SignInForm input[name="pass"], form#signin-form input[name="pass"]';
public $continue_button = 'form#SignInForm button#sgnBt, form#signin-form button#signin-continue-btn';
public $login_submit_selector = "form#SignInForm button#sgnBt, form#signin-form div.password-box-wrapper ~ button#sgnBt";
public $remember_selector = 'form#SignInForm input[name="keepMeSignInOption2"]';
public $restrictPages = 3;
public $fetch_transaction = 3;
public $isNoInvoice = true;

/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    $this->fetch_transaction = isset($this->exts->config_array["fetch_transaction"]) ? (int)@$this->exts->config_array["fetch_transaction"] : 0;

    $this->exts->openUrl($this->baseUrl);

    $this->checkAndReloadUrl($this->exts->getUrl());
    $this->callRecaptcha();
    $this->exts->capture('before-check-geetest');
    $this->checkAndSolveGeeTestCaptcha();
    sleep(15);

    $this->reloadPage('div#captcha-box span#captcha-status-message[style*="inline"]', 15);

    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);

    $this->checkAndReloadUrl($this->exts->getUrl());
    $this->callRecaptcha();
    sleep(1);

    $this->exts->capture('before-check-geetest');
    $this->checkAndSolveGeeTestCaptcha();
    sleep(15);
    $this->reloadPage('div#captcha-box span#captcha-status-message[style*="inline"]', 15);

    $this->exts->capture('1-init-page');

    if (!$this->checkLogin()) {
        $this->exts->click_by_xdotool('');
    }


    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        // $this->exts->clearCookies();
        // sleep(2);
        $this->exts->openUrl($this->baseUrl);
        sleep(15);

        $this->checkAndReloadUrl($this->exts->getUrl());
        $this->callRecaptcha();
        sleep(1);

        $this->exts->capture('before-check-geetest');
        $this->checkAndSolveGeeTestCaptcha();
        sleep(15);
        $this->reloadPage('div#captcha-box span#captcha-status-message[style*="inline"]', 15);

        $this->exts->openUrl($this->baseUrl);
        sleep(15);

        if ($this->exts->querySelector($this->signinLink) != null) {
            $this->exts->log("Found Primary Login Link!!");
            $this->exts->querySelector($this->signinLink)->click();
        } elseif ($this->exts->querySelector('span[class="gh-identity-signed-out-unrecognized"] a[href*="signin"]') != null) {
            $this->exts->log("Found Primary Login Link!!");
            $this->exts->click_element('span[class="gh-identity-signed-out-unrecognized"] a[href*="signin"]');
        } else {
            $this->exts->openUrl($this->invoice_url);
        }
        sleep(15);

        $msg = trim(strtolower($this->exts->extract('div.pgHeading h1')));
        $this->exts->log('msg: ' . $msg);
        if (strpos($msg, 'unable to identify your browser') !== false) {
            $this->exts->openUrl($this->invoice_url);
            sleep(15);
        }

        if ($this->exts->exists('div#captcha-box input[name="geetest_validate"]')) {
            $this->exts->capture('before-check-geetest');
            $this->checkAndSolveGeeTestCaptcha();
            sleep(15);
            $this->reloadPage('div#captcha-box span#captcha-status-message[style*="inline"]', 15);
        }

        $this->checkAndReloadUrl($this->exts->getUrl());
        $this->callRecaptcha();
        sleep(1);

        $this->exts->capture('before-check-geetest');
        $this->checkAndSolveGeeTestCaptcha();
        sleep(15);
        $this->reloadPage('div#captcha-box span#captcha-status-message[style*="inline"]', 15);

        $this->fillForm(0);
        sleep(20);

        if ($this->exts->exists('div#uciEditForm')) {
            $this->exts->click_by_xdotool('button[data-testid="test-ask-me-later-btn"]');
            sleep(12);
        }
        // button[data-testid="test-ask-me-later-btn"]
        $this->checkAndReloadUrl($this->exts->getUrl());
        $this->callRecaptcha();
        sleep(1);

        $this->exts->capture('before-check-geetest');
        $this->checkAndSolveGeeTestCaptcha();
        sleep(15);
        $this->reloadPage('div#captcha-box span#captcha-status-message[style*="inline"]', 15);
    }

    if ($this->checkLogin()) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        $this->processAfterLogin(0);
    } else {

        // phone 2FA
        // if ($this->exts->exists('input[value="phone"]')) {
        // 	$this->exts->click_by_xdotool('input[value="phone"]:not(:checked)');
        // 	sleep(2);

        // 	$this->exts->click_by_xdotool('button[name="submitBtn"]');
        // 	sleep(15);

        // 	$this->exts->click_by_xdotool('input#numSelected1:not(:checked)');
        // 	sleep(2);

        // 	$this->exts->click_by_xdotool('#fullscale[action="/Phone"] button[value="text"]');
        // 	sleep(15);

        // 	$two_factor_selector = 'form#SignIn2FA input[name="pin"], #verifyCodeForm #code';
        // 	$two_factor_submit_selector = 'form#SignIn2FA button#subBtn, [name="submitBtn"]';
        // 	$two_factor_message_selector = 'span#mfa_send_status, form#verifyCodeForm > h3, form#verifyCodeForm > p#subheader';
        // 	$this->checkFillTwoFactor($two_factor_selector, $two_factor_submit_selector, $two_factor_message_selector);
        // 	sleep(15);
        // } else if($this->exts->exists('form#SignIn2FA input[name="pin"], #verifyCodeForm #code')) {
        // 	$two_factor_selector = 'form#SignIn2FA input[name="pin"], #verifyCodeForm #code';
        // 	$two_factor_submit_selector = 'form#SignIn2FA button#subBtn, [name="submitBtn"]';
        // 	$two_factor_message_selector = 'span#mfa_send_status, form#verifyCodeForm > h3, form#verifyCodeForm > p#subheader';
        // 	$this->checkFillTwoFactor($two_factor_selector, $two_factor_submit_selector, $two_factor_message_selector);
        // 	sleep(15);
        // } else if ($this->exts->exists('input[value="email"]')) {
        // 	// email 2FA
        // 	$this->exts->click_by_xdotool('input[value="email"]:not(:checked)');
        // 	sleep(2);

        // 	$this->exts->click_by_xdotool('button[name="submitBtn"]');
        // 	sleep(15);

        // 	// security question
        // 	if ($this->exts->exists('form#securityQuestionForm')) {
        // 		$this->exts->click_by_xdotool('input#questionId1:not(:checked)');
        // 		sleep(2);

        // 		$two_factor_selector = '[name="answer"]';
        // 		$two_factor_submit_selector = '[name="submitBtn"]';
        // 		$two_factor_message_selector = '[for="questionId1"], form#securityQuestionForm p';
        // 		$this->checkFillTwoFactor($two_factor_selector, $two_factor_submit_selector, $two_factor_message_selector);
        // 		sleep(15);
        // 	} else if ($this->exts->exists('form#SignIn2FA input[name="pin"], #verifyCodeForm #code')){
        // 		$two_factor_selector = 'form#SignIn2FA input[name="pin"], #verifyCodeForm #code';
        // 		$two_factor_submit_selector = 'form#SignIn2FA button#subBtn, [name="submitBtn"]';
        // 		$two_factor_message_selector = 'span#mfa_send_status, form#verifyCodeForm > h3, form#verifyCodeForm > p#subheader';
        // 		$this->checkFillTwoFactor($two_factor_selector, $two_factor_submit_selector, $two_factor_message_selector);
        // 		sleep(15);
        // 	}
        // } else if ($this->exts->exists('div#verifyitsyou ~ div a[href*="/StartPhone/"]')) {
        // 	$this->exts->click_by_xdotool('div#verifyitsyou ~ div a[href*="/StartPhone/"]');
        // 	sleep(15);

        // 	$this->checkFillTwoFactor('input[name="code"]', 'button[name="submitBtn"]', 'div#verifyCodeContent p');
        // } else if ($this->exts->exists('div#verifyitsyou ~ a[href*="/Email/"]')) {
        // 	$this->exts->click_by_xdotool('div#verifyitsyou ~ a[href*="/Email/"]');
        // 	sleep(15);

        // 	$this->checkFillTwoFactorWithEmail('div#email p');
        // } else if ($this->exts->exists('div.mfa_contr div.push-2fa-main-container')) {
        // 	$this->checkFillTwoFactorWithEmail('h2#push-main-header + div span.pushNoticeText');
        // }

        $this->check2FA();
        $this->check2FA();

        // click some button when finished 2FA
        if ($this->exts->querySelector('form[name="contactInfoForm"] a#rmdLtr') != null) {
            $this->exts->click_by_xdotool('form[name="contactInfoForm"] a#rmdLtr');
        } else if ($this->exts->querySelector('#fullscale [name="submitBtn"]') != null) {
            $this->exts->click_by_xdotool('#fullscale [name="submitBtn"]');
        } else if ($this->exts->querySelector('.primsecbtns [value="text"]') != null) {
            $this->exts->click_by_xdotool('.primsecbtns [value="text"]');
        } else if ($this->exts->querySelector("a#continue-get") != null) {
            $this->exts->click_by_xdotool("a#continue-get");
        }

        sleep(2);

        // Check login after finished 2FA
        if ($this->checkLogin()) {
            $this->exts->openUrl($this->invoice_url);
            sleep(15);

            $this->checkAndReloadUrl($this->exts->getUrl());
            $this->callRecaptcha();
            sleep(1);

            $this->exts->capture('before-check-geetest');
            $this->checkAndSolveGeeTestCaptcha();
            sleep(15);
            $this->reloadPage('div#captcha-box span#captcha-status-message[style*="inline"]', 15);

            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            $this->processAfterLogin(0);
        } else {
            if ($this->exts->exists('span.mi-er span.sd-err, #errf')) {
                $this->exts->capture("Wrong credentials");
                $this->exts->loginFailure(1);
            } else if ($this->exts->exists('div.need-help button') && $this->exts->exists('form#signin-form p#signin-error-msg')) {
                $bt_txt = strtolower($this->exts->extract('div.need-help button', null, 'innerText'));
                $bt_txt1 = strtolower($this->exts->extract('form#signin-form p#signin-error-msg', null, 'innerText'));
                $this->exts->log($bt_txt);
                $this->exts->log($bt_txt1);
                if (strpos($bt_txt, 'reset your password') !== false || strpos($bt_txt1, 'reset your password') !== false) {
                    $this->exts->account_not_ready();
                } else if (strpos($this->exts->getUrl(), 'reqinput=') !== false) {
                    $this->exts->account_not_ready();
                } else if (strpos($bt_txt, "that's not a match") !== false || strpos($bt_txt1, "not a match") !== false) {
                    $this->exts->loginFailure(1);
                } else {
                    $this->exts->capture('login-failed-test');
                }
            } else if (strpos($this->exts->getUrl(), 'reqinput=') !== false) {
                $this->exts->account_not_ready();
            } else {
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure();
            }
        }
    }
}

private function reloadPage($sel_chk, $time)
{
    if ($this->exts->exists($sel_chk)) {
        $this->exts->refresh();
        sleep($time);
        $this->exts->capture('before-check-geetest');
        $this->checkAndSolveGeeTestCaptcha();
        sleep(15);
    }
}

private function checkAndReloadUrl($url)
{
    sleep(15);
    $this->exts->capture('check-and-reload');
    $msg = trim(strtolower($this->exts->extract('div.pgHeading h1', null, 'innerText')));
    $this->exts->log('msg: ' . $msg);
    if (strpos($msg, 'unable to identify your browser') !== false || strpos($msg, 'browser konnte nicht erkannt werden') !== false) {
        $this->exts->openUrl($url);
        sleep(15);
    } else if (!$this->exts->exists('a[href="https://www.ebay.com/"] img[id*="logo"]')) {
        for ($i = 0; $i < 3; $i++) {
            $msg1 = strtolower($this->exts->extract('div#main-frame-error p[jsselect="summary"]', null, 'innerText'));
            if (strpos($msg1, 'took too long to respond') !== false) {
                $this->exts->refresh();
                sleep(15);
                $this->exts->capture('after-refresh-cant-be-reach-' . $i);
            } else {
                break;
            }
        }
    }

    $msg = trim(strtolower($this->exts->extract('div#myerr p', null, 'innerText')));
    if (strpos($msg, ' technical difficulties') !== false || $this->exts->exists('a[href*="/DefaultPage"][href*="reqinput"]')) {
        $this->exts->click_by_xdotool('a[href*="/DefaultPage"]');
        sleep(15);
    }
}

/**
 * Method to fill login form
 * @param Integer $count Number of times portal is retried.
 */
private function fillForm($count = 1)
{
    $this->exts->log("Begin fillForm " . $count);
    try {

        if ($this->exts->querySelector($this->password_selector) != null || $this->exts->querySelector($this->username_selector) != null) {
            $this->exts->capture("1-pre-login");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);

            if ($this->exts->exists($this->continue_button)) {
                $this->exts->click_by_xdotool($this->continue_button);
            }

            $this->checkAndReloadUrl($this->exts->getUrl());
            $this->callRecaptcha();

            $mesg = strtolower($this->exts->extract('p#signin-error-msg', null, 'innerText'));
            $this->exts->log($mesg);
            if (strpos($mesg, 'we ran into a problem. please try again later') !== false) {
                $this->exts->refresh();
                sleep(15);

                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(2);

                if ($this->exts->exists($this->continue_button)) {
                    $this->exts->click_by_xdotool($this->continue_button);
                    sleep(15);
                }

                $this->checkAndReloadUrl($this->exts->getUrl());
                $this->callRecaptcha();
            }

            if (strpos($mesg, "that doesn't match") !== false) {
                $this->exts->loginFailure(1);
            }

            $this->exts->capture('before-check-geetest');
            $this->checkAndSolveGeeTestCaptcha();
            $this->checkAndSolveGeeTestCaptcha();
            sleep(15);
            $this->exts->capture('after-submit-username');
            $this->reloadPage('div#captcha-box span#captcha-status-message[style*="inline"]', 15);
            $this->exts->capture('after-submit-username-1');

            if ($this->exts->exists($this->username_selector)) {
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(2);

                if ($this->exts->exists($this->continue_button)) {
                    $this->exts->click_by_xdotool($this->continue_button);
                    sleep(30);
                }
            }

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            $this->exts->capture("2-filled-login");
            $this->exts->click_by_xdotool($this->login_submit_selector);
            sleep(8);
            $this->checkAndReloadUrl($this->exts->getUrl());
            $this->callRecaptcha();
            sleep(10);

            $mesg = strtolower($this->exts->extract('p#signin-error-msg', null, 'innerText'));
            $this->exts->log($mesg);
            if (strpos($mesg, 'we ran into a problem. please try again later') !== false) {
                $this->exts->refresh();
                sleep(15);

                $this->exts->capture('input-password-error');

                $this->exts->log("Enter password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(2);

                if ($this->exts->exists($this->login_submit_selector)) {
                    $this->exts->click_by_xdotool($this->login_submit_selector);
                    sleep(15);
                }

                $this->checkAndReloadUrl($this->exts->getUrl());
                $this->callRecaptcha();
            }

            if (strpos($mesg, "that doesn't match") !== false) {
                $this->exts->loginFailure(1);
            }

            if (strpos($mesg, "locked your account") !== false) {
                $this->exts->account_not_ready();
            }

            $this->exts->capture('before-check-geetest');
            $this->checkAndSolveGeeTestCaptcha();
            sleep(15);
            $this->reloadPage('div#captcha-box span#captcha-status-message[style*="inline"]', 15);

            $mesg = strtolower($this->exts->extract('p#signin-error-msg', null, 'innerText'));
            $this->exts->log($mesg);
            if (strpos($mesg, "that's not a match") !== false) {
                $this->exts->loginFailure(1);
            }

            $this->checkAndReloadUrl($this->exts->getUrl());
            $this->callRecaptcha();
            sleep(1);
            $this->exts->log('checkAndSolveGeeTestCaptchabody:' . $this->exts->extract('body', null, 'innerHTML'));
            $this->exts->capture('before-check-geetest');
            $this->checkAndSolveGeeTestCaptcha();
            sleep(15);
            $this->exts->capture('after-submit-password');
            $this->reloadPage('div#captcha-box span#captcha-status-message[style*="inline"]', 15);
            $this->exts->capture('after-submit-password-1');

            if ($this->exts->exists($this->password_selector)) {
                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(2);

                $this->exts->capture("2-filled-login");
                $this->exts->click_by_xdotool($this->login_submit_selector);
                sleep(15);
            }
        }
        sleep(4);
    } catch (\Exception $exception) {
        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

private function checkAndSolveGeeTestCaptcha($counter = 1)
{
    if ($this->exts->querySelector("div[id='captcha-box']")) {
        $this->exts->log("Found captcha, process to solve");
        $geetestKey = $this->exts->executeSafeScript("return window.GeeGT;");
        $api_server = 'api-na.geetest.com';
        $script = '
        function httpGet(theUrl) {
            var xmlHttp = new XMLHttpRequest();
            xmlHttp.open( "GET", theUrl, false ); // false for synchronous request
            xmlHttp.send( null );
            return xmlHttp.responseText;
        }
        return httpGet("https://www.ebay.com/distil_r_captcha_challenge");
        ';
        $geetestChallenge = explode(";", $this->exts->executeSafeScript($script))[0];
        $this->exts->log("key: " . $geetestKey . " challenge: " . $geetestChallenge);
        $this->exts->processGeeTestCaptcha('form[id="distilCaptchaForm"]', $geetestKey, $geetestChallenge, $this->exts->getUrl(), $api_server);
    } else {
        $counter++;
        $this->exts->log($this->exts->getUrl());
        $msg = trim(strtolower($this->exts->extract('div.pgHeading h1', null, 'innerText')));
        $this->exts->log('msg: ' . $msg);
        if ($this->exts->exists('iframe#captchaFrame') && $counter < 5) {
            $this->exts->refresh();
            sleep(15);
            $this->exts->capture("No captcha found!" . $counter);
            $this->checkAndSolveGeeTestCaptcha($counter);
        }
        // $this->exts->log('checkAndSolveGeeTestCaptchabody:' . $this->exts->extract('body', null, 'innerHTML'));
        // if ($counter < 5) {
        // 	$this->exts->refresh();
        // 	sleep(15);
        // 	$this->exts->capture("No captcha found!" . $counter);
        // 	$this->checkAndSolveGeeTestCaptcha($counter);
        // }
    }
}

private function checkFillRecaptcha($count = 1)
{
    $this->exts->log(__FUNCTION__);
    $recaptcha_iframe_selector = 'iframe[src*="/recaptcha/api2/anchor?"]';
    $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
    $this->exts->waitTillPresent($recaptcha_iframe_selector, 20);
    if ($this->exts->exists($recaptcha_iframe_selector)) {
        $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
        $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
        $this->exts->log("iframe url  - " . $iframeUrl);
        $this->exts->log("SiteKey - " . $data_siteKey);

        $isCaptchaSolved = $this->exts->processRecaptcha($this->exts->getUrl(), $data_siteKey, false);
        $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);

        if ($isCaptchaSolved) {
            // Step 1 fill answer to textarea
            $this->exts->log(__FUNCTION__ . "::filling reCaptcha response..");
            $recaptcha_textareas =  $this->exts->querySelectorAll($recaptcha_textarea_selector);
            for ($i = 0; $i < count($recaptcha_textareas); $i++) {
                $this->exts->execute_javascript("arguments[0].innerHTML = '" . $this->exts->recaptcha_answer . "';", [$recaptcha_textareas[$i]]);
            }
            sleep(2);
            $this->exts->capture('recaptcha-filled');

            $gcallbackFunction = $this->exts->execute_javascript('
                (function() { 
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
                })();
			');
            $this->exts->log('Callback function: ' . $gcallbackFunction);
            $this->exts->log('Callback function: ' . $this->exts->recaptcha_answer);
            if ($gcallbackFunction != null) {
                $this->exts->execute_javascript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
                sleep(10);
            }
        } else {
            // try again if recaptcha expired
            if ($count < 3) {
                $count++;
                $this->checkFillRecaptcha($count);
            }
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
    }
}

// private function checkFillHcaptcha()
// {
//     $hcaptcha_iframe_selector = 'iframe[src*="hcaptcha"]';
//     if ($this->exts->exists($hcaptcha_iframe_selector)) {
//         $iframeUrl = $this->exts->extract($hcaptcha_iframe_selector, null, 'src');
//         $data_siteKey =  end(explode("&sitekey=", $iframeUrl));
//         $jsonRes = $this->exts->processHumanCaptcha("", $data_siteKey, $this->exts->getUrl(), false);
//         $captchaScript = '
//         function submitToken(token) {
//         document.querySelector("[name=g-recaptcha-response]").innerText = token;
//         document.querySelector("[name=h-captcha-response]").innerText = token;
//         }
//         submitToken(arguments[0]);
//         ';
//         $params = array($jsonRes);

//         sleep(2);
//         $guiId = $this->exts->extract('input[id*="captcha-data"]', null, 'value');
//         $guiId = trim(explode('"', end(explode('"guid":"', $guiId)))[0]);
//         $this->exts->log('guiId: ' . $guiId);
//         $this->exts->executeSafeScript($captchaScript, $params);
//         $str_command = 'var btn = document.createElement("INPUT");
//                         var att = document.createAttribute("type");
//                         att.value = "hidden";
//                         btn.setAttributeNode(att);
//                         var att = document.createAttribute("name");
//                         att.value = "captchaTokenInput";
//                         btn.setAttributeNode(att);
//                         var att = document.createAttribute("value");
//                         btn.setAttributeNode(att);
//                           form1 = document.querySelector("#captcha_form");
//                           form1.appendChild(btn);';
//         $this->exts->executeSafeScript($str_command);
//         sleep(2);
//         $captchaScript = '
//         function submitToken1(token) {
//         document.querySelector("[name=captchaTokenInput]").value = token;
//         }
//         submitToken1(arguments[0]);
//         ';
//         $captchaTokenInputValue = '%7B%22guid%22%3A%22' . $guiId . '%22%2C%22provider%22%3A%22' . 'hcaptcha' . '%22%2C%22appName%22%3A%22' . 'orch' . '%22%2C%22token%22%3A%22' . $jsonRes . '%22%7D';
//         $params = array($captchaTokenInputValue);
//         $this->exts->executeSafeScript($captchaScript, $params);

//         $this->exts->log($this->exts->extract('input[name="captchaTokenInput"]', null, 'value'));
//         sleep(2);
//         $gcallbackFunction = 'captchaCallback';
//         $this->exts->executeSafeScript($gcallbackFunction . '("' . $jsonRes . '");');

//         sleep(15);
//     }
// }

private function solve_captcha_by_clicking($count = 1)
{
    $this->exts->log("Checking captcha");
    $language_code = '';
    $unsolved_hcaptcha_submit_selector = 'div[class="target-icaptcha-slot"] iframe[data-hcaptcha-response=""]';
    $hcaptcha_challenger_wraper_selector = 'div[style*="visible"] iframe[src*="hcaptcha"][title*="hallenge"]';
    $this->exts->waitTillAnyPresent([$unsolved_hcaptcha_submit_selector, $hcaptcha_challenger_wraper_selector], 20);
    if ($this->exts->check_exist_by_chromedevtool($unsolved_hcaptcha_submit_selector) || $this->exts->exists($hcaptcha_challenger_wraper_selector)) {
        // Check if challenge images hasn't showed yet, Click checkbox to show images challenge
        if (!$this->exts->check_exist_by_chromedevtool($hcaptcha_challenger_wraper_selector)) {
            $this->exts->click_by_xdotool($unsolved_hcaptcha_submit_selector);
            $this->exts->waitTillPresent($hcaptcha_challenger_wraper_selector, 20);
        }
        $this->exts->capture("tesla-captcha");

        $captcha_instruction = '';

        //$captcha_instruction = $this->exts->extract($iframeElement_instartion,null, 'innerText');
        $this->exts->log('language_code: ' . $language_code . ' Instruction: ' . $captcha_instruction);
        sleep(5);
        $captcha_wraper_selector = 'div[style*="visible"] iframe[src*="hcaptcha"][title*="hallenge"]';

        if ($this->exts->exists($captcha_wraper_selector)) {
            $coordinates = $this->getCoordinates($captcha_wraper_selector, $captcha_instruction, '', $json_result = false);


            // if($coordinates == '' || count($coordinates) < 2){
            //  $coordinates = $this->exts->processClickCaptcha($captcha_wraper_selector, $captcha_instruction, '', $json_result=false);
            // }
            if ($coordinates != '') {
                // $challenge_wraper = $this->exts->querySelector($captcha_wraper_selector);

                foreach ($coordinates as $coordinate) {
                    $this->click_hcaptcha_point($captcha_wraper_selector, (int)$coordinate['x'], (int)$coordinate['y']);
                }

                $this->exts->capture("tesla-captcha-selected " . $count);
                $this->exts->makeFrameExecutable('div[style*="visible"] iframe[src*="hcaptcha"][title*="hallenge"]')->click_element('div.button-submit');
                sleep(10);
                return true;
            }
        }

        return false;
    }
}

private function click_hcaptcha_point($selector = '', $x_on_element = 0, $y_on_element = 0)
{
    $this->exts->log(__FUNCTION__ . " $selector $x_on_element $y_on_element");
    $selector = base64_encode($selector);
    $element_coo = $this->exts->execute_javascript('
		var x_on_element = ' . $x_on_element . '; 
		var y_on_element = ' . $y_on_element . ';
		var coo = document.querySelector(atob("' . $selector . '")).getBoundingClientRect();
		// Default get center point in element, if offset inputted, out put them
		if(x_on_element > 0 || y_on_element > 0) {
			Math.round(coo.x + x_on_element) + "|" + Math.round(coo.y + y_on_element);
		} else {
			Math.round(coo.x + coo.width/2) + "|" + Math.round(coo.y + coo.height/2);
		}
		
	');
    // sleep(1);
    $this->exts->log("Browser clicking position: $element_coo");
    $element_coo = explode('|', $element_coo);

    $root_position = $this->exts->get_brower_root_position();
    $this->exts->log("Browser root position");
    $this->exts->log(print_r($root_position, true));

    $clicking_x = (int)$element_coo[0] + (int)$root_position['root_x'];
    $clicking_y = (int)$element_coo[1] + (int)$root_position['root_y'];
    $this->exts->log("Screen clicking position: $clicking_x $clicking_y");
    $node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-" . $this->exts->process_uid;
    // move randomly
    exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 60, $clicking_x + 60) . " " . rand($clicking_y - 50, $clicking_y + 50) . "'");
    exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 50, $clicking_x + 50) . " " . rand($clicking_y - 50, $clicking_y + 50) . "'");
    exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 40, $clicking_x + 40) . " " . rand($clicking_y - 41, $clicking_y + 40) . "'");
    exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 30, $clicking_x + 30) . " " . rand($clicking_y - 35, $clicking_y + 30) . "'");
    exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 20, $clicking_x + 20) . " " . rand($clicking_y - 25, $clicking_y + 25) . "'");
    exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 10, $clicking_x + 10) . " " . rand($clicking_y - 10, $clicking_y + 10) . "'");

    exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . $clicking_x . " " . $clicking_y . " click 1;'");
}

private function getCoordinates(
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

/**
 * Method to Process Two-Factor Authentication
 */
private function checkFillTwoFactor($two_factor_selector, $two_factor_submit_selector, $two_factor_message_selector)
{
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

            $this->exts->click_by_xdotool($two_factor_submit_selector);
            sleep(15);

            if ($this->exts->querySelector($two_factor_selector) == null) {
                $this->exts->log("Two factor solved");
            } else if ($this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                $this->notification_uid = "";
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
            sleep(15);

            if ($this->exts->querySelector($two_factor_message_selector) == null && !$this->exts->exists('div.mfa_contr div.push-2fa-main-container')) {
                $this->exts->log("Two factor solved");
            } else if ($this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                $this->notification_uid = "";
                $this->checkFillTwoFactorWithEmail($two_factor_message_selector);
            } else {
                $this->exts->log("Two factor can not solved");
            }
        } else {
            $this->exts->log("Not received two factor code");
        }
    }
}

private function checkFillSercurityQuestions()
{
    $two_factor_selector = 'input#answer';
    $two_factor_message_selector = 'form#securityQuestionForm h3, form#securityQuestionForm p';
    $two_factor_submit_selector = 'form#securityQuestionForm button[name="submitBtn"]';

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

            $this->exts->click_by_xdotool($two_factor_submit_selector);
            sleep(15);

            $this->exts->capture('after-confirm-email');

            if ($this->exts->querySelector($two_factor_selector) == null) {
                $this->exts->log("Two factor solved");
                $this->exts->capture("Two factor solved");
            } else if ($this->exts->two_factor_attempts < 3) {
                $this->exts->capture("Two factor try again");
                $this->exts->two_factor_attempts++;
                $this->notification_uid = "";
                $this->checkFillSercurityQuestions();
            } else {
                $this->exts->log("Two factor can not solved");
                $this->exts->capture("Two factor can not solved");
            }
        } else {
            $this->exts->log("Not received two factor code");
            $this->exts->capture("Not received two factor code");
        }
    }
}

/**
 * Method to Check where user is logged in or not
 * return boolean true/false
 */
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
            if (strpos($bt_text, 'hi ', 0) !== false && strpos($bt_text, '!') !== false) {
                // try{
                // 	$this->exts->log('Click account button');
                // 	$button->click();
                // } catch(\Exception $exception){
                // 	$this->exts->log('Click account button by javascript');
                // 	$this->exts->executeSafeScript("arguments[0].click()", [$button]);
                // }
                // sleep(5);
                $isLoggedIn = true;
                break;
            }
        }


        if ($this->exts->exists('a[href*="SignIn&lgout=1"], a[href$="MyEbay&gbh=1"], form#secretQuesForm, form#contactInfoForm, select[name="invoiceMonthYear"], a#continue-get') && !$this->exts->exists($this->username_selector) && !$this->exts->exists($this->password_selector) && !$this->exts->exists('[class*="guest"] a[href*="SignIn"][href*="signin.ebay.com"]') && !$this->exts->exists('div.signout-banner a#signin-link')) {
            $this->exts->log(">>>>>>>>>>>>>>>Login success!!!!");
            $isLoggedIn = true;
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception checking loggedin " . $exception->getMessage());
    }

    return $isLoggedIn;
}

private function check2FA()
{
    if ($this->exts->exists('input[value="phone"]')) {
        $this->exts->click_by_xdotool('input[value="phone"]:not(:checked)');
        sleep(2);

        $this->exts->click_by_xdotool('button[name="submitBtn"]');
        sleep(20);

        $this->checkAndReloadUrl($this->exts->getUrl());

        $this->exts->capture('after-choose-phone-method');

        for ($i = 0; $i < 3; $i++) {
            if ($this->exts->exists('input[value="phone"]')) {
                $this->exts->click_by_xdotool('input[value="phone"]:not(:checked)');
                sleep(2);

                $this->exts->click_by_xdotool('button[name="submitBtn"]');
                sleep(20);

                $this->exts->capture('after-choose-phone-method-again');
            } else {
                break;
            }
        }


        $this->exts->click_by_xdotool('input#numSelected1:not(:checked)');
        sleep(2);

        $this->exts->click_by_xdotool('#fullscale[action="/Phone"] button[value="text"]');
        sleep(15);

        $this->checkAndReloadUrl($this->exts->getUrl());
        $this->callRecaptcha();

        $this->exts->capture('after-choose-phone');
        for ($i = 0; $i < 3; $i++) {
            if ($this->exts->exists('input#numSelected1')) {
                $this->exts->click_by_xdotool('input#numSelected1:not(:checked)');
                sleep(2);

                $this->exts->click_by_xdotool('#fullscale[action="/Phone"] button[value="text"]');
                sleep(15);
                $this->exts->capture('after-choose-phone-again');
            } else {
                break;
            }
        }

        $this->checkFillSercurityQuestions();

        $two_factor_selector = 'form#SignIn2FA input[name="pin"], #verifyCodeForm #code';
        $two_factor_submit_selector = 'form#SignIn2FA button#subBtn, [name="submitBtn"]';
        $two_factor_message_selector = 'span#mfa_send_status, form#verifyCodeForm > h3, form#verifyCodeForm > p#subheader';
        $this->checkFillTwoFactor($two_factor_selector, $two_factor_submit_selector, $two_factor_message_selector);
        sleep(15);
    } else if ($this->exts->exists('input[value="email"]')) {
        // email 2FA
        $this->exts->click_by_xdotool('input[value="email"]:not(:checked)');
        sleep(2);

        $this->exts->click_by_xdotool('button[name="submitBtn"]');
        sleep(15);

        $this->checkAndReloadUrl($this->exts->getUrl());
        $this->callRecaptcha();

        $this->exts->capture('after-choose-email');

        for ($i = 0; $i < 3; $i++) {
            if ($this->exts->exists('input[value="email"]')) {
                $this->exts->click_by_xdotool('input[value="email"]:not(:checked)');
                sleep(2);

                $this->exts->click_by_xdotool('button[name="submitBtn"]');
                sleep(15);
                $this->exts->capture('after-choose-email-again');
            } else {
                break;
            }
        }

        $this->checkFillSercurityQuestions();

        // security question
        if ($this->exts->exists('form#securityQuestionForm')) {
            $this->exts->click_by_xdotool('input#questionId1:not(:checked)');
            sleep(2);

            $this->checkFillSercurityQuestions();

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
        $this->exts->click_by_xdotool('div#verifyitsyou ~ div a[href*="/StartPhone/"]');
        sleep(15);

        $this->checkAndReloadUrl($this->exts->getUrl());
        $this->callRecaptcha();
        $this->exts->capture('after-choose-sms-method');
        for ($i = 0; $i < 3; $i++) {
            if ($this->exts->exists('div#verifyitsyou ~ div a[href*="/StartPhone/"]')) {
                $this->exts->click_by_xdotool('div#verifyitsyou ~ div a[href*="/StartPhone/"]');
                sleep(15);
                $this->exts->capture('after-choose-sms-method-again');
            } else {
                break;
            }
        }

        $this->checkFillSercurityQuestions();

        $this->checkFillTwoFactor('input[name="code"]', 'button[name="submitBtn"]', 'div#verifyCodeContent p');
    } else if ($this->exts->exists('div#verifyitsyou ~ a[href*="/Email/"]')) {
        $this->exts->click_by_xdotool('div#verifyitsyou ~ a[href*="/Email/"]');
        sleep(15);

        $this->checkAndReloadUrl($this->exts->getUrl());
        $this->callRecaptcha();

        $this->exts->capture('after-choose-email-method');
        for ($i = 0; $i < 3; $i++) {
            if ($this->exts->exists('div#verifyitsyou ~ a[href*="/Email/"]')) {
                $this->exts->click_by_xdotool('div#verifyitsyou ~ a[href*="/Email/"]');
                sleep(15);
                $this->exts->capture('after-choose-email-method-again');
            } else {
                break;
            }
        }

        $this->checkFillSercurityQuestions();

        $this->checkFillTwoFactorWithEmail('div#email p');
    } else if ($this->exts->exists('div.mfa_contr div.push-2fa-main-container')) {
        $this->checkFillSercurityQuestions();
        $this->checkFillTwoFactorWithEmail('h2#push-main-header + div span.pushNoticeText');
    } else if ($this->exts->exists('form#SignIn2FA input[name="pin"], #verifyCodeForm #code')) {
        $this->checkFillSercurityQuestions();
        $two_factor_selector = 'form#SignIn2FA input[name="pin"], #verifyCodeForm #code';
        $two_factor_submit_selector = 'form#SignIn2FA button#subBtn, [name="submitBtn"]';
        $two_factor_message_selector = 'span#mfa_send_status, form#verifyCodeForm > h3, form#verifyCodeForm > p#subheader';
        $this->checkFillTwoFactor($two_factor_selector, $two_factor_submit_selector, $two_factor_message_selector);
        sleep(15);
    }
}

/**
 * Method to relogin when use cookie -> login successfully but access list invoice url displayed login page
 */
private function reLogin($current_url)
{
    $this->exts->openUrl($this->baseUrl);
    sleep(15);

    $this->checkAndSolveGeeTestCaptcha();
    sleep(15);
    $this->reloadPage('div#captcha-box span#captcha-status-message[style*="inline"]', 15);

    $this->exts->click_by_xdotool($this->signinLink);
    sleep(15);

    $this->checkAndSolveGeeTestCaptcha();
    sleep(15);
    $this->reloadPage('div#captcha-box span#captcha-status-message[style*="inline"]', 15);

    $this->checkAndReloadUrl($this->exts->getUrl());
    $this->callRecaptcha();

    $this->checkAndSolveGeeTestCaptcha();
    sleep(15);
    $this->reloadPage('div#captcha-box span#captcha-status-message[style*="inline"]', 15);

    $this->fillForm(0);
    sleep(15);

    if ($this->exts->exists('div#uciEditForm')) {
        $this->exts->click_by_xdotool('button[data-testid="test-ask-me-later-btn"]');
        sleep(12);
    }

    // phone 2FA
    if ($this->exts->exists('input[value="phone"]')) {
        $this->exts->click_by_xdotool('input[value="phone"]:not(:checked)');
        sleep(2);

        $this->exts->click_by_xdotool('button[name="submitBtn"]');
        sleep(15);

        $this->exts->click_by_xdotool('input#numSelected1:not(:checked)');
        sleep(2);

        $this->exts->click_by_xdotool('#fullscale[action="/Phone"] button[value="text"]');
        sleep(15);

        $two_factor_selector = 'form#SignIn2FA input[name="pin"], #verifyCodeForm #code';
        $two_factor_submit_selector = 'form#SignIn2FA button#subBtn, [name="submitBtn"]';
        $two_factor_message_selector = 'span#mfa_send_status, form#verifyCodeForm > h3, form#verifyCodeForm > p#subheader';
        $this->checkFillTwoFactor($two_factor_selector, $two_factor_submit_selector, $two_factor_message_selector);
        sleep(15);
    } else if ($this->exts->exists('form#SignIn2FA input[name="pin"], #verifyCodeForm #code')) {
        $two_factor_selector = 'form#SignIn2FA input[name="pin"], #verifyCodeForm #code';
        $two_factor_submit_selector = 'form#SignIn2FA button#subBtn, [name="submitBtn"]';
        $two_factor_message_selector = 'span#mfa_send_status, form#verifyCodeForm > h3, form#verifyCodeForm > p#subheader';
        $this->checkFillTwoFactor($two_factor_selector, $two_factor_submit_selector, $two_factor_message_selector);
        sleep(15);
    } else if ($this->exts->exists('input[value="email"]')) {
        // email 2FA
        $this->exts->click_by_xdotool('input[value="email"]:not(:checked)');
        sleep(2);

        $this->exts->click_by_xdotool('button[name="submitBtn"]');
        sleep(15);

        // security question
        if ($this->exts->exists('form#securityQuestionForm')) {
            $this->exts->click_by_xdotool('input#questionId1:not(:checked)');
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
        $this->exts->click_by_xdotool('div#verifyitsyou ~ div a[href*="/StartPhone/"]');
        sleep(15);

        $this->checkFillTwoFactor('input[name="code"]', 'button[name="submitBtn"]', 'div#verifyCodeContent p');
    } else if ($this->exts->exists('div#verifyitsyou ~ a[href*="/Email/"]')) {
        $this->exts->click_by_xdotool('div#verifyitsyou ~ a[href*="/Email/"]');
        sleep(15);

        $this->checkFillTwoFactorWithEmail('div#email p');
    } else if ($this->exts->exists('div.mfa_contr div.push-2fa-main-container')) {
        $this->checkFillTwoFactorWithEmail('h2#push-main-header + div span.pushNoticeText');
    }

    // click some button when finished 2FA
    if ($this->exts->querySelector('form[name="contactInfoForm"] a#rmdLtr') != null) {
        $this->exts->click_by_xdotool('form[name="contactInfoForm"] a#rmdLtr');
    } else if ($this->exts->querySelector('#fullscale [name="submitBtn"]') != null) {
        $this->exts->click_by_xdotool('#fullscale [name="submitBtn"]');
    } else if ($this->exts->querySelector('.primsecbtns [value="text"]') != null) {
        $this->exts->click_by_xdotool('.primsecbtns [value="text"]');
    } else if ($this->exts->querySelector("a#continue-get") != null) {
        $this->exts->click_by_xdotool("a#continue-get");
    }

    sleep(2);

    // Check login after finished 2FA
    if ($this->checkLogin()) {
        $this->exts->openUrl($this->invoice_url);
        sleep(15);

        $this->checkAndReloadUrl($this->exts->getUrl());
        $this->callRecaptcha();

        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        $this->exts->openUrl($current_url);
        sleep(15);
    } else {
        if ($this->exts->exists('span.mi-er span.sd-err, #errf')) {
            $this->exts->capture("Wrong credentials");
            $this->exts->loginFailure(1);
        } else {
            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure();
        }
    }
}

private function callRecaptcha()
{
    for ($i = 0; $i < 2; $i++) {
        if ($this->exts->exists('iframe[src*="/recaptcha/api2/anchor?"]')) {
            $this->checkFillRecaptcha();
            sleep(5);
            if ($this->exts->exists('form.recaptcha2 input[type="submit"]')) {
                $this->exts->click_by_xdotool('form.recaptcha2 input[type="submit"]');
                sleep(15);
            }
        } elseif ($this->exts->exists('div[class="target-icaptcha-slot"] iframe[data-hcaptcha-response=""]')) {
            $is_captcha = $this->solve_captcha_by_clicking(0);
            if ($is_captcha) {
                for ($i = 1; $i < 30; $i++) {
                    if ($is_captcha == false) {
                        break;
                    }
                    $is_captcha = $this->solve_captcha_by_clicking($i);
                }
            }
        } else if ($this->exts->exists('iframe[src*="distil_r_captcha.html"]')) {
            $iframe_src_url = $this->exts->extract('iframe[src*="distil_r_captcha.html"]', null, 'src');
            $this->exts->switchToFrame('iframe[src*="distil_r_captcha.html"]');
            if ($this->exts->exists('iframe[src*="/recaptcha/api2/anchor?"]')) {
                $this->checkFillRecaptcha($iframe_src_url);
            } else {
                $this->exts->switchToDefault();
            }
        } else if ($this->exts->exists('a[href*="reload()"]')) {
            $this->exts->refresh();
            sleep(15);
        } else {
            break;
        }
    }
}

private function processAfterLogin($count)
{
    if (stripos($this->exts->getCurrentUrl(), "/eBayISAPI.dll?MyEbay&CurrentPage=MyeBayMyAccounts") !== false) {
        if ($this->exts->exists('div.cards-account-settings a[href*="MyeBaySellerAccounts"]')) {
            $this->exts->click_by_xdotool('div.cards-account-settings a[href*="MyeBaySellerAccounts"]');
            sleep(15);

            $this->checkAndReloadUrl($this->exts->getUrl());
            $this->callRecaptcha();
            sleep(15);

            if ($this->exts->exists($this->username_selector)) {
                $this->reLogin($this->exts->getUrl());
            }
        }

        if ($this->exts->exists('div#LocalNavigation a[href*="MyeBaySellerAccounts"]')) {
            $this->exts->click_by_xdotool('div#LocalNavigation a[href*="MyeBaySellerAccounts"]');
            sleep(15);

            $this->checkAndReloadUrl($this->exts->getUrl());
            $this->callRecaptcha();
            sleep(15);

            if ($this->exts->exists($this->username_selector)) {
                $this->reLogin($this->exts->getUrl());
            }
        }
        $this->processAccounts();
    } else {
        $this->exts->openUrl($this->invoice_url);
        sleep(15);

        $this->checkAndReloadUrl($this->exts->getUrl());
        $this->callRecaptcha();

        if ($this->exts->exists('div.cards-account-settings a[href*="MyeBaySellerAccounts"]')) {
            $this->exts->click_by_xdotool('div.cards-account-settings a[href*="MyeBaySellerAccounts"]');
            sleep(15);

            $this->checkAndReloadUrl($this->exts->getUrl());
            $this->callRecaptcha();
            sleep(15);

            if ($this->exts->exists($this->username_selector)) {
                $this->reLogin($this->exts->getUrl());
            }

            if ($this->exts->exists('div#LocalNavigation a[href*="MyeBaySellerAccounts"]')) {
                $this->exts->click_by_xdotool('div#LocalNavigation a[href*="MyeBaySellerAccounts"]');
                sleep(15);

                $this->checkAndReloadUrl($this->exts->getUrl());
                $this->callRecaptcha();
                sleep(15);

                if ($this->exts->exists($this->username_selector)) {
                    $this->reLogin($this->exts->getUrl());
                }
            }
        } else if ($this->exts->exists('a[href*="sellerstandards"][href*="dashboard"]')) {
            $this->exts->click_by_xdotool('a[href*="sellerstandards"][href*="dashboard"]');
            sleep(15);

            $this->checkAndReloadUrl($this->exts->getUrl());
            $this->callRecaptcha();
            sleep(15);

            if ($this->exts->exists($this->username_selector)) {
                $this->reLogin($this->exts->getUrl());
            }

            if ($this->exts->exists('ul.myaccount-menu a[href*="MyeBayNextSellerAccounts"]')) {
                $this->exts->click_by_xdotool('ul.myaccount-menu a[href*="MyeBayNextSellerAccounts"]');
                sleep(15);

                $this->checkAndReloadUrl($this->exts->getUrl());
                $this->callRecaptcha();
                sleep(15);

                if ($this->exts->exists($this->username_selector)) {
                    $this->reLogin($this->exts->getUrl());
                }
            }
        }

        $this->processAccounts(0);
    }

    if ((int)@$this->fetch_transaction == 1) {
        $this->exts->openUrl($this->purchase_history);
        sleep(10);

        $this->checkAndReloadUrl($this->exts->getUrl());
        $this->callRecaptcha();

        if ($this->exts->exists($this->username_selector)) {
            $this->reLogin($this->exts->getUrl());
        }

        $this->processOrderDetails(0);
    }

    if ($this->isNoInvoice) {
        $this->exts->no_invoice();
    }

    $this->exts->success();
}

private function processAccounts()
{
    $this->exts->capture('processAccounts');
    try {
        $this->checkAndReloadUrl($this->exts->getUrl());
        $this->callRecaptcha();
        sleep(10);

        if ($this->exts->querySelector('form[name="ViewAccount"] select[name="cid"] option') != null) {
            $selectAccountElements = $this->exts->querySelectorAll('form[name="ViewAccount"] select[name="cid"] option');
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
                            $this->exts->openUrl($this->invoice_url);
                            sleep(15);

                            $this->checkAndReloadUrl($this->exts->getUrl());
                            $this->callRecaptcha();
                        }
                        // Fill Account Select
                        $optionSelAccEle = 'select[name="cid"] option[value="' . $optionAccountSelector . '"]';
                        $this->exts->log("processing account element  " . $optionSelAccEle);
                        $this->exts->click_by_xdotool($optionSelAccEle);
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
        if ($this->exts->querySelector('select[name="invoiceMonthYear"] option') != null) {
            $invoiceMonthOptions = $this->exts->querySelectorAll('select[name="invoiceMonthYear"] option');
            if (count($invoiceMonthOptions) > 0) {
                $optionInvoiceSelectors = array();
                foreach ($invoiceMonthOptions as $invoiceMonthOption) {
                    if ((int)@$this->restrictPages > 0 && count($optionInvoiceSelectors) > 6) break;
                    $elementInvoiceValue = trim($invoiceMonthOption->getAttribute('value'));
                    if (stripos($elementInvoiceValue, ":") === false)  continue;
                    $elementAccountText = trim($invoiceMonthOption->getText());
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
                        } catch (\Exception $exception) {
                            $this->exts->log("Exception while extraction each invoice " . $optionInvoiceSelector . " - " . $exception->getMessage());
                        }
                    }

                    // $base_handle = $this->exts->getWindowHandle();

                    if (count($invoice_data_arr) > 0) {
                        // Fill First Invoice Select
                        $optionSelAccEle = 'select[name="invoiceMonthYear"] option[value="' . $invoice_data_arr[0]["option_value"] . '"]';
                        $this->exts->log("processing account element  " . $optionSelAccEle);
                        // $selectAccountElement = $this->exts->findElement(WebDriverBy::cssSelector($optionSelAccEle));
                        // $selectAccountElement->click();
                        $this->exts->click_by_xdotool($optionSelAccEle);
                        sleep(1);

                        if ($this->exts->exists('form[name="AccountStatusForm"] input[type="submit"]')) {
                            $this->exts->click_by_xdotool('form[name="AccountStatusForm"] input[type="submit"]');
                            sleep(5);
                        } else if ($this->exts->exists('input#InvButtonId')) {
                            $this->exts->click_by_xdotool('input#InvButtonId');
                            sleep(15);
                        }

                        $this->exts->switchToNewestActiveTab();

                        $filename = $invoice_data_arr[0]["invoice_name"] . ".pdf";
                        $invoicePrintUrl = "";
                        if ($this->exts->querySelector('iframe[src*="/eBayISAPI.dll?ViewInvoice"]') != null) {
                            $invoicePrintUrl = $this->exts->querySelector('iframe[src*="/eBayISAPI.dll?ViewInvoice"]')->getAttribute("src");
                        } else if ($this->exts->exists('a[href*="DownloadInvoice"]')) {
                            $invoicePrintUrl = $this->exts->extract('a[href*="DownloadInvoice"]', null, 'href');
                        }
                        $this->exts->log("invoicePrintUrl - " . $invoicePrintUrl);

                        if (trim($invoicePrintUrl) != "") {
                            if (strpos($invoicePrintUrl, 'DownloadInvoice') !== false) {
                                $this->exts->switchToInitTab();
                                $this->exts->closeAllTabsButThis();

                                $this->exts->openUrl($invoicePrintUrl);
                                sleep(15);

                                $this->exts->click_by_xdotool('[name="Submit_btn"]');

                                $this->exts->wait_and_check_download('pdf');
                                $this->exts->wait_and_check_download('pdf');
                                $this->exts->wait_and_check_download('pdf');

                                $downloaded_file = $this->exts->find_saved_file('pdf', $filename);
                                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                                    $this->exts->new_invoice($invoice_data_arr[0]['invoice_name'], $invoice_data_arr[0]['invoice_date'], $invoice_data_arr[0]['invoice_amount'], $filename);
                                    sleep(1);
                                } else {
                                    $this->exts->log(__FUNCTION__ . '::No download ' . $filename);
                                }
                            } else {
                                $downloaded_file = $this->exts->download_capture($invoicePrintUrl, $filename, 5);
                                if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                                    $this->exts->new_invoice($invoice_data_arr[0]['invoice_name'], $invoice_data_arr[0]['invoice_date'], $invoice_data_arr[0]['invoice_amount'], $filename);
                                }
                            }
                        } else {
                            try {
                                $this->exts->click_by_xdotool('form#ViewInvoice a[onclick*="window.open"]');
                                sleep(5);

                                $this->exts->switchToNewestActiveTab();

                                $downloaded_file = $this->exts->download_current($filename, 5);
                                if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                                    $this->exts->new_invoice($invoice_data_arr[0]['invoice_name'], $invoice_data_arr[0]['invoice_date'], $invoice_data_arr[0]['invoice_amount'], $filename);
                                }

                                $this->exts->switchToInitTab();
                                $this->exts->closeAllTabsButThis();
                            } catch (\Exception $exception) {
                                $this->exts->log("Exception in click on print view " . $invoice_data_arr[0]['invoiceName'] . " - " . $exception->getMessage());
                            }
                        }

                        $this->exts->switchToInitTab();
                        $this->exts->closeAllTabsButThis();
                        sleep(3);

                        $this->exts->openUrl($current_url);
                        sleep(15);

                        $this->checkAndReloadUrl($this->exts->getUrl());
                        $this->callRecaptcha();

                        if (count($invoice_data_arr) > 1) {
                            foreach ($invoice_data_arr as $key => $invoice_data) {
                                if ($key == 0) continue;
                                $optionSelAccEle = 'select[name="invoiceMonthYear"] option[value="' . $invoice_data["option_value"] . '"]';
                                $this->exts->log("processing account element  " . $optionSelAccEle);
                                // $selectAccountElement = $this->exts->findElement(WebDriverBy::cssSelector($optionSelAccEle));
                                // $selectAccountElement->click();
                                $this->exts->click_by_xdotool($optionSelAccEle);
                                sleep(1);

                                // $this->exts->querySelector('form#invoiceForm button#invSubmit[type="submit"]');
                                // sleep(5);
                                if ($this->exts->exists('form[name="AccountStatusForm"] input[type="submit"]')) {
                                    $this->exts->click_by_xdotool('form[name="AccountStatusForm"] input[type="submit"]');
                                    sleep(5);
                                } else if ($this->exts->exists('input#InvButtonId')) {
                                    $this->exts->click_by_xdotool('input#InvButtonId');
                                    sleep(15);
                                } else if ($this->exts->exists('#invSubmit')) {
                                    $this->exts->click_by_xdotool('#invSubmit');
                                    sleep(15);
                                }

                                $this->exts->switchToNewestActiveTab();

                                $filename = $invoice_data["invoice_name"] . ".pdf";
                                $invoicePrintUrl = "";
                                if ($this->exts->querySelector('iframe[src*="/eBayISAPI.dll?ViewInvoice"]') != null) {
                                    $invoicePrintUrl = $this->exts->querySelector('iframe[src*="/eBayISAPI.dll?ViewInvoice"]')->getAttribute("src");
                                } else if ($this->exts->exists('a[href*="DownloadInvoice"]')) {
                                    $invoicePrintUrl = $this->exts->extract('a[href*="DownloadInvoice"]', null, 'href');
                                }
                                $this->exts->log("invoicePrintUrl - " . $invoicePrintUrl);

                                if (trim($invoicePrintUrl) != "") {
                                    if (strpos($invoicePrintUrl, 'DownloadInvoice') !== false) {
                                        $this->exts->switchToInitTab();
                                        $this->exts->closeAllTabsButThis();

                                        sleep(3);

                                        $this->exts->openUrl($invoicePrintUrl);
                                        sleep(15);

                                        $this->exts->click_by_xdotool('[name="Submit_btn"]');

                                        $this->exts->wait_and_check_download('pdf');
                                        $this->exts->wait_and_check_download('pdf');
                                        $this->exts->wait_and_check_download('pdf');

                                        $downloaded_file = $this->exts->find_saved_file('pdf', $filename);
                                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                                            $this->exts->new_invoice($invoice_data_arr[0]['invoice_name'], $invoice_data_arr[0]['invoice_date'], $invoice_data_arr[0]['invoice_amount'], $filename);
                                            sleep(1);
                                        } else {
                                            $this->exts->log(__FUNCTION__ . '::No download ' . $filename);
                                        }
                                    } else {
                                        $downloaded_file = $this->exts->download_capture($invoicePrintUrl, $filename, 5);
                                        if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                                            $this->exts->new_invoice($invoice_data['invoice_name'], $invoice_data['invoice_date'], $invoice_data['invoice_amount'], $filename);
                                        }
                                    }
                                } else {
                                    try {
                                        $this->exts->click_by_xdotool('form#ViewInvoice a[onclick*="window.open"]');
                                        sleep(5);

                                        $this->exts->switchToNewestActiveTab();
                                        $downloaded_file = $this->exts->download_current($filename, 5);
                                        if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                                            $this->exts->new_invoice($invoice_data['invoice_name'], $invoice_data['invoice_date'], $invoice_data['invoice_amount'], $filename);
                                        }

                                        $this->exts->switchToInitTab();
                                        $this->exts->closeAllTabsButThis();
                                    } catch (\Exception $exception) {
                                        $this->exts->log("Exception in click on print view " . $invoice_data['invoiceName'] . " - " . $exception->getMessage());
                                    }
                                }

                                $this->exts->switchToInitTab();
                                $this->exts->closeAllTabsButThis();
                                sleep(3);

                                $this->exts->openUrl($current_url);
                                sleep(15);

                                $this->checkAndReloadUrl($this->exts->getUrl());
                                $this->callRecaptcha();
                            }
                        }
                    } else {
                        $this->exts->success();
                    }
                }
            }
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception checking process invoice " . $exception->getMessage());
    }
}

private function  processOrderDetails($count, $pageNumber = 1)
{
    $this->exts->capture("4-invoices-page");
    $invoices = [];

    $rows = $this->exts->querySelectorAll('div#purchase-history div.icard');
    foreach ($rows as $row) {
        if ($this->exts->querySelector('div.icard__header div.icard__status-bar.delivered', $row) != null) {
            $invoiceUrl = '';
            $itemCardListEle = $this->exts->querySelectorAll("div.icard__items div.item-card", $row);
            if (count($itemCardListEle) > 0) {
                $wouldLike = $this->exts->querySelector("div.icard_items-actions div.dropdown.mactions button.secItemActions", $itemCardListEle[0]);

                try {
                    $this->exts->log('Click wouldLike button');
                    $wouldLike->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click wouldLike button by javascript');
                    $this->exts->executeSafeScript("arguments[0].click()", [$wouldLike]);
                }

                sleep(5);

                $invoiceUrl = trim($this->exts->extract('div.icard_items-actions div.dropdown.mactions ul.dropdown-menu li a[href*="/eBayISAPI.dll?ViewPaymentStatus"]', $itemCardListEle[0], 'href'));
            }

            $invoiceName = $row->getAttribute('id');
            $invoiceDate = trim($this->exts->extract('div.icard__header div.icard__datefield label.date', $row));
            $invoiceAmount = '';
            if (count($itemCardListEle) > 0) {
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('div.item-details div.item-priceInfo label.item-price', $itemCardListEle[0]))) . ' EUR';
            }

            array_push($invoices, array(
                'invoiceName' => $invoiceName,
                'invoiceDate' => $invoiceDate,
                'invoiceAmount' => $invoiceAmount,
                'invoiceUrl' => $invoiceUrl
            ));
            $this->isNoInvoice = false;
        }
    }

    $rows = $this->exts->querySelectorAll('div#purchase-history div#orders div.order-r.item-list-all');
    foreach ($rows as $row) {
        if ($this->exts->querySelector('div.order-action-wrap a[href*="/eBayISAPI.dll?ViewPaymentStatus"]', $row) != null || $this->exts->querySelector("div.order-action-wrap div.action-col div.dropdown.mactions a.dropdown-toggle", $row) != null) {
            $invoiceUrl = trim($this->exts->extract('div.order-action-wrap a[href*="/eBayISAPI.dll?ViewPaymentStatus"]', $row, 'href'));
            if ($invoiceUrl == '') {
            }

            $moreActions = $this->exts->querySelector("div.order-action-wrap div.action-col div.dropdown.mactions a.dropdown-toggle", $row);

            try {
                $this->exts->log('Click wouldLike button');
                $moreActions->click();
            } catch (\Exception $exception) {
                $this->exts->log('Click wouldLike button by javascript');
                $this->exts->executeSafeScript("arguments[0].click()", [$moreActions]);
            }

            sleep(5);

            $invoiceUrl = trim($this->exts->extract('div.order-action-wrap div.action-col div.dropdown.mactions ul.dropdown-menu li a[href*="/eBayISAPI.dll?ViewPaymentStatus"]', $row, 'href'));
            if ($invoiceUrl == '') {
                $invoiceUrl = trim($this->exts->extract('a[href*="/FetchOrderDetails"]', $row, 'href'));
            }

            $invoiceName = trim($this->exts->extract('div.order-num div.row-value', $row));
            if ($invoiceName == '') {
                $invoiceName = trim($this->exts->extract('div.order-action-wrap a[href*="feedback.ebay.com/ws/eBayISAPI.dll?LeaveFeedbackShow"]', $orderItem, 'aria-describedby'));
            }

            $invoiceDate = trim($this->exts->extract('div.order-date div.row-value', $row));
            $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', trim($this->exts->extract('div.purchase-info span.cost-label', $row)))) . ' EUR';

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

        $invoiceFileName = $invoice['invoiceName'] . '.pdf';
        $invoice['invoiceDate'] = $this->exts->parse_date(str_replace('.', '', $invoice['invoiceDate']), 'd M Y', 'Y-m-d', 'fr');
        $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

        // Open New window To process Invoice
        // $this->exts->open_new_window();

        // Call Processing function to process current page invoices
        $this->exts->openNewTab($invoice['invoiceUrl']);
        sleep(10);

        $this->exts->click_by_xdotool('button.btn[aria-controls="printerFriendlyContent"]');
        sleep(1);

        $downloaded_file = $this->exts->download_current($invoiceFileName, 5);
        if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
            $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
            sleep(1);
        } else {
            $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
        }

        // Close new window
        $this->exts->switchToInitTab();
        $this->exts->closeAllTabsButThis();
    }


    if ($this->restrictPages == 0 && $pageNumber < 50 && $this->exts->querySelector('div.pagination div.pagn a.gspr.next') != null) {
        $pageNumber++;
        $this->exts->click_by_xdotool('div.pagination div.pagn a.gspr.next');
        sleep(5);
        $this->processOrderDetails($count, $pageNumber);
    }
}