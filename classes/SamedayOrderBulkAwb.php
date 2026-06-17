<?php
/**
 * 2007-2020 PrestaShop
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 */

class SamedayOrderBulkAwb
{
    const TABLE_NAME = 'sameday_order_bulk_awb';

    const STATUS_PENDING = 0;
    const STATUS_SUCCESS = 1;
    const STATUS_ERROR = 2;

    public static function bulkEntry(int $orderId): void
    {
        $orderId = (int) $orderId;
        $now = date('Y-m-d H:i:s');
        $exists = (int) Db::getInstance()->getValue(
            'SELECT id_order FROM ' . _DB_PREFIX_ . self::TABLE_NAME .
            ' WHERE id_order = ' . $orderId
        );

        if ($exists) {
            Db::getInstance()->update(
                self::TABLE_NAME,
                [
                    'status' => self::STATUS_PENDING,
                    'feedback' => '',
                    'date_upd' => $now,
                ],
                'id_order = ' . $orderId
            );

            return;
        }

        Db::getInstance()->insert(self::TABLE_NAME, [
            'id_order' => $orderId,
            'status' => self::STATUS_PENDING,
            'feedback' => '',
            'date_add' => $now,
            'date_upd' => $now,
        ]);
    }

    public static function updateFeedback(int $orderId, int $status, array $payload): void
    {
        self::bulkEntry($orderId);

        Db::getInstance()->update(
            self::TABLE_NAME,
            [
                'status' => (int) $status,
                'feedback' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'date_upd' => date('Y-m-d H:i:s'),
            ],
            'id_order = ' . (int) $orderId
        );
    }

    /**
     * @param int[] $orderIds
     *
     * @return array<int, array>
     */
    public static function getByOrderIds(array $orderIds): array
    {
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
        if ($orderIds === []) {
            return [];
        }

        $rows = Db::getInstance()->executeS(
            'SELECT * FROM ' . _DB_PREFIX_ . self::TABLE_NAME .
            ' WHERE id_order IN (' . implode(',', $orderIds) . ')'
        );

        $result = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $result[(int) $row['id_order']] = $row;
            }
        }

        return $result;
    }

    /**
     * @return int[]
     */
    public static function clearWithoutGeneratedAwb(): array
    {
        $rows = Db::getInstance()->executeS(
            'SELECT b.id_order FROM ' . _DB_PREFIX_ . self::TABLE_NAME . ' b
            LEFT JOIN ' . _DB_PREFIX_ . SamedayAwb::TABLE_NAME . ' a
                ON a.id_order = b.id_order AND TRIM(a.awb_number) <> \'\'
            WHERE a.id_order IS NULL'
        );

        $orderIds = [];
        if (is_array($rows)) {
            $orderIds = array_map('intval', array_column($rows, 'id_order'));
        }

        if ($orderIds === []) {
            return [];
        }

        Db::getInstance()->execute(
            'DELETE b FROM ' . _DB_PREFIX_ . self::TABLE_NAME . ' b
            LEFT JOIN ' . _DB_PREFIX_ . SamedayAwb::TABLE_NAME . ' a
                ON a.id_order = b.id_order AND TRIM(a.awb_number) <> \'\'
            WHERE a.id_order IS NULL'
        );

        return $orderIds;
    }

    public static function deleteByOrderId(int $orderId): void
    {
        Db::getInstance()->delete(self::TABLE_NAME, 'id_order = ' . (int) $orderId);
    }

    /**
     * @param array|null $bulkRow
     * @param array|null $awbRow
     */
    public static function formatForGrid($bulkRow, $awbRow, Module $module): string
    {
        $pending = $module->l('Pending');
        $empty = '—';

        if (is_array($bulkRow) && !empty($bulkRow)) {
            $status = (int) ($bulkRow['status'] ?? self::STATUS_PENDING);
            $payload = self::decodeFeedback((string) ($bulkRow['feedback'] ?? ''));

            if ($status === self::STATUS_ERROR) {
                return !empty($payload['error'])
                    ? (string) $payload['error']
                    : $empty;
            }

            if ($status === self::STATUS_PENDING) {
                return $pending;
            }

            if (!empty($payload['awb_number'])) {
                return (string) $payload['awb_number'];
            }
        }

        if (is_array($awbRow) && !empty($awbRow['awb_number'])) {
            return (string) $awbRow['awb_number'];
        }

        return $empty;
    }

    /**
     * @return array{awb_number?: string, error?: string}
     */
    public static function decodeFeedback(string $feedback): array
    {
        if ($feedback === '') {
            return [];
        }

        $decoded = json_decode($feedback, true);

        return is_array($decoded) ? $decoded : [];
    }
}
