<?php
namespace SimiyuSamuel\Kopokopo\Infrastructure;

use SimiyuSamuel\Kopokopo\Domain\PaymentException;
use SimiyuSamuel\Kopokopo\Domain\Repository;

final class AttemptRepository implements Repository
{
    private readonly \Db $db;
    private readonly string $table;

    public function __construct()
    {
        $this->db = \Db::getInstance();
        $this->table = _DB_PREFIX_ . 'kopokopo_attempt';
    }

    public function install(): bool
    {
        return $this->db->execute('CREATE TABLE IF NOT EXISTS `' . bqSQL($this->table) . '` (
            `id` CHAR(32) NOT NULL, `shop_id` INT UNSIGNED NOT NULL,
            `cart_id` INT UNSIGNED NOT NULL, `customer_id` INT UNSIGNED NOT NULL,
            `currency_id` INT UNSIGNED NOT NULL, `currency` CHAR(3) NOT NULL,
            `amount_minor` BIGINT UNSIGNED NOT NULL, `snapshot` CHAR(64) NOT NULL,
            `environment` VARCHAR(16) NOT NULL, `till` VARCHAR(32) NOT NULL,
            `provider_id` CHAR(36) NULL, `provider_url` VARCHAR(255) NULL,
            `receipt` VARCHAR(64) NULL, `state` VARCHAR(16) NOT NULL,
            `order_id` INT UNSIGNED NULL, `order_reference` VARCHAR(32) NOT NULL,
            `reason` VARCHAR(64) NOT NULL DEFAULT \'\', `resolution_note` VARCHAR(512) NOT NULL DEFAULT \'\',
            `created_at` DATETIME NOT NULL, `updated_at` DATETIME NOT NULL, `checked_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `provider_request` (`environment`, `provider_id`),
            UNIQUE KEY `transaction_receipt` (`environment`, `receipt`),
            KEY `cart_state` (`shop_id`, `cart_id`, `state`),
            KEY `customer_rate` (`shop_id`, `customer_id`, `created_at`),
            KEY `reconciliation` (`shop_id`, `state`, `checked_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    public function locked(int $shop, int $cart, \Closure $operation): mixed
    {
        $name = 'k2_' . substr(hash('sha256', _DB_NAME_ . $this->table . ':' . $shop . ':' . $cart), 0, 50);
        if ((int) $this->db->getValue("SELECT GET_LOCK('" . pSQL($name) . "', 2)", false) !== 1) {
            throw new PaymentException('Payment is being processed. Please check its status shortly.', 503);
        }
        try {
            return $operation();
        } finally {
            $this->db->getValue("SELECT RELEASE_LOCK('" . pSQL($name) . "')", false);
        }
    }

    public function create(array $attempt): void
    {
        if (!$this->db->insert('kopokopo_attempt', $this->escape($attempt))) {
            throw new \RuntimeException('Could not persist payment attempt.');
        }
    }

    public function find(string $id): ?array
    {
        return $this->db->getRow('SELECT * FROM `' . bqSQL($this->table) . "` WHERE id = '" . pSQL($id) . "'", false) ?: null;
    }

    public function update(string $id, array $changes): void
    {
        $changes['updated_at'] = gmdate('Y-m-d H:i:s');
        if (!$this->db->update('kopokopo_attempt', $this->escape($changes), "id = '" . pSQL($id) . "'")) {
            throw new \RuntimeException('Could not update payment attempt.');
        }
    }

    public function openForCart(int $shop, int $cart): ?array
    {
        return $this->db->getRow('SELECT * FROM `' . bqSQL($this->table) . '` WHERE shop_id = ' . $shop
            . ' AND cart_id = ' . $cart . " AND state NOT IN ('failed','resolved') ORDER BY created_at DESC, id DESC", false) ?: null;
    }

    public function countRecent(int $shop, int $customer): int
    {
        return (int) $this->db->getValue('SELECT COUNT(*) FROM `' . bqSQL($this->table) . '` WHERE shop_id = ' . $shop
            . ' AND customer_id = ' . $customer . ' AND created_at > UTC_TIMESTAMP() - INTERVAL 1 HOUR', false);
    }

    public function recent(int $shop, int $limit = 30): array
    {
        return $this->db->executeS('SELECT * FROM `' . bqSQL($this->table) . '` WHERE shop_id = ' . $shop
            . ' ORDER BY created_at DESC LIMIT ' . max(1, min(100, $limit)), true, false) ?: [];
    }

    public function due(int $shop): array
    {
        return $this->db->executeS('SELECT * FROM `' . bqSQL($this->table) . '` WHERE shop_id = ' . $shop
            . " AND state IN ('initiating','unknown','pending','confirmed','completing')"
            . ' AND created_at < UTC_TIMESTAMP() - INTERVAL 30 SECOND'
            . ' AND (checked_at IS NULL OR checked_at < UTC_TIMESTAMP() - INTERVAL 1 MINUTE)'
            . ' ORDER BY COALESCE(checked_at, created_at) ASC LIMIT 10', true, false) ?: [];
    }

    public function hasOpen(int $shop, string $environment): bool
    {
        return (bool) $this->db->getValue('SELECT id FROM `' . bqSQL($this->table) . '` WHERE shop_id = ' . $shop
            . " AND environment = '" . pSQL($environment) . "' AND state IN ('initiating','unknown','pending','confirmed','completing')", false);
    }

    private function escape(array $data): array
    {
        foreach ($data as &$value) {
            if ($value === null) {
                $value = ['type' => 'sql', 'value' => 'NULL'];
            } elseif (is_string($value)) {
                $value = pSQL($value);
            }
        }
        return $data;
    }
}
