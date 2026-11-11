<?php
/**
 * Reusable HelperList pagination (request/cookie state + SQL page fetch).
 */
class SamedayHelperListPagination
{
    const DEFAULT_LIMIT = 50;

    /** @var int[] */
    public static $options = array(20, 50, 100, 300);

    /**
     * Read page/limit/order from the same request + cookie keys HelperList uses.
     *
     * @param string $listId
     * @param Context|null $context
     * @param int $defaultLimit
     *
     * @return array{limit:int,page:int,offset:int,order_by:string,order_way:string}
     */
    public static function resolveState($listId, $context = null, $defaultLimit = self::DEFAULT_LIMIT)
    {
        if (null === $context) {
            $context = Context::getContext();
        }

        $cookieKey = $listId . '_pagination';
        $limit = (int) Tools::getValue(
            $cookieKey,
            (null !== $context && isset($context->cookie->{$cookieKey}))
                ? $context->cookie->{$cookieKey}
                : $defaultLimit
        );
        if (!in_array($limit, self::$options, true)) {
            $limit = (int) $defaultLimit;
        }

        $page = (int) Tools::getValue('submitFilter' . $listId);
        if ($page < 1) {
            $page = 1;
        }

        return array(
            'limit' => $limit,
            'page' => $page,
            'offset' => ($page - 1) * $limit,
            'order_by' => (string) Tools::getValue($listId . 'Orderby', 'id'),
            'order_way' => (string) Tools::getValue($listId . 'Orderway', 'ASC'),
        );
    }

    /**
     * Clamp page/offset when the requested page is past the last page.
     *
     * @param array $state
     * @param int $listTotal
     *
     * @return array
     */
    public static function normalizeState(array $state, $listTotal)
    {
        $limit = max(1, (int) $state['limit']);
        $listTotal = max(0, (int) $listTotal);
        $maxPage = max(1, (int) ceil($listTotal / $limit));
        $page = (int) $state['page'];
        if ($page > $maxPage) {
            $page = $maxPage;
        }
        if ($page < 1) {
            $page = 1;
        }

        $state['limit'] = $limit;
        $state['page'] = $page;
        $state['offset'] = ($page - 1) * $limit;

        return $state;
    }

    /**
     * @param string $table Table name without DB prefix
     * @param string $where SQL WHERE body (no leading WHERE)
     *
     * @return int
     */
    public static function countRows($table, $where = '1')
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . bqSQL($table) . '` WHERE ' . $where
        );
    }

    /**
     * @param string $table Table name without DB prefix
     * @param array $state From resolveState()/normalizeState()
     * @param array $allowedOrderBy Whitelisted column names
     * @param string $where SQL WHERE body (no leading WHERE)
     * @param string $defaultOrderBy
     *
     * @return array
     * @throws PrestaShopDatabaseException
     */
    public static function fetchRows($table, array $state, array $allowedOrderBy, $where = '1', $defaultOrderBy = 'id')
    {
        $orderBy = (string) $state['order_by'];
        if (!in_array($orderBy, $allowedOrderBy, true)) {
            $orderBy = $defaultOrderBy;
        }
        $orderWay = Tools::strtoupper((string) $state['order_way']) === 'DESC' ? 'DESC' : 'ASC';

        $rows = Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . bqSQL($table) . '` WHERE ' . $where .
            ' ORDER BY `' . bqSQL($orderBy) . '` ' . $orderWay .
            ' LIMIT ' . (int) $state['offset'] . ', ' . (int) $state['limit']
        );

        return is_array($rows) ? $rows : array();
    }

    /**
     * Resolve state, count, clamp page, and fetch one page in a single call.
     *
     * @param string $listId HelperList list_id (usually table name)
     * @param string $table Table name without DB prefix
     * @param array $allowedOrderBy
     * @param string $where
     * @param string $defaultOrderBy
     * @param Context|null $context
     * @param int $defaultLimit
     *
     * @return array{list_id:string,rows:array,total:int,state:array}
     * @throws PrestaShopDatabaseException
     */
    public static function loadPage(
        $listId,
        $table,
        array $allowedOrderBy,
        $where = '1',
        $defaultOrderBy = 'id',
        $context = null,
        $defaultLimit = self::DEFAULT_LIMIT
    ) {
        $state = self::resolveState($listId, $context, $defaultLimit);
        $total = self::countRows($table, $where);
        $state = self::normalizeState($state, $total);

        return array(
            'list_id' => $listId,
            'rows' => self::fetchRows($table, $state, $allowedOrderBy, $where, $defaultOrderBy),
            'total' => $total,
            'state' => $state,
        );
    }

    /**
     * Apply pagination metadata to a HelperList instance.
     *
     * @param HelperList $helper
     * @param string $listId
     * @param array $state
     * @param int $listTotal
     *
     * @return void
     */
    public static function configureHelper(HelperList $helper, $listId, array $state, $listTotal)
    {
        $helper->list_id = $listId;
        $helper->_default_pagination = (int) $state['limit'];
        $helper->_pagination = self::$options;
        $helper->listTotal = (int) $listTotal;
        $helper->orderBy = (string) $state['order_by'];
        $helper->orderWay = (string) $state['order_way'];
    }

    /**
     * Live-mode filter used by lockers / pickup points tables.
     *
     * @return string
     */
    public static function liveModeWhere()
    {
        return "live_mode = '" . (int) Configuration::get('SAMEDAY_LIVE_MODE', 0) . "'";
    }
}
