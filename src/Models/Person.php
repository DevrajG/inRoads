<?php
/**
 * @copyright 2009-2018 City of Bloomington, Indiana
 * @license http://www.gnu.org/licenses/agpl.txt GNU/AGPL, see LICENSE.txt
 */
namespace Application\Models;
use Blossom\Classes\ActiveRecord;
use Blossom\Classes\Database;
use Blossom\Classes\ExternalIdentity;

class Person extends ActiveRecord
{
	protected $tablename = 'people';

	protected $department;

	const ERROR_UNKNOWN_PERSON = 'person/unknown';

	/**
	 * Returns the matching Person object or null if not found
	 *
	 * @return Person
	 */
	public static function findByUsername(string $username)
	{
        $zend_db = Database::getConnection();
        $sql = 'select * from people where username=?';

        $result = $zend_db->createStatement($sql)->execute([$username]);
        if (count($result)) {
            return new Person($result->current());
        }
	}

	/**
	 * Populates the object with data
	 *
	 * Passing in an associative array of data will populate this object without
	 * hitting the database.
	 *
	 * Passing in a scalar will load the data from the database.
	 * This will load all fields in the table as properties of this class.
	 * You may want to replace this with, or add your own extra, custom loading
	 *
	 * @param int|string|array $id (ID, email, username)
	 */
	public function __construct($id=null)
	{
		if ($id) {
			if (is_array($id)) {
				$this->exchangeArray($id);
			}
			else {
				$zend_db = Database::getConnection();
				if (ActiveRecord::isId($id)) {
					$sql = 'select * from people where id=?';
				}
				elseif (false !== strpos($id,'@')) {
					$sql = 'select * from people where email=?';
				}
				else {
					$sql = 'select * from people where username=?';
				}
				$result = $zend_db->createStatement($sql)->execute([$id]);
				if (count($result)) {
					$this->exchangeArray($result->current());
				}
				else {
					throw new \Exception(self::ERROR_UNKNOWN_PERSON);
				}
			}
		}
		else {
			// This is where the code goes to generate a new, empty instance.
			// Set any default values for properties that need it here
			$this->setAuthenticationMethod('local');
		}
	}

	/**
	 * Performs validation checks and returns any problems
	 *
	 * @return array Errors
	 */
	public function validate()
	{
        $errors = [];

		if (!$this->getFirstname()) { $errors['firstname'][] = 'missingRequiredField'; }
		if (!$this->getEmail())     { $errors['email'][]     = 'missingRequiredField'; }

		if ($this->getUsername()) {
            if (!$this->getAuthenticationMethod()) { $this->setAuthenticationMethod('local'); }
            if (!$this->getRole())                 { $this->setRole('Public'); }
            // Members of the public with user accounts should assigned to a department
		}

		if (count($errors)) {
            return ['people' => $errors];
		}
	}

	/**
	 * @return array Errors
	 */
	public function save()
	{
		return parent::save();
	}

	/**
	 * Removes all the user account related fields from this Person
	 */
	public function deleteUserAccount()
	{
		$userAccountFields = ['username', 'password', 'authenticationMethod', 'role'];
		foreach ($userAccountFields as $f) {
			$this->data[$f] = null;
		}
	}


	//----------------------------------------------------------------
	// Generic Getters & Setters
	//----------------------------------------------------------------
	public function getId()            { return parent::get('id');           }
	public function getFirstname()     { return parent::get('firstname');    }
	public function getLastname()      { return parent::get('lastname');     }
	public function getEmail()         { return parent::get('email');        }
	public function getPhone()         { return parent::get('phone');        }

	public function setFirstname   ($s) { parent::set('firstname',    $s); }
	public function setLastname    ($s) { parent::set('lastname',     $s); }
	public function setEmail       ($s) { parent::set('email',        $s); }
	public function setPhone       ($s) { parent::set('phone',        $s); }

	public function getUsername()             { return parent::get('username'); }
	public function getPassword()             { return parent::get('password'); } # Encrypted
	public function getRole()                 { return parent::get('role');     }
	public function getAuthenticationMethod() { return parent::get('authenticationMethod'); }
    public function getNotifications(): int   { return parent::get('notifications') ? 1 : 0; }
	public function getDepartment_id()        { return parent::get('getDepartment_id'); }
    public function getDepartment() { return parent::getForeignKeyObject(__namespace__.'\Department', 'department_id'); }

	public function setUsername            ($s) { parent::set('username',             $s); }
	public function setRole                ($s) { parent::set('role',                 $s); }
	public function setAuthenticationMethod($s) { parent::set('authenticationMethod', $s); }
	public function setNotifications       ($b) { parent::set('notifications', $b ? 1 : 0); }
    public function setDepartment_id($i) { parent::setForeignKeyField (__namespace__.'\Department', 'department_id', $i); }
    public function setDepartment   ($i) { parent::setForeignKeyObject(__namespace__.'\Department', 'department_id', $i); }

	public function setPassword($s)
	{
		$s = trim($s);
		if ($s) { $this->data['password'] = sha1($s); }
		else    { $this->data['password'] = null;     }
	}

	/**
	 * @param array $post
	 */
	public function handleUpdate($post)
	{
		$fields = ['firstname', 'middlename', 'lastname', 'email', 'phone', 'notifications'];
		foreach ($fields as $field) {
			if (isset($post[$field])) {
				$set = 'set'.ucfirst($field);
				$this->$set($post[$field]);
			}
		}
	}

	/**
	 * @param array $post
	 */
	public function handleUpdateUserAccount($post)
	{
		$fields = [
			'firstname', 'lastname', 'email', 'phone', 'department_id',
			'username', 'authenticationMethod', 'role'
		];

		foreach ($fields as $f) {
			if (isset($post[$f])) {
				$set = 'set'.ucfirst($f);
				$this->$set($post[$f]);
			}
			if (!empty($post['password'])) {
				$this->setPassword($post['password']);
			}
		}

		$method = $this->getAuthenticationMethod();
		if ($this->getUsername() && $method && $method != 'local') {
			$class = "Blossom\\Classes\\$method";
			$identity = new $class($this->getUsername());
			$this->populateFromExternalIdentity($identity);
		}
	}

	//----------------------------------------------------------------
	// User Authentication
	//----------------------------------------------------------------
	/**
	 * Should provide the list of methods supported
	 *
	 * There should always be at least one method, called "local"
	 * Additional methods must match classes that implement External Identities
	 * See: ExternalIdentity.php
	 *
	 * @return array
	 */
	public static function getAuthenticationMethods()
	{
		global $DIRECTORY_CONFIG;
		return array_merge(['local'], array_keys($DIRECTORY_CONFIG));
	}

	/**
	 * Determines which authentication scheme to use for the user and calls the appropriate method
	 *
	 * Local users will get authenticated against the database
	 * Other authenticationMethods will need to write a class implementing ExternalIdentity
	 * See: /libraries/framework/classes/ExternalIdentity.php
	 *
	 * @param string $password
	 * @return boolean
	 */
	public function authenticate($password)
	{
		if ($this->getUsername()) {
			switch($this->getAuthenticationMethod()) {
				case "local":
					return $this->getPassword()==sha1($password);
				break;

				default:
					$method = $this->getAuthenticationMethod();
					$class = "Blossom\\Classes\\$method";
					return $class::authenticate($this->getUsername(),$password);
			}
		}
	}

	/**
	 * Checks if the user is supposed to have acces to the resource
	 *
	 * This is implemented by checking against a Zend_Acl object
	 * The Zend_Acl should be created in bootstrap.inc
	 *
	 * @param string $resource
	 * @param string $action
	 * @return boolean
	 */
	public static function isAllowed($resource, $action=null)
	{
		global $ZEND_ACL;
		$role = 'Anonymous';
		if (  isset($_SESSION['USER']) && $_SESSION['USER']->getRole()) {
			$role = $_SESSION['USER']->getRole();
		}
		return $ZEND_ACL->isAllowed($role, $resource, $action);
	}

	//----------------------------------------------------------------
	// Custom Functions
	//----------------------------------------------------------------
	public function getUrl() { return BASE_URL.'/people/view?person_id='.$this->getId(); }
	public function getUri() { return BASE_URI.'/people/view?person_id='.$this->getId(); }

	/**
	 * @return string
	 */
	public function getFullname()
	{
		return "{$this->getFirstname()} {$this->getLastname()}";
	}

	/**
	 * @param ExternalIdentity $identity An object implementing ExternalIdentity
	 */
	public function populateFromExternalIdentity(ExternalIdentity $identity)
	{
		if (!$this->getFirstname() && $identity->getFirstname()) {
			$this->setFirstname($identity->getFirstname());
		}
		if (!$this->getLastname() && $identity->getLastname()) {
			$this->setLastname($identity->getLastname());
		}
		if (!$this->getEmail() && $identity->getEmail()) {
			$this->setEmail($identity->getEmail());
		}
		if (!$this->getPhone() && $identity->getPhone()) {
            $this->setPhone($identity->getPhone());
		}
	}

}
