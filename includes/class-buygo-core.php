<?php

use BuyGo\Core\App;
use BuyGo\Core\Services\LineService;
use BuyGo\Core\Services\SettingsService;
use BuyGo\Core\Services\RoleManager;

class BuyGo_Core {

    /**
     * Get the main App instance.
     * 
     * @return App
     */
    public static function app() {
        return App::instance();
    }

    /**
     * Get Line Service.
     * 
     * @return LineService
     */
    public static function line() {
        return self::app()->make(LineService::class);
    }

    /**
     * Get Settings Service (The 'Box').
     * 
     * @return SettingsService
     */
    public static function settings() {
        return self::app()->make(SettingsService::class);
    }

    /**
     * Get Role Manager.
     * 
     * @return RoleManager
     */
    public static function roles() {
        return self::app()->make(RoleManager::class);
    }
    
    /**
     * Check if Core is active.
     */
    public static function is_active() {
        return true;
    }
}
