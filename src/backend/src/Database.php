<?php

const OBJECT = 'OBJECT';

const OBJECT_K = 'OBJECT_K';

const ARRAY_A = 'ARRAY_A';

const ARRAY_N = 'ARRAY_N';

#[AllowDynamicProperties]
class db
{
    /**
     * Whether to show SQL/DB errors.
     *
     * Default is to show errors if both WP_DEBUG and WP_DEBUG_DISPLAY evaluate to true.
     *
     * @var bool
     */
    public $show_errors = false;

    /**
     * Whether to suppress errors during the DB bootstrapping. Default false.
     *
     * @var bool
     */
    public $suppress_errors = false;

    /**
     * The error encountered during the last query.
     *
     * @var string
     */
    public $last_error = '';

    /**
     * The number of queries made.
     *
     * @var int
     */
    public $num_queries = 0;

    /**
     * Count of rows returned by the last query.
     *
     * @var int
     */
    public $num_rows = 0;

    /**
     * Count of rows affected by the last query.
     *
     * @var int
     */
    public $rows_affected = 0;

    /**
     * The ID generated for an AUTO_INCREMENT column by the last query (usually INSERT).
     *
     * @var int
     */
    public $insert_id = 0;

    /**
     * The last query made.
     *
     * @var string
     */
    public $last_query;

    /**
     * Results of the last query.
     *
     * @var stdClass[]|null
     */
    public $last_result;

    /**
     * Database query result.
     *
     * Possible values:
     *
     * - `mysqli_result` instance for successful SELECT, SHOW, DESCRIBE, or EXPLAIN queries
     * - `true` for other query types that were successful
     * - `null` if a query is yet to be made or if the result has since been flushed
     * - `false` if the query returned an error
     *
     * @var mysqli_result|bool|null
     */
    protected $result;

    /**
     * Cached column info, for confidence checking data before inserting.
     *
     * @var array
     */
    protected $col_meta = array();

    /**
     * Calculated character sets keyed by table name.
     *
     * @var string[]
     */
    protected $table_charset = array();

    /**
     * Whether text fields in the current query need to be confidence checked.
     *
     * @var bool
     */
    protected $check_current_query = true;

    /**
     * Flag to ensure we don't run into recursion problems when checking the collation.
     *
     * @see db::check_safe_collation()
     * @var bool
     */
    private $checking_collation = false;

    /**
     * Saved info on the table column.
     *
     * @var array
     */
    protected $col_info;

    /**
     * The number of times to retry reconnecting before dying. Default 5.
     *
     * @see db::check_connection()
     * @var int
     */
    protected $reconnect_retries = 5;

    /**
     * Whether the database queries are ready to start executing.
     *
     * @var bool
     */
    public $ready = false;

    /**
     * Format specifiers for DB columns.
     *
     * Columns not listed here default to %s. Initialized during WP load.
     * Keys are column names, values are format types: 'ID' => '%d'.
     *
     * @see db::prepare()
     * @see db::insert()
     * @see db::update()
     * @see db::delete()
     * @var array
     */
    public $field_types = [];

    /**
     * Database table columns charset.
     *
     * @var string
     */
    public $charset;

    /**
     * Database table columns collate.
     *
     * @var string
     */
    public $collate;

    /**
     * Database Username.
     *
     * @var string
     */
    protected $dbuser;

    /**
     * Database Password.
     *
     * @var string
     */
    protected $dbpassword;

    /**
     * Database Name.
     *
     * @var string
     */
    protected $dbname;

    /**
     * Database Host.
     *
     * @var string
     */
    protected $dbhost;

    /**
     * Database handle.
     *
     * Possible values:
     *
     * - `mysqli` instance during normal operation
     * - `null` if the connection is yet to be made or has been closed
     * - `false` if the connection has failed
     *
     * @var mysqli|false|null
     */
    protected $dbh;

    /**
     * A textual description of the last query/get_row/get_var call.
     *
     * @var string
     */
    public $func_call;

    /**
     * Whether MySQL is used as the database engine.
     *
     * Set in db::db_connect() to true, by default. This is used when checking
     * against the required MySQL version for . Normally, a replacement
     * database drop-in (db.php) will skip these checks, but setting this to true
     * will force the checks to occur.
     *
     * @var bool
     */
    public $is_mysql = null;

    /**
     * Backward compatibility, where db::prepare() has not quoted formatted/argnum placeholders.
     *
     * This is often used for table/field names (before %i was supported), and sometimes string formatting, e.g.
     *
     *     $db->prepare( 'WHERE `%1$s` = "%2$s something %3$s" OR %1$s = "%4$-10s"', 'field_1', 'a', 'b', 'c' );
     *
     * But it's risky, e.g. forgetting to add quotes, resulting in SQL Injection vulnerabilities:
     *
     *     $db->prepare( 'WHERE (id = %1s) OR (id = %2$s)', $_GET['id'], $_GET['id'] ); // ?id=id
     *
     * This feature is preserved while plugin authors update their code to use safer approaches:
     *
     *     $_GET['key'] = 'a`b';
     *
     *     $db->prepare( 'WHERE %1s = %s',        $_GET['key'], $_GET['value'] ); // WHERE a`b = 'value'
     *     $db->prepare( 'WHERE `%1$s` = "%2$s"', $_GET['key'], $_GET['value'] ); // WHERE `a`b` = "value"
     *
     *     $db->prepare( 'WHERE %i = %s',         $_GET['key'], $_GET['value'] ); // WHERE `a``b` = 'value'
     *
     * While changing to false will be fine for queries not using formatted/argnum placeholders,
     * any remaining cases are most likely going to result in SQL errors (good, in a way):
     *
     *     $db->prepare( 'WHERE %1$s = "%2$-10s"', 'my_field', 'my_value' );
     *     true  = WHERE my_field = "my_value  "
     *     false = WHERE 'my_field' = "'my_value  '"
     *
     * But there may be some queries that result in an SQL Injection vulnerability:
     *
     *     $db->prepare( 'WHERE id = %1$s', $_GET['id'] ); // ?id=id
     *
     * So there may need to be a `_$this->doing_it_wrong()` phase, after we know everyone can use
     * identifier placeholders (%i), but before this feature is disabled or removed.
     * @var bool
     */
    private $allow_unsafe_unquoted_parameters = true;

    /**
     * Whether we've managed to successfully connect at some point.
     *
     * @var bool
     */
    private $has_connected = false;

    /**
     * The last SQL error that was encountered.
     *
     * @var Error|string
     */
    public $error = null;

    /**
     * Connects to the database server and selects a database.
     *
     * Does the actual setting up
     * of the class properties and connection to the database.
     *
     * @link https://core.trac..org/ticket/3354
     *
     * @param string $dbuser     Database user.
     * @param string $dbpassword Database password.
     * @param string $dbname     Database name.
     * @param string $dbhost     Database host.
     */
    public function __construct($dbuser, $dbpassword, $dbname, $dbhost)
    {
        $this->show_errors();

        $this->dbuser     = $dbuser;
        $this->dbpassword = $dbpassword;
        $this->dbname     = $dbname;
        $this->dbhost     = $dbhost;

        $this->db_connect();
    }

    /**
     * Sets $this->charset and $this->collate.
     *
     * @since 3.1.0
     */
    public function init_charset()
    {
        $charset = '';
        $collate = '';

        $charset_collate = $this->determine_charset($charset, $collate);

        $this->charset = $charset_collate['charset'];
        $this->collate = $charset_collate['collate'];
    }

    /**
     * Determines the best charset and collation to use given a charset and collation.
     *
     * For example, when able, utf8mb4 should be used instead of utf8.
     *
     * @since 4.6.0
     *
     * @param string $charset The character set to check.
     * @param string $collate The collation to check.
     * @return array {
     *     The most appropriate character set and collation to use.
     *
     *     @type string $charset Character set.
     *     @type string $collate Collation.
     * }
     */
    public function determine_charset($charset, $collate)
    {
        if ((! ($this->dbh instanceof mysqli)) || empty($this->dbh)) {
            return compact('charset', 'collate');
        }

        if ('utf8' === $charset) {
            $charset = 'utf8mb4';
        }

        if ('utf8mb4' === $charset) {
            // _general_ is outdated, so we can upgrade it to _unicode_, instead.
            $collate = (!$collate || 'utf8_general_ci' === $collate) ? 'utf8mb4_unicode_ci' : str_replace('utf8_', 'utf8mb4_', $collate);
        }

        // _unicode_520_ is a better collation, we should use that when it's available.
        if ($this->has_cap('utf8mb4_520') && 'utf8mb4_unicode_ci' === $collate) {
            $collate = 'utf8mb4_unicode_520_ci';
        }

        return compact('charset', 'collate');
    }

    /**
     * Sets the connection's character set.
     *
     * @param mysqli $dbh     The connection returned by `mysqli_connect()`.
     * @param string $charset Optional. The character set. Default null.
     * @param string $collate Optional. The collation. Default null.
     */
    public function set_charset($dbh, $charset = null, $collate = null)
    {
        if (! isset($charset)) {
            $charset = $this->charset;
        }
        if (! isset($collate)) {
            $collate = $this->collate;
        }
        if ($this->has_cap('collation') && ! empty($charset)) {
            $set_charset_succeeded = true;

            if (function_exists('mysqli_set_charset') && $this->has_cap('set_charset')) {
                $set_charset_succeeded = mysqli_set_charset($dbh, $charset);
            }

            if ($set_charset_succeeded) {
                $query = $this->prepare('SET NAMES %s', $charset);
                if (! empty($collate)) {
                    $query .= $this->prepare(' COLLATE %s', $collate);
                }
                mysqli_query($dbh, $query);
            }
        }
    }

    /**
     * Changes the current SQL mode, and ensures its  compatibility.
     *
     * If no modes are passed, it will ensure the current MySQL server modes are compatible.
     *
     * @param array $modes Optional. A list of SQL modes to set. Default empty array.
     */
    public function set_sql_mode($modes = array())
    {
        if (empty($modes)) {
            $res = mysqli_query($this->dbh, 'SELECT @@SESSION.sql_mode');

            if (empty($res)) {
                return;
            }

            $modes_array = mysqli_fetch_array($res);

            if (empty($modes_array[0])) {
                return;
            }

            $modes_str = $modes_array[0];

            if (empty($modes_str)) {
                return;
            }

            $modes = explode(',', $modes_str);
        }

        $modes = array_change_key_case($modes, CASE_UPPER);

        $modes_str = implode(',', $modes);

        mysqli_query($this->dbh, "SET SESSION sql_mode='$modes_str'");
    }








    /**
     * Selects a database using the current or provided database connection.
     *
     * The database name will be changed based on the current database connection.
     * On failure, the execution will bail and display a DB error.
     *
     * @param string $db  Database name.
     * @param mysqli $dbh Optional. Database connection.
     *                    Defaults to the current database handle.
     */
    public function select($db, $dbh = null)
    {
        if (is_null($dbh)) {
            $dbh = $this->dbh;
        }

        $success = mysqli_select_db($dbh, $db);

        if (! $success) {
            $this->ready = false;

            $message = "<h1>Cannot select database</h1>\n";

            $message .= '<p>' . sprintf(
                /* translators: %s: Database name. */
                'The database server could be connected to (which means your username and password is okay) but the %s database could not be selected.',
                '<code>' . htmlspecialchars($db, ENT_QUOTES) . '</code>'
            ) . "</p>\n";

            $message .= "<ul>\n";
            $message .= "<li>Are you sure it exists?</li>\n";

            $message .= '<li>' . sprintf(
                /* translators: 1: Database user, 2: Database name. */
                'Does the user %1$s have permission to use the %2$s database?',
                '<code>' . htmlspecialchars($this->dbuser, ENT_QUOTES) . '</code>',
                '<code>' . htmlspecialchars($db, ENT_QUOTES) . '</code>'
            ) . "</li>\n";

            $message .= '<li>' . sprintf(
                /* translators: %s: Database name. */
                'On some systems the name of your database is prefixed with your username, so it would be like <code>username_%1$s</code>. Could that be the problem?',
                htmlspecialchars($db, ENT_QUOTES)
            ) . "</li>\n";

            $message .= "</ul>\n";

            $message .= '<p>' . sprintf(
                /* translators: %s: Support forums URL. */
                'If you do not know how to set up a database you should <strong>contact your host</strong>. If all else fails you may find help at the <a href="%s"> support forums</a>.',
                'https://.org/support/forums/'
            ) . "</p>\n";

            $this->bail($message, 'db_select_fail');
        }
    }

    /**
     * Real escape using mysqli_real_escape_string().
     *
     * @see mysqli_real_escape_string()
     *
     * @param string $data String to escape.
     * @return string Escaped string.
     */
    public function _real_escape($data)
    {
        if (! is_scalar($data)) {
            return '';
        }

        if ($this->dbh) {
            $escaped = mysqli_real_escape_string($this->dbh, $data);
        } else {
            $class = get_class($this);

            /* translators: %s: Database access abstraction class, usually db or a class extending db. */
            $this->doing_it_wrong($class, sprintf('%s must set a database connection for use with escaping.', $class));

            $escaped = addslashes($data);
        }

        return $this->add_placeholder_escape($escaped);
    }



    /**
     * Escapes an identifier value without adding the surrounding quotes.
     *
     * - Permitted characters in quoted identifiers include the full Unicode
     *   Basic Multilingual Plane (BMP), except U+0000.
     * - To quote the identifier itself, you need to double the character, e.g. `a``b`.
     *
     * @link https://dev.mysql.com/doc/refman/8.0/en/identifiers.html
     *
     * @param string $identifier Identifier to escape.
     * @return string Escaped identifier.
     */
    private function _escape_identifier_value($identifier)
    {
        return str_replace('`', '``', $identifier);
    }

    /**
     * Prepares a SQL query for safe execution.
     *
     * Uses `sprintf()`-like syntax. The following placeholders can be used in the query string:
     *
     * - `%d` (integer)
     * - `%f` (float)
     * - `%s` (string)
     * - `%i` (identifier, e.g. table/field names)
     *
     * All placeholders MUST be left unquoted in the query string. A corresponding argument
     * MUST be passed for each placeholder.
     *
     * Note: There is one exception to the above: for compatibility with old behavior,
     * numbered or formatted string placeholders (eg, `%1$s`, `%5s`) will not have quotes
     * added by this function, so should be passed with appropriate quotes around them.
     *
     * Literal percentage signs (`%`) in the query string must be written as `%%`. Percentage wildcards
     * (for example, to use in LIKE syntax) must be passed via a substitution argument containing
     * the complete LIKE string, these cannot be inserted directly in the query string.
     * Also see db::esc_like().
     *
     * Arguments may be passed as individual arguments to the method, or as a single array
     * containing all arguments. A combination of the two is not supported.
     *
     * Examples:
     *
     *     $db->prepare(
     *         "SELECT * FROM `table` WHERE `column` = %s AND `field` = %d OR `other_field` LIKE %s",
     *         array( 'foo', 1337, '%bar' )
     *     );
     *
     *     $db->prepare(
     *         "SELECT DATE_FORMAT(`field`, '%%c') FROM `table` WHERE `column` = %s",
     *         'foo'
     *     );
     *
     * @link https://www.php.net/sprintf Description of syntax.
     *
     * @param string      $query   Query statement with `sprintf()`-like placeholders.
     * @param array|mixed $args    The array of variables to substitute into the query's placeholders
     *                             if being called with an array of arguments, or the first variable
     *                             to substitute into the query's placeholders if being called with
     *                             individual arguments.
     * @param mixed       ...$args Further variables to substitute into the query's placeholders
     *                             if being called with individual arguments.
     * @return string|void Sanitized query string, if there is a query to prepare.
     */
    public function prepare($query, ...$args)
    {
        if ($query === null) {
            return;
        }

        /*
         * This is not meant to be foolproof -- but it will catch obviously incorrect usage.
         *
         * Note: str_contains() is not used here, as this file can be included
         * directly outside of  core, e.g. by HyperDB, in which case
         * the polyfills from wp-includes/compat.php are not loaded.
         */
        if (false === strpos($query, '%')) {
            $this->doing_it_wrong(
                'db::prepare',
                sprintf(
                    /* translators: %s: db::prepare() */
                    'The query argument of %s must have a placeholder.',
                    'db::prepare()'
                ),
            );
        }

        /*
         * Specify the formatting allowed in a placeholder. The following are allowed:
         *
         * - Sign specifier, e.g. $+d
         * - Numbered placeholders, e.g. %1$s
         * - Padding specifier, including custom padding characters, e.g. %05s, %'#5s
         * - Alignment specifier, e.g. %05-s
         * - Precision specifier, e.g. %.2f
         */
        $allowed_format = '(?:[1-9][0-9]*[$])?[-+0-9]*(?: |0|\'.)?[-+0-9]*(?:\.[0-9]+)?';

        /*
         * If a %s placeholder already has quotes around it, removing the existing quotes
         * and re-inserting them ensures the quotes are consistent.
         *
         * For backward compatibility, this is only applied to %s, and not to placeholders like %1$s,
         * which are frequently used in the middle of longer strings, or as table name placeholders.
         */
        $query = str_replace("'%s'", '%s', $query); // Strip any existing single quotes.
        $query = str_replace('"%s"', '%s', $query); // Strip any existing double quotes.

        // Escape any unescaped percents (i.e. anything unrecognised).
        $query = preg_replace("/%(?:%|$|(?!($allowed_format)?[sdfFi]))/", '%%\\1', $query);

        // Extract placeholders from the query.
        $split_query = preg_split("/(^|[^%]|(?:%%)+)(%(?:$allowed_format)?[sdfFi])/", $query, -1, PREG_SPLIT_DELIM_CAPTURE);

        $split_query_count = count($split_query);

        /*
         * Split always returns with 1 value before the first placeholder (even with $query = "%s"),
         * then 3 additional values per placeholder.
         */
        $placeholder_count = (($split_query_count - 1) / 3);

        // If args were passed as an array, as in vsprintf(), move them up.
        $passed_as_array = isset($args[0]) && is_array($args[0]) && 1 === count($args);
        if ($passed_as_array) {
            $args = $args[0];
        }

        $new_query       = '';
        $key             = 2; // Keys 0 and 1 in $split_query contain values before the first placeholder.
        $arg_id          = 0;
        $arg_identifiers = [];
        $arg_strings     = [];

        while ($key < $split_query_count) {
            $placeholder = $split_query[ $key ];

            $format = substr($placeholder, 1, -1);
            $type   = substr($placeholder, -1);

            if ('f' === $type && true === $this->allow_unsafe_unquoted_parameters
                /*
                 * Note: str_ends_with() is not used here, as this file can be included
                 * directly outside of  core, e.g. by HyperDB, in which case
                 * the polyfills from wp-includes/compat.php are not loaded.
                 */
                && '%' === substr($split_query[ $key - 1 ], -1, 1)
            ) {

                /*
                 * Before WP 6.2 the "force floats to be locale-unaware" RegEx didn't
                 * convert "%%%f" to "%%%F" (note the uppercase F).
                 * This was because it didn't check to see if the leading "%" was escaped.
                 * And because the "Escape any unescaped percents" RegEx used "[sdF]" in its
                 * negative lookahead assertion, when there was an odd number of "%", it added
                 * an extra "%", to give the fully escaped "%%%%f" (not a placeholder).
                 */

                $s = $split_query[ $key - 2 ] . $split_query[ $key - 1 ];
                $k = 1;
                $l = strlen($s);
                while ($k <= $l && '%' === $s[ $l - $k ]) {
                    ++$k;
                }

                $placeholder = '%' . ($k % 2 ? '%' : '') . $format . $type;

                --$placeholder_count;

            } else {

                // Force floats to be locale-unaware.
                if ('f' === $type) {
                    $type        = 'F';
                    $placeholder = "%$format$type";
                }

                if ('i' === $type) {
                    $placeholder = '`%' . $format . 's`';
                    // Using a simple strpos() due to previous checking (e.g. $allowed_format).
                    $argnum_pos = strpos($format, '$');

                    $arg_identifiers[] = (false !== $argnum_pos) ? (((int) substr($format, 0, $argnum_pos)) - 1) : $arg_id;
                } elseif ('d' !== $type && 'F' !== $type) {
                    /*
                     * i.e. ( 's' === $type ), where 'd' and 'F' keeps $placeholder unchanged,
                     * and we ensure string escaping is used as a safe default (e.g. even if 'x').
                     */
                    $argnum_pos = strpos($format, '$');

                    $arg_strings[] = (false !== $argnum_pos) ? (((int) substr($format, 0, $argnum_pos)) - 1) : $arg_id;

                    /*
                     * Unquoted strings for backward compatibility (dangerous).
                     * First, "numbered or formatted string placeholders (eg, %1$s, %5s)".
                     * Second, if "%s" has a "%" before it, even if it's unrelated (e.g. "LIKE '%%%s%%'").
                     */
                    if (true !== $this->allow_unsafe_unquoted_parameters
                        /*
                         * Note: str_ends_with() is not used here, as this file can be included
                         * directly outside of  core, e.g. by HyperDB, in which case
                         * the polyfills from wp-includes/compat.php are not loaded.
                         */
                        || ('' === $format && '%' !== substr($split_query[ $key - 1 ], -1, 1))
                    ) {
                        $placeholder = "'%{$format}s'";
                    }
                }
            }

            // Glue (-2), any leading characters (-1), then the new $placeholder.
            $new_query .= $split_query[ $key - 2 ] . $split_query[ $key - 1 ] . $placeholder;

            $key += 3;
            ++$arg_id;
        }

        // Replace $query; and add remaining $query characters, or index 0 if there were no placeholders.
        $query = $new_query . $split_query[ $key - 2 ];

        $dual_use = array_intersect($arg_identifiers, $arg_strings);

        if (count($dual_use) > 0) {

            $used_placeholders = [];

            $key    = 2;
            $arg_id = 0;
            // Parse again (only used when there is an error).
            while ($key < $split_query_count) {
                $placeholder = $split_query[ $key ];

                $format = substr($placeholder, 1, -1);

                $argnum_pos = strpos($format, '$');

                $arg_pos = (false !== $argnum_pos) ? ((int) substr($format, 0, $argnum_pos)) - 1 : $arg_id;

                $used_placeholders[ $arg_pos ][] = $placeholder;

                $key += 3;
                ++$arg_id;
            }

            $conflicts = [];
            foreach ($dual_use as $arg_pos) {
                $conflicts[] = implode(' and ', $used_placeholders[ $arg_pos ]);
            }

            $this->doing_it_wrong(
                'db::prepare',
                sprintf(
                    /* translators: %s: A list of placeholders found to be a problem. */
                    'Arguments cannot be prepared as both an Identifier and Value. Found the following conflicts: %s',
                    implode(', ', $conflicts)
                ),
            );

            return;
        }

        $args_count = count($args);

        if ($args_count !== $placeholder_count) {
            if (1 === $placeholder_count && $passed_as_array) {
                /*
                 *       If the passed query only expected one argument,
                 * but the wrong number of arguments was sent as an array, bail.
                 */
                $this->doing_it_wrong(
                    'db::prepare',
                    'The query only expected one placeholder, but an array of multiple placeholders was sent.',
                );

                return;
            } else {
                /*
                 * If we don't have the right number of placeholders,
                 * but they were passed as individual arguments,
                 * or we were expecting multiple arguments in an array, throw a warning.
                 */
                $this->doing_it_wrong(
                    'db::prepare',
                    sprintf(
                        /* translators: 1: Number of placeholders, 2: Number of arguments passed. */
                        'The query does not contain the correct number of placeholders (%1$d) for the number of arguments passed (%2$d).',
                        $placeholder_count,
                        $args_count
                    ),
                );

                /*
                 * If we don't have enough arguments to match the placeholders,
                 * return an empty string to avoid a fatal error on PHP 8.
                 */
                if ($args_count < $placeholder_count) {
                    $max_numbered_placeholder = 0;

                    for ($i = 2, $l = $split_query_count; $i < $l; $i += 3) {
                        // Assume a leading number is for a numbered placeholder, e.g. '%3$s'.
                        $argnum = (int) substr($split_query[ $i ], 1);

                        if ($max_numbered_placeholder < $argnum) {
                            $max_numbered_placeholder = $argnum;
                        }
                    }

                    if (! $max_numbered_placeholder || $args_count < $max_numbered_placeholder) {
                        return '';
                    }
                }
            }
        }

        $args_escaped = [];

        foreach ($args as $i => $value) {
            if (in_array($i, $arg_identifiers, true)) {
                $args_escaped[] = $this->_escape_identifier_value($value);
            } elseif (is_int($value) || is_float($value)) {
                $args_escaped[] = $value;
            } else {
                if (! is_scalar($value) && ! is_null($value)) {
                    $this->doing_it_wrong(
                        'db::prepare',
                        sprintf(
                            /* translators: %s: Value type. */
                            'Unsupported value type (%s).',
                            gettype($value)
                        ),
                    );

                    // Preserving old behavior, where values are escaped as strings.
                    $value = '';
                }

                $args_escaped[] = $this->_real_escape($value);
            }
        }

        $query = vsprintf($query, $args_escaped);

        return $this->add_placeholder_escape($query);
    }

    /**
     * Prints SQL/DB error.
     *
     * @global array $EZSQL_ERROR Stores error information of query and error string.
     *
     * @param string $str The error to display.
     * @return void|false Void if the showing of errors is enabled, false if disabled.
     */
    public function print_error($str = '')
    {
        global $EZSQL_ERROR;

        if (! $str) {
            $str = mysqli_error($this->dbh);
        }

        $EZSQL_ERROR[] = [
            'query' => $this->last_query,
            'error_str' => $str,
        ];

        if ($this->suppress_errors) {
            return false;
        }

        $error_str = sprintf(' database error %1$s for query %2$s', $str, $this->last_query);

        error_log($error_str);

        // Are we showing errors?
        if (! $this->show_errors) {
            return false;
        }



        $str   = htmlspecialchars($str, ENT_QUOTES);
        $query = htmlspecialchars($this->last_query, ENT_QUOTES);

        printf(
            '<div id="error"><p class="dberror"><strong>%s</strong> [%s]<br /><code>%s</code></p></div>',
            ' database error:',
            $str,
            $query
        );
    }

    /**
     * Enables showing of database errors.
     *
     * This function should be used only to enable showing of errors.
     * db::hide_errors() should be used instead for hiding errors.
     *
     * @see db::hide_errors()
     *
     * @param bool $show Optional. Whether to show errors. Default true.
     * @return bool Whether showing of errors was previously active.
     */
    public function show_errors($show = true)
    {
        $errors            = $this->show_errors;
        $this->show_errors = $show;
        return $errors;
    }

    /**
     * Disables showing of database errors.
     *
     * By default database errors are not shown.
     *
     * @see db::show_errors()
     *
     * @return bool Whether showing of errors was previously active.
     */
    public function hide_errors()
    {
        $show              = $this->show_errors;
        $this->show_errors = false;
        return $show;
    }

    /**
     * Kills cached query results.
     */
    public function flush()
    {
        $this->last_result   = [];
        $this->col_info      = null;
        $this->last_query    = null;
        $this->rows_affected = 0;
        $this->num_rows      = 0;
        $this->last_error    = '';

        if ($this->result instanceof mysqli_result) {
            mysqli_free_result($this->result);
            $this->result = null;

            // Confidence check before using the handle.
            if (empty($this->dbh) || ! ($this->dbh instanceof mysqli)) {
                return;
            }

            // Clear out any results from a multi-query.
            while (mysqli_more_results($this->dbh)) {
                mysqli_next_result($this->dbh);
            }
        }
    }

    /**
     * Connects to and selects database.
     *
     * If `$allow_bail` is false, the lack of database connection will need to be handled manually.
     *
     * @param bool $allow_bail Optional. Allows the function to bail. Default true.
     * @return bool True with a successful connection, false on failure.
     */
    public function db_connect($allow_bail = true)
    {
        $this->is_mysql = true;

        $client_flags = 0;

        /*
         * Set the MySQLi error reporting off because  handles its own.
         * This is due to the default value change from `MYSQLI_REPORT_OFF`
         * to `MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT` in PHP 8.1.
         */
        mysqli_report(MYSQLI_REPORT_OFF);

        $this->dbh = mysqli_init();

        $host    = $this->dbhost;
        $port    = null;
        $socket  = null;
        $is_ipv6 = false;

        $host_data = $this->parse_db_host($this->dbhost);
        if ($host_data) {
            [$host, $port, $socket, $is_ipv6] = $host_data;
        }

        /*
         * If using the `mysqlnd` library, the IPv6 address needs to be enclosed
         * in square brackets, whereas it doesn't while using the `libmysqlclient` library.
         * @see https://bugs.php.net/bug.php?id=67563
         */
        if ($is_ipv6 && extension_loaded('mysqlnd')) {
            $host = "[$host]";
        }

        mysqli_real_connect($this->dbh, $host, $this->dbuser, $this->dbpassword, null, $port, $socket, $client_flags);

        if ($this->dbh->connect_errno) {
            $this->dbh = null;
        }

        if (! $this->dbh && $allow_bail) {


            $message = "<h1>Error establishing a database connection</h1>\n";

            $message .= '<p>' . sprintf(
                'This either means that the username and password information in your %1$s file is incorrect or that contact with the database server at %2$s could not be established. This could mean your host&#8217;s database server is down.',
                '<code>wp-config.php</code>',
                '<code>' . htmlspecialchars($this->dbhost, ENT_QUOTES) . '</code>'
            ) . "</p>\n";

            $message .= "<ul>\n";
            $message .= "<li>Are you sure you have the correct username and password?</li>\n";
            $message .= "<li>Are you sure you have typed the correct hostname?</li>\n";
            $message .= "<li>Are you sure the database server is running?</li>\n";
            $message .= "</ul>\n";

            $message .= '<p>' . sprintf(
                /* translators: %s: Support forums URL. */
                'If you are unsure what these terms mean you should probably contact your host. If you still need help you can always visit the <a href="%s"> support forums</a>.',
                'https://.org/support/forums/'
            ) . "</p>\n";

            $this->bail($message, 'db_connect_fail');

            return false;
        } elseif ($this->dbh) {
            if (! $this->has_connected) {
                $this->init_charset();
            }

            $this->has_connected = true;

            $this->set_charset($this->dbh);

            $this->ready = true;
            $this->set_sql_mode();
            $this->select($this->dbname, $this->dbh);

            return true;
        }

        return false;
    }

    /**
     * Parses the DB_HOST setting to interpret it for mysqli_real_connect().
     *
     * mysqli_real_connect() doesn't support the host param including a port or socket
     * like mysql_connect() does. This duplicates how mysql_connect() detects a port
     * and/or socket file.
     *
     * @param string $host The DB_HOST setting to parse.
     * @return array|false {
     *     Array containing the host, the port, the socket and
     *     whether it is an IPv6 address, in that order.
     *     False if the host couldn't be parsed.
     *
     *     @type string      $0 Host name.
     *     @type string|null $1 Port.
     *     @type string|null $2 Socket.
     *     @type bool        $3 Whether it is an IPv6 address.
     * }
     */
    public function parse_db_host($host)
    {
        $socket  = null;
        $is_ipv6 = false;

        // First peel off the socket parameter from the right, if it exists.
        $socket_pos = strpos($host, ':/');
        if (false !== $socket_pos) {
            $socket = substr($host, $socket_pos + 1);
            $host   = substr($host, 0, $socket_pos);
        }

        /*
         * We need to check for an IPv6 address first.
         * An IPv6 address will always contain at least two colons.
         */
        if (substr_count($host, ':') > 1) {
            $pattern = '#^(?:\[)?(?P<host>[0-9a-fA-F:]+)(?:\]:(?P<port>[\d]+))?#';
            $is_ipv6 = true;
        } else {
            // We seem to be dealing with an IPv4 address.
            $pattern = '#^(?P<host>[^:/]*)(?::(?P<port>[\d]+))?#';
        }

        $matches = [];
        $result  = preg_match($pattern, $host, $matches);

        if (1 !== $result) {
            // Couldn't parse the address, bail.
            return false;
        }

        $host = ! empty($matches['host']) ? $matches['host'] : '';
        // MySQLi port cannot be a string; must be null or an integer.
        $port = ! empty($matches['port']) ? abs((int)$matches['port']) : null;

        return [$host, $port, $socket, $is_ipv6];
    }

    /**
     * Checks that the connection to the database is still up. If not, try to reconnect.
     *
     * If this function is unable to reconnect, it will forcibly die, or if called
     * after the {@see 'template_redirect'} hook has been fired, return false instead.
     *
     * If `$allow_bail` is false, the lack of database connection will need to be handled manually.
     *
     * @param bool $allow_bail Optional. Allows the function to bail. Default true.
     * @return bool|void True if the connection is up.
     */
    public function check_connection($allow_bail = true)
    {
        // Check if the connection is alive.
        if (! empty($this->dbh) && mysqli_query($this->dbh, 'DO 1') !== false) {
            return true;
        }

        $error_reporting = false;

        $error_reporting = error_reporting();
        error_reporting($error_reporting & ~E_WARNING);

        for ($tries = 1; $tries <= $this->reconnect_retries; $tries++) {
            /*
             * On the last try, re-enable warnings. We want to see a single instance
             * of the "unable to connect" message on the bail() screen, if it appears.
             */
            if ($this->reconnect_retries === $tries) {
                error_reporting($error_reporting);
            }

            if ($this->db_connect(false)) {
                if ($error_reporting) {
                    error_reporting($error_reporting);
                }

                return true;
            }

            sleep(1);
        }



        if (! $allow_bail) {
            return false;
        }
        $message = "<h1>Error reconnecting to the database</h1>\n";

        $message .= '<p>' . sprintf(
            /* translators: %s: Database host. */
            'This means that the contact with the database server at %s was lost. This could mean your host&#8217;s database server is down.',
            '<code>' . htmlspecialchars($this->dbhost, ENT_QUOTES) . '</code>'
        ) . "</p>\n";

        $message .= "<ul>\n";
        $message .= "<li>Are you sure the database server is running?</li>\n";
        $message .= "<li>Are you sure the database server is not under particularly heavy load?</li>\n";
        $message .= "</ul>\n";

        $message .= '<p>' . sprintf(
            /* translators: %s: Support forums URL. */
            'If you are unsure what these terms mean you should probably contact your host. If you still need help you can always visit the <a href="%s"> support forums</a>.',
            'https://.org/support/forums/'
        ) . "</p>\n";

        // We weren't able to reconnect, so we better bail.
        $this->bail($message, 'db_connect_fail');
    }

    /**
     * Performs a database query, using current database connection.
     *
     * More information can be found on the documentation page.
     *
     * @param string $query Database query.
     * @return int|bool Boolean true for CREATE, ALTER, TRUNCATE and DROP queries. Number of rows
     *                  affected/selected for all other queries. Boolean false on error.
     */
    public function query($query)
    {
        if (! $this->ready) {
            $this->check_current_query = true;
            return false;
        }



        if (! $query) {
            $this->insert_id = 0;
            return false;
        }

        $this->flush();

        // Log how the function was called.
        $this->func_call = "\$db->query(\"$query\")";

        // If we're writing to the database, make sure the query will write safely.
        if ($this->check_current_query && ! $this->check_ascii($query)) {
            $stripped_query = $this->strip_invalid_text_from_query($query);
            /*
             * strip_invalid_text_from_query() can perform queries, so we need
             * to flush again, just to make sure everything is clear.
             */
            $this->flush();
            if ($stripped_query !== $query) {
                $this->insert_id  = 0;
                $this->last_query = $query;


                $this->last_error = ' database error: Could not perform query because it contains invalid data.';

                return false;
            }
        }

        $this->check_current_query = true;

        // Keep track of the last query for debug.
        $this->last_query = $query;

        $this->_do_query($query);

        // Database server has gone away, try to reconnect.
        $mysql_errno = 0;

        $mysql_errno = ($this->dbh instanceof mysqli) ? mysqli_errno($this->dbh) : 2006;

        if (empty($this->dbh) || 2006 === $mysql_errno) {
            if ($this->check_connection()) {
                $this->_do_query($query);
            } else {
                $this->insert_id = 0;
                return false;
            }
        }

        // If there is an error then take note of it.
        $this->last_error = ($this->dbh instanceof mysqli) ? mysqli_error($this->dbh) : 'Unable to retrieve the error message from MySQL';

        if ($this->last_error) {
            // Clear insert_id on a subsequent failed insert.
            if ($this->insert_id && preg_match('/^\s*(insert|replace)\s/i', $query)) {
                $this->insert_id = 0;
            }

            $this->print_error();
            return false;
        }

        if (preg_match('/^\s*(create|alter|truncate|drop)\s/i', $query)) {
            $return_val = $this->result;
        } elseif (preg_match('/^\s*(insert|delete|update|replace)\s/i', $query)) {
            $this->rows_affected = mysqli_affected_rows($this->dbh);

            // Take note of the insert_id.
            if (preg_match('/^\s*(insert|replace)\s/i', $query)) {
                $this->insert_id = mysqli_insert_id($this->dbh);
            }

            // Return number of rows affected.
            $return_val = $this->rows_affected;
        } else {
            $num_rows = 0;

            if ($this->result instanceof mysqli_result) {
                while ($row = mysqli_fetch_object($this->result)) {
                    $this->last_result[ $num_rows ] = $row;
                    ++$num_rows;
                }
            }

            // Log and return the number of rows selected.
            $this->num_rows = $num_rows;
            $return_val     = $num_rows;
        }

        return $return_val;
    }

    /**
     * Internal function to perform the mysqli_query() call.
     *
     * @see db::query()
     *
     * @param string $query The query to run.
     */
    private function _do_query($query)
    {

        if (! empty($this->dbh)) {
            $this->result = mysqli_query($this->dbh, $query);
        }

        ++$this->num_queries;

    }


    /**
     * Generates and returns a placeholder escape string for use in queries returned by ::prepare().
     *
     * @return string String to escape placeholders.
     */
    public function placeholder_escape()
    {
        static $placeholder;

        if (! $placeholder) {
            // If ext/hash is not present, compat.php's hash_hmac() does not support sha256.
            $algo = function_exists('hash') ? 'sha256' : 'sha1';
            // Old WP installs may not have AUTH_SALT defined.
            $salt = (string) rand();

            $placeholder = '{' . hash_hmac($algo, uniqid($salt, true), $salt) . '}';
        }

        return $placeholder;
    }

    /**
     * Adds a placeholder escape string, to escape anything that resembles a printf() placeholder.
     *
     * @param string $query The query to escape.
     * @return string The query with the placeholder escape string inserted where necessary.
     */
    public function add_placeholder_escape($query)
    {
        /*
         * To prevent returning anything that even vaguely resembles a placeholder,
         * we clobber every % we can find.
         */
        return str_replace('%', $this->placeholder_escape(), $query);
    }

    /**
     * Inserts a row into the table.
     *
     * Examples:
     *
     *     $db->insert(
     *         'table',
     *         array(
     *             'column1' => 'foo',
     *             'column2' => 'bar',
     *         )
     *     );
     *     $db->insert(
     *         'table',
     *         array(
     *             'column1' => 'foo',
     *             'column2' => 1337,
     *         ),
     *         array(
     *             '%s',
     *             '%d',
     *         )
     *     );
     *
     * @see db::prepare()
     * @see db::$field_types
     *
     * @param string          $table  Table name.
     * @param array           $data   Data to insert (in column => value pairs).
     *                                Both `$data` columns and `$data` values should be "raw" (neither should be SQL escaped).
     *                                Sending a null value will cause the column to be set to NULL - the corresponding
     *                                format is ignored in this case.
     * @param string[]|string $format Optional. An array of formats to be mapped to each of the value in `$data`.
     *                                If string, that format will be used for all of the values in `$data`.
     *                                A format is one of '%d', '%f', '%s' (integer, float, string).
     *                                If omitted, all values in `$data` will be treated as strings unless otherwise
     *                                specified in db::$field_types. Default null.
     * @return int|false The number of rows inserted, or false on error.
     */
    public function insert($table, $data, $format = null)
    {
        return $this->_insert_replace_helper($table, $data, $format, 'INSERT');
    }

    /**
     * Helper function for insert and replace.
     *
     * Runs an insert or replace query based on `$type` argument.
     *
     * @see db::prepare()
     * @see db::$field_types
     *
     * @param string          $table  Table name.
     * @param array           $data   Data to insert (in column => value pairs).
     *                                Both `$data` columns and `$data` values should be "raw" (neither should be SQL escaped).
     *                                Sending a null value will cause the column to be set to NULL - the corresponding
     *                                format is ignored in this case.
     * @param string[]|string $format Optional. An array of formats to be mapped to each of the value in `$data`.
     *                                If string, that format will be used for all of the values in `$data`.
     *                                A format is one of '%d', '%f', '%s' (integer, float, string).
     *                                If omitted, all values in `$data` will be treated as strings unless otherwise
     *                                specified in db::$field_types. Default null.
     * @param string          $type   Optional. Type of operation. Either 'INSERT' or 'REPLACE'.
     *                                Default 'INSERT'.
     * @return int|false The number of rows affected, or false on error.
     */
    public function _insert_replace_helper($table, $data, $format = null, $type = 'INSERT')
    {
        $this->insert_id = 0;

        if (! in_array(strtoupper($type), array( 'REPLACE', 'INSERT' ), true)) {
            return false;
        }

        $data = $this->process_fields($table, $data, $format);
        if (false === $data) {
            return false;
        }

        $formats = [];
        $values  = [];
        foreach ($data as $value) {
            if ($value['value'] === null) {
                $formats[] = 'NULL';
                continue;
            }

            $formats[] = $value['format'];
            $values[]  = $value['value'];
        }

        $fields  = '`' . implode('`, `', array_keys($data)) . '`';
        $formats = implode(', ', $formats);

        $sql = "$type INTO `$table` ($fields) VALUES ($formats)";

        $this->check_current_query = false;
        return $this->query($this->prepare($sql, $values));
    }

    /**
     * Updates a row in the table.
     *
     * Examples:
     *
     *     $db->update(
     *         'table',
     *         array(
     *             'column1' => 'foo',
     *             'column2' => 'bar',
     *         ),
     *         array(
     *             'ID' => 1,
     *         )
     *     );
     *     $db->update(
     *         'table',
     *         array(
     *             'column1' => 'foo',
     *             'column2' => 1337,
     *         ),
     *         array(
     *             'ID' => 1,
     *         ),
     *         array(
     *             '%s',
     *             '%d',
     *         ),
     *         array(
     *             '%d',
     *         )
     *     );
     *
     * @see db::prepare()
     * @see db::$field_types
     *
     * @param string       $table           Table name.
     * @param array        $data            Data to update (in column => value pairs).
     *                                      Both $data columns and $data values should be "raw" (neither should be SQL escaped).
     *                                      Sending a null value will cause the column to be set to NULL - the corresponding
     *                                      format is ignored in this case.
     * @param array        $where           A named array of WHERE clauses (in column => value pairs).
     *                                      Multiple clauses will be joined with ANDs.
     *                                      Both $where columns and $where values should be "raw".
     *                                      Sending a null value will create an IS NULL comparison - the corresponding
     *                                      format will be ignored in this case.
     * @param string[]|string $format       Optional. An array of formats to be mapped to each of the values in $data.
     *                                      If string, that format will be used for all of the values in $data.
     *                                      A format is one of '%d', '%f', '%s' (integer, float, string).
     *                                      If omitted, all values in $data will be treated as strings unless otherwise
     *                                      specified in db::$field_types. Default null.
     * @param string[]|string $where_format Optional. An array of formats to be mapped to each of the values in $where.
     *                                      If string, that format will be used for all of the items in $where.
     *                                      A format is one of '%d', '%f', '%s' (integer, float, string).
     *                                      If omitted, all values in $where will be treated as strings unless otherwise
     *                                      specified in db::$field_types. Default null.
     * @return int|false The number of rows updated, or false on error.
     */
    public function update($table, $data, $where, $format = null, $where_format = null)
    {
        if (! is_array($data) || ! is_array($where)) {
            return false;
        }

        $data = $this->process_fields($table, $data, $format);
        if (false === $data) {
            return false;
        }
        $where = $this->process_fields($table, $where, $where_format);
        if (false === $where) {
            return false;
        }

        $fields     = [];
        $conditions = [];
        $values     = [];
        foreach ($data as $field => $value) {
            if ($value['value'] === null) {
                $fields[] = "`$field` = NULL";
                continue;
            }

            $fields[] = "`$field` = " . $value['format'];
            $values[] = $value['value'];
        }
        foreach ($where as $field => $value) {
            if ($value['value'] === null) {
                $conditions[] = "`$field` IS NULL";
                continue;
            }

            $conditions[] = "`$field` = " . $value['format'];
            $values[]     = $value['value'];
        }

        $fields     = implode(', ', $fields);
        $conditions = implode(' AND ', $conditions);

        $sql = "UPDATE `$table` SET $fields WHERE $conditions";

        $this->check_current_query = false;
        return $this->query($this->prepare($sql, $values));
    }

    /**
     * Deletes a row in the table.
     *
     * Examples:
     *
     *     $db->delete(
     *         'table',
     *         array(
     *             'ID' => 1,
     *         )
     *     );
     *     $db->delete(
     *         'table',
     *         array(
     *             'ID' => 1,
     *         ),
     *         array(
     *             '%d',
     *         )
     *     );
     *
     * @see db::prepare()
     * @see db::$field_types
     *
     * @param string          $table        Table name.
     * @param array           $where        A named array of WHERE clauses (in column => value pairs).
     *                                      Multiple clauses will be joined with ANDs.
     *                                      Both $where columns and $where values should be "raw".
     *                                      Sending a null value will create an IS NULL comparison - the corresponding
     *                                      format will be ignored in this case.
     * @param string[]|string $where_format Optional. An array of formats to be mapped to each of the values in $where.
     *                                      If string, that format will be used for all of the items in $where.
     *                                      A format is one of '%d', '%f', '%s' (integer, float, string).
     *                                      If omitted, all values in $data will be treated as strings unless otherwise
     *                                      specified in db::$field_types. Default null.
     * @return int|false The number of rows deleted, or false on error.
     */
    public function delete($table, $where, $where_format = null)
    {
        if (! is_array($where)) {
            return false;
        }

        $where = $this->process_fields($table, $where, $where_format);
        if (false === $where) {
            return false;
        }

        $conditions = [];
        $values     = [];
        foreach ($where as $field => $value) {
            if ($value['value'] === null) {
                $conditions[] = "`$field` IS NULL";
                continue;
            }

            $conditions[] = "`$field` = " . $value['format'];
            $values[]     = $value['value'];
        }

        $conditions = implode(' AND ', $conditions);

        $sql = "DELETE FROM `$table` WHERE $conditions";

        $this->check_current_query = false;
        return $this->query($this->prepare($sql, $values));
    }

    /**
     * Processes arrays of field/value pairs and field formats.
     *
     * This is a helper method for db's CRUD methods, which take field/value pairs
     * for inserts, updates, and where clauses. This method first pairs each value
     * with a format. Then it determines the charset of that field, using that
     * to determine if any invalid text would be stripped. If text is stripped,
     * then field processing is rejected and the query fails.
     *
     * @param string          $table  Table name.
     * @param array           $data   Array of values keyed by their field names.
     * @param string[]|string $format Formats or format to be mapped to the values in the data.
     * @return array|false An array of fields that contain paired value and formats.
     *                     False for invalid values.
     */
    protected function process_fields($table, $data, $format)
    {
        $data = $this->process_field_formats($data, $format);
        if (false === $data) {
            return false;
        }

        $data = $this->process_field_charsets($data, $table);
        if (false === $data) {
            return false;
        }

        $data = $this->process_field_lengths($data, $table);
        if (false === $data) {
            return false;
        }

        $converted_data = $this->strip_invalid_text($data);

        if ($data !== $converted_data) {

            $problem_fields = array();
            foreach ($data as $field => $value) {
                if ($value !== $converted_data[ $field ]) {
                    $problem_fields[] = $field;
                }
            }


            $this->last_error = (1 === count($problem_fields)) ? sprintf(
                /* translators: %s: Database field where the error occurred. */
                ' Database error: Processing the value for the following field failed: %s. The supplied value may be too long or contains invalid data.',
                reset($problem_fields)
            ) : sprintf(
                /* translators: %s: Database fields where the error occurred. */
                ' Database error: Processing the values for the following fields failed: %s. The supplied values may be too long or contain invalid data.',
                implode(', ', $problem_fields)
            );

            return false;
        }

        return $data;
    }

    /**
     * Prepares arrays of value/format pairs as passed to db CRUD methods.
     *
     * @param array           $data   Array of values keyed by their field names.
     * @param string[]|string $format Formats or format to be mapped to the values in the data.
     * @return array {
     *     Array of values and formats keyed by their field names.
     *
     *     @type mixed  $value  The value to be formatted.
     *     @type string $format The format to be mapped to the value.
     * }
     */
    protected function process_field_formats($data, $format)
    {
        $formats          = (array) $format;
        $original_formats = $formats;

        foreach ($data as $field => $value) {
            $value = [
                'value' => $value,
                'format' => '%s',
            ];

            if (! empty($format)) {
                $value['format'] = array_shift($formats);
                if (! $value['format']) {
                    $value['format'] = reset($original_formats);
                }
            } elseif (isset($this->field_types[ $field ])) {
                $value['format'] = $this->field_types[ $field ];
            }

            $data[ $field ] = $value;
        }

        return $data;
    }

    /**
     * Adds field charsets to field/value/format arrays generated by db::process_field_formats().
     *
     * @param array $data {
     *     Array of values and formats keyed by their field names,
     *     as it comes from the db::process_field_formats() method.
     *
     *     @type array ...$0 {
     *         Value and format for this field.
     *
     *         @type mixed  $value  The value to be formatted.
     *         @type string $format The format to be mapped to the value.
     *     }
     * }
     * @param string $table Table name.
     * @return array|false {
     *     The same array of data with additional 'charset' keys, or false if
     *     the charset for the table cannot be found.
     *
     *     @type array ...$0 {
     *         Value, format, and charset for this field.
     *
     *         @type mixed        $value   The value to be formatted.
     *         @type string       $format  The format to be mapped to the value.
     *         @type string|false $charset The charset to be used for the value.
     *     }
     * }
     */
    protected function process_field_charsets($data, $table)
    {
        foreach ($data as $field => $value) {
            if ('%d' === $value['format'] || '%f' === $value['format']) {
                /*
                 * We can skip this field if we know it isn't a string.
                 * This checks %d/%f versus ! %s because its sprintf() could take more.
                 */
                $value['charset'] = false;
            } else {
                $value['charset'] = $this->get_col_charset($table, $field);
                if ($this->is_error($value['charset'])) {
                    return false;
                }
            }

            $data[ $field ] = $value;
        }

        return $data;
    }

    protected function is_error($var): bool
{
    return is_object($var) && $var instanceof Error;
}

    /**
     * For string fields, records the maximum string length that field can safely save.
     *
     * @param array $data {
     *     Array of values, formats, and charsets keyed by their field names,
     *     as it comes from the db::process_field_charsets() method.
     *
     *     @type array ...$0 {
     *         Value, format, and charset for this field.
     *
     *         @type mixed        $value   The value to be formatted.
     *         @type string       $format  The format to be mapped to the value.
     *         @type string|false $charset The charset to be used for the value.
     *     }
     * }
     * @param string $table Table name.
     * @return array|false {
     *     The same array of data with additional 'length' keys, or false if
     *     information for the table cannot be found.
     *
     *     @type array ...$0 {
     *         Value, format, charset, and length for this field.
     *
     *         @type mixed        $value   The value to be formatted.
     *         @type string       $format  The format to be mapped to the value.
     *         @type string|false $charset The charset to be used for the value.
     *         @type array|false  $length  {
     *             Information about the maximum length of the value.
     *             False if the column has no length.
     *
     *             @type string $type   One of 'byte' or 'char'.
     *             @type int    $length The column length.
     *         }
     *     }
     * }
     */
    protected function process_field_lengths($data, $table)
    {
        foreach ($data as $field => $value) {
            if ('%d' === $value['format'] || '%f' === $value['format']) {
                /*
                 * We can skip this field if we know it isn't a string.
                 * This checks %d/%f versus ! %s because its sprintf() could take more.
                 */
                $value['length'] = false;
            } else {
                $value['length'] = $this->get_col_length($table, $field);
                if ($this->is_error($value['length'])) {
                    return false;
                }
            }

            $data[ $field ] = $value;
        }

        return $data;
    }

    /**
     * Retrieves one value from the database.
     *
     * Executes a SQL query and returns the value from the SQL result.
     * If the SQL result contains more than one column and/or more than one row,
     * the value in the column and row specified is returned. If $query is null,
     * the value in the specified column and row from the previous SQL result is returned.
     *
     * @param string|null $query Optional. SQL query. Defaults to null, use the result from the previous query.
     * @param int         $x     Optional. Column of value to return. Indexed from 0. Default 0.
     * @param int         $y     Optional. Row of value to return. Indexed from 0. Default 0.
     * @return string|null Database query result (as string), or null on failure.
     */
    public function get_var($query = null, $x = 0, $y = 0)
    {
        $this->func_call = "\$db->get_var(\"$query\", $x, $y)";

        if ($query) {
            if ($this->check_current_query && $this->check_safe_collation($query)) {
                $this->check_current_query = false;
            }

            $this->query($query);
        }

        // Extract var out of cached results based on x,y vals.
        if (! empty($this->last_result[ $y ])) {
            $values = array_values(get_object_vars($this->last_result[ $y ]));
        }

        // If there is a value return it, else return null.
        return (isset($values[ $x ]) && '' !== $values[ $x ]) ? $values[ $x ] : null;
    }

    /**
     * Retrieves one row from the database.
     *
     * Executes a SQL query and returns the row from the SQL result.
     *
     * @param string|null $query  SQL query.
     * @param string      $output Optional. The required return type. One of OBJECT, ARRAY_A, or ARRAY_N, which
     *                            correspond to an stdClass object, an associative array, or a numeric array,
     *                            respectively. Default OBJECT.
     * @param int         $y      Optional. Row to return. Indexed from 0. Default 0.
     * @return array|object|null|void Database query result in format specified by $output or null on failure.
     */
    public function get_row($query = null, $output = OBJECT, $y = 0)
    {
        $this->func_call = "\$db->get_row(\"$query\",$output,$y)";

        if ($query) {
            if ($this->check_current_query && $this->check_safe_collation($query)) {
                $this->check_current_query = false;
            }

            $this->query($query);
        } else {
            return null;
        }

        if (! isset($this->last_result[ $y ])) {
            return null;
        }

        if (OBJECT === $output) {
            return $this->last_result[$y] ?: null;
        } elseif (ARRAY_A === $output) {
            return $this->last_result[ $y ] ? get_object_vars($this->last_result[ $y ]) : null;
        } elseif (ARRAY_N === $output) {
            return $this->last_result[ $y ] ? array_values(get_object_vars($this->last_result[ $y ])) : null;
        } elseif (OBJECT === strtoupper($output)) {
            // Back compat for OBJECT being previously case-insensitive.
            return $this->last_result[$y] ?: null;
        } else {
            $this->print_error(' $db->get_row(string query, output type, int offset) -- Output type must be one of: OBJECT, ARRAY_A, ARRAY_N');
        }
    }

    /**
     * Retrieves one column from the database.
     *
     * Executes a SQL query and returns the column from the SQL result.
     * If the SQL result contains more than one column, the column specified is returned.
     * If $query is null, the specified column from the previous SQL result is returned.
     *
     * @param string|null $query Optional. SQL query. Defaults to previous query.
     * @param int         $x     Optional. Column to return. Indexed from 0. Default 0.
     * @return array Database query result. Array indexed from 0 by SQL result row number.
     */
    public function get_col($query = null, $x = 0)
    {
        if ($query) {
            if ($this->check_current_query && $this->check_safe_collation($query)) {
                $this->check_current_query = false;
            }

            $this->query($query);
        }

        $new_array = [];
        // Extract the column values.
        if ($this->last_result) {
            for ($i = 0, $j = count($this->last_result); $i < $j; $i++) {
                $new_array[ $i ] = $this->get_var(null, $x, $i);
            }
        }
        return $new_array;
    }

    /**
     * Retrieves an entire SQL result set from the database (i.e., many rows).
     *
     * Executes a SQL query and returns the entire SQL result.
     *
     * @param string $query  SQL query.
     * @param string $output Optional. Any of ARRAY_A | ARRAY_N | OBJECT | OBJECT_K constants.
     *                       With one of the first three, return an array of rows indexed
     *                       from 0 by SQL result row number. Each row is an associative array
     *                       (column => value, ...), a numerically indexed array (0 => value, ...),
     *                       or an object ( ->column = value ), respectively. With OBJECT_K,
     *                       return an associative array of row objects keyed by the value
     *                       of each row's first column's value. Duplicate keys are discarded.
     *                       Default OBJECT.
     * @return array|object|null Database query results.
     */
    public function get_results($query = null, $output = OBJECT)
    {
        $this->func_call = "\$db->get_results(\"$query\", $output)";

        if ($query) {
            if ($this->check_current_query && $this->check_safe_collation($query)) {
                $this->check_current_query = false;
            }

            $this->query($query);
        } else {
            return null;
        }

        $new_array = [];
        if (OBJECT === $output) {
            // Return an integer-keyed array of row objects.
            return $this->last_result;
        } elseif (OBJECT_K === $output) {
            /*
             * Return an array of row objects with keys from column 1.
             * (Duplicates are discarded.)
             */
            if ($this->last_result) {
                foreach ($this->last_result as $row) {
                    $var_by_ref = get_object_vars($row);
                    $key        = array_shift($var_by_ref);
                    if (! isset($new_array[ $key ])) {
                        $new_array[ $key ] = $row;
                    }
                }
            }
            return $new_array;
        } elseif (ARRAY_A === $output || ARRAY_N === $output) {
            // Return an integer-keyed array of...
            if ($this->last_result) {
                if (ARRAY_N === $output) {
                    foreach ((array) $this->last_result as $row) {
                        // ...integer-keyed row arrays.
                        $new_array[] = array_values(get_object_vars($row));
                    }
                } else {
                    foreach ((array) $this->last_result as $row) {
                        // ...column name-keyed row arrays.
                        $new_array[] = get_object_vars($row);
                    }
                }
            }
            return $new_array;
        } elseif (strtoupper($output) === OBJECT) {
            // Back compat for OBJECT being previously case-insensitive.
            return $this->last_result;
        }
        return null;
    }

    /**
     * Retrieves the character set for the given table.
     *
     * @param string $table Table name.
     * @return string|Error Table character set, WP_Error object if it couldn't be found.
     */
    protected function get_table_charset($table)
    {
        $tablekey = strtolower($table);


        if (isset($this->table_charset[ $tablekey ])) {
            return $this->table_charset[ $tablekey ];
        }

        $charsets = array();
        $columns  = array();

        $table_parts = explode('.', $table);
        $table       = '`' . implode('`.`', $table_parts) . '`';
        $results     = $this->get_results("SHOW FULL COLUMNS FROM $table");
        if (! $results) {
            return new Error('Could not retrieve table charset.');
        }

        foreach ($results as $column) {
            $columns[ strtolower($column->Field) ] = $column;
        }

        $this->col_meta[ $tablekey ] = $columns;

        foreach ($columns as $column) {
            if (! empty($column->Collation)) {
                [$charset] = explode('_', $column->Collation);

                $charsets[ strtolower($charset) ] = true;
            }

            [$type] = explode('(', $column->Type);

            // A binary/blob means the whole query gets treated like this.
            if (in_array(strtoupper($type), array( 'BINARY', 'VARBINARY', 'TINYBLOB', 'MEDIUMBLOB', 'BLOB', 'LONGBLOB' ), true)) {
                $this->table_charset[ $tablekey ] = 'binary';
                return 'binary';
            }
        }

        // utf8mb3 is an alias for utf8.
        if (isset($charsets['utf8mb3'])) {
            $charsets['utf8'] = true;
            unset($charsets['utf8mb3']);
        }

        // Check if we have more than one charset in play.
        $count = count($charsets);
        if (1 === $count) {
            $charset = key($charsets);
        } elseif (0 === $count) {
            // No charsets, assume this table can store whatever.
            $charset = false;
        } else {
            // More than one charset. Remove latin1 if present and recalculate.
            unset($charsets['latin1']);
            $count = count($charsets);
            if (1 === $count) {
                // Only one charset (besides latin1).
                $charset = key($charsets);
            } elseif (2 === $count && isset($charsets['utf8'], $charsets['utf8mb4'])) {
                // Two charsets, but they're utf8 and utf8mb4, use utf8.
                $charset = 'utf8';
            } else {
                // Two mixed character sets. ascii.
                $charset = 'ascii';
            }
        }

        $this->table_charset[ $tablekey ] = $charset;
        return $charset;
    }

    /**
     * Retrieves the character set for the given column.
     *
     * @param string $table  Table name.
     * @param string $column Column name.
     * @return string|false|Error Column character set as a string. False if the column has
     *                               no character set. WP_Error object if there was an error.
     */
    public function get_col_charset($table, $column)
    {
        $tablekey  = strtolower($table);
        $columnkey = strtolower($column);


        // Skip this entirely if this isn't a MySQL database.
        if (empty($this->is_mysql)) {
            return false;
        }

        if (empty($this->table_charset[ $tablekey ])) {
            // This primes column information for us.
            $table_charset = $this->get_table_charset($table);
            if ($this->is_error($table_charset)) {
                return $table_charset;
            }
        }

        // If still no column information, return the table charset.
        if (empty($this->col_meta[ $tablekey ])) {
            return $this->table_charset[ $tablekey ];
        }

        // If this column doesn't exist, return the table charset.
        if (empty($this->col_meta[ $tablekey ][ $columnkey ])) {
            return $this->table_charset[ $tablekey ];
        }

        // Return false when it's not a string column.
        if (empty($this->col_meta[ $tablekey ][ $columnkey ]->Collation)) {
            return false;
        }

        [$charset] = explode('_', $this->col_meta[ $tablekey ][ $columnkey ]->Collation);
        return $charset;
    }

    /**
     * Retrieves the maximum string length allowed in a given column.
     *
     * The length may either be specified as a byte length or a character length.
     *
     * @param string $table  Table name.
     * @param string $column Column name.
     * @return array|false|Error {
     *     Array of column length information, false if the column has no length (for
     *     example, numeric column), WP_Error object if there was an error.
     *
     *     @type string $type   One of 'byte' or 'char'.
     *     @type int    $length The column length.
     * }
     */
    public function get_col_length($table, $column)
    {
        $tablekey  = strtolower($table);
        $columnkey = strtolower($column);

        // Skip this entirely if this isn't a MySQL database.
        if (empty($this->is_mysql)) {
            return false;
        }

        if (empty($this->col_meta[ $tablekey ])) {
            // This primes column information for us.
            $table_charset = $this->get_table_charset($table);
            if ($this->is_error($table_charset)) {
                return $table_charset;
            }
        }

        if (empty($this->col_meta[ $tablekey ][ $columnkey ])) {
            return false;
        }

        $typeinfo = explode('(', $this->col_meta[ $tablekey ][ $columnkey ]->Type);

        $type = strtolower($typeinfo[0]);
        $length = (!empty($typeinfo[1])) ? trim($typeinfo[1], ')') : false;

        switch ($type) {
            case 'char':
            case 'varchar':
                return [
                    'type' => 'char',
                    'length' => (int) $length,
                ];

            case 'binary':
            case 'varbinary':
                return [
                    'type' => 'byte',
                    'length' => (int) $length,
                ];

            case 'tinyblob':
            case 'tinytext':
                return [
                    'type' => 'byte',
                    'length' => 255,        // 2^8 - 1
                ];

            case 'blob':
            case 'text':
                return [
                    'type' => 'byte',
                    'length' => 65535,      // 2^16 - 1
                ];

            case 'mediumblob':
            case 'mediumtext':
                return [
                    'type' => 'byte',
                    'length' => 16777215,   // 2^24 - 1
                ];

            case 'longblob':
            case 'longtext':
                return [
                    'type' => 'byte',
                    'length' => 4294967295, // 2^32 - 1
                ];

            default:
                return false;
        }
    }

    /**
     * Checks if a string is ASCII.
     *
     * The negative regex is faster for non-ASCII strings, as it allows
     * the search to finish as soon as it encounters a non-ASCII character.
     *
     * @param string $input_string String to check.
     * @return bool True if ASCII, false if not.
     */
    protected function check_ascii($input_string)
    {
        if (function_exists('mb_check_encoding')) {
            if (mb_check_encoding($input_string, 'ASCII')) {
                return true;
            }
        } elseif (! preg_match('/[^\x00-\x7F]/', $input_string)) {
            return true;
        }

        return false;
    }

    /**
     * Checks if the query is accessing a collation considered safe on the current version of MySQL.
     *
     * @param string $query The query to check.
     * @return bool True if the collation is safe, false if it isn't.
     */
    protected function check_safe_collation($query)
    {
        if ($this->checking_collation) {
            return true;
        }

        // We don't need to check the collation for queries that don't read data.
        $query = ltrim($query, "\r\n\t (");
        if (preg_match('/^(?:SHOW|DESCRIBE|DESC|EXPLAIN|CREATE)\s/i', $query)) {
            return true;
        }

        // All-ASCII queries don't need extra checking.
        if ($this->check_ascii($query)) {
            return true;
        }

        $table = $this->get_table_from_query($query);
        if (! $table) {
            return false;
        }

        $this->checking_collation = true;
        $collation                = $this->get_table_charset($table);
        $this->checking_collation = false;

        // Tables with no collation, or latin1 only, don't need extra checking.
        if (false === $collation || 'latin1' === $collation) {
            return true;
        }

        $table = strtolower($table);
        if (empty($this->col_meta[ $table ])) {
            return false;
        }

        // If any of the columns don't have one of these collations, it needs more confidence checking.
        $safe_collations = array(
            'utf8_bin',
            'utf8_general_ci',
            'utf8mb3_bin',
            'utf8mb3_general_ci',
            'utf8mb4_bin',
            'utf8mb4_general_ci',
        );

        foreach ($this->col_meta[ $table ] as $col) {
            if (empty($col->Collation)) {
                continue;
            }

            if (! in_array($col->Collation, $safe_collations, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Strips any invalid characters based on value/charset pairs.
     *
     * @param array $data Array of value arrays. Each value array has the keys 'value', 'charset', and 'length'.
     *                    An optional 'ascii' key can be set to false to avoid redundant ASCII checks.
     * @return array|Error The $data parameter, with invalid characters removed from each value.
     *                        This works as a passthrough: any additional keys such as 'field' are
     *                        retained in each value array. If we cannot remove invalid characters,
     *                        a WP_Error object is returned.
     */
    protected function strip_invalid_text($data)
    {
        $db_check_string = false;

        foreach ($data as &$value) {
            $charset = $value['charset'];

            if (is_array($value['length'])) {
                $length                  = $value['length']['length'];
                $truncate_by_byte_length = 'byte' === $value['length']['type'];
            } else {
                $length = false;
                /*
                 * Since we have no length, we'll never truncate. Initialize the variable to false.
                 * True would take us through an unnecessary (for this case) codepath below.
                 */
                $truncate_by_byte_length = false;
            }

            // There's no charset to work with.
            if (false === $charset) {
                continue;
            }

            // Column isn't a string.
            if (! is_string($value['value'])) {
                continue;
            }

            $needs_validation = true;
            if (
                // latin1 can store any byte sequence.
                'latin1' === $charset
            ||
                // ASCII is always OK.
                (! isset($value['ascii']) && $this->check_ascii($value['value']))
            ) {
                $truncate_by_byte_length = true;
                $needs_validation        = false;
            }

            if ($truncate_by_byte_length) {
                if (false !== $length && strlen($value['value']) > $length) {
                    $value['value'] = substr($value['value'], 0, $length);
                }

                if (! $needs_validation) {
                    continue;
                }
            }

            // utf8 can be handled by regex, which is a bunch faster than a DB lookup.
            if (('utf8' === $charset || 'utf8mb3' === $charset || 'utf8mb4' === $charset) && function_exists('mb_strlen')) {
                $regex = '/
					(
						(?: [\x00-\x7F]                  # single-byte sequences   0xxxxxxx
						|   [\xC2-\xDF][\x80-\xBF]       # double-byte sequences   110xxxxx 10xxxxxx
						|   \xE0[\xA0-\xBF][\x80-\xBF]   # triple-byte sequences   1110xxxx 10xxxxxx * 2
						|   [\xE1-\xEC][\x80-\xBF]{2}
						|   \xED[\x80-\x9F][\x80-\xBF]
						|   [\xEE-\xEF][\x80-\xBF]{2}';

                if ('utf8mb4' === $charset) {
                    $regex .= '
						|    \xF0[\x90-\xBF][\x80-\xBF]{2} # four-byte sequences   11110xxx 10xxxxxx * 3
						|    [\xF1-\xF3][\x80-\xBF]{3}
						|    \xF4[\x80-\x8F][\x80-\xBF]{2}
					';
                }

                $regex         .= '){1,40}                          # ...one or more times
					)
					| .                                  # anything else
					/x';
                $value['value'] = preg_replace($regex, '$1', $value['value']);

                if (false !== $length && mb_strlen($value['value'], 'UTF-8') > $length) {
                    $value['value'] = mb_substr($value['value'], 0, $length, 'UTF-8');
                }
                continue;
            }

            // We couldn't use any local conversions, send it to the DB.
            $value['db']     = true;
            $db_check_string = true;
        }
        unset($value); // Remove by reference.

        if ($db_check_string) {
            $queries = [];
            foreach ($data as $col => $value) {
                if (! empty($value['db'])) {
                    // We're going to need to truncate by characters or bytes, depending on the length value we have.
                    $charset = (isset($value['length']['type']) && 'byte' === $value['length']['type']) ? 'binary' : $value['charset'];

                    $connection_charset = $this->charset ?: mysqli_character_set_name($this->dbh);

                    if (is_array($value['length'])) {
                        $length          = sprintf('%.0f', $value['length']['length']);
                        $queries[ $col ] = $this->prepare("CONVERT( LEFT( CONVERT( %s USING $charset ), $length ) USING $connection_charset )", $value['value']);
                    } elseif ('binary' !== $charset) {
                        // If we don't have a length, there's no need to convert binary - it will always return the same result.
                        $queries[ $col ] = $this->prepare("CONVERT( CONVERT( %s USING $charset ) USING $connection_charset )", $value['value']);
                    }

                    unset($data[ $col ]['db']);
                }
            }

            $sql = [];
            foreach ($queries as $column => $query) {
                if (! $query) {
                    continue;
                }

                $sql[] = "$query AS x_$column";
            }

            $this->check_current_query = false;
            $row                       = $this->get_row('SELECT ' . implode(', ', $sql), ARRAY_A);
            if (! $row) {
                return new Error('Could not strip invalid text.');
            }

            foreach (array_keys($data) as $column) {
                if (isset($row[ "x_$column" ])) {
                    $data[ $column ]['value'] = $row[ "x_$column" ];
                }
            }
        }

        return $data;
    }

    /**
     * Strips any invalid characters from the query.
     *
     * @param string $query Query to convert.
     * @return string|Error The converted query, or a WP_Error object if the conversion fails.
     */
    protected function strip_invalid_text_from_query($query)
    {
        // We don't need to check the collation for queries that don't read data.
        $trimmed_query = ltrim($query, "\r\n\t (");
        if (preg_match('/^(?:SHOW|DESCRIBE|DESC|EXPLAIN|CREATE)\s/i', $trimmed_query)) {
            return $query;
        }

        $table = $this->get_table_from_query($query);
        if ($table) {
            $charset = $this->get_table_charset($table);
            if ($this->is_error($charset)) {
                return $charset;
            }

            // We can't reliably strip text from tables containing binary/blob columns.
            if ('binary' === $charset) {
                return $query;
            }
        } else {
            $charset = $this->charset;
        }

        $data = [
            'value' => $query,
            'charset' => $charset,
            'ascii' => false,
            'length' => false,
        ];

        $data = $this->strip_invalid_text(array( $data ));
        if ($this->is_error($data)) {
            return $data;
        }

        return $data[0]['value'];
    }

    /**
     * Finds the first table name referenced in a query.
     *
     * @param string $query The query to search.
     * @return string|false The table name found, or false if a table couldn't be found.
     */
    protected function get_table_from_query($query)
    {
        // Remove characters that can legally trail the table name.
        $query = rtrim($query, ';/-#');

        // Allow (select...) union [...] style queries. Use the first query's table name.
        $query = ltrim($query, "\r\n\t (");

        // Strip everything between parentheses except nested selects.
        $query = preg_replace('/\((?!\s*select)[^(]*?\)/is', '()', $query);

        // Quickly match most common queries.
        if (preg_match(
            "/^\\s*(?:SELECT.*?\\s+FROM|INSERT(?:\\s+LOW_PRIORITY|\\s+DELAYED|\\s+HIGH_PRIORITY)?(?:\\s+IGNORE)?(?:\\s+INTO)?|REPLACE(?:\\s+LOW_PRIORITY|\\s+DELAYED)?(?:\\s+INTO)?|UPDATE(?:\\s+LOW_PRIORITY)?(?:\\s+IGNORE)?|DELETE(?:\\s+LOW_PRIORITY|\\s+QUICK|\\s+IGNORE)*(?:.+?FROM)?)\\s+((?:[0-9a-zA-Z\$_.`-]|[\\xC2-\\xDF][\\x80-\\xBF])+)/is",
            $query,
            $maybe
        )) {
            return str_replace('`', '', $maybe[1]);
        }

        // SHOW TABLE STATUS and SHOW TABLES WHERE Name = 'wp_posts'
        if (preg_match('/^\s*SHOW\s+(?:TABLE\s+STATUS|(?:FULL\s+)?TABLES).+WHERE\s+Name\s*=\s*("|\')((?:[0-9a-zA-Z$_.-]|[\xC2-\xDF][\x80-\xBF])+)\\1/is', $query, $maybe)) {
            return $maybe[2];
        }

        /*
         * SHOW TABLE STATUS LIKE and SHOW TABLES LIKE 'wp\_123\_%'
         * This quoted LIKE operand seldom holds a full table name.
         * It is usually a pattern for matching a prefix so we just
         * strip the trailing % and unescape the _ to get 'wp_123_'
         * which drop-ins can use for routing these SQL statements.
         */
        if (preg_match('/^\s*SHOW\s+(?:TABLE\s+STATUS|(?:FULL\s+)?TABLES)\s+(?:WHERE\s+Name\s+)?LIKE\s*("|\')((?:[\\\\0-9a-zA-Z$_.-]|[\xC2-\xDF][\x80-\xBF])+)%?\\1/is', $query, $maybe)) {
            return str_replace('\\_', '_', $maybe[2]);
        }

        // Big pattern for the rest of the table-related queries.
        if (preg_match(
            "/^\\s*(?:(?:EXPLAIN\\s+(?:EXTENDED\\s+)?)?SELECT.*?\\s+FROM|DESCRIBE|DESC|EXPLAIN|HANDLER|(?:LOCK|UNLOCK)\\s+TABLE(?:S)?|(?:RENAME|OPTIMIZE|BACKUP|RESTORE|CHECK|CHECKSUM|ANALYZE|REPAIR).*\\s+TABLE|TRUNCATE(?:\\s+TABLE)?|CREATE(?:\\s+TEMPORARY)?\\s+TABLE(?:\\s+IF\\s+NOT\\s+EXISTS)?|ALTER(?:\\s+IGNORE)?\\s+TABLE|DROP\\s+TABLE(?:\\s+IF\\s+EXISTS)?|CREATE(?:\\s+\\w+)?\\s+INDEX.*\\s+ON|DROP\\s+INDEX.*\\s+ON|LOAD\\s+DATA.*INFILE.*INTO\\s+TABLE|(?:GRANT|REVOKE).*ON\\s+TABLE|SHOW\\s+(?:.*FROM|.*TABLE))\\s+\\(*\\s*((?:[0-9a-zA-Z\$_.`-]|[\\xC2-\\xDF][\\x80-\\xBF])+)\\s*\\)*/is",
            $query,
            $maybe
        )) {
            return str_replace('`', '', $maybe[1]);
        }

        return false;
    }

    /**
     * Wraps errors in a nice header and footer and dies.
     *
     * @param string $message    The error message.
     * @param string $error_code Optional. A computer-readable string to identify the error.
     *                           Default '500'.
     * @return void|false Void if the showing of errors is enabled, false if disabled.
     */
    public function bail($message, $error_code = '500')
    {
        $error = '';

        if ($this->dbh instanceof mysqli) {
            $error = mysqli_error($this->dbh);
        } elseif (mysqli_connect_errno()) {
            $error = mysqli_connect_error();
        }

        if ($error) {
            $message = "<p><code>$error</code></p>\n$message";
        }

        $this->doing_it_wrong('db:bail', $message);
    }

    /**
     * Determines whether the database or db supports a particular feature.
     *
     * @param string $db_cap The feature to check for. Accepts 'collation', 'group_concat',
     *                       'subqueries', 'set_charset', 'utf8mb4', 'utf8mb4_520',
     *                       or 'identifier_placeholders'.
     * @return bool True when the database feature is supported, false otherwise.
     */
    public function has_cap($db_cap)
    {
        $db_version     = $this->db_version();
        $db_server_info = $this->db_server_info();

        /*
         * Account for MariaDB version being prefixed with '5.5.5-' on older PHP versions.
         *
         * Note: str_contains() is not used here, as this file can be included
         * directly outside of  core, e.g. by HyperDB, in which case
         * the polyfills from wp-includes/compat.php are not loaded.
         */
        if ('5.5.5' === $db_version && false !== strpos($db_server_info, 'MariaDB')
            && PHP_VERSION_ID < 80016
        ) {
            // Strip the '5.5.5-' prefix and set the version to the correct value.
            $db_server_info = preg_replace('/^5\.5\.5-(.*)/', '$1', $db_server_info);
            $db_version     = preg_replace('/[^0-9.].*/', '', $db_server_info);
        }

        switch (strtolower($db_cap)) {
            case 'collation':
            case 'group_concat':
            case 'subqueries':
                return version_compare($db_version, '4.1', '>=');
            case 'set_charset':
                return version_compare($db_version, '5.0.7', '>=');
            case 'utf8mb4':
                return true;
            case 'utf8mb4_520':
                return version_compare($db_version, '5.6', '>=');
            case 'identifier_placeholders':
                return true;
        }

        return false;
    }

    /**
     * Retrieves the database server version.
     *
     * @return string|null Version number on success, null on failure.
     */
    public function db_version()
    {
        return preg_replace('/[^0-9.].*/', '', $this->db_server_info());
    }

    /**
     * Returns the version of the MySQL server.
     *
     * @return string Server version as a string.
     */
    public function db_server_info()
    {
        return mysqli_get_server_info($this->dbh);
    }

    private function doing_it_wrong($function, $message)
    {
       error_log("An error occurred in function $function: $message");
    }
}