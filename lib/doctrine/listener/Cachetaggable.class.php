<?php

class Doctrine_Template_Listener_Cachetaggable extends Doctrine_Record_Listener
{
  /**
   * Array of sortable options
   *
   * @var array
   */
  protected $_options = array();

  /**
   * __construct
   *
   * @param array $options
   * @return void
   */
  public function __construct(array $options)
  {
    $this->_options = $options;
  }

  private function getCache ()
  {
    if (sfContext::hasInstance() and sfConfig::get('sf_cache'))
    {
      $manager = sfContext::getInstance()->getViewCacheManager();

      if (! $manager instanceof sfViewCacheTagManager)
      {
        throw new sfConfigurationException('sfCacheTaggingPlugin will work only with own sfViewCacheTagManager. Please, edit yours factories.yml to fix this problem');
      }

      $cache = $manager->getCache();

      if (! $cache instanceof sfCacheTagInterface)
      {
        throw new sfConfigurationException('sfCacheTaggingPlugin will work only with own sf%cache_engine%CacheTag class. Please, edit yours factories.yml to fix this problem');
      }

      return $cache;
    }

    return null;
  }

  /**
   * @param string $Doctrine_Event
   * @return void
   */
  public function postDelete(Doctrine_Event $event)
  {
    if (! is_null($cache = $this->getCache()))
    {
      $cache->removeTag($event->getInvoker()->getTagName());
    }
  }

  /**
   * @param string $Doctrine_Event
   * @return void
   */
  public function preSave (Doctrine_Event $event)
  {
    # transform "0.20573100 1258907456" to "1258907456205731"
    $version = substr(implode('', array_reverse(explode(' ', substr(microtime(), 2)))), -2);

    $event->getInvoker()->setObjectVersion($version);
  }

  public function postSave (Doctrine_Event $event)
  {
    if (! is_null($cache = $this->getCache()))
    {
      $object = $event->getInvoker();

      $cache->setTag(
        $object->getTagName(),
        $object->getObjectVersion(),
        sfConfig::get('app_sfcachetaggingplugin_tag_lifetime', 86400)
      );
    }
  }
}