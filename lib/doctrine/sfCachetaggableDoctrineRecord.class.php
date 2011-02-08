<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2011 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  /**
   * @package sfCacheTaggingPlugin
   * @subpackage doctrine
   * @author Ilya Sabelnikov <fruit.dev@gmail.com>
   */
  abstract class sfCachetaggableDoctrineRecord extends sfDoctrineRecord
  {
    /**
     * @var string $templateName Template (behavior) name
     * @return Doctrine_Template
     */
    protected function getTempleteWithInvoker ($templateName, $method)
    {
      $table = $this->getTable();

      if ( ! $table->hasTemplate($templateName))
      {
        throw new InvalidArgumentException(sprintf(
            'Template "%s" is not registered', $templateName
        ));
      }

      $template = $table->getTemplate($templateName);
      $template->setInvoker($this);
      
      $table->setMethodOwner($method, $template);

      return $template;
    }

    /**
     * @return Doctrine_Record
     */
    public function updateObjectVersion ()
    {
      return $this->getCachetaggable('updateObjectVersion')->updateObjectVersion();
    }

    /**
     * @return Doctrine_Template_Cachetaggable
     */
    protected function getCachetaggable ($method)
    {
      return $this->getTempleteWithInvoker('Cachetaggable', $method);
    }

    /**
     * @var boolean $recursively (whether to fetch tags recursively from all joined tables)
     * @return array Object tags
     */
    public function getTags ($recursively = false)
    {
      return $this->getCachetaggable('getTags')->getTags($recursively);
    }

    /**
     * @return string
     */
    public function obtainTagName ()
    {
      return $this->getCachetaggable('obtainTagName')->obtainTagName();
    }

    /**
     * @return string
     */
    public function obtainObjectVersion ()
    {
      return $this->getCachetaggable('obtainObjectVersion')->obtainObjectVersion();
    }
  }