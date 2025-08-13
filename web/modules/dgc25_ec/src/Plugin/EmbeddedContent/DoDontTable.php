<?php

namespace Drupal\dgc25_ec\Plugin\EmbeddedContent;

use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\embedded_content\EmbeddedContentInterface;
use Drupal\embedded_content\EmbeddedContentPluginBase;
use Drupal\multivalue_form_element\Element\MultiValue;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates an Embedded Content "Do Don't Table".
 *
 * @EmbeddedContent(
 *   id = "dgc25_ec_do_dont_table",
 *   label = @Translation("Do/Don't Table"),
 *   description = @Translation("Renders a Do/Don't Table."),
 * )
 */
class DoDontTable extends EmbeddedContentPluginBase implements EmbeddedContentInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a DoDontTable instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'rows' => NULL,
      'description' => NULL,
      'content_reference' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $content_reference = NULL;
    if ($this->configuration['content_reference']) {
      $node = $this->entityTypeManager
        ->getStorage('node')
        ->load($this->configuration['content_reference']);
      $content_reference = $this
        ->entityTypeManager->getViewBuilder('node')
        ->view($node, 'teaser');
    }
    return [
      'my_template' => [
        '#theme' => 'dgc25_ec_do_dont_table',
        '#description' => $this->configuration['description'],
        '#rows' => $this->configuration['rows'],
      ],
      'entity_ref' => $content_reference,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['description'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Description'),
      '#default_value' => $this->configuration['description']['value'] ?? '',
      '#format' => $this->configuration['description']['format'] ?? '',
      '#allowed_formats' => ['html'],
      '#rows' => 1,
    ];
    $node = NULL;
    if (!empty($this->configuration['content_reference'])) {
      $node = $this->entityTypeManager
        ->getStorage('node')
        ->load($this->configuration['content_reference']);
    }
    $form['content_reference'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Content Reference'),
      '#target_type' => 'node',
      '#default_value' => $node,
      '#selection_settings' => [
        'target_bundles' => [
          'article',
        ],
      ],
    ];
    $form['rows'] = [
      '#type' => 'multivalue',
      '#title' => $this->t("Rows"),
      '#add_more_label' => $this->t('Add Row'),
      '#cardinality' => MultiValue::CARDINALITY_UNLIMITED,
      // This is required because on submit, $this->configuration values are
      // actually stored in the plugin ID instead of just rows when the form
      // is reloaded. This will cause the row weight validation hooks to fail.
      '#default_value' => $this->configuration['rows'] ?? $this->configuration['dgc25_ec_do_dont_table']['rows'] ?? [],
      'do' => [
        '#type' => 'textarea',
        '#title' => $this->t('Do'),
        '#description' => $this->t('This will add text for the Do Colum'),
        '#rows' => 3,
      ],
      'dont' => [
        '#type' => 'textarea',
        '#title' => $this->t("Don't"),
        '#description' => $this->t("This will add text for the Don't Colum"),
        '#rows' => 3,
      ],
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function massageFormValues(array &$values, array $form, FormStateInterface $form_state) {
    // Grab the temporary data and set it as the values to store on the field.
    $values = $form_state->getTemporaryValue('my_value');
    if (!empty($values['rows'])) {
      foreach ($values['rows'] as &$item) {
        // Remove '_actions' keys that do nothing.
        if (is_array($item) && isset($item['_actions'])) {
          unset($item['_actions']);
        }
      }
    }
  }

  /**
   * {@inheritDoc}
   */
  public function validateConfigurationForm(
    array &$form,
    FormStateInterface $form_state
  ) {
    parent::validateConfigurationForm(
      $form,
      $form_state
    );
    // Multivalue field does changes to the form state values in validation
    // hooks. If this is not captured in a temporary value, those changes
    // will be lost, and things like 'add_row' and '_weight' will be back.
    $form_state->setTemporaryValue('my_value', $form_state->getValues());
  }

  /**
   * {@inheritDoc}
   */
  public function isInline(): bool {
    return FALSE;
  }

}
