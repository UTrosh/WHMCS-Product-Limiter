<?php
use WHMCS\Database\Capsule as Capsule;

if (!defined("WHMCS")) {
    die("Ce fichier ne peut pas être accédé directement");
}

class LimitPurchase
{
    public $config;

    public function __construct()
    {
        $this->loadConfig();
    }

    protected function loadConfig()
    {
        $this->config = array();

        $pdo = Capsule::connection()->getPdo();

        $sql = "SELECT * FROM mod_limit_purchase_config";
        $statement = $pdo->query($sql);

        while ($config_details = $statement->fetch(PDO::FETCH_ASSOC)) {
            $this->config[$config_details['name']] = $config_details['value'];
        }
    }

    public function setConfig($name, $value)
    {
        $pdo = Capsule::connection()->getPdo();

        if (isset($this->config[$name])) {
            $sql = "UPDATE mod_limit_purchase_config SET value = :value WHERE name = :name";
            $statement = $pdo->prepare($sql);
            $statement->execute(['value' => $value, 'name' => $name]);
        } else {
            $sql = "INSERT INTO mod_limit_purchase_config (`name`, `value`) VALUES (:name, :value)";
            $statement = $pdo->prepare($sql);
            $statement->execute(['name' => $name, 'value' => $value]);
        }

        $this->config[$name] = $value;
    }

    public function getLimitedProducts()
    {
        $output = array();

        $pdo = Capsule::connection()->getPdo();

        $sql = "SELECT l.* FROM mod_limit_purchase as l INNER JOIN tblproducts as p ON p.id = l.product_id WHERE l.active = 1";
        $statement = $pdo->query($sql);

        while ($limits = $statement->fetch(PDO::FETCH_ASSOC)) {
            $output[$limits['product_id']] = array('limit' => $limits['limit'], 'error' => $limits['error']);
        }

        return $output;
    }
}
