<?php

namespace BuyGo\Core\Utils;

require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

class MigrationRunner {

    public function run() {
        $migrations = glob(dirname(dirname(__DIR__)) . '/database/migrations/*.php');
        
        foreach ($migrations as $migration) {
            $class_name = $this->get_class_name_from_file($migration);
            
            if ($class_name) {
                require_once $migration;
                if (class_exists($class_name)) {
                    $instance = new $class_name();
                    if (method_exists($instance, 'up')) {
                        $instance->up();
                    }
                }
            }
        }
    }

    private function get_class_name_from_file($file) {
        $content = file_get_contents($file);
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
