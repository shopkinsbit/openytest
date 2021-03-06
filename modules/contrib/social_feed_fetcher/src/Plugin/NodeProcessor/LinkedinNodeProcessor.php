<?php

namespace Drupal\social_feed_fetcher\Plugin\NodeProcessor;

use Drupal\Core\File\FileSystemInterface;
use Drupal\social_feed_fetcher\PluginNodeProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class LinkedinNodeProcessor.
 *
 * @package Drupal\social_feed_fetcher\Plugin\NodeProcessor
 *
 * @PluginNodeProcessor(
 *   id = "linkedin_processor",
 *   label = @Translation("Linkedin node processor")
 * )
 */
class LinkedinNodeProcessor extends PluginNodeProcessorPluginBase {

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function processItem($source, $data_item) {
    $item = $data_item['updateContent']['companyStatusUpdate']['share'];
    if (!is_array($item)) {
      return FALSE;
    }
    if (!$this->isPostIdExist($item['id'])) {

      $file = NULL;
      if (isset($item['content'])) {
        $file = $this->processImageFile($item['content']['eyebrowUrl'], 'public://linkedin');
      }

      $node = $this->entityStorage->create([
        'type' => 'social_post',
        'title' => 'Post ID: ' . $item['id'],
        'field_platform' => ucwords($source),
        'field_id' => $item['id'],
        'field_post' => [
          'value' => social_feed_fetcher_linkify(html_entity_decode($item['comment'] ?: '')),
          'format' => $this->config->get('formats_post_format'),
        ],
        'field_social_feed_link' => [
          'uri' => $item['link'] ?? '',
          'title' => '',
          'options' => [],
        ],
        'field_sp_image' => [
          'target_id' => $file,
        ],
        'field_posted' => [
          'value' => $this->setPostTime('now'),
        ],
      ]);
      return $node->save();
    }
    return FALSE;
  }

  /**
   * Save external file.
   *
   * @param string $filename
   *   File name.
   * @param string $path
   *   Current path.
   *
   * @return int
   *   Id of the file entity.
   */
  public function processImageFile($filename, $path) {
    $name = basename($filename);
    $response = $this->httpClient->get($filename);
    $data = $response->getBody();
    $uri = $path . '/' . $name;
    $this->fileSystem->prepareDirectory($path, FileSystemInterface::CREATE_DIRECTORY);
    $uri = explode('?', $uri);
    if (!file_save_data($data, $uri[0], FileSystemInterface::EXISTS_REPLACE)) {
      return 0;
    }
    return file_save_data($data, $uri[0], FileSystemInterface::EXISTS_REPLACE)->id();
  }

}
