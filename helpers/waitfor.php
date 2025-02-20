<?php

use GmiChromeManager;

class WaitFor
{
    protected $exts;

    public function __construct()
    {
        $this->exts = new GmiChromeManager();
    }

    /**
     * Waits for the given selector to appear within a specified time period.
     * If the selector is found before the timeout, the loop breaks early.
     *
     * @param string $selector The CSS or XPath selector to wait for.
     * @param string|null $button (Optional) A button selector to click if the element is not found.
     */
    public function holdTillSelector($selector, $button = null)
    {
        $this->exts->waitTillPresent($selector, 10);
        $timeout = 200; // Max wait time in seconds
        $interval = 5;  // Time to wait between checks (adjust as needed)
        $startTime = time();


        while (time() - $startTime < $timeout) {
            $this->exts->log("Finding selector " . time());
            if ($this->exts->exists($selector)) {
                $this->exts->log("selector Found");
                break;
            }
            if ($button != null) {
                $this->exts->click_by_xdotool($button);
            }

            $this->exts->waitTillPresent($selector, 10);
            sleep($interval);
        }

        // Optional: Handle case where the element was not found within 200 seconds
        if (!$this->exts->exists($selector)) {
            $this->exts->log("selector not found within 200 seconds.");
        }
    }
}
