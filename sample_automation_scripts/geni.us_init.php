public $baseUrl = "https://my.geni.us/account/billing";
public $username_selector = '#UserName';
public $password_selector = '#Password';
public $submit_btn = '#sign-in-button';
public $logout_btn = '.sign-out';



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

    $this->exts->openUrl($this->baseUrl);
    sleep(10);

    if ($isCookieLoaded) {
        $this->exts->capture("Home-page-with-cookie");
    } else {
        $this->exts->capture("Home-page-without-cookie");
    }

    for ($i = 0; $i < 3; $i++) {
        if (!$this->checkLogin() && !$this->exts->exists('.alert-error')) {
            $this->exts->capture("after-login-clicked");
            $this->fillForm($i);
            sleep(20);
        } else {
            break;
        }
    }

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }
        
        $this->exts->success();
    } else {
        $this->exts->capture("LoginFailed");
        if (strpos(strtolower($this->exts->extract('.alert-error p')), 'passwor') !== false) {
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

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username, 5);

            if ($this->exts->querySelector('#RememberMe') != null) {
                $this->exts->click_by_xdotool('#RememberMe');
                sleep(2);
            }

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password, 5);

            $this->exts->capture("1-pre-login-1");
            $this->checkFillRecaptcha(0);

            $this->exts->moveToElementAndClick($this->submit_btn, 10);
        } else if ($this->exts->exists("iframe[src*=\"https://www.google.com/recaptcha/api2/anchor?\"]") && $this->exts->exists("textarea[name=\"g-recaptcha-response\"]")) {
            $this->checkFillRecaptcha(0);
            if ($count < 5) {
                $count++;
                $this->fillForm($count);
            } else {
                $this->exts->log(__FUNCTION__ . " :: too many recaptcha attempts " . $count);
                $this->exts->loginFailure();
            }
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

function checkFillRecaptcha($counter)
{

    if ($this->exts->exists('iframe[src*="https://www.google.com/recaptcha/api2/anchor?"]') && $this->exts->exists('textarea[name="g-recaptcha-response"]')) {

        if ($this->exts->exists("div.g-recaptcha[data-sitekey]")) {
            $data_siteKey = trim($this->exts->getElement("div.g-recaptcha")->getAttribute("data-sitekey"));
        } else {
            $iframeUrl = $this->exts->getElement("iframe[src*=\"https://www.google.com/recaptcha/api2/anchor?\"]")->getAttribute("src");
            $tempArr = explode("&k=", $iframeUrl);
            $tempArr = explode("&", $tempArr[count($tempArr) - 1]);

            $data_siteKey = trim($tempArr[0]);
            $this->exts->log("iframe url  - " . $iframeUrl);
        }
        $this->exts->log("SiteKey - " . $data_siteKey);

        $isCaptchaSolved = $this->exts->processRecaptcha($this->exts->webdriver->getCurrentUrl(), $data_siteKey, false);
        $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);

        if ($isCaptchaSolved) {
            $this->exts->log("isCaptchaSolved");
            $this->exts->executeSafeScript("document.querySelector(\"#g-recaptcha-response\").value = '" . $this->exts->recaptcha_answer . "';");
            $this->exts->executeSafeScript("document.querySelector(\"#g-recaptcha-response\").innerHTML = '" . $this->exts->recaptcha_answer . "';");
            sleep(5);
            try {
                $tag = $this->exts->getElement("[data-callback]");
                if ($tag != null && trim($tag->getAttribute("data-callback")) != "") {
                    $func =  trim($tag->getAttribute("data-callback"));
                    $this->exts->executeSafeScript(
                        $func . "('" . $this->exts->recaptcha_answer . "');"
                    );
                } else {

                    $this->exts->executeSafeScript(
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

/**
    * Method to Check where user is logged in or not
    * return boolean true/false
    */
function checkLogin()
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

function downloadInvoice($count)
{
    $this->exts->log("Begin download invoice - " . $count);
    try {
        if ($this->exts->exists('.past-invoices > tbody > tr')) {
            $this->exts->capture("2-download-invoice");
            $invoices = array();

            $receipts = $this->exts->getElements('.past-invoices > tbody > tr');
            $i = 0;
            foreach ($receipts as $receipt) {
                $i++;
                try {
                    $receiptDate = trim($this->exts->extract('td:nth-child(2)', $receipt));
                } catch (\Exception $exception) {
                    $receiptDate = null;
                }

                if ($receiptDate != null && $this->exts->extract('[href*="invoice"]', $receipt, 'href') != null) {
                    $this->exts->log($receiptDate);

                    $receiptName = $this->exts->extract('[href*="invoice"]', $receipt, 'href');
                    $receiptName = array_pop(explode('id=', $receiptName));

                    $this->exts->log($receiptName);
                    $parsed_date = $this->exts->parse_date($receiptDate, 'F dS, Y', 'Y-m-d');
                    $this->exts->log($parsed_date);
                    $receiptFileName = !empty($receiptName) ? $receiptName . '.pdf' : '';
                    $this->exts->log($receiptFileName);

                    $receiptAmount = trim($this->exts->extract('td:nth-child(4)', $receipt));
                    $receiptAmount = preg_replace('/[^\d\.,]/m', '', $receiptAmount);
                    $receiptAmount = !empty($receiptAmount) ? $receiptAmount . ' USD' : '';

                    $receiptUrl = $this->exts->extract('[href*="invoice"]', $receipt, 'href');
                    $invoice = array(
                        'receiptName' => $receiptName,
                        'receiptUrl' => $receiptUrl,
                        'parsed_date' => $parsed_date,
                        'receiptAmount' => $receiptAmount,
                        'receiptFileName' => $receiptFileName
                    );
                    array_push($invoices, $invoice);
                }
            }

            foreach ($invoices as $invoice) {
                try {
                    $this->exts->openUrl($invoice['receiptUrl']);
                    sleep(5);
                    $downloaded_file = $this->exts->click_and_download('.print-button', 'pdf', $invoice['receiptFileName']);
                    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $downloaded_file);
                    }
                } catch (\Exception $exception) {
                    $this->exts->log("Exception downloading invoice - " . $exception->getMessage());
                }
            };
        } else {
            $this->exts->log("No invoice !!! ");
            $this->exts->no_invoice();
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception downloading invoice " . $exception->getMessage());
    }
}