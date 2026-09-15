<?php

declare(strict_types=1);

namespace ZnoteX\Tests\Security;

use PHPUnit\Framework\TestCase;
use ZipArchive;

final class UpdateInstallSecurityTest extends TestCase
{
	private static string $realPublicKeyFile;
	private static string $realPublicKeyBackup;

	public static function setUpBeforeClass(): void
	{
		// znote_update_verify_manifest() always reads the real production
		// public key from disk. To exercise that real function (rather than
		// re-implement its logic in the test) we swap that file for our
		// disposable test key for the duration of this class, and restore
		// the real one in tearDownAfterClass() even if a test fails.
		self::$realPublicKeyFile = dirname(__DIR__, 2) . '/engine/update-public.pem';
		self::$realPublicKeyBackup = file_get_contents(self::$realPublicKeyFile);
		file_put_contents(
			self::$realPublicKeyFile,
			file_get_contents(__DIR__ . '/../fixtures/test-update-public.pem')
		);
	}

	public static function tearDownAfterClass(): void
	{
		file_put_contents(self::$realPublicKeyFile, self::$realPublicKeyBackup);
	}

	/** @dataProvider traversalPathProvider */
	public function testPathTraversalIsRejected(string $malicious): void
	{
		$this->assertSame('', \znote_update_path($malicious));
	}

	public static function traversalPathProvider(): array
	{
		return [
			'parent directory' => ['../../../etc/passwd'],
			'nested traversal' => ['engine/../../config.local.php'],
			'bare dot dot' => ['..'],
			'dot segment' => ['engine/./config.php'],
			'null byte' => ["engine/config\0.php"],
			'colon (windows drive / ADS)' => ['C:/Windows/System32'],
			'wildcard' => ['engine/*.php'],
			'empty segment' => ['engine//config.php'],
		];
	}

	public function testAnOrdinaryPackagedPathIsAccepted(): void
	{
		$this->assertSame('engine/function/general.php', \znote_update_path('engine/function/general.php'));
	}

	public function testBackslashesAreNormalisedToForwardSlashes(): void
	{
		$this->assertSame('engine/function/general.php', \znote_update_path('engine\\function\\general.php'));
	}

	/** @dataProvider protectedPathProvider */
	public function testProtectedPathsCanNeverBeOverwrittenByAnUpdate(string $path): void
	{
		$this->assertTrue(\znote_update_protected($path));
	}

	public static function protectedPathProvider(): array
	{
		return [
			'config.php' => ['config.php'],
			'config.local.php' => ['config.local.php'],
			'a plugin file' => ['plugins/shop_coupons/plugin.php'],
			'a theme file' => ['layouts/bloodfang/shells/default.php'],
			'the update storage itself' => ['engine/update/installed.json'],
			'uploaded theme images' => ['engine/img/theme/banner.png'],
			'the installer' => ['install/index.php'],
			'a release artifact' => ['release/2.0.3/znotex-core-2.0.3.zip'],
			'git internals' => ['.git/config'],
			'github workflows' => ['.github/workflows/ci.yml'],
		];
	}

	public function testAnOrdinaryCoreFileIsNotProtected(): void
	{
		$this->assertFalse(\znote_update_protected('engine/function/general.php'));
	}

	public function testManifestSignatureVerification(): void
	{
		[$manifest, $json, $signature] = $this->signedManifest();

		$result = \znote_update_verify_manifest($json, $signature);
		$this->assertTrue($result['ok']);
		$this->assertSame($manifest['version'], $result['manifest']['version']);
	}

	public function testTamperedManifestBodyFailsVerificationEvenWithAValidSignature(): void
	{
		[, $json, $signature] = $this->signedManifest();

		$tampered = $json . ' ';
		$result = \znote_update_verify_manifest($tampered, $signature);
		$this->assertFalse($result['ok']);
	}

	public function testForgedSignatureIsRejected(): void
	{
		[, $json] = $this->signedManifest();

		$result = \znote_update_verify_manifest($json, base64_encode(str_repeat('x', 256)));
		$this->assertFalse($result['ok']);
	}

	public function testManifestWithAnInvalidPackageNameIsRejected(): void
	{
		[$manifest] = $this->signedManifest();
		$manifest['package']['name'] = '../evil.zip';
		[$json, $signature] = $this->sign($manifest);

		$result = \znote_update_verify_manifest($json, $signature);
		$this->assertFalse($result['ok']);
	}

	public function testManifestListingAProtectedFileIsRejected(): void
	{
		// The signature check itself does not know about protected paths -
		// that is enforced separately by znote_update_extract(). This test
		// documents that boundary: a manifest can list any path shape as far
		// as signature verification is concerned, but znote_update_path()
		// still normalises it, so a raw ".." entry is caught here already.
		[$manifest] = $this->signedManifest();
		$manifest['files'] = ['../../config.local.php' => str_repeat('a', 64)];
		[$json, $signature] = $this->sign($manifest);

		$result = \znote_update_verify_manifest($json, $signature);
		$this->assertFalse($result['ok']);
	}

	public function testExtractRejectsAFileNotListedInTheSignedManifest(): void
	{
		$zipFile = $this->buildZip(['engine/function/general.php' => 'safe contents']);
		$destination = sys_get_temp_dir() . '/znotex-test-extract-' . bin2hex(random_bytes(6));

		try {
			$files = []; // Nothing is listed as signed.
			$result = \znote_update_extract($zipFile, $files, $destination);
			$this->assertFalse($result['ok']);
			$this->assertStringContainsString('unexpected or protected', $result['error']);
		} finally {
			@unlink($zipFile);
			$this->removeTree($destination);
		}
	}

	public function testExtractRejectsAProtectedFileEvenIfItsHashMatches(): void
	{
		$content = "<?php \$config['sqlPassword'] = 'stolen';";
		$hash = hash('sha256', $content);
		$zipFile = $this->buildZip(['config.local.php' => $content]);
		$destination = sys_get_temp_dir() . '/znotex-test-extract-' . bin2hex(random_bytes(6));

		try {
			$result = \znote_update_extract($zipFile, ['config.local.php' => $hash], $destination);
			$this->assertFalse($result['ok']);
			$this->assertStringContainsString('unexpected or protected', $result['error']);
		} finally {
			@unlink($zipFile);
			$this->removeTree($destination);
		}
	}

	public function testExtractRejectsAFileWhoseContentDoesNotMatchItsSignedHash(): void
	{
		$zipFile = $this->buildZip(['engine/function/general.php' => 'tampered contents']);
		$destination = sys_get_temp_dir() . '/znotex-test-extract-' . bin2hex(random_bytes(6));

		try {
			$wrongHash = hash('sha256', 'original untampered contents');
			$result = \znote_update_extract($zipFile, ['engine/function/general.php' => $wrongHash], $destination);
			$this->assertFalse($result['ok']);
			$this->assertStringContainsString('checksum validation', $result['error']);
		} finally {
			@unlink($zipFile);
			$this->removeTree($destination);
		}
	}

	public function testExtractAcceptsAFileThatMatchesItsSignedHash(): void
	{
		$content = 'genuine contents';
		$hash = hash('sha256', $content);
		$zipFile = $this->buildZip(['engine/function/example.php' => $content]);
		$destination = sys_get_temp_dir() . '/znotex-test-extract-' . bin2hex(random_bytes(6));

		try {
			$result = \znote_update_extract($zipFile, ['engine/function/example.php' => $hash], $destination);
			$this->assertTrue($result['ok']);
			$this->assertSame($content, file_get_contents($destination . '/engine/function/example.php'));
		} finally {
			@unlink($zipFile);
			$this->removeTree($destination);
		}
	}

	/** @return array{0: array, 1: string, 2: string} */
	private function signedManifest(): array
	{
		$manifest = [
			'schema' => 1,
			'version' => '9.9.9',
			'package' => [
				'name' => 'znotex-core-9.9.9.zip',
				'sha256' => str_repeat('a', 64),
				'size' => 123,
			],
			'files' => [
				'engine/function/general.php' => str_repeat('b', 64),
			],
		];
		[$json, $signature] = $this->sign($manifest);

		return [$manifest, $json, $signature];
	}

	private function sign(array $manifest): array
	{
		$json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
		$privateKey = file_get_contents(__DIR__ . '/../fixtures/test-update-private.pem');
		openssl_sign($json, $signature, $privateKey, OPENSSL_ALGO_SHA256);

		return [$json, base64_encode($signature) . "\n"];
	}

	private function buildZip(array $files): string
	{
		$path = sys_get_temp_dir() . '/znotex-test-' . bin2hex(random_bytes(6)) . '.zip';
		$zip = new ZipArchive();
		$zip->open($path, ZipArchive::CREATE);
		foreach ($files as $name => $content) {
			$zip->addFromString($name, $content);
		}
		$zip->close();

		return $path;
	}

	private function removeTree(string $path): void
	{
		if (!is_dir($path)) {
			return;
		}
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ($iterator as $item) {
			$item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
		}
		rmdir($path);
	}
}
