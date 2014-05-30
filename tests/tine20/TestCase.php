<?php
/**
 * Tine 2.0 - http://www.tine20.org
 * 
 * @package     Tests
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2013 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Philipp Schüle <p.schuele@metaways.de>
 */

/**
 * Test helper
 */
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'TestHelper.php';

/**
 * Test class for Calendar_Backend_Sql
 * 
 * @package     Tests
 */
abstract class TestCase extends PHPUnit_Framework_TestCase
{
    /**
     * transaction id if test is wrapped in an transaction
     * 
     * @var string
     */
    protected $_transactionId = null;
    
    /**
     * usernames to be deleted (in sync backend)
     * 
     * @var string
     */
    protected $_usernamesToDelete = array();
    
    /**
     * groups (ID) to be deleted (in sync backend)
     * 
     * @var array
     */
    protected $_groupIdsToDelete = array();
    
    /**
     * remove group members, too when deleting groups
     * 
     * @var boolean
     */
    protected $_removeGroupMembers = true;
    
    /**
     * test personas
     * 
     * @var array
     */
    protected $_personas = array();
    
    /**
     * unit in test
     *
     * @var Object
     */
    protected $_uit = null;
    
    /**
     * the test user
     *
     * @var Tinebase_Model_FullUser
     */
    protected $_originalTestUser;
    
    /**
     * the mailer
     * 
     * @var Zend_Mail_Transport_Array
     */
    protected static $_mailer = null;
    
    /**
     * set up tests
     */
    protected function setUp()
    {
        $this->_transactionId = Tinebase_TransactionManager::getInstance()->startTransaction(Tinebase_Core::getDb());
        
        Addressbook_Controller_Contact::getInstance()->setGeoDataForContacts(false);
        
        $this->_personas = Zend_Registry::get('personas');
        
        $this->_originalTestUser = Tinebase_Core::getUser();
    }
    
    /**
     * tear down tests
     */
    protected function tearDown()
    {
        if (Tinebase_User::getConfiguredBackend() === Tinebase_User::LDAP) {
            $this->_deleteUsers();
            $this->_deleteGroups();
        }
        if ($this->_transactionId) {
            Tinebase_TransactionManager::getInstance()->rollBack();
        }
        
        Addressbook_Controller_Contact::getInstance()->setGeoDataForContacts(true);
        
        Tinebase_Core::set(Tinebase_Core::USER, $this->_originalTestUser);
    }
    
    /**
     * test needs transaction
     */
    protected function _testNeedsTransaction()
    {
        if ($this->_transactionId) {
            Tinebase_TransactionManager::getInstance()->commitTransaction($this->_transactionId);
            $this->_transactionId = null;
        }
    }
    
    /**
     * delete groups and their members
     * 
     * - also deletes groups and users in sync backends
     */
    protected function _deleteGroups()
    {
        foreach ($this->_groupIdsToDelete as $groupId) {
            if ($this->_removeGroupMembers) {
                foreach (Tinebase_Group::getInstance()->getGroupMembers($groupId) as $userId) {
                    try {
                        Tinebase_User::getInstance()->deleteUser($userId);
                    } catch (Exception $e) {
                        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                            . ' error while deleting user: ' . $e->getMessage());
                    }
                }
            }
            try {
                Tinebase_Group::getInstance()->deleteGroups($groupId);
            } catch (Exception $e) {
                if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                    . ' error while deleting group: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * delete users
     */
    protected function _deleteUsers()
    {
        foreach ($this->_usernamesToDelete as $username) {
            try {
                Tinebase_User::getInstance()->deleteUser(Tinebase_User::getInstance()->getUserByLoginName($username));
            } catch (Exception $e) {
                if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                    . ' error while deleting user: ' . $e->getMessage());
            }
        }
    }

    /**
     * get personal container
     * 
     * @param string $applicationName
     * @param Tinebase_Model_User $user
     * @return Tinebase_Model_Container
     */
    protected function _getPersonalContainer($applicationName, $user = null)
    {
        if ($user === null) {
            $user = Tinebase_Core::getUser();
        }
        
        return Tinebase_Container::getInstance()->getPersonalContainer(
            $user,
            $applicationName, 
            $user,
            Tinebase_Model_Grants::GRANT_EDIT
        )->getFirstRecord();
    }

    /**
     * get test mail domain
     * 
     * @return string
     */
    protected function _getMailDomain()
    {
        $testconfig = Zend_Registry::get('testConfig');
        return ($testconfig && isset($testconfig->maildomain)) ? $testconfig->maildomain : 'tine20.org';
    }

    /**
     * lazy init of uit
     * 
     * @return Object
     * 
     * @todo fix ide object class detection for completions
     */
    protected function _getUit()
    {
        if ($this->_uit === null) {
            $uitClass = preg_replace('/Tests{0,1}$/', '', get_class($this));
            if (@method_exists($uitClass, 'getInstance')) {
                $this->_uit = call_user_func($uitClass . '::getInstance');
            } else if (@class_exists($uitClass)) {
                $this->_uit = new $uitClass();
            } else {
                throw new Exception('could not find class ' . $uitClass);
            }
        }
        
        return $this->_uit;
    }
    
    /**
     * get messages
     * 
     * @return array
     */
    public static function getMessages()
    {
        // make sure messages are sent if queue is activated
        if (isset(Tinebase_Core::getConfig()->actionqueue)) {
            Tinebase_ActionQueue::getInstance()->processQueue(100);
        }
        
        return self::getMailer()->getMessages();
    }
    
    /**
     * get mailer
     * 
     * @return Zend_Mail_Transport_Abstract
     */
    public static function getMailer()
    {
        if (! self::$_mailer) {
            self::$_mailer = Tinebase_Smtp::getDefaultTransport();
        }
        
        return self::$_mailer;
    }
    
    /**
     * flush mailer (send all remaining mails first)
     */
    public static function flushMailer()
    {
        // make sure all messages are sent if queue is activated
        if (isset(Tinebase_Core::getConfig()->actionqueue)) {
            Tinebase_ActionQueue::getInstance()->processQueue(10000);
        }
        
        self::getMailer()->flush();
    }
}
