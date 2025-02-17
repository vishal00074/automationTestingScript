<?php

class ClearCookies
{

    /**
     * Clears Chrome browser history, cookies, and cache.
     * This method automates navigating to Chrome's "Clear Browsing Data" settings page 
     * and selecting the necessary options using keyboard inputs.
     */
    private function clearChrome()
    {
        // Log the clearing process
        $this->exts->log("Clearing browser history, cookies, and cache");

        // Open Chrome's Clear Browsing Data settings page
        $this->exts->openUrl('chrome://settings/clearBrowserData');
        sleep(10); // Wait for the page to load

        // Capture screenshot of the clear browsing data page
        $this->exts->capture("clear-page");

        // Navigate using tab key (moving through UI elements)
        for ($i = 0; $i < 2; $i++) {
            $this->exts->type_key_by_xdotool('Tab');
            sleep(1);
        }

        // Press Tab again to focus on the dropdown menu (Time range)
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);

        // Press Enter to open the dropdown
        $this->exts->type_key_by_xdotool('Return');
        sleep(1);

        // Select "All time" option by pressing 'a' (assuming shortcut selection)
        $this->exts->type_key_by_xdotool('a');
        sleep(1);

        // Confirm selection by pressing Enter
        $this->exts->type_key_by_xdotool('Return');
        sleep(3);

        // Capture screenshot after selection
        $this->exts->capture("clear-page");

        // Navigate further using Tab to reach the "Clear Data" button
        for ($i = 0; $i < 5; $i++) {
            $this->exts->type_key_by_xdotool('Tab');
            sleep(1);
        }

        // Press Enter to confirm and clear the browsing data
        $this->exts->type_key_by_xdotool('Return');
        sleep(15); // Wait for the clearing process to complete

        // Capture screenshot after clearing
        $this->exts->capture("after-clear");
    }
}
