<?php

declare(strict_types=1);

namespace ZnoteX\Tests\Security;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/engine/function/plugin_settings.php';

final class PluginSettingsTest extends TestCase
{
	private const TEST_PLUGIN = 'zztest_settings_plugin';

	private function pluginDir(): string {
		return ZNOTE_PLUGIN_DIR . '/' . self::TEST_PLUGIN;
	}

	protected function tearDown(): void
	{
		$dir = $this->pluginDir();
		if (is_file($dir . '/settings.json')) {
			unlink($dir . '/settings.json');
		}
		if (is_dir($dir)) {
			rmdir($dir);
		}
	}

	private function writeSchema(array $data): void
	{
		mkdir($this->pluginDir(), 0775, true);
		file_put_contents($this->pluginDir() . '/settings.json', json_encode($data));
	}

	public function testHasIsFalseWithoutAFile(): void
	{
		$this->assertFalse(\znote_plugin_settings_has(self::TEST_PLUGIN));
	}

	public function testSchemaIsEmptyForAnUnknownPlugin(): void
	{
		$this->assertSame([], \znote_plugin_settings_schema('../../../etc/passwd'));
	}

	public function testSchemaParsesValidFields(): void
	{
		$this->writeSchema(['fields' => [
			['key' => 'api_key', 'type' => 'text', 'label' => 'API key', 'default' => ''],
			['key' => 'enabled', 'type' => 'bool', 'default' => '1'],
			['key' => 'mode', 'type' => 'select', 'default' => 'test', 'options' => ['test' => 'Test', 'live' => 'Live']],
			['key' => 'max_items', 'type' => 'int', 'default' => '10', 'min' => 1, 'max' => 100],
		]]);

		$schema = \znote_plugin_settings_schema(self::TEST_PLUGIN);

		$this->assertTrue(\znote_plugin_settings_has(self::TEST_PLUGIN));
		$this->assertSame(['api_key', 'enabled', 'mode', 'max_items'], array_keys($schema));
		$this->assertSame('API key', $schema['api_key']['label']);
		$this->assertSame(1, $schema['max_items']['min']);
		$this->assertSame(100, $schema['max_items']['max']);
	}

	public function testSchemaDropsFieldsWithAnUnknownType(): void
	{
		$this->writeSchema(['fields' => [
			['key' => 'good', 'type' => 'text'],
			['key' => 'bad', 'type' => 'not_a_real_type'],
		]]);

		$schema = \znote_plugin_settings_schema(self::TEST_PLUGIN);

		$this->assertArrayHasKey('good', $schema);
		$this->assertArrayNotHasKey('bad', $schema);
	}

	public function testSchemaDropsFieldsWithAnInvalidKey(): void
	{
		$this->writeSchema(['fields' => [
			['key' => 'ok_key', 'type' => 'text'],
			['key' => 'has spaces', 'type' => 'text'],
			['key' => '../traversal', 'type' => 'text'],
			['key' => '', 'type' => 'text'],
		]]);

		$schema = \znote_plugin_settings_schema(self::TEST_PLUGIN);

		$this->assertSame(['ok_key'], array_keys($schema));
	}

	public function testMalformedJsonYieldsAnEmptySchema(): void
	{
		mkdir($this->pluginDir(), 0775, true);
		file_put_contents($this->pluginDir() . '/settings.json', '{not valid json');

		$this->assertSame([], \znote_plugin_settings_schema(self::TEST_PLUGIN));
	}

	public function testStorageKeyMatchesTheExtensionApiNamespace(): void
	{
		$this->assertSame(
			'plugin:my_plugin:setting:api_key',
			\znote_plugin_settings_storage_key('my_plugin', 'api_key')
		);
	}

	public function testSanitizeBoolCoercesAnyTruthyInputToOneOrZero(): void
	{
		$field = ['type' => 'bool'];
		$this->assertSame('1', \znote_plugin_settings_sanitize_field($field, '1'));
		$this->assertSame('1', \znote_plugin_settings_sanitize_field($field, 'on'));
		$this->assertSame('0', \znote_plugin_settings_sanitize_field($field, null));
		$this->assertSame('0', \znote_plugin_settings_sanitize_field($field, '0'));
	}

	public function testSanitizeIntRejectsNonNumericInput(): void
	{
		$field = ['type' => 'int', 'min' => null, 'max' => null];
		$this->assertNull(\znote_plugin_settings_sanitize_field($field, 'not a number'));
		$this->assertNull(\znote_plugin_settings_sanitize_field($field, '12; DROP TABLE accounts'));
	}

	public function testSanitizeIntClampsToMinAndMax(): void
	{
		$field = ['type' => 'int', 'min' => 1, 'max' => 10];
		$this->assertSame('1', \znote_plugin_settings_sanitize_field($field, '-5'));
		$this->assertSame('10', \znote_plugin_settings_sanitize_field($field, '500'));
		$this->assertSame('5', \znote_plugin_settings_sanitize_field($field, '5'));
	}

	public function testSanitizeSelectRejectsAValueOutsideItsOptions(): void
	{
		$field = ['type' => 'select', 'options' => ['a' => 'A', 'b' => 'B']];
		$this->assertSame('a', \znote_plugin_settings_sanitize_field($field, 'a'));
		$this->assertNull(\znote_plugin_settings_sanitize_field($field, 'injected'));
	}

	public function testSanitizeChecklistKeepsOnlyKnownOptions(): void
	{
		$field = ['type' => 'checklist', 'options' => ['a' => 'A', 'b' => 'B', 'c' => 'C']];
		$this->assertSame('a,c', \znote_plugin_settings_sanitize_field($field, ['a', 'c', 'not_an_option']));
	}

	public function testSanitizeTextRejectsNonScalarInput(): void
	{
		$field = ['type' => 'text'];
		$this->assertNull(\znote_plugin_settings_sanitize_field($field, ['array', 'not', 'scalar']));
		$this->assertSame('hello', \znote_plugin_settings_sanitize_field($field, 'hello'));
	}
}
