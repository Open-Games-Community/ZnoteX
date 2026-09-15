<?php

declare(strict_types=1);

namespace ZnoteX\Tests\Security;

use PHPUnit\Framework\TestCase;

final class MigrationSqlSplitterTest extends TestCase
{
	public function testSplitsSimpleStatements(): void
	{
		$sql = "CREATE TABLE a (id INT);\nINSERT INTO a VALUES (1);";
		$this->assertSame(
			['CREATE TABLE a (id INT)', 'INSERT INTO a VALUES (1)'],
			\znote_migration_split_sql($sql)
		);
	}

	public function testSemicolonInsideAStringLiteralDoesNotSplit(): void
	{
		$sql = "INSERT INTO a (name) VALUES ('it;s here');";
		$statements = \znote_migration_split_sql($sql);
		$this->assertCount(1, $statements);
		$this->assertStringContainsString("'it;s here'", $statements[0]);
	}

	public function testSemicolonInsideALineCommentDoesNotSplit(): void
	{
		// The comment has no terminating semicolon of its own, so it attaches
		// to the following statement as a single chunk.
		$sql = "-- comment ; still comment\nINSERT INTO a VALUES (1);";
		$statements = \znote_migration_split_sql($sql);
		$this->assertCount(1, $statements);
		$this->assertStringContainsString('INSERT INTO a VALUES (1)', $statements[0]);
	}

	public function testSemicolonInsideABlockCommentDoesNotSplit(): void
	{
		$sql = "/* a ; block ; comment */ INSERT INTO a VALUES (1);";
		$statements = \znote_migration_split_sql($sql);
		$this->assertCount(1, $statements);
	}

	public function testEscapedQuoteInsideAStringDoesNotCloseIt(): void
	{
		$sql = "INSERT INTO a (name) VALUES ('it\\'s a trap; still one statement');";
		$statements = \znote_migration_split_sql($sql);
		$this->assertCount(1, $statements);
	}

	public function testHashStartsALineComment(): void
	{
		$sql = "# hash comment ; here\nINSERT INTO a VALUES (2);";
		$statements = \znote_migration_split_sql($sql);
		$this->assertCount(1, $statements);
		$this->assertStringContainsString('INSERT INTO a VALUES (2)', $statements[0]);
	}

	public function testEmptyStatementsAreDropped(): void
	{
		$sql = "INSERT INTO a VALUES (1);;;  ;\nINSERT INTO a VALUES (2);";
		$statements = \znote_migration_split_sql($sql);
		$this->assertCount(2, $statements);
	}

	public function testTrailingStatementWithoutASemicolonIsKept(): void
	{
		$sql = "INSERT INTO a VALUES (1);\nINSERT INTO a VALUES (2)";
		$statements = \znote_migration_split_sql($sql);
		$this->assertCount(2, $statements);
		$this->assertSame('INSERT INTO a VALUES (2)', $statements[1]);
	}

	public function testDestructiveStatementsAreRejectedByTheUpdaterSafetyCheck(): void
	{
		$this->assertFalse(\znote_update_migration_safe('DROP TABLE accounts;'));
		$this->assertFalse(\znote_update_migration_safe('DELETE FROM accounts;'));
		$this->assertFalse(\znote_update_migration_safe('UPDATE accounts SET password = \'\';'));
		$this->assertFalse(\znote_update_migration_safe('TRUNCATE accounts;'));
	}

	public function testAdditiveStatementsAreAcceptedByTheUpdaterSafetyCheck(): void
	{
		$this->assertTrue(\znote_update_migration_safe('CREATE TABLE IF NOT EXISTS foo (id INT);'));
		$this->assertTrue(\znote_update_migration_safe('ALTER TABLE foo ADD COLUMN bar INT;'));
		$this->assertTrue(\znote_update_migration_safe("INSERT IGNORE INTO foo (id) VALUES (1);"));
	}

	public function testAMixOfSafeAndDestructiveStatementsIsRejected(): void
	{
		$sql = "CREATE TABLE IF NOT EXISTS foo (id INT);\nDROP TABLE bar;";
		$this->assertFalse(\znote_update_migration_safe($sql));
	}

	public function testDestructiveKeywordHiddenInAStringLiteralIsStillCaughtByTheRegexScan(): void
	{
		// znote_update_migration_safe() only strips comments, not string
		// contents, so a DROP/DELETE keyword anywhere in the statement -
		// even inside a quoted value - is treated as unsafe. This is a
		// deliberately conservative false positive, not a bypass.
		$sql = "INSERT IGNORE INTO foo (note) VALUES ('please DROP by later');";
		$this->assertFalse(\znote_update_migration_safe($sql));
	}
}
