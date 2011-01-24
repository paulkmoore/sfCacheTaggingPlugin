<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2011 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  /**
   * Toolkit with frequently used methods.
   *
   * @package sfCacheTaggingPlugin
   * @subpackage util
   * @author Ilya Sabelnikov <fruit.dev@gmail.com>
   */
  class sfCacheTaggingToolkit
  {
    const NAMESPACE_CACHE_TAGS = 'symfony.cache.tags';
    const TEMPLATE_NAME        = 'Cachetaggable';

    /**
     * @throws sfCacheDisabledException   when "sf_cache" is OFF
     * @throws sfInitializationException  if context is not initialized
     * @throws sfConfigurationException   on plugin configuration issues
     * 
     * @return sfCacheTagging
     */
    public static function getTaggingCache ()
    {
      if (! sfConfig::get('sf_cache'))
      {
        throw new sfCacheDisabledException('Cache "sf_cache" is disabled');
      }

      if (! sfContext::hasInstance())
      {
        throw new sfCacheMissingContextException(
          sprintf('Content is not initialized for "%s"', __CLASS__)
        );
      }

      $viewCacheManager = sfContext::getInstance()->getViewCacheManager();

      if (! $viewCacheManager instanceof sfViewCacheTagManager)
      {
        throw new sfConfigurationException(
          'sfCacheTaggingPlugin is not properly configured'
        );
      }

      return $viewCacheManager->getTaggingCache();
    }

    /**
     * Build version base on currenct microtime
     *
     * @param double|int $microtime
     * @return string Number list the represents a current timestamp
     */
    public static function generateVersion ($microtime = null)
    {
      $microtime = null === $microtime ? microtime(true) : $microtime;

      return sprintf("%0.0f", pow(10, self::getPrecision()) * $microtime);
    }

    /**
     * Returns app.yml precision, otherwise, return default value (5)
     *
     * @return int
     */
    public static function getPrecision ()
    {
      $presision = (int) sfConfig::get(
        'app_sfcachetaggingplugin_microtime_precision', 5
      );

      if (0 > $presision || 6 < $presision)
      {
        throw new OutOfRangeException(sprintf(
          'Value of "app_sfcachetaggingplugin_microtime_precision" is ' .
            'out of the range (0…6)'
        ));
      }

      return $presision;
    }

    /**
     * @return string
     */
    public static function getMetadataClassName ()
    {
      return (string) sfConfig::get(
        'app_sfcachetaggingplugin_metadata_class', 'CacheMetadata'
      );
    }

    /**
     *
     * @return string
     */
    public static function getModelTagNameSeparator ()
    {
      return (string) sfConfig::get(
        'app_sfcachetaggingplugin_model_tag_name_separator', sfCache::SEPARATOR
      );
    }
    
    /**
     * Format passed tags to the array
     *
     * @param mixed $tags array|Doctrine_Collection_Cachetaggable|
     *                    Doctrine_Record|ArrayIterator|Iterator
     * @throws InvalidArgumentException
     * @return array
     */
    public static function formatTags ($tags)
    {
      $tagsToReturn = array();

      if (is_array($tags))
      {
        $tagsToReturn = $tags;
      }
      elseif ($tags instanceof Doctrine_Collection_Cachetaggable)
      {
        $tagsToReturn = $tags->getTags();
      }
      elseif ($tags instanceof Doctrine_Record)
      {
        $table = $tags->getTable();

        if (! $table->hasTemplate(sfCacheTaggingToolkit::TEMPLATE_NAME))
        {
          throw new InvalidArgumentException(sprintf(
            'Object "%s" should have the "%s" template',
            $table->getClassnameToReturn(),
            sfCacheTaggingToolkit::TEMPLATE_NAME
          ));
        }

        $tagsToReturn = $tags->getTags();
      }
      # Doctrine_Collection_Cachetaggable and Doctrine_Record are
      # instances of ArrayAccess
      # this check should be after them
      elseif ($tags instanceof ArrayIterator || $tags instanceof ArrayObject)
      {
        $tagsToReturn = $tags->getArrayCopy();
      }
      elseif (
          $tags instanceof IteratorAggregate
        ||
          $tags instanceof Iterator
      )
      {
        foreach ($tags as $key => $value)
        {
          $tagsToReturn[$key] = $value;
        }
      }
      else
      {
        throw new InvalidArgumentException(
          sprintf(
            'Invalid argument\'s type "%s". ' .
            'See acceptable types in the PHPDOC of "%s"',
            sprintf(
              '%s%s',
              gettype($tags),
              is_object($tags) ? '('.get_class($tags).')' : ''
            ),
            __METHOD__
          )
        );
      }

      return $tagsToReturn;
    }

    /**
     * Listens on "component.method_not_found"
     *
     * @param sfEvent $event
     * @return void
     */
    public static function listenOnComponentMethodNotFoundEvent (sfEvent $event)
    {
      $event->setProcessed(true);

      try
      {
        $taggingCache = sfCacheTaggingToolkit::getTaggingCache();
      }
      catch (sfCacheDisabledException $e)
      {
        sfCacheTaggingToolkit::notifyApplicationLog(
          __CLASS__, $e->getMessage(), sfLogger::NOTICE
        );
        
        return;
      }
      catch (sfConfigurationException $e)
      {
        sfCacheTaggingToolkit::notifyApplicationLog(
          __CLASS__, $e->getMessage(), sfLogger::WARNING
        );

        return;
      }

      try
      {
        $callable = array(
          new sfViewCacheTagManagerBridge($taggingCache), $event['method']
        );

        $event
          ->setReturnValue(call_user_func_array($callable, $event['arguments']))
        ;
      }
      catch (BadMethodCallException $e)
      {
        $event->setProcessed(false);
      }
    }

    /**
     * If tag name provider is registerd, then it passes object class name
     * to it.
     *
     * Useful, when backend works with classes prefixed by "Backend*Models"
     * and frontend with "Frontend*Models", and tags should be equal to "Models"
     *
     * @staticvar array $classNames stores function calls results
     * @param string $className get_class of Doctrine_Record's model
     * @return string
     */
    public static function getBaseClassName ($className)
    {
      static $classNames = array();

      $callableArray = sfConfig::get(
        'app_sfcachetaggingplugin_object_class_tag_name_provider'
      );

      if (null !== $callableArray)
      {
        if (! array_key_exists($className, $classNames))
        {
          $classNames[$className] = call_user_func($callableArray, $className);
        }

        return $classNames[$className];
      }

      return $className;
    }

    /**
     * @param mixed   $object   object or string, or null
     * @param string  $message
     * @param int     $priority sfLogger::* see constants
     */
    public static function notifyApplicationLog ($object, $message, $priority = null)
    {
      ProjectConfiguration::getActive()
        ->getEventDispatcher()
        ->notify(new sfEvent($object, 'application.log', array($message)))
      ;
    }
  }
