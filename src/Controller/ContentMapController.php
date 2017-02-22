<?php

namespace Drupal\content_map\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for config module routes.
 */
class ContentMapController implements ContainerInjectionInterface {

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Constructs a FeaturesController object.
   *
   * @param \Drupal\Core\Entity\EntityFieldManager|\Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   * @param \Drupal\Core\Entity\EntityTypeManager|\Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   */
  public function __construct(EntityFieldManagerInterface $entity_field_manager,EntityTypeBundleInfoInterface $entity_type_bundle_info, EntityTypeManagerInterface $entity_type_manager) {
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * View the content map.
   */
  public function viewMap() {

    $return = "digraph MAP {";
    $return .= "  rankdir=&quot;LR&quot;;";

    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_machine_name => $type) {
      if (!in_array($entity_type_machine_name, $this->get_exceptions()) && $type instanceof ContentEntityTypeInterface) {
        if ($type->hasKey('bundle')) {
          foreach ($this->entityTypeBundleInfo->getBundleInfo($entity_type_machine_name) as $bundle_machine_name => $bundle) {
            foreach($this->get_entity_reference($entity_type_machine_name, $bundle_machine_name) as $ref) {
              $return .= "  " . $entity_type_machine_name . '_' . $bundle_machine_name . " -&gt; " . $ref . ";";
            }
          }
        }
        else {
          foreach($this->get_entity_reference($entity_type_machine_name, $entity_type_machine_name) as $ref) {
            $return .= "  " . $entity_type_machine_name . " -&gt; " . $ref . ";";
          }
        }
      }
    }
    $return .= "}";

    return [
      '#type' => 'inline_template',
      '#template' => '<div id="content_map" data-dot="' . $return . '"></div>',
      '#attached' =>[
        'library' => [
          'content_map/content-map-js',
          'content_map/content-map-css',
        ],
      ],
    ];
  }

  /**
   * Get all references for a couple of entity_type / bundle.
   *
   * @param string $entity_type=
   *   The entity type name.
   *
   * @param string $bundle
   *   The entity type bundle name.
   *
   * @return array
   *   An array containing all references.
   */
  protected function get_entity_reference($entity_type, $bundle) {
    $reference = [];

    /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $bundle_fields */
    $bundle_fields = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);
    foreach ($bundle_fields as $field_key => $bundle_field) {
      if (in_array($bundle_field->getType(), ['entity_reference', 'entity_reference_revisions'])) {
        if (!$this->entityTypeManager->getDefinition($bundle_field->getSettings()['target_type'])->get('bundle_entity_type')) {
          if (!in_array($bundle_field->getSettings()['target_type'], $this->get_exceptions())) {
            $reference[] = $bundle_field->getSettings()['target_type'];
          }
        }
        else {
          if (isset($bundle_field->getSettings()['handler_settings']['target_bundles']) && $bundle_field->getSettings()['handler_settings']['target_bundles']) {
            foreach ($bundle_field->getSettings()['handler_settings']['target_bundles'] as $target_bundle) {
              if (!in_array($bundle_field->getSettings()['target_type'] . '_' . $target_bundle, $this->get_exceptions())) {
                $reference[] = $bundle_field->getSettings()['target_type'] . '_' . $target_bundle;
              }
            }
          }
        }
      }
    }

    return $reference;
  }

  protected function get_exceptions() {
    return [
      'user',
      'paragraphs_type',
      'node_type',
      'media_bundle',
      'taxonomy_vocabulary',
      'entity_queue',
    ];
  }

}
