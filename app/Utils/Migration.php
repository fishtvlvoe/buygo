<?php

namespace BuyGo\Core\Utils;

abstract class Migration {
    abstract public function up();
    abstract public function down();
}
