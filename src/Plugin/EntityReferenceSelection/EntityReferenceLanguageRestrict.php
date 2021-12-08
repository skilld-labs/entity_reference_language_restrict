<?php

namespace Drupal\entity_reference_language_restrict\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Entity reference with language restriction selection plugin.
 *
 * @EntityReferenceSelection(
 *   id = "entity_reference_language_restrict",
 *   label = @Translation("Entity reference with language restriction"),
 *   group = "entity_reference_language_restrict",
 *   weight = 0,
 *   deriver = "Drupal\Core\Entity\Plugin\Derivative\DefaultSelectionDeriver"
 * )
 */
class EntityReferenceLanguageRestrict extends DefaultSelection {

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a new DefaultSelection object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info service.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, AccountInterface $current_user, EntityFieldManagerInterface $entity_field_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, EntityRepositoryInterface $entity_repository, LanguageManagerInterface $language_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $module_handler, $current_user, $entity_field_manager, $entity_type_bundle_info, $entity_repository);
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('current_user'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity.repository'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'language_restriction' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $configuration = $this->getConfiguration();

    $form['language_restriction'] = [
      '#type' => 'select',
      '#title' => $this->t('Restrict available items by language'),
      '#options' => $this->getLanguageRestrictionOptions(),
      '#default_value' => $configuration['language_restriction'],
    ];

    return $form;
  }

  /**
   * Get language_restriction options list.
   *
   * @return array
   *   Options list.
   */
  protected function getLanguageRestrictionOptions() {

    $options = [
      '' => $this->t('No restrictions'),
      LanguageInterface::LANGCODE_SITE_DEFAULT => $this->t("Site's default language (@language)", ['@language' => $this->languageManager->getDefaultLanguage()->getName()]),
      'current_interface' => $this->t('Interface text language selected for page'),
      'authors_default' => $this->t("Author's preferred language"),
    ];

    $languages = $this->languageManager->getLanguages(LanguageInterface::STATE_ALL);
    foreach ($languages as $langcode => $language) {
      $options[$langcode] = $language->isLocked() ? t('- @name -', ['@name' => $language->getName()]) : $language->getName();
    }

    return $options;
  }

  /**
   * Return language restriction settings for current field instance.
   *
   * @return string
   *   Language restriction settings.
   */
  protected function getLanguageRestriction() {
    $configuration = $this->getConfiguration();
    $language = '';

    if ($configuration['language_restriction']) {
      $language_interface = $this->languageManager->getCurrentLanguage();
      switch ($configuration['language_restriction']) {

        case LanguageInterface::LANGCODE_SITE_DEFAULT:
          $language = $this->languageManager->getDefaultLanguage()->getId();
          break;

        case 'current_interface':
          $language = $language_interface->getId();
          break;

        case 'authors_default':
          $language_code = $this->currentUser->getPreferredLangcode();
          $language = $language_code ? $language_code : $language_interface->getId();
          break;

        default:
          $language = $configuration['language_restriction'];
          break;
      }
    }

    return $language;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);
    $configuration = $this->getConfiguration();
    $entity_type = $this->entityTypeManager->getDefinition($configuration['target_type']);

    if (($langcode = $this->getLanguageRestriction()) && ($langcode_key = $entity_type->getKey('langcode'))) {
      $query->condition($langcode_key, $langcode);
    }

    return $query;
  }

}
