<?php
/**
 * Created by PhpStorm.
 * User: robert
 * Date: 7/17/18
 * Time: 11:38 PM
 */

namespace Leroy\LeDb;

use Exception;
use InvalidArgumentException;
use PDO;
use PDOStatement;
use stdClass;

class LeDbService
{
    /** @var  array */
    private $statement_cache;
    /** @var  array */
    private $pdo_cache;
    /** @var  string */
    private $domain_credentials;
    /** @var array */
    private $pdo_parameters;

    const SERVER_TYPE_PRIME = 'nexus';// master server
    const SERVER_TYPE_REPLICA = 'replicant';// slave server
    const SQL_TYPE_WRITE = 'write';
    const SQL_TYPE_INSERT = 'insert';
    const SQL_TYPE_READ = 'read';

    /**
     * LeDbService constructor.
     * @param string $data_source_name
     * @param string|array|stdClass $db_configuration
     * @param array $pdo_parameters
     * @throws Exception
     */
    private function __construct(string $data_source_name, $db_configuration, array $pdo_parameters)
    {
        $this->statement_cache = [];
        $this->pdo_cache = [];
        $this->pdo_parameters = $pdo_parameters;
        $this->domain_credentials = $this->getDomainCredentials($db_configuration, $data_source_name);
    }

    /**
     * @param string $data_source_name
     * @param string|null $db_configuration
     * @param array $pdo_parameters
     * @return LeDbService
     * @throws Exception
     */
    public static function init(
        string $data_source_name,
        string $db_configuration = null,
        array $pdo_parameters = [PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY]
    ) {
        /** @var array $LeDbServiceSingleton caches the LeDbService objects */
        static $LeDbServiceSingleton;
        /** @var array $LeDbConfigFileCache caches the config file, so it only has to be entered once */
        static $LeDbConfigFileCache;

        /* load the db con file, which will be required with the first init. Subsequent inits can be empty
            as long as the db con data has the dsn. If it doesn't then things wont work out so well! */
        if (is_null($LeDbConfigFileCache)) {
            $LeDbConfigFileCache = [];
            if (is_null($data_source_name)) {
                throw new InvalidArgumentException('Configuration file path not provided');
            }
        }
        /* We need a key, so if one a new file is passed in, we use that.
           If not, we use the first file used to init the object */
        if (!empty($db_configuration)) {
            $conf_array_key = md5(serialize($db_configuration));
        } else {
            $conf_array_key = current(array_keys($LeDbConfigFileCache));
        }
        /* Add the file to the cache */
        if (!array_key_exists($conf_array_key, $LeDbConfigFileCache)) {
            $LeDbConfigFileCache[$conf_array_key] = $db_configuration;
        }

        /* Cache the LeDbService object. Every DSN will be a new LeDbService object. */
        if (is_null($LeDbServiceSingleton)) {
            $LeDbServiceSingleton = [];
        }
        if (!array_key_exists($data_source_name, $LeDbServiceSingleton)) {
            $LeDbServiceSingleton[$data_source_name] = new LeDbService(
                $data_source_name,
                $LeDbConfigFileCache[$conf_array_key],
                $pdo_parameters
            );
        }
        return $LeDbServiceSingleton[$data_source_name];
    }

    //<editor-fold desc="Getter/Setter Functions">
    /**
     * @return LeDbResultInterface
     */
    protected function getDbResult()
    {
        return new LeDbResult();
    }
    //</editor-fold>

    /**
     * @param string $file
     * @param bool $create_db_in_file
     * @return array
     *
     * @todo provide parameter to choose master or slave
     */
    public function executeFile(string $file,  bool $create_db_in_file = false)
    {
        $cred = $this->domain_credentials->master;
        $dbName = ($create_db_in_file) ? '' : " {$cred->dbName}";
        exec(
            "mysql -h localhost -u {$cred->userName} -p{$cred->password}{$dbName} < \"{$file}\" 2>&1",
            $output,
            $return_var
        );
        return [$output, $return_var];
    }

    /**
     * @param string $sql
     * @param array $bindings
     * @param bool $associate
     * @param bool $use_prime
     * @return LeDbResultInterface
     */
    public function execute(string $sql, array $bindings = [], bool $associate = false, bool $use_prime = false)
    {
        /** @var LeDbResultInterface $output */
        $output = $this->getDbResult();
        $output->setBindings($bindings);
        try {
            $sql_type = $this->getSqlType($sql);
            $server_type = $this->getServerType($use_prime, $sql_type);
            $pdo = $this->initPdo($server_type);
            /* If $bindings are empty, then the sql is run. */
            $stmt = $this->getStatement($sql, $pdo, !empty($bindings));
            if (!empty($bindings)) {
                if ($associate) {
                    foreach ($bindings as $key => $val) {
                        /* There is no type for floats, because there is no PDO
                            constant for floats, so they are treated like strings. */
                        if (is_int($val)) {
                            $var_type = PDO::PARAM_INT;
                        } elseif (is_bool($val)) {
                            $var_type = PDO::PARAM_BOOL;
                        } elseif (is_null($val)) {
                            $var_type = PDO::PARAM_NULL;
                        } else {
                            $var_type = (65535 >= strlen($val)) ? PDO::PARAM_STR : PDO::PARAM_LOB;
                        }
                        $stmt->bindValue(":{$key}", $val, $var_type);
                    }
                }
                $stmt->execute($bindings);
            }
            $output->setSqlType($sql_type);
            $output->setPdoStatement($stmt);
            if (self::SQL_TYPE_INSERT == $sql_type) {
                $output->setLastInsertId($pdo->lastInsertId());
            }
            if (0 === strpos($sql, 'SELECT SQL_CALC_FOUND_ROWS')) {
                $rows_found = $pdo->query('SELECT FOUND_ROWS();')->fetchColumn();
                $output->setRowsFound($rows_found);
            }
            /* On successful transactions, errorCode() and errorInfo() will be populated with 0000.
                We do not want to count that as an error, so we check for that and skip it if true. */
            if (0 !== (int)$pdo->errorCode()) {
                $output->setErrorCode($pdo->errorCode());
            }
            $error_info = $pdo->errorInfo();
            if (0 !== (int)current($error_info)) {
                $output->setErrorInfo($pdo->errorInfo());
            }
        } catch (Exception $e) {
            $output->setException($e);
        }
        return $output;
    }

    /**
     * @return array
     * @note for debugging
     */
    public function toArray()
    {
        $output = [];
        foreach ($this as $k => $v) {
            $output[$k] = $v;
        }
        return $output;
    }

    //<editor-fold desc="Private Functions">
    /**
     * @param string $sql
     * @param PDO $pdo
     * @param bool $prepare
     * @return PDOStatement
     */
    private function getStatement(string $sql, PDO $pdo, $prepare = false)
    {
        $output = null;
        if ($prepare) {
            $key = 'SQL' . md5($sql);
            if (!array_key_exists($key, $this->statement_cache)) {
                $this->statement_cache[$key] = $pdo->prepare($sql, $this->pdo_parameters);
            }
            $output = $this->statement_cache[$key];
        } else {
            $output = $pdo->query($sql);
        }
        return $output;
    }

    /**
     * @param string|array|stdClass $db_configuration
     * @param string $dsn
     * @return string
     * @throws Exception
     */
    private function getDomainCredentials($db_configuration, string $dsn)
    {
        $stdClass = null;
        if (is_array($db_configuration)) {
            $stdClass = json_decode(json_encode($db_configuration));
        } elseif (is_string($db_configuration)) {
            if (file_exists($db_configuration)) {
                $json_string = file_get_contents($db_configuration);
                $stdClass = json_decode($json_string);
            } else {
                $stdClass = json_decode($db_configuration);
            }
        } elseif ($db_configuration instanceof stdClass) {
            $stdClass = $db_configuration;
        }

        if (! $stdClass instanceof stdClass) {
            throw new Exception("Can't make heads or tails our of: " . print_r($db_configuration, true));
        } elseif (!isset($stdClass->$dsn)) {
            throw new Exception("The DSN '{$dsn}'' is not found in: " . print_r($db_configuration, true));
        }

        return $stdClass->$dsn;
    }

    /**
     * @param string $type
     * @return PDO
     */
    private function initPdo(string $type)
    {
        if (self::SERVER_TYPE_PRIME == $type) {
            $cred = $this->domain_credentials->master;
        } else {
            $slaves = (array)$this->domain_credentials->slave;
            $slave = rand(0, count($slaves) - 1);
            $cred = $slaves[$slave];
        }
        $conn_str = "mysql:host={$cred->host};dbname={$cred->dbName};port={$cred->port};";
        $key = 'CONN' . md5($conn_str);
        if (!array_key_exists($key, $this->pdo_cache)) {
            $this->pdo_cache[$key] = new PDO(
                $conn_str,
                $cred->userName,
                $cred->password,
                [
                    PDO::ATTR_PERSISTENT => true,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::MYSQL_ATTR_FOUND_ROWS => true
                ]
            );
        }
        return $this->pdo_cache[$key];
    }

    /**
     * @param boolean $use_prime
     * @param string|null $sql_type
     * @return string
     */
    private function getServerType(bool $use_prime, string $sql_type = null)
    {
        return ($use_prime || self::SQL_TYPE_WRITE == $sql_type) ? self::SERVER_TYPE_PRIME : self::SERVER_TYPE_REPLICA;
    }

    /**
     * @param string $sql
     * @return string
     */
    private function getSqlType(string $sql)
    {
        /* Remove all line returns and trim the beginning and end of the sql string */
        $parts = explode(' ', trim(preg_replace('/\s\s+/', ' ', $sql)));
        $first_word = strtolower(current($parts));
        if ($first_word === 'select') {
            $output = self::SQL_TYPE_READ;
        } elseif ($first_word === self::SQL_TYPE_INSERT) {
            $output = self::SQL_TYPE_INSERT;
        } else {
            $output = self::SQL_TYPE_WRITE;
        }
        return $output;
    }
    //</editor-fold>
}
