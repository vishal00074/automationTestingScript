public $baseUrl = 'https://eu.charge.mercedes.me/web/de/daimler-de/dashboard/start';
public $loginUrl = 'https://eu.charge.mercedes.me/web/de/daimler-de';
public $invoicePageUrl = 'https://eu.charge.mercedes.me/web/de/daimler-de/dashboard/invoices';

public $username_selector = 'input[id="username"]';
public $password_selector = 'input#password';
public $remember_me_selector = 'input#rememberMe';
public $submit_login_selector = 'button[id="confirm"]';

public $check_login_failed_selector = 'div[id="server-errors"] ul li';
public $check_login_success_selector = 'a[href*="logout"]';

public $isNoInvoice = true;

private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->baseUrl);
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        // $this->exts->waitTillPresent("a[href*='login']", 10);
        sleep(10);
        $this->exts->moveToElementAndClick("a[href*='login']");
        sleep(10);
        $this->fillForm(0);
    }

    sleep(5);

    $error_text = strtolower($this->exts->extract($this->check_login_failed_selector));

    $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);
    if (stripos($error_text, strtolower('Invalid login details')) !== false) {
        $this->exts->loginFailure(1);
    }

    if ($this->exts->exists('iframe[src*="oneDoCSPA"]')) {
        $iframe_block = $this->exts->makeFrameExecutable('iframe[src*="oneDoCSPA"]');
        $iframe_block->moveToElementAndClick('button#saveAllTitleButton');
        sleep(5);
        // $accept_all_button = $this->exts->getElementByText('button[type=submit]','Einwilligen in alles')
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
    }

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
			$this->exts->triggerLoginSuccess();
		}

        $this->exts->success();
    } else {
        if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'access data') !== false) {
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
    $this->exts->waitTillPresent($this->username_selector, 5);
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(3);

            if ($this->exts->exists('button[id="continue"]')) {
                $this->exts->click_by_xdotool('button[id="continue"]');
            }
            sleep(3);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(3);

            if ($this->exts->exists($this->remember_me_selector)) {
                $this->exts->click_by_xdotool($this->remember_me_selector);
                sleep(1);
            }
            $this->exts->capture("1-login-page-filled");
            sleep(5);
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->click_by_xdotool($this->submit_login_selector);
                sleep(5);
            }
        }
    } catch (\Exception $exception) {

        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

function checkLogin()
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