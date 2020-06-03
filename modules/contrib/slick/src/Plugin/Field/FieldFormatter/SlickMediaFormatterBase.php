<?php

namespace Drupal\slick\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\blazy\BlazyOEmbed;
use Drupal\blazy\Plugin\Field\FieldFormatter\BlazyMediaFormatterBase;
use Drupal\slick\SlickFormatterInterface;
use Drupal\slick\SlickManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Slick media formatters with field details, and oEmbed support.
 *
 * @see Drupal\slick_media\Plugin\Field\FieldFormatter\SlickMediaFormatter
 */
abstract class SlickMediaFormatterBase extends BlazyMediaFormatterBase implements ContainerFactoryPluginInterface {

  use SlickFormatterTrait;

  /**
   * The image style entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $imageStyleStorage;

  /**
   * Constructs a SlickMediaFormatter object.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, LoggerChannelFactoryInterface $logger_factory, BlazyOEmbed $blazy_oembed, SlickFormatterInterface $formatter, EntityStorageInterface $image_style_storage, SlickManagerInterface $manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings, $logger_factory, $blazy_oembed, $formatter);

    // @todo $this->imageFactory = $image_factory;
    $this->imageStyleStorage = $image_style_storage;
    $this->formatter         = $formatter;
    $this->manager           = $manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('logger.factory'),
      $container->get('blazy.oembed'),
      $container->get('slick.formatter'),
      $container->get('entity.manager')->getStorage('image_style'),
      $container->get('slick.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getScopedFormElements() {
    $multiple = $this->fieldDefinition->getFieldStorageDefinition()->isMultiple();

    return [
      'namespace' => 'slick',
      'grid_form' => $multiple,
      'style' => $multiple,
    ] + parent::getScopedFormElements();
  }

}
