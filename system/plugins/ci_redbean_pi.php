<?php

/**
 * This is a plugin to integrate the RedBean library into the web framework
 * CodeIgniter. It provides a class which your application's models can extend
 * to inherit the functionality needed to use the redbean library.
 *
 * Copyright (C) 2010 Florian Herlings
 *
 * This program is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation; either version 3 of the License, or (at your option)
 * any later version.
 *
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for
 * more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses/>.
 */


require_once 'rb.php';



/**
 * To have nice auto loading in the controller, the plugin provides an
 * autoloader by using the 5.X spl function. This will break PHP4 compatibility,
 * but RedBean does not run with 4.X anyway.
 */
function ci_redbean_pi_autoload($class)
{
    $filename = APPPATH . 'models/' . $class . EXT;
    if (file_exists($filename))
      require($filename);
}
spl_autoload_register('ci_redbean_pi_autoload');





/**
 * This class wraps the redbean functionality. Every class that extends
 * this class will be persisted in the database, when you use the class's
 * save method.
 *
 * @version 0.1
 * @author <mail@florianherlings.de>
 */
class RedbeanModel
{
    /**
     * Holds the redbean instance. The first constructor that is called
     * will instantiate the object, every other instance of every other
     * class that extends the class will have inherited this object.
     * @var RedBean_OODB
     */
  protected static $redbeanInstance;

  /**
   * Holds the static toolbox entity that is used to query the database.
   * @var RedBean_ToolBox
   */
  protected static $toolbox;

    /**
     * The name that is used as a database name. If you do not provide this
     * to the constructor, it will be automatically set.
     * @var string
     */
  protected $className;


    /**
     * The object that is provided by the redbean library. It represents
     * the current database entry.
     * @var RedBean_OODBBean
     */
  protected $redBeanObject;

    /**
     * The database id of the current object. It is only available when
     * the object was correctly persisted to or loaded from the database.
     * @var integer
     */
  protected $objectId;


    /**
     * Constructor.
     *
     * @param RedBean_OODBBean $redBeanObject
     * @param string $className
     */
  public function __construct($redBeanObject = null, $className = null)
  {
    if (!self::$redbeanInstance)
    {
            self::$redbeanInstance = self::createRedbeanInstance();
    }

    if ($redBeanObject && $className)
    {
      $this->className = $className;
      $this->redBeanObject = $redBeanObject;
    }
    else
    {
      $this->className = get_class($this);
      $this->redBeanObject = self::$redbeanInstance->dispense( strtolower($this->className) );
    }
        
    log_message('debug', "RedbeanModel Class Initialized");
  }

    
    /**
     * Creates and returns a redbean db instance using the database settings
     * provided by codeigniter.
     * 
     * @return RedBean_OODB
     */
    protected static function createRedbeanInstance()
    {
        $CI =& get_instance();
        $CI->load->database();

        $host = sprintf("mysql:host=%s;dbname=%s", $CI->db->hostname, $CI->db->database);
        $username = $CI->db->username;
        $password = $CI->db->password;

        
        if (array_key_exists('frozen', (array)$CI->db) && $CI->db->frozen === True)
        {
          self::$toolbox = RedBean_Setup::kickstartFrozen($host, $username, $password);
        }
        else 
        {
          self::$toolbox = RedBean_Setup::kickstartDev($host, $username, $password);
        }
        
        return self::$toolbox->getRedBean();
    }
    
    
    /**
     * Returns the database adapter. You have to use this when querying
     * the database in static model methods.
     *
     * Use it like this in your Model:
     * 
     * class Stuff_model extends RedbeanModel {
     * 
     *   static function findMyStuff()
     *   {
     *     $db = self::getDb();
     *     return $db->get("SELECT * FROM 'STUFF'");
     *   }
     * }
     * 
     * @return RedBean_Adapter_DBAdapter
     */
    protected static function getDb()
    {
      self::createRedbeanInstance();
      return self::$toolbox->getDatabaseAdapter();
    }


  /**
    * Returns an instance of the class the static method was called on.
    * That means if you create a class named "Car" you will recieve an
    * instance of "Car" when calling "Car::load();" if an item with the
    * provided id is persisted in the database.
    * 
    * @param integer $id
    * @return Instance of the called class
    */
  public static function load($id)
  {
    $className = get_called_class();
    if (!self::$redbeanInstance)
    {
            self::$redbeanInstance = self::createRedbeanInstance();
    }
    $object = self::$redbeanInstance->load( strtolower($className), $id);
    return new $className($object, $className);
  }

    
    /**
     * A proxy method to tunnel all set operations to the embedded RedBean
     * object.
     */
  public function __set($name, $value)
  {
    $this->redBeanObject->$name = $value;
  }


    /**
     * A proxy method to tunnel all get operations to the embedded RedBean
     * object.
     */
  public function __get($name)
  {
    return $this->redBeanObject->$name;
  }


    /**
     * Persists the current object to the database.
     * @return integer  The saved item's database id.
     */
  public function save()
  {
    $this->objectId = self::$redbeanInstance->store( $this->redBeanObject );
    return $this->objectId;
  }


}