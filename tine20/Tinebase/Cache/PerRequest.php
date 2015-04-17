<?php
/**
 * Tine 2.0
 * 
 * @package     Tinebase
 * @subpackage  Cache
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2015-2015 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Lars Kneschke <l.kneschke@metaways.de>
 */

/**
 * per request in memory cache class
 * 
 * a central place to manage in class caches
 * 
 * @package     Tinebase
 * @subpackage  Cache
 */
class Tinebase_Cache_PerRequest 
{
    /**
     * the cache
     *
     * @var array
     */
    protected $_inMemoryCache = array();
    
    /**
     * use Zend_Cache as fallback
     *
     * @var bool
     *
     * TODO allow to configure this
     */
    protected $_usePersistentCache = false;

    /**
     * verbose log
     *
     * @var bool
     *
     * TODO allow to configure this
     */
    protected $_verboseLog = false;

    /**
     * holds the instance of the singleton
     *
     * @var Tinebase_Cache_PerRequest
     */
    private static $_instance = NULL;
    
    /**
     * the constructor
     *
     * don't use the constructor. use the singleton 
     */
    private function __construct()
    {
    }
    
    /**
     * don't clone. Use the singleton.
     *
     */
    private function __clone() 
    {
    }
    
    /**
     * the singleton pattern
     *
     * @return Tinebase_Cache_PerRequest
     */
    public static function getInstance() 
    {
        if (self::$_instance === NULL) {
            self::$_instance = new Tinebase_Cache_PerRequest();
        }
        
        return self::$_instance;
    }
    
    /**
     * set/get persistent cache usage
     *
     * @param  boolean optional
     * @return boolean
     */
    public function usePersistentCache()
    {
        $value = (func_num_args() === 1) ? (bool) func_get_arg(0) : NULL;
        $currValue = $this->_usePersistentCache;
        if ($value !== NULL) {
            $this->_usePersistentCache = $value;
        }
        
        return $currValue;
    }
    
    /**
     * save entry in cache
     * 
     * @param string $class
     * @param string $method
     * @param string $cacheId
     * @param string $value
     * @return Tinebase_Cache_PerRequest
     */
    public function save($class, $method, $cacheId, $value)
    {
        $cacheId = sha1($cacheId);
        
        $this->_inMemoryCache[$class][$method][$cacheId] = $value;
        
        if ($this->_usePersistentCache) {
            // store in external cache for 60 seconds
            Tinebase_Core::getCache()->save(
                $value,
                $this->_getPersistentCacheId($method, $cacheId),
                array(),
                60);
        }
        
        return $this;
    }
    
    /**
     * load entry from cache
     * 
     * @param string $class
     * @param string $method
     * @param string $cacheId
     * @throws Tinebase_Exception_NotFound
     * @return mixed
     */
    public function load($class, $method, $cacheId)
    {
        if ($this->_verboseLog) {
            $traceException = new Exception();
            Tinebase_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__
                . " class/method/cacheid: $class $method $cacheId stack: " . $traceException->getTraceAsString());
        }

        $cacheId = sha1($cacheId);

        if (!isset($this->_inMemoryCache[$class]) ||
            !isset($this->_inMemoryCache[$class][$method]) ||
            !(isset($this->_inMemoryCache[$class][$method][$cacheId]) || array_key_exists($cacheId, $this->_inMemoryCache[$class][$method]))
        ) {

            if ($this->_verboseLog) {
                Tinebase_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . ' ... not found in class cache');
            }

            if ($this->_usePersistentCache) {
                // TODO better use Tinebase_Core::getCache()->test() ?
                $value = Tinebase_Core::getCache()->load($this->_getPersistentCacheId($method, $cacheId));
                if ($value !== false) {
                    $this->_inMemoryCache[$class][$method][$cacheId] = $value;
                    
                    return $value;
                }

                if ($this->_verboseLog) {
                    Tinebase_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . ' ... not found in zend cache');
                }
            }
            
            throw new Tinebase_Exception_NotFound('cacheId not found');
        };
        
        return $this->_inMemoryCache[$class][$method][$cacheId];
    }
    
    /**
     * reset cache
     * 
     * @param string $class
     * @param string $method
     * @param string $cacheId
     * @return Tinebase_Cache_PerRequest
     */
    public function resetCache($class = null, $method = null, $cacheId = null)
    {
        $cacheId = $cacheId ? sha1($cacheId) : $cacheId;
        
        if (empty($class)) {
            $this->_inMemoryCache = array();
            
            return $this;
        }
        
        if (empty($method)) {
            $this->_inMemoryCache[$class] = array();
            
            return $this;
        }
        
        if (empty($cacheId)) {
            $this->_inMemoryCache[$class][$method] = array();
            
            return $this;
        }

        if ($this->_usePersistentCache && $method && $cacheId) {
            Tinebase_Core::getCache()->remove($this->_getPersistentCacheId($method, $cacheId));
        }
        
        if (isset($this->_inMemoryCache[$class]) &&
            isset($this->_inMemoryCache[$class][$method]) &&
            (isset($this->_inMemoryCache[$class][$method][$cacheId]) || array_key_exists($cacheId, $this->_inMemoryCache[$class][$method]))
        ) {
            unset($this->_inMemoryCache[$class][$method][$cacheId]);
        };
        
        return $this;
    }

    /**
     * get cache id for persistent cache
     *
     * @param $method
     * @param $cacheId
     * @return string
     */
    protected function _getPersistentCacheId($method, $cacheId)
    {
        $userId = (is_object(Tinebase_Core::getUser())) ? Tinebase_Core::getUser()->getId() : 'NOUSER';
        return $userId . $method . $cacheId;
    }
}