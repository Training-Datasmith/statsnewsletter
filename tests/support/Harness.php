<?php

if (!defined('_DB_PREFIX_')) {
    define('_DB_PREFIX_', 'ps_');
}
if (!defined('_PS_USE_SQL_SLAVE_')) {
    define('_PS_USE_SQL_SLAVE_', false);
}
if (!defined('_PS_VERSION_')) {
    define('_PS_VERSION_', '1.7.8.7');
}

function pSQL($value, $htmlOK = false)
{
    return str_replace(array('\\', "\0", "\n", "\r", "'", '"', "\x1a"), array('\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'), $value);
}

class Db
{
    /** @var Db */
    private static $instance;

    /** @var PDO */
    private $pdo;

    /** @var array */
    private static $statementLog = array();

    private function __construct()
    {
        $host = getenv('STATSNEWSLETTER_DB_HOST');
        $name = getenv('STATSNEWSLETTER_DB_NAME');
        $user = getenv('STATSNEWSLETTER_DB_USER');
        $pass = getenv('STATSNEWSLETTER_DB_PASSWORD');
        if ($host === false || $host === '') {
            $host = '127.0.0.1';
        }
        if ($name === false || $name === '') {
            $name = 'statsnewsletter';
        }
        if ($user === false || $user === '') {
            $user = 'statsnewsletter';
        }
        if ($pass === false) {
            $pass = 'statsnewsletter';
        }
        $dsn = 'mysql:host=' . $host . ';dbname=' . $name . ';charset=utf8';
        $this->pdo = new PDO($dsn, $user, $pass, array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ));
    }

    public static function getInstance($slave = false)
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function resetInstance()
    {
        self::$instance = null;
        self::$statementLog = array();
    }

    public static function getStatementLog()
    {
        return self::$statementLog;
    }

    public function execute($sql)
    {
        self::$statementLog[] = $sql;

        return $this->pdo->exec($sql) !== false;
    }

    public function getRow($sql)
    {
        self::$statementLog[] = $sql;
        $stmt = $this->pdo->query($sql);
        if ($stmt === false) {
            return false;
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return false;
        }

        return $row;
    }

    public function executeS($sql)
    {
        self::$statementLog[] = $sql;
        $stmt = $this->pdo->query($sql);
        if ($stmt === false) {
            return false;
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === false) {
            return false;
        }

        return $rows;
    }

    public function insert($table, $data)
    {
        $columns = array();
        $values = array();
        foreach ($data as $col => $val) {
            $columns[] = '`' . $col . '`';
            if ($val === null) {
                $values[] = 'NULL';
            } else {
                $values[] = "'" . pSQL($val) . "'";
            }
        }
        $sql = 'INSERT INTO `' . $table . '` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')';
        $this->execute($sql);

        return (int) $this->pdo->lastInsertId();
    }

    public function truncateTables(array $tables)
    {
        foreach ($tables as $table) {
            $this->execute('TRUNCATE TABLE `' . $table . '`');
        }
    }
}

class Configuration
{
    private static $values = array();

    public static function set($key, $value)
    {
        self::$values[$key] = $value;
    }

    public static function get($key, $default = false)
    {
        if (array_key_exists($key, self::$values)) {
            return self::$values[$key];
        }

        return $default;
    }

    public static function reset()
    {
        self::$values = array();
    }
}

class Validate
{
    public static function isModuleName($name)
    {
        return is_string($name) && preg_match('/^[a-zA-Z0-9_-]+$/', $name);
    }

    public static function isLoadedObject($object)
    {
        return is_object($object) && isset($object->id) && (int) $object->id > 0;
    }
}

class Employee
{
    public $id;
    public $stats_date_from;
    public $stats_date_to;

    public function __construct($id = null)
    {
        $this->id = (int) $id;
        if ($id) {
            $row = Db::getInstance()->getRow(
                'SELECT * FROM `ps_employee` WHERE `id_employee` = ' . (int) $id
            );
            if ($row) {
                $this->stats_date_from = $row['stats_date_from'];
                $this->stats_date_to = $row['stats_date_to'];
            }
        }
    }

    public function update()
    {
        return true;
    }
}

class Context
{
  /** @var Context */
    private static $instance;

    /** @var Employee */
    public $employee;

    /** @var object */
    public $language;

    public function __construct()
    {
        $this->language = new stdClass();
        $this->language->id = 1;
        $this->employee = new Employee();
    }

    public static function getContext()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function reset()
    {
        self::$instance = null;
    }

    public function getTranslator()
    {
        return $this;
    }

    public function trans($id, array $parameters = array(), $domain = null, $locale = null)
    {
        $out = $id;
        foreach ($parameters as $value) {
            $out = preg_replace('/%s/', $value, $out, 1);
        }

        return $out;
    }
}

class Tools
{
    public static function getValue($key, $default = false)
    {
        if (isset($_POST[$key])) {
            return $_POST[$key];
        }
        if (isset($_GET[$key])) {
            return $_GET[$key];
        }

        return $default;
    }

    public static function safeOutput($value)
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

class Shop
{
    const CONTEXT_SHOP = 4;
    const CONTEXT_GROUP = 2;
    const CONTEXT_ALL = 1;
    const SHARE_CUSTOMER = 'share_customer';

    private static $context = self::CONTEXT_SHOP;
    private static $contextIdShop = 1;
    private static $contextIdShopGroup = 1;

    public static function resetStaticCache()
    {
        self::$context = self::CONTEXT_SHOP;
        self::$contextIdShop = 1;
        self::$contextIdShopGroup = 1;
    }

    public static function setContext($type, $id = null)
    {
        self::$context = (int) $type;
        if ($type === self::CONTEXT_SHOP) {
            self::$contextIdShop = (int) $id;
            $row = Db::getInstance()->getRow(
                'SELECT `id_shop_group` FROM `ps_shop` WHERE `id_shop` = ' . (int) $id
            );
            if ($row) {
                self::$contextIdShopGroup = (int) $row['id_shop_group'];
            }
        }
    }

    public static function getContext()
    {
        return self::$context;
    }

    public static function getContextShopID($null_value_without_multishop = false)
    {
        return self::$contextIdShop;
    }

    public static function getContextShopGroupID($null_value_without_multishop = false)
    {
        return self::$contextIdShopGroup;
    }

    public static function getGroupFromShop($shopId, $cache = false)
    {
        $row = Db::getInstance()->getRow(
            'SELECT sg.`id_shop_group`, sg.`share_customer`
             FROM `ps_shop` s
             INNER JOIN `ps_shop_group` sg ON sg.`id_shop_group` = s.`id_shop_group`
             WHERE s.`id_shop` = ' . (int) $shopId
        );
        if (!$row) {
            return array('share_customer' => 0);
        }

        return array(
            'id_shop_group' => (int) $row['id_shop_group'],
            'share_customer' => (int) $row['share_customer'],
        );
    }

    public static function getSharedShops($shop_id, $type)
    {
        $group = self::getGroupFromShop($shop_id, false);
        if ($type === self::SHARE_CUSTOMER && !empty($group['share_customer'])) {
            $rows = Db::getInstance()->executeS(
                'SELECT `id_shop` FROM `ps_shop` WHERE `id_shop_group` = ' . (int) $group['id_shop_group'] . ' ORDER BY `id_shop` ASC'
            );
            if (!$rows) {
                return array((int) $shop_id);
            }
            $ids = array();
            foreach ($rows as $row) {
                $ids[] = (int) $row['id_shop'];
            }

            return $ids;
        }

        return array((int) $shop_id);
    }

    public static function getContextListShopID($share = false)
    {
        if (self::$context === self::CONTEXT_SHOP) {
            if ($share) {
                return self::getSharedShops(self::$contextIdShop, $share);
            }

            return array(self::$contextIdShop);
        }

        return array(self::$contextIdShop);
    }

    public static function addSqlRestriction($share = false, $alias = null)
    {
        if ($alias) {
            $alias .= '.';
        } else {
            $alias = '';
        }

        $group = self::getGroupFromShop(self::getContextShopID(), false);
        if ($share == self::SHARE_CUSTOMER && self::getContext() == self::CONTEXT_SHOP && !empty($group['share_customer'])) {
            return ' AND ' . $alias . 'id_shop_group = ' . (int) self::getContextShopGroupID() . ' ';
        }

        $ids = self::getContextListShopID($share);
        sort($ids, SORT_NUMERIC);

        return ' AND ' . $alias . 'id_shop IN (' . implode(', ', array_map('intval', $ids)) . ') ';
    }
}

class Module
{
    public function __construct()
    {
    }

    public $name;
    public $tab;
    public $version;
    public $author;
    public $need_instance;
    public $displayName;
    public $description;
    public $ps_versions_compliancy = array();

    private static $installedCache = null;

    public function trans($id, array $parameters = array(), $domain = null, $locale = null)
    {
        $out = $id;
        foreach ($parameters as $value) {
            $out = preg_replace('/%s/', $value, $out, 1);
        }

        return $out;
    }

    public function install()
    {
        $existing = Db::getInstance()->getRow(
            "SELECT `id_module` FROM `" . _DB_PREFIX_ . "module` WHERE `name` = '" . pSQL($this->name) . "'"
        );
        if ($existing) {
            return false;
        }
        Db::getInstance()->insert('ps_module', array(
            'name' => $this->name,
            'active' => 1,
        ));
        self::$installedCache = null;

        return true;
    }

    public function registerHook($hookName)
    {
        $hook = Db::getInstance()->getRow(
            "SELECT `id_hook` FROM `" . _DB_PREFIX_ . "hook` WHERE `name` = '" . pSQL($hookName) . "'"
        );
        if (!$hook) {
            $hookId = Db::getInstance()->insert('ps_hook', array('name' => $hookName));
        } else {
            $hookId = (int) $hook['id_hook'];
        }
        $module = Db::getInstance()->getRow(
            "SELECT `id_module` FROM `" . _DB_PREFIX_ . "module` WHERE `name` = '" . pSQL($this->name) . "'"
        );
        if (!$module) {
            return false;
        }
        $shopId = Shop::getContextShopID();
        $existing = Db::getInstance()->getRow(
            'SELECT 1 FROM `' . _DB_PREFIX_ . 'hook_module` WHERE `id_module` = ' . (int) $module['id_module']
            . ' AND `id_hook` = ' . (int) $hookId . ' AND `id_shop` = ' . (int) $shopId
        );
        if (!$existing) {
            Db::getInstance()->insert('ps_hook_module', array(
                'id_module' => (int) $module['id_module'],
                'id_hook' => (int) $hookId,
                'id_shop' => (int) $shopId,
            ));
        }

        return true;
    }

    public function unregisterHook($hookName)
    {
        $hook = Db::getInstance()->getRow(
            "SELECT `id_hook` FROM `" . _DB_PREFIX_ . "hook` WHERE `name` = '" . pSQL($hookName) . "'"
        );
        if (!$hook) {
            return true;
        }
        $module = Db::getInstance()->getRow(
            "SELECT `id_module` FROM `" . _DB_PREFIX_ . "module` WHERE `name` = '" . pSQL($this->name) . "'"
        );
        if (!$module) {
            return true;
        }
        Db::getInstance()->execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'hook_module` WHERE `id_module` = ' . (int) $module['id_module']
            . ' AND `id_hook` = ' . (int) $hook['id_hook']
        );

        return true;
    }

    public static function isInstalled($moduleName)
    {
        if (self::$installedCache === null) {
            self::$installedCache = array();
        }
        if (array_key_exists($moduleName, self::$installedCache)) {
            return self::$installedCache[$moduleName];
        }
        $row = Db::getInstance()->getRow(
            "SELECT `id_module` FROM `" . _DB_PREFIX_ . "module` WHERE `name` = '" . pSQL($moduleName) . "'"
        );
        $result = (bool) $row;
        self::$installedCache[$moduleName] = $result;

        return $result;
    }

    public static function resetInstalledCache()
    {
        self::$installedCache = null;
    }
}

abstract class ModuleGraph extends Module
{
    public function __construct()
    {
        parent::__construct();
    }

    /** @var Employee */
    protected $_employee;

    protected $_values = array();
    protected $_legend = array();
    protected $_titles = array('main' => null, 'x' => null, 'y' => null);
    protected $_csv = '';

    abstract protected function getData($layers);

    public function setEmployee($id_employee)
    {
        $this->_employee = new Employee((int) $id_employee);
    }

    public function setLang($id_lang)
    {
        $this->_id_lang = (int) $id_lang;
    }

    protected function setDateGraph($layers, $legend = false)
    {
        $from_array = getdate(strtotime($this->_employee->stats_date_from));
        $to_array = getdate(strtotime($this->_employee->stats_date_to));

        if ($this->_employee->stats_date_from == $this->_employee->stats_date_to) {
            if ($legend) {
                for ($i = 0; $i < 24; ++$i) {
                    if ($layers == 1) {
                        $this->_values[$i] = 0;
                    } else {
                        for ($j = 0; $j < $layers; ++$j) {
                            $this->_values[$j][$i] = 0;
                        }
                    }
                    $this->_legend[$i] = ($i % 2) ? '' : sprintf('%02dh', $i);
                }
            }
            if (is_callable(array($this, 'setDayValues'))) {
                $this->setDayValues($layers);
            }
        } elseif (strtotime($this->_employee->stats_date_to) - strtotime($this->_employee->stats_date_from) <= 2678400) {
            if ($legend) {
                $days = array();
                if ($from_array['mon'] == $to_array['mon']) {
                    for ($i = $from_array['mday']; $i <= $to_array['mday']; ++$i) {
                        $days[] = $i;
                    }
                } else {
                    $imax = date('t', mktime(0, 0, 0, $from_array['mon'], 1, $from_array['year']));
                    for ($i = $from_array['mday']; $i <= $imax; ++$i) {
                        $days[] = $i;
                    }
                    for ($i = 1; $i <= $to_array['mday']; ++$i) {
                        $days[] = $i;
                    }
                }
                foreach ($days as $i) {
                    if ($layers == 1) {
                        $this->_values[$i] = 0;
                    } else {
                        for ($j = 0; $j < $layers; ++$j) {
                            $this->_values[$j][$i] = 0;
                        }
                    }
                    $this->_legend[$i] = ($i % 2) ? '' : sprintf('%02d', $i);
                }
            }
            if (is_callable(array($this, 'setMonthValues'))) {
                $this->setMonthValues($layers);
            }
        } elseif (strtotime('-1 year', strtotime($this->_employee->stats_date_to)) < strtotime($this->_employee->stats_date_from)) {
            if ($legend) {
                $months = array();
                if ($from_array['year'] == $to_array['year']) {
                    for ($i = $from_array['mon']; $i <= $to_array['mon']; ++$i) {
                        $months[] = $i;
                    }
                } else {
                    for ($i = $from_array['mon']; $i <= 12; ++$i) {
                        $months[] = $i;
                    }
                    for ($i = 1; $i <= $to_array['mon']; ++$i) {
                        $months[] = $i;
                    }
                }
                foreach ($months as $i) {
                    if ($layers == 1) {
                        $this->_values[$i] = 0;
                    } else {
                        for ($j = 0; $j < $layers; ++$j) {
                            $this->_values[$j][$i] = 0;
                        }
                    }
                    $this->_legend[$i] = sprintf('%02d', $i);
                }
            }
            if (is_callable(array($this, 'setYearValues'))) {
                $this->setYearValues($layers);
            }
        } else {
            if ($legend) {
                $years = array();
                for ($i = $from_array['year']; $i <= $to_array['year']; ++$i) {
                    $years[] = $i;
                }
                foreach ($years as $i) {
                    if ($layers == 1) {
                        $this->_values[$i] = 0;
                    } else {
                        for ($j = 0; $j < $layers; ++$j) {
                            $this->_values[$j][$i] = 0;
                        }
                    }
                    $this->_legend[$i] = sprintf('%04d', $i);
                }
            }
            if (is_callable(array($this, 'setAllTimeValues'))) {
                $this->setAllTimeValues($layers);
            }
        }
    }

    protected function csvExport($datas)
    {
        $context = Context::getContext();

        $this->setEmployee($context->employee->id);
        $this->setLang($context->language->id);

        $layers = isset($datas['layers']) ? $datas['layers'] : 1;
        if (isset($datas['option'])) {
            $this->setOption($datas['option'], $layers);
        }

        $this->getData($layers);

        if (is_array($this->_titles['main'])) {
            for ($i = 0, $total_main = count($this->_titles['main']); $i <= $total_main; ++$i) {
                if ($i > 0) {
                    $this->_csv .= ';';
                }
                if (isset($this->_titles['main'][$i])) {
                    $this->_csv .= $this->escapeCell($this->_titles['main'][$i]);
                }
            }
        } else {
            $this->_csv .= ';' . $this->escapeCell($this->_titles['main']);
        }

        $this->_csv .= "\n";
        if (count($this->_legend)) {
            foreach ($this->_legend as $key => $legend) {
                $this->_csv .= $this->escapeCell($legend) . ';';
                for ($i = 0, $total_main = (is_array($this->_titles['main']) ? count($this->_values) : 1); $i < $total_main; ++$i) {
                    if (!isset($this->_values[$i]) || !is_array($this->_values[$i])) {
                        if (isset($this->_values[$key])) {
                            if (is_numeric($this->_values[$key])) {
                                $this->_csv .= $this->_values[$key];
                            } else {
                                $this->_csv .= $this->escapeCell($this->_values[$key]);
                            }
                        } else {
                            $this->_csv .= '0';
                        }
                    } else {
                        if (is_numeric($this->_values[$i][$key])) {
                            $this->_csv .= $this->_values[$i][$key];
                        } else {
                            $this->_csv .= $this->escapeCell($this->_values[$i][$key]);
                        }
                    }
                    $this->_csv .= ';';
                }
                $this->_csv .= "\n";
            }
        }

        $this->_displayCsv();
    }

    protected function _displayCsv()
    {
        if (ob_get_level() && ob_get_length() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $this->displayName . ' - ' . time() . '.csv"');
        echo $this->_csv;
        exit;
    }

    public function setOption($option, $layers = 1)
    {
    }

    public function engine($params)
    {
        $render = Configuration::get('PS_STATS_RENDER');
        if (!$render) {
            return Context::getContext()->getTranslator()->trans('No graph engine selected', array(), 'Admin.Modules.Notification');
        }

        return '';
    }

    protected static function getEmployee($employee = null, $context = null)
    {
        if (!Validate::isLoadedObject($employee)) {
            if (!$context) {
                $context = Context::getContext();
            }
            if (!Validate::isLoadedObject($context->employee)) {
                return false;
            }
            $employee = $context->employee;
        }

        if (empty($employee->stats_date_from) || empty($employee->stats_date_to)
            || $employee->stats_date_from == '0000-00-00' || $employee->stats_date_to == '0000-00-00') {
            if (empty($employee->stats_date_from) || $employee->stats_date_from == '0000-00-00') {
                $employee->stats_date_from = date('Y') . '-01-01';
            }
            if (empty($employee->stats_date_to) || $employee->stats_date_to == '0000-00-00') {
                $employee->stats_date_to = date('Y') . '-12-31';
            }
            $employee->update();
        }

        return $employee;
    }

    public function getDate()
    {
        return static::getDateBetween($this->_employee);
    }

    public static function getDateBetween($employee = null)
    {
        if ($employee = static::getEmployee($employee)) {
            return ' \'' . pSQL($employee->stats_date_from) . ' 00:00:00\' AND \'' . pSQL($employee->stats_date_to) . ' 23:59:59\' ';
        }

        return ' \'' . date('Y-m') . '-01 00:00:00\' AND \'' . date('Y-m-t') . ' 23:59:59\' ';
    }

    public function escapeCell($content)
    {
        $escaped = '"';
        if (preg_match('/^[=+\\-@]/', $content)) {
            $content = '\'' . $content;
        }
        $escaped .= str_replace('"', '""', $content);
        $escaped .= '"';

        return $escaped;
    }
}

class TestEnvironment
{
    public static function resetRequest()
    {
        $_GET = array();
        $_POST = array();
        $_REQUEST = array();
        $_SERVER['REQUEST_URI'] = '/admin/index.php';
    }

    public static function seedDefaultShops()
    {
        $db = Db::getInstance();
        $db->execute('INSERT IGNORE INTO `ps_shop_group` (`id_shop_group`, `share_customer`) VALUES (1, 0)');
        $db->execute('INSERT IGNORE INTO `ps_shop` (`id_shop`, `id_shop_group`) VALUES (1, 1), (2, 1)');
        Shop::setContext(Shop::CONTEXT_SHOP, 1);
    }

    public static function resetDatabaseState()
    {
        $db = Db::getInstance();
        $db->truncateTables(array(
            'ps_customer',
            'ps_emailsubscription',
            'ps_module',
            'ps_hook',
            'ps_hook_module',
            'ps_employee',
        ));
        self::seedDefaultShops();
    }

    public static function resetAll()
    {
        self::resetRequest();
        Db::resetInstance();
        Shop::resetStaticCache();
        Module::resetInstalledCache();
        Context::reset();
        Configuration::reset();
        self::resetDatabaseState();
        $ctx = Context::getContext();
        $ctx->employee = new Employee();
    }

    public static function setEmployeeDates($id, $from, $to)
    {
        $db = Db::getInstance();
        $db->execute(
            'INSERT INTO `ps_employee` (`id_employee`, `stats_date_from`, `stats_date_to`) VALUES ('
            . (int) $id . ", '" . pSQL($from) . "', '" . pSQL($to) . "')"
            . ' ON DUPLICATE KEY UPDATE `stats_date_from` = VALUES(`stats_date_from`), `stats_date_to` = VALUES(`stats_date_to`)'
        );
        $ctx = Context::getContext();
        $ctx->employee = new Employee($id);
    }

    public static function installCompanionModule()
    {
        Db::getInstance()->insert('ps_module', array(
            'name' => 'ps_emailsubscription',
            'active' => 1,
        ));
        Module::resetInstalledCache();
    }

    public static function insertCustomer($idShop, $date)
    {
        Db::getInstance()->insert('ps_customer', array(
            'id_shop' => (int) $idShop,
            'id_shop_group' => 1,
            'newsletter_date_add' => $date,
        ));
    }

    public static function insertCustomerNull($idShop)
    {
        Db::getInstance()->insert('ps_customer', array(
            'id_shop' => (int) $idShop,
            'id_shop_group' => 1,
            'newsletter_date_add' => null,
        ));
    }

    public static function insertVisitor($idShop, $date)
    {
        Db::getInstance()->insert('ps_emailsubscription', array(
            'id_shop' => (int) $idShop,
            'id_shop_group' => 1,
            'newsletter_date_add' => $date,
        ));
    }

    public static function insertVisitorNull($idShop)
    {
        Db::getInstance()->insert('ps_emailsubscription', array(
            'id_shop' => (int) $idShop,
            'id_shop_group' => 1,
            'newsletter_date_add' => null,
        ));
    }

    public static function hookIsRegistered($hookName)
    {
        $row = Db::getInstance()->getRow(
            "SELECT hm.`id_module` FROM `ps_hook_module` hm
             INNER JOIN `ps_hook` h ON h.`id_hook` = hm.`id_hook`
             INNER JOIN `ps_module` m ON m.`id_module` = hm.`id_module`
             WHERE h.`name` = '" . pSQL($hookName) . "' AND m.`name` = 'statsnewsletter'"
        );

        return (bool) $row;
    }
}
