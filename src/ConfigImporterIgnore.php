<?php

namespace Drupal\config_ignore;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Config\ConfigImporter;
use Drupal\user\SharedTempStore;

/**
 * Class ConfigImporterIgnore.
 *
 * @package Drupal\config_ignore
 */
class ConfigImporterIgnore {

  /**
   * Gather config that we want to keep.
   *
   * Saves the values, that are to be ignored, so that we can put them back in
   * later on.
   *
   * @param array $context
   *   Context of the config import.
   * @param ConfigImporter $config_importer
   *   Config importer object.
   */
  public static function preImport(array &$context, ConfigImporter $config_importer) {
    $config_to_ignore = [];
    $config_ignore_settings = \Drupal::config('config_ignore.settings')->get('ignored_config_entities');
    foreach (['delete', 'create', 'rename', 'update'] as $op) {
      // For now, we only support updates.
      if ($op == 'update') {
        foreach ($config_importer->getUnprocessedConfiguration($op) as $config) {
          foreach ($config_ignore_settings as $config_ignore_setting) {
            // Check if the last character in the string is an asterisk.
            // If so, it means that it is a wildcard.
            if (Unicode::substr($config_ignore_setting, -1) == '*') {
              // Remove the asterisk character from the end of the string.
              $config_ignore_setting = rtrim($config_ignore_setting, '*');
              // Test if the start of the config, we are checking, are matching
              // the $config_ignore_setting string. If it is a match, mark
              // that config name to be ignored.
              if (Unicode::substr($config, 0, strlen($config_ignore_setting)) == $config_ignore_setting) {
                $config_to_ignore[$op][$config] = \Drupal::config($config)->getRawData();
              }
            }
            // If string does not contain an asterisk in the end, just compare
            // the two strings, and if they match, mark that config name to be
            // ignored.
            elseif ($config == $config_ignore_setting) {
              $config_to_ignore[$op][$config] = \Drupal::config($config)->getRawData();
            }
          }
        }
      }
      // We do not support core.extension.
      unset($config_to_ignore[$op]['core.extension']);
    }

    /** @var SharedTempStore $temp_store */
    $temp_store = \Drupal::service('user.shared_tempstore')->get('config_ignore');
    $temp_store->set('config_to_ignore', $config_to_ignore);

    $context['finished'] = 1;
  }

  /**
   * Replace the overridden values with the original ones.
   *
   * @param array $context
   *   Context of the config import.
   * @param ConfigImporter $config_importer
   *   Config importer object.
   */
  public static function postImport(array &$context, ConfigImporter $config_importer) {
    /** @var SharedTempStore $temp_store */
    $temp_store = \Drupal::service('user.shared_tempstore')->get('config_ignore');
    $config_to_ignore = $temp_store->get('config_to_ignore');
    foreach ($config_to_ignore as $op) {
      foreach ($op as $config_name => $config) {
        /** @var \Drupal\Core\Config\Config $config_to_restore */
        $config_to_restore = \Drupal::service('config.factory')->getEditable($config_name);
        $config_to_restore->setData($config)->save();
      }
    }
    $context['finished'] = 1;
    $temp_store->delete('config_to_ignore');
  }

}
