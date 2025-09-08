public $baseUrl = 'https://app.evaboot.com/?page=export';
public $loginUrl = 'https://app.evaboot.com/access?p=login';
public $invoicePageUrl = 'https://app.evaboot.com/?page=account';

public $username_selector = 'input#email';
public $password_selector = 'input#signup-email';
public $remember_me_selector = '';
public $submit_login_selector = 'button.clickable-element:nth-child(4)';

public $check_login_failed_selector = './/div[normalize-space(.)="Please check your email and password combination or log in using Google/Microsoft"]';
public $check_login_success_selector = './/div[contains(text(),"Logout")]';

public $isNoInvoice = true;

/**<input type="password" name="password" autocomplete="current-password" class="textinput textInput" required id="id_password">

    * Entry Method thats called for a portal

    * @param Integer $count Number of times portal is retried.

    */
private function initPortal($count)
{

    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->baseUrl);

    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);

        $this->fillForm(0);
        sleep(30);

        if ($this->isExists('button [href*="fa-close"]')) {
            $this->exts->moveToElementAndClick('button [href*="fa-close"]');
            sleep(2);
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
        if (count($this->exts->getElements($this->check_login_failed_selector)) != 0) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    // $this->exts->waitTillPresent($this->username_selector, 15);
    for ($i = 0; $i < 10 && $this->exts->getElement($this->username_selector) == null; $i++) {
        sleep(2);
    }
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            if ($this->isExists($this->remember_me_selector)) {
                $this->exts->log("Remember Me");
                $this->exts->moveToElementAndClick($this->remember_me_selector);
                sleep(1);
            }

            $this->exts->capture("1-login-page-filled");
            sleep(5);

            if ($this->isExists($this->submit_login_selector)) {
                $this->exts->click_by_xdotool($this->submit_login_selector);
            }
            sleep(10);
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception filling loginform " . $exception->getMessage());
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
        // $this->exts->waitTillPresent($this->check_login_success_selector, 20);
        for ($i = 0; $i < 10 && $this->exts->getElement($this->check_login_success_selector) == null && $this->exts->getElement($this->username_selector) == null; $i++) {
            sleep(2);
        }
        if ($this->exts->queryXpath('.//div[contains(text(),"Account")]') != null && $this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->moveToElementAndClick('.//div[contains(text(),"Account")]');
            sleep(2);
        }
        if ($this->exts->getElement($this->check_login_success_selector)) {

            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

            $isLoggedIn = true;
        }
    } catch (Exception $exception) {
        $this->exts->log("Exception checking loggedin " . $exception);
    }

    return $isLoggedIn;
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