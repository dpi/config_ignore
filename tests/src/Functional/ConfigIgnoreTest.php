<?php

namespace Drupal\Tests\config_ignore\Functional;

/**
 * Test functionality of config_ignore module.
 *
 * @package Drupal\Tests\config_ignore\Functional
 *
 * @group config_ignore
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ConfigIgnoreTest extends ConfigIgnoreBrowserTestBase {

  /**
   * Verify that the Sync. table gets update with appropriate ignore actions.
   */
  public function testSyncTableUpdate() {

    $this->config('system.site')->set('name', 'Test import')->save();
    $this->config('system.date')->set('first_day', '0')->save();
    $this->config('config_ignore.settings')->set('ignored_config_entities', ['system.site'])->save();

    $this->doExport();

    // Login with a user that has permission to sync. config.
    $this->drupalLogin($this->drupalCreateUser(['synchronize configuration']));

    // Change the site name, which is supposed to look as an ignored change
    // in on the sync. page.
    $this->config('system.site')->set('name', 'Test import with changed title')->save();
    $this->config('system.date')->set('first_day', '1')->save();

    // Validate that the sync. table informs the user that the config will be
    // ignored.
    $this->drupalGet('admin/config/development/configuration');
    $this->assertSession()->linkExists('Config Ignore Settings');
    /** @var \Behat\Mink\Element\NodeElement[] $table_content */
    $table_content = $this->xpath('//table[@id="edit-ignored"]//td');

    $table_values = [];
    foreach ($table_content as $item) {
      $table_values[] = $item->getHtml();
    }

    $this->assertTrue(in_array('system.site', $table_values));
    $this->assertFalse(in_array('system.date', $table_values));
  }

  /**
   * Verify that the settings form works.
   */
  public function testSettingsForm() {
    // Login with a user that has permission to import config.
    $this->drupalLogin($this->drupalCreateUser(['import configuration']));

    $edit = [
      'ignored_config_entities' => 'config.test',
    ];

    $this->drupalGet('admin/config/development/configuration/ignore');
    $this->submitForm($edit, t('Save configuration'));

    $settings = $this->config('config_ignore.settings')->get('ignored_config_entities');

    $this->assertEqual($settings, ['config.test']);
  }

}
