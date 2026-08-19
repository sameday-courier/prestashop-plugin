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

    public static function bulkEntry(int $orderId)
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

    public static function updateFeedback(int $orderId, int $status, array $payload)
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

    public static function deleteByOrderId(int $orderId)
    {
        Db::getInstance()->delete(self::TABLE_NAME, 'id_order = ' . (int) $orderId);
    }

    /**
     * @param array|null $bulkRow
     * @param array|null $awbRow
     */
    public static function formatForGrid($bulkRow, $awbRow, SamedayCourier $module, int $orderId): string
    {
        $awbRowData = self::resolveAwbRowData($bulkRow, $awbRow);
        if ($awbRowData !== null) {
            return self::buildAwbActionsHtml($orderId, $awbRowData, $module);
        }

        $plain = self::formatPlainFeedback($bulkRow, $awbRow, $module);
        $escaped = htmlspecialchars($plain, ENT_QUOTES, 'UTF-8');
        if (
            is_array($bulkRow)
            && (int) ($bulkRow['status'] ?? self::STATUS_PENDING) === self::STATUS_ERROR
        ) {
            return '<span class="sameday-feedback-error">' . $escaped . '</span>';
        }

        return $escaped;
    }

    /**
     * @param array|null $bulkRow
     * @param array|null $awbRow
     */
    public static function formatPlainFeedback($bulkRow, $awbRow, Module $module): string
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
     * @param array|null $bulkRow
     * @param array|null $awbRow
     */
    /**
     * @param array|null $bulkRow
     * @param array|null $awbRow
     *
     * @return array|null
     */
    private static function resolveAwbRowData($bulkRow, $awbRow)
    {
        if (is_array($awbRow) && !empty($awbRow['awb_number']) && !empty($awbRow['id'])) {
            return $awbRow;
        }

        return null;
    }

    /**
     * @param array $awbRow
     */
    private static function buildAwbActionsHtml(int $orderId, array $awbRow, SamedayCourier $module): string
    {
        $awbNumber = (string) $awbRow['awb_number'];
        $awbId = (int) $awbRow['id'];

        $pdfUrl = SamedayTools::appendQueryParams($module->getBulkAwbAjaxUrl(), [
            'action' => 'download_awb_pdf',
            'order_id' => (int) $orderId,
            'token' => $module->getBulkAwbAdminToken(),
        ]);

        $pdfTitle = htmlspecialchars($module->l('Download AWB'), ENT_QUOTES, 'UTF-8');
        $removeTitle = htmlspecialchars($module->l('Remove AWB'), ENT_QUOTES, 'UTF-8');
        $historyTitle = htmlspecialchars($module->l('AWB History'), ENT_QUOTES, 'UTF-8');
        $awbLabel = htmlspecialchars($awbNumber, ENT_QUOTES, 'UTF-8');

        return '<div class="sameday-feedback-cell">' .
            '<a href="#" class="sameday-feedback-history" data-awb-id="' . $awbId . '" title="' . $historyTitle . '">' . $awbLabel . '</a>' .
            '<span class="sameday-feedback-actions">' .
            '<a href="' . htmlspecialchars($pdfUrl, ENT_QUOTES, 'UTF-8') . '" class="btn btn-primary btn-sm sameday-feedback-btn sameday-feedback-pdf" target="_blank" rel="noopener noreferrer" title="' . $pdfTitle . '">' .
            '<i class="material-icons">description</i></a>' .
            '<button type="button" class="btn btn-danger btn-sm sameday-feedback-btn sameday-feedback-remove" data-order-id="' . (int) $orderId . '" title="' . $removeTitle . '">' .
            '<i class="material-icons">delete</i></button>' .
            '</span></div>';
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
