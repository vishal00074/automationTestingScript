public $baseUrl = 'https://www.sonepar.de';
public $loginUrl = 'https://www.sonepar.de/authentication';
public $invoicePageUrl = 'https://www.sonepar.de/sp/orders';

public $region_selector = 'select#org';
public $customer_id_selector = 'input#cust';
public $username_selector = 'input#user';
public $email_selector = 'input#email';
public $login_with_email_selector = 'div#login-email-link';
public $password_selector = 'input[type="password"]';
public $remember_me_selector = 'input[type="checkbox"]';
public $submit_login_selector = 'button[type="submit"]';

public $check_login_failed_selector = 'div.sonepar-alert-error';
public $check_login_success_selector = 'a[href="/sp/overview"]';
public $start_date = '.filter-container__scroll-area label:nth-child(1) input';

public $customer_id = '';

public $isNoInvoice = true;

/**

    * Entry Method thats called for a portal

    * @param Integer $count Number of times portal is retried.

    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->baseUrl);
    sleep(3);

    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);

        $this->exts->waitTillPresent("#usercentrics-root", 20);
        if ($this->exts->exists("#usercentrics-root")) {
            $this->exts->execute_javascript("
                var shadowHost = document.querySelector('#usercentrics-root');
                if (shadowHost && shadowHost.shadowRoot) {
                    var button = shadowHost.shadowRoot.querySelector('button[data-testid=\"uc-accept-all-button\"]');
                    if (button) {
                        button.click();
                    }
                }
            ");
            sleep(1);
        }

        $this->fillForm(0);
        sleep(5);
    }

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");
        
        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } elseif ($this->exts->exists("//div[text()='Please enter your 6-digit customer ID.']")) {
        $this->exts->log("Please enter your 6-digit customer ID.");
        $this->exts->loginFailure(1);
        sleep(1);
    } else {
        if ($this->exts->exists($this->check_login_failed_selector)) {
            $this->exts->log("Wrong credentials !!!!");
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

public function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    try {
        if (isset($this->exts->config_array['CUSTOMER_ID'])) {
            $this->customer_id = trim($this->exts->config_array['CUSTOMER_ID']);
        } else if (isset($this->exts->config_array['customer_id'])) {
            $this->customer_id = trim($this->exts->config_array['customer_id']);
        }
        if ($this->customer_id != '') {
            $this->exts->waitTillPresent($this->username_selector, 10);
            if ($this->exts->querySelector($this->username_selector) != null) {


                $this->exts->capture("1-pre-login");
                $this->exts->log("Select Region");
                $this->exts->moveToElementAndClick($this->region_selector);
                sleep(1);
                $this->exts->execute_javascript("document.getElementById('org').value = '" . $this->exts->getConfig('region') . "'; document.getElementById('org').dispatchEvent(new Event('change'));");

                sleep(2);


                $this->exts->log("Enter Customer-Id");
                $this->exts->moveToElementAndType($this->customer_id_selector, $this->customer_id);
                sleep(1);
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);

                sleep(2);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(1);

                if ($this->exts->exists($this->remember_me_selector)) {
                    $this->exts->click_by_xdotool($this->remember_me_selector);
                    sleep(1);
                }
                $this->exts->capture("1-login-page-filled");
                sleep(2);
                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->click_by_xdotool($this->submit_login_selector);
                }
            }
        } else {
            sleep(10);
            $this->exts->click_element($this->login_with_email_selector);
            sleep(2);
            $this->exts->waitTillPresent($this->email_selector, 10);
            if ($this->exts->querySelector($this->email_selector) != null) {
                $this->exts->log("Enter Email");
                $this->exts->moveToElementAndType($this->email_selector, $this->username);
                sleep(2);
                if (!$this->isValidEmail($this->username)) {
                    $this->exts->loginFailure(1);
                }
                sleep(2);
                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(1);

                $this->exts->capture("1-login-page-filled");
                sleep(5);
                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->click_by_xdotool($this->submit_login_selector);
                }
            }
        }
    } catch (\Exception $exception) {

        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}



public function isValidEmail($username)
{
    // Regular expression for email validation
    $emailPattern = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
    if (preg_match($emailPattern, $username)) {
        return 'email';
    }
    return false;
}



/**

    * Method to Check where user is logged in or not

    * return boolean true/false

    */
public function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        $this->exts->waitTillPresent($this->check_login_success_selector, 20);
        if ($this->exts->exists($this->check_login_success_selector)) {

            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

            $isLoggedIn = true;
        }
    } catch (Exception $exception) {

        $this->exts->log("Exception checking loggedin " . $exception);
    }

    return $isLoggedIn;
}