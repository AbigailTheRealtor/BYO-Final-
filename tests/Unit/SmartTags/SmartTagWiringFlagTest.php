<?php

namespace Tests\Unit\SmartTags;

use PHPUnit\Framework\TestCase;

/**
 * The activation gates, evaluated the way an operator actually sets them.
 *
 * config/smart_tags_wiring.php is evaluated in a CHILD PROCESS with the variables
 * set to the literal strings people type, because the parent process has already
 * loaded the file and `env()` is read once. The same method
 * RequiredProductionDefaultsTest uses, for the same reason.
 */
class SmartTagWiringFlagTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * @param array<string, string|null> $env
     * @return array{enabled: bool, bridge_enabled: bool}
     */
    private function evaluate(array $env): array
    {
        $assignments = '';
        foreach ($env as $key => $value) {
            $assignments .= $value === null
                ? sprintf("unset(\$_ENV[%s], \$_SERVER[%s]); putenv(%s);\n",
                    var_export($key, true), var_export($key, true), var_export($key, true))
                : sprintf("\$_ENV[%s] = %s; \$_SERVER[%s] = %s; putenv(%s);\n",
                    var_export($key, true), var_export($value, true),
                    var_export($key, true), var_export($value, true),
                    var_export($key . '=' . $value, true));
        }

        $script = <<<PHP
<?php
{$assignments}
if (! function_exists('env')) {
    function env(\$key, \$default = null) {
        \$v = \$_ENV[\$key] ?? \$_SERVER[\$key] ?? getenv(\$key);
        return (\$v === false || \$v === null) ? \$default : \$v;
    }
}
\$config = require '{$this->root()}/config/smart_tags_wiring.php';
echo json_encode(\$config);
PHP;

        $file = tempnam(sys_get_temp_dir(), 'smarttagflag') . '.php';
        file_put_contents($file, $script);
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1');
        @unlink($file);

        $decoded = json_decode((string) $output, true);
        $this->assertIsArray($decoded, "config/smart_tags_wiring.php did not evaluate: {$output}");

        return $decoded;
    }

    /** @test */
    public function both_gates_default_to_off_when_nothing_is_set(): void
    {
        $config = $this->evaluate([
            'SMART_TAGS_DERIVATION_ENABLED' => null,
            'SMART_TAGS_BRIDGE_ENABLED'     => null,
        ]);

        $this->assertFalse($config['enabled'], 'The master gate must ship OFF.');
        $this->assertFalse($config['bridge_enabled'], 'The Bridge gate must ship OFF.');
        $this->assertFalse($config['seeker_matching_enabled'], 'The seeker MATCHING gate must ship OFF.');
    }

    /**
     * The seeker matching gate parses exactly like the others, and is read
     * independently of the picker gate — the picker can be on with matching off.
     *
     * @test
     */
    public function the_seeker_matching_gate_parses_fail_closed_and_independently(): void
    {
        foreach (self::onValues() as [$on]) {
            $this->assertTrue($this->evaluate(['SMART_TAGS_SEEKER_MATCHING_ENABLED' => $on])['seeker_matching_enabled'], $on);
        }

        foreach (self::offValues() as [$off]) {
            $this->assertFalse($this->evaluate(['SMART_TAGS_SEEKER_MATCHING_ENABLED' => $off])['seeker_matching_enabled'], $off);
        }

        $pickerOnly = $this->evaluate(['SMART_TAGS_SEEKER_PREFERENCES_ENABLED' => 'true', 'SMART_TAGS_SEEKER_MATCHING_ENABLED' => null]);
        $this->assertTrue($pickerOnly['seeker_preferences_enabled']);
        $this->assertFalse($pickerOnly['seeker_matching_enabled'], 'Enabling the picker must not enable matching.');
    }

    /**
     * The per-context activation list ships EMPTY — matching on alone scores
     * nothing — and is a plain list of the exact strings the operator typed.
     * Validation against SmartTagContext is the gate's job, not the parser's.
     *
     * @test
     */
    public function the_seeker_matching_context_list_ships_empty_and_parses_a_comma_list(): void
    {
        $this->assertSame([], $this->evaluate(['SMART_TAGS_SEEKER_MATCHING_CONTEXTS' => null])['seeker_matching_contexts']);
        $this->assertSame([], $this->evaluate(['SMART_TAGS_SEEKER_MATCHING_CONTEXTS' => ''])['seeker_matching_contexts']);
        $this->assertSame([], $this->evaluate(['SMART_TAGS_SEEKER_MATCHING_CONTEXTS' => ' , '])['seeker_matching_contexts']);
        $this->assertSame(
            ['residential.sale', 'residential.lease'],
            $this->evaluate(['SMART_TAGS_SEEKER_MATCHING_CONTEXTS' => ' residential.sale , residential.lease '])['seeker_matching_contexts'],
        );

        $matchingOnly = $this->evaluate(['SMART_TAGS_SEEKER_MATCHING_ENABLED' => 'true', 'SMART_TAGS_SEEKER_MATCHING_CONTEXTS' => null]);
        $this->assertSame([], $matchingOnly['seeker_matching_contexts'], 'Turning matching on must not activate a context.');
    }

    /** @test */
    public function the_bridge_catch_up_schedule_gate_ships_off_and_parses_fail_closed(): void
    {
        $this->assertFalse($this->evaluate(['SMART_TAGS_BRIDGE_CATCHUP_SCHEDULE_ENABLED' => null])['bridge_catch_up_schedule_enabled']);

        foreach (self::onValues() as [$on]) {
            $this->assertTrue($this->evaluate(['SMART_TAGS_BRIDGE_CATCHUP_SCHEDULE_ENABLED' => $on])['bridge_catch_up_schedule_enabled'], $on);
        }

        foreach (self::offValues() as [$off]) {
            $this->assertFalse($this->evaluate(['SMART_TAGS_BRIDGE_CATCHUP_SCHEDULE_ENABLED' => $off])['bridge_catch_up_schedule_enabled'], $off);
        }

        $derivationOnly = $this->evaluate(['SMART_TAGS_DERIVATION_ENABLED' => 'true', 'SMART_TAGS_BRIDGE_ENABLED' => 'true', 'SMART_TAGS_BRIDGE_CATCHUP_SCHEDULE_ENABLED' => null]);
        $this->assertFalse($derivationOnly['bridge_catch_up_schedule_enabled'], 'Opening derivation must not schedule a catch-up.');
    }

    /**
     * @test
     * @dataProvider onValues
     */
    public function only_the_approved_true_representations_turn_a_gate_on(string $value): void
    {
        $config = $this->evaluate([
            'SMART_TAGS_DERIVATION_ENABLED' => $value,
            'SMART_TAGS_BRIDGE_ENABLED'     => $value,
        ]);

        $this->assertTrue($config['enabled'], "'{$value}' should enable the master gate.");
        $this->assertTrue($config['bridge_enabled'], "'{$value}' should enable the Bridge gate.");
    }

    public static function onValues(): array
    {
        return [['true'], ['TRUE'], ['1'], ['on'], ['ON'], ['yes'], ['YES']];
    }

    /**
     * The values a plain (bool) cast reads as ON. `off` and `no` are the two that
     * have actually caused this in production elsewhere in this codebase.
     *
     * @test
     * @dataProvider offValues
     */
    public function everything_else_including_malformed_values_reads_as_off(string $value): void
    {
        $config = $this->evaluate([
            'SMART_TAGS_DERIVATION_ENABLED' => $value,
            'SMART_TAGS_BRIDGE_ENABLED'     => $value,
        ]);

        $this->assertFalse($config['enabled'], "'{$value}' must not enable the master gate.");
        $this->assertFalse($config['bridge_enabled'], "'{$value}' must not enable the Bridge gate.");
    }

    public static function offValues(): array
    {
        return [
            ['false'], ['FALSE'], ['0'], ['off'], ['OFF'], ['no'], ['NO'],
            [''], [' '], ['maybe'], ['enabled'], ['null'], ['2'], ['-1'], ['truthy'],
        ];
    }

    /** @test */
    public function the_two_gates_are_independent(): void
    {
        $masterOnly = $this->evaluate([
            'SMART_TAGS_DERIVATION_ENABLED' => 'true',
            'SMART_TAGS_BRIDGE_ENABLED'     => null,
        ]);

        $this->assertTrue($masterOnly['enabled']);
        $this->assertFalse($masterOnly['bridge_enabled'],
            'The master gate must not imply the Bridge gate.');

        $bridgeOnly = $this->evaluate([
            'SMART_TAGS_DERIVATION_ENABLED' => null,
            'SMART_TAGS_BRIDGE_ENABLED'     => 'true',
        ]);

        $this->assertFalse($bridgeOnly['enabled']);
        $this->assertTrue($bridgeOnly['bridge_enabled'],
            'The two values are read independently; enabledFor() is what combines them.');
    }
}
