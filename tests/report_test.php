<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace gradereport_markingguide;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionMethod;

/**
 * Tests for the cell-safety helpers on the marking guide report.
 *
 * These pin the output-escaping and spreadsheet-formula behaviour. flexible_table
 * does not escape the content it is handed, so the report is responsible for making
 * every cell value safe; these tests fail if that responsibility is dropped.
 *
 * @package    gradereport_markingguide
 * @copyright  2026 onward Brickfield Education Labs Ltd, https://www.brickfield.ie
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(report::class)]
final class report_test extends \advanced_testcase {
    /**
     * Call the protected static neutralise_formula() helper.
     *
     * @param string $value The cell value to pass in.
     * @return string The helper's return value.
     */
    private static function neutralise(string $value): string {
        $method = new ReflectionMethod(report::class, 'neutralise_formula');
        $method->setAccessible(true);
        return $method->invoke(null, $value);
    }

    /**
     * Call the protected format_cell_value() helper without constructing a report.
     *
     * The constructor needs a real course and grade tree, none of which this helper
     * touches, so an uninitialised instance keeps the test a pure unit test.
     *
     * @param string $value The cell value to pass in.
     * @param bool $downloading Whether to exercise the download path.
     * @return string The helper's return value.
     */
    private static function formatcell(string $value, bool $downloading): string {
        $instance = (new ReflectionClass(report::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(report::class, 'format_cell_value');
        $method->setAccessible(true);
        return $method->invoke($instance, $value, $downloading);
    }

    /**
     * Values whose first character makes a spreadsheet evaluate the cell.
     *
     * @return array<string, array{string, bool}>
     */
    public static function formula_provider(): array {
        return [
            'equals'            => ['=1+1', true],
            'plus'              => ['+1', true],
            'minus'             => ['-1', true],
            'at'                => ['@SUM(A1)', true],
            'hyperlink payload' => ['=HYPERLINK("http://evil.example","click")', true],
            'dde payload'       => ['=cmd|\' /c calc\'!A1', true],
            'leading space'     => [' =1+1', true],
            'leading tab'       => ["\t=1+1", true],
            'plain text'        => ['Good work', false],
            'leading digit'     => ['8.5 - see above', false],
            'empty'             => ['', false],
            'whitespace only'   => ['   ', false],
            'mid-string equals' => ['score = 10', false],
        ];
    }

    /**
     * Test that only values a spreadsheet would evaluate get the apostrophe prefix.
     *
     * @param string $value The cell value.
     * @param bool $expectprefix Whether the value should be prefixed.
     */
    #[DataProvider('formula_provider')]
    public function test_neutralise_formula(string $value, bool $expectprefix): void {
        $result = self::neutralise($value);

        if ($expectprefix) {
            $this->assertSame("'" . $value, $result, "'$value' must be neutralised for spreadsheets");
        } else {
            $this->assertSame($value, $result, "'$value' must be passed through unchanged");
        }
    }

    /**
     * Test that leading whitespace does not defeat the formula check.
     *
     * A spreadsheet still evaluates a cell that begins with whitespace, so testing
     * only the very first character would let a padded payload through.
     */
    public function test_neutralise_formula_ignores_leading_whitespace(): void {
        $this->assertSame("' =cmd", self::neutralise(' =cmd'));
        $this->assertSame("'\t+1", self::neutralise("\t+1"));
    }

    /**
     * Test that the HTML path escapes markup rather than emitting it raw.
     *
     * flexible_table passes cell content to html_writer::tag(), which escapes
     * attributes only, so an unescaped script tag would execute for the viewer.
     */
    public function test_format_cell_value_escapes_html(): void {
        $payload = '<img src=x onerror="alert(1)">';

        $result = self::formatcell($payload, false);

        $this->assertStringNotContainsString('<img', $result, 'Markup must not survive into the cell');
        $this->assertStringNotContainsString('onerror="', $result, 'Event handler must not survive unescaped');
        $this->assertStringContainsString('&lt;img', $result, 'Markup must be escaped, not stripped');
    }

    /**
     * Test that the HTML path leaves ordinary text alone.
     */
    public function test_format_cell_value_preserves_plain_text(): void {
        $this->assertSame('Clear and well argued', self::formatcell('Clear and well argued', false));
    }

    /**
     * Test that the download path neutralises formulas instead of escaping HTML.
     *
     * A CSV cell is not HTML, so escaping there would put entities in the export;
     * the risk to defuse is the spreadsheet evaluating the cell as a formula.
     */
    public function test_format_cell_value_neutralises_formula_on_download(): void {
        $this->assertSame("'=1+1", self::formatcell('=1+1', true));
        $this->assertSame('Ada Lovelace', self::formatcell('Ada Lovelace', true));
    }

    /**
     * Test that an identity value a user controls is made safe on both paths.
     *
     * An idnumber can be set by bulk upload or auth sync, so it is not covered by
     * the PARAM_NOTAGS cleaning applied to profile name fields on the edit form.
     */
    public function test_format_cell_value_handles_hostile_idnumber(): void {
        $idnumber = '=cmd|\' /c calc\'!A1<script>alert(1)</script>';

        $html = self::formatcell($idnumber, false);
        $this->assertStringNotContainsString('<script>', $html, 'Script tag must be escaped for display');

        $download = self::formatcell($idnumber, true);
        $this->assertStringStartsWith("'", $download, 'Formula must be neutralised for export');
    }
}
