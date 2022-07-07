<?php

namespace tool_vault\local\models;

/**
 *
 * @property-read int $timecreated
 * @property-read string $backupkey
 * @property-read int $timefinished
 * @property-read string $status
 * @property-read array $info
 */
class remote_backup {
    protected $data;

    public function __construct(array $b) {
        if (!empty($b['metadata'])) {
            foreach ($b['metadata'] as $k => $v) {
                $b[$k] = $v;
            }
        }
        unset($b['metadata']);
        $this->data = $b;
    }

    public function __get(string $name) {
        return $this->data[$name] ?? null;
    }
}