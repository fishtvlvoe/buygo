<?php

use BuyGo\Core\App;

if (!function_exists('buygo_app')) {
    /**
     * Get the global BuyGo App instance.
     *
     * @return \BuyGo\Core\App
     */
    function buygo_app() {
        return App::instance();
    }
}
