#!/usr/bin/php
<?php
/**
 * This script can be used to fix URL problems encountered when changing site Domains. 
 * It check and update the option values in the 'wp_options' table and traverse serialized arrays, specially those theme related values.
 *
 * @author      Rommel S. Laranjo <rslaranjo@gmail.com>
 */

/**
 * Change the values accordingly
 */
define('DB_SERVER',             '127.0.0.1');
define('DB_PORT',               '3306');
define('DB_NAME',               'wordpress_site');
define('DB_USER',               'root');
define('DB_PASS',               'abc123');

// The domain to be replaced
define('OLD_DOMAIN',            'olddomain.com');
// The new domain to be used
define('NEW_DOMAIN',            'mynewdomain.net');

/**
 * Handles the replacement of OLD_DOMAIN with NEW_DOMAIN
 */
class WordPressThemeLinkFixer {
    private $pdo;
    private $old_domain = '';
    private $new_domain = '';

    /**
     * WordPressThemeLinkFixer Constructor
     *
     * @param string $db_server         Domain name or IP address of DB server
     * @param string $db_port           DB server port number
     * @param string $db_name           DB name
     * @param string $db_user           DB username
     * @param string $db_pass           DB password
     */
    function __construct($db_server, $db_port, $db_name, $db_user, $db_pass) {
        $conn = 'mysql:host=' . $db_server . 
            ';port=' . $db_port . 
            ';dbname=' . $db_name . 
            ';charset=utf8';
        $this->pdo = new PDO($conn, $db_user, $db_pass);
    }

    /**
     * Unset PDOStatement Object
     *
     * @param PDOStatementObject $query         The PDOStatement Object to be freed
     */
    private function closeQuery(&$query) {
        $query->closeCursor();
        unset($query);
    }

    /**
     * Returns the value of old_domain
     *
     * @return string           The value of new_domain
     */
    public function getOldDomain() {
        return $this->old_domain;
    }

    /**
     * Set a value to old_domain
     *
     * @param string $old_domain        The old domain name
     */
    public function setOldDomain($old_domain) {
        $this->old_domain = $old_domain;
    }

    /**
     * Returns the value of new_domain
     *
     * @return string           The value of new_domain
     */
    public function getNewDomain() {
        return $this->new_domain;
    }

    /** 
     * Set a value to new_domain
     *
     * @param string $new_domain       The new domain name
     */
    public function setNewDomain($new_domain) {
        $this->new_domain = $new_domain;
    }

    /**
     * Read the 'wp_options' table with 'option_value' containing the old_domain string
     *
     * @return array            An array containing the query results, else, Bool False
     */
    public function readWordPressOptions() {
        $sql_query = "SELECT * FROM wp_options WHERE option_value LIKE ?;";
        $stmt      = $this->pdo->prepare($sql_query);
        $stmt->execute(array('%'.$this->old_domain.'%'));
        $results = $stmt->fetchAll();
        $this->closeQuery($stmt);
        if(count($results)) {
            return $results;
        } else {
            return false;
        }
    }

    /**
     * Updates the 'option_value' containing the new_domain
     *
     * @param integer $id               The 'option_id' to be updated
     * @param string  $option_value     The new 'option_value'
     * @return bool                     Return true on successfull update, else, false
     */
    public function updateWordPressOptionValue($id, $option_value) {
        $sql_query = "UPDATE wp_options SET option_value = ? WHERE option_id = ?;";
        $stmt      = $this->pdo->prepare($sql_query);
        try {
            $stmt->execute(array($option_value, $id));
            $this->closeQuery($stmt);
            return true;
        } catch (PDOException $e) {
            print "Error encountered while updating option_value!\n";
        }
        return false;
    }

    /**
     * Read the WordPress options containing the 'old_domain' string and updates them to 'new_domain'
     *
     * @return bool             Return true on success, otherwise, false
     */
    public function replaceOldWithNewDomain() {
        $results = $this->readWordPressOptions();
        if ($results === false) {
            return false;
        } 

        foreach ($results as $result) {
            if (strlen($result['option_value']) == 0) {
                continue;
            }
            $option_value = @unserialize($result['option_value']);
            if (is_array($option_value) === true) {
                // option_value is an array
                $new_option_value = array();
                $has_changes      = false;
                $new_option_value = $this->examineOptionValues($option_value, $has_changes);
                // update option_value if there are changes
                if ($has_changes) {
                    if (false === $this->updateWordPressOptionValue($result['option_id'], serialize($new_option_value))) {
                        print "Failed to update option_id: " . $result['option_id'] . "\n";
                    }
                }
            } else {
                // option_value is just a string
                $option_value = $result['option_value'];
                $pattern = '/(http|https)\:\/\/(www\.|)' . $this->old_domain . '(\/|)(.*?)/';
                $new_option_value = $option_value;
                if (preg_match($pattern, $option_value, $matches)) {
                    // match is found, replace it
                    $new_option_value = str_replace($this->old_domain, $this->new_domain, $option_value);
                    // update option_value 
                    if (false === $this->updateWordPressOptionValue($result['option_id'], $new_option_value)) {
                        print "Failed to update option_id: " . $result['option_id'] . "\n";
                    }
                }
            }
        } 
        
        return true;
    }

    /**
     * Function that traverse option values that are array and replace any occurrence of 'old_domain' with 'new_domain' in each array element
     *
     * @param array $option_value               An option value that is in array form
     * @param bool  $has_changes                Will be set to true when changes are made in the option value
     * @return array $new_option_value          The new option value that possibly contains changes
     */
    private function examineOptionValues($option_value, &$has_changes) {
        $new_option_value       = array();
        foreach ($option_value as $key => $value) {
            $new_value          = $value;
            // check if the resulting $value is an array
            if (is_array($value) === true) {
                // $value is also an array so go deeper 
                $new_value      = $this->examineOptionValues($value, $has_changes);
            } else {
                // $value is just a string so examine the string 
                $pattern        = '/(http|https)\:\/\/(www\.|)' . $this->old_domain . '(\/|)(.*?)/';
                $new_value      = $value;
                $match_result = @preg_match($pattern, $value, $matches);
                if ($match_result == 1) {
                    // match is found, replace it
                    $new_value = str_replace($this->old_domain, $this->new_domain, $value); 
                    $has_changes = true;
                }
            }
            $new_option_value[$key] = $new_value;
        }
        return $new_option_value;
    }

}

// Script starts here...
$fixer = new WordPressThemeLinkFixer(DB_SERVER, DB_PORT, DB_NAME, DB_USER, DB_PASS);

$fixer->setOldDomain(OLD_DOMAIN);
$fixer->setNewDomain(NEW_DOMAIN);

$result = $fixer->replaceOldWithNewDomain();
if ($result) {
    print "Success!\n";
} else {
    print "Exits with error. Please check and report problem.\n";
}

exit(0);
?>
