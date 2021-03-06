<?php

namespace Drupal\Tests\blazy\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\blazy\BlazyMedia;

/**
 * Tests the Blazy image formatter.
 *
 * @coversDefaultClass \Drupal\blazy\Plugin\Field\FieldFormatter\BlazyFormatter
 * @todo it right with NULL formatterInstance.
 *
 * @group blazy
 */
class BlazyFormatterTest extends BlazyKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $data['fields'] = [
      'field_video' => 'image',
      'field_image' => 'image',
      'field_id'    => 'text',
    ];

    // Create contents.
    $bundle = $this->bundle;
    $this->setUpContentTypeTest($bundle, $data);

    $data['settings'] = $this->getFormatterSettings();
    $this->display = $this->setUpFormatterDisplay($bundle, $data);

    $this->setUpContentWithItems($bundle);
    $this->setUpRealImage();

    $this->formatterInstance = $this->getFormatterInstance();
  }

  /**
   * Tests the Blazy formatter methods.
   */
  public function testBlazyFormatterMethods() {
    // Tests type definition.
    $this->typeDefinition = $this->blazyAdminFormatter->getTypedConfig()->getDefinition('blazy.settings');
    $this->assertEquals('Blazy settings', $this->typeDefinition['label']);

    // Tests cache.
    $entity = $this->entity;
    $build = $this->display->build($entity);

    $this->assertInstanceOf('\Drupal\Core\Field\FieldItemListInterface', $this->testItems, 'Field implements interface.');
    $this->assertInstanceOf('\Drupal\blazy\BlazyManagerInterface', $this->formatterInstance->blazyManager(), 'BlazyManager implements interface.');

    // Tests cache tags matching entity ::getCacheTags().
    $item = $entity->{$this->testFieldName};
    $this->assertEquals($item[0]->entity->getCacheTags(), $build[$this->testFieldName][0]['#build']['settings']['file_tags'], 'First image cache tags is as expected');
    $this->assertEquals($item[1]->entity->getCacheTags(), $build[$this->testFieldName][1]['#build']['settings']['file_tags'], 'Second image cache tags is as expected');

    $render = $this->blazyManager->getRenderer()->renderRoot($build);
    $this->assertNotEmpty($render);

    // Tests ::settingsForm.
    $form = [];
    $definition = $this->getFormatterDefinition();
    $definition['_views'] = TRUE;

    // Check for setttings form.
    $form_state = new FormState();
    $elements = $this->formatterInstance->settingsForm($form, $form_state);
    $this->assertArrayHasKey('closing', $elements);

    $formatter_settings = $this->formatterInstance->buildSettings();
    $this->assertArrayHasKey('plugin_id', $formatter_settings);

    // Tests formatter settings.
    $build = $this->display->build($this->entity);

    $result = $this->entity->{$this->testFieldName}->view(['type' => 'blazy']);
    $this->assertEquals('blazy', $result[0]['#theme']);

    $component = $this->display->getComponent($this->testFieldName);

    $this->assertEquals($this->testPluginId, $component['type']);
    $this->assertEquals($this->testPluginId, $build[$this->testFieldName]['#formatter']);

    $format['settings'] = $this->getFormatterSettings();

    $settings = &$format['settings'];

    $settings['breakpoints']     = $this->getDataBreakpoints(TRUE);
    $settings['bundle']          = $this->bundle;
    $settings['blazy']           = TRUE;
    $settings['grid']            = 0;
    $settings['lazy']            = 'blazy';
    $settings['background']      = TRUE;
    $settings['thumbnail_style'] = 'thumbnail';
    $settings['ratio']           = 'enforced';
    $settings['image_style']     = 'blazy_crop';

    try {
      $settings['vanilla'] = TRUE;
      $this->blazyFormatterManager->buildSettings($format, $this->testItems);
    }
    catch (\PHPUnit_Framework_Exception $e) {
    }

    $this->assertEquals($this->testFieldName, $settings['field_name']);

    $settings['vanilla'] = FALSE;
    $this->blazyFormatterManager->buildSettings($format, $this->testItems);

    $this->assertEquals($this->testFieldName, $settings['field_name']);
    $this->assertArrayHasKey('#blazy', $build[$this->testFieldName]);

    // Tests options.
    // Verify no optionsets without a defined function paramater.
    try {
      $options_1a = $this->blazyAdminFormatter->getOptionsetOptions();
    }
    catch (\PHPUnit_Framework_Exception $e) {
    }
    $this->assertEmpty($options_1a);

    $options_1b = $this->blazyAdminFormatter->getOptionsetOptions('image_style');
    $this->assertArrayHasKey('large', $options_1b);

    // Tests grid.
    $new_settings = $this->getFormatterSettings();

    $new_settings['grid']         = 4;
    $new_settings['grid_medium']  = 3;
    $new_settings['grid_small']   = 2;
    $new_settings['media_switch'] = 'blazy_test';
    $new_settings['style']        = 'column';
    $new_settings['image_style']  = 'blazy_crop';

    $this->display->setComponent($this->testFieldName, [
      'type'     => $this->testPluginId,
      'settings' => $new_settings,
      'label'    => 'hidden',
    ]);

    $build = $this->display->build($this->entity);

    // Verify theme_field() is taken over by BlazyGrid::build().
    $this->assertArrayNotHasKey('#blazy', $build[$this->testFieldName]);
  }

  /**
   * Tests the Blazy formatter file.
   *
   * @todo skip not working, so disabled till figured out.
   * @requires module video_embed_media
   */
  public function todoTestBlazyFile() {
    $settings = [
      'media_switch' => 'media',
      'ratio'        => 'fluid',
      'view_mode'    => 'default',
    ];

    $data = [
      'field_name' => 'field_image',
      'plugin_id'  => 'blazy_file',
      'settings'   => $settings + $this->getFormatterSettings(),
    ];
    $display = $this->setUpFormatterDisplay($this->bundle, $data);

    $formatter = $this->getFormatterInstance('blazy_file');
    $build = $this->display->build($this->entity);

    $render = $this->blazyManager->getRenderer()->renderRoot($build);
    $this->assertTrue(strpos($render, 'data-blazy') !== FALSE);
  }

  /**
   * Tests the Blazy formatter faked Media integration.
   *
   * @param mixed|string|bool $input_url
   *   Input URL, else empty.
   * @param bool $expected
   *   The expected output.
   *
   * @dataProvider providerTestBlazyMedia
   */
  public function testBlazyMedia($input_url, $expected) {
    $entity = $this->entity;

    $settings = [
      'input_url'       => $input_url,
      'source_field'    => $this->testFieldName,
      'view_mode'       => 'default',
      'bundle'          => $this->bundle,
      'thumbnail_style' => 'thumbnail',
      'uri'             => $this->uri,
    ];

    $build = $this->display->build($entity);

    $render = BlazyMedia::build($entity, $settings);

    if ($expected && $render) {
      $this->assertNotEmpty($render);

      $field[0] = $render;
      $wrap = BlazyMedia::wrap($field, $settings);
      $this->assertNotEmpty($wrap);

      $render = $this->blazyManager->getRenderer()->renderRoot($build[$this->testFieldName]);
      $this->assertTrue(strpos($render, 'data-blazy') !== FALSE);
    }
    else {
      $this->assertFalse($render);
    }
  }

  /**
   * Provide test cases for ::testBlazyMedia().
   *
   * @return array
   *   An array of tested data.
   */
  public function providerTestBlazyMedia() {
    return [
      ['', TRUE],
      ['http://xyz123.com/x/123', FALSE],
      ['user', TRUE],
    ];
  }

}
