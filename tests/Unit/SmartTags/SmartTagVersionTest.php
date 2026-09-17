<?php

namespace Tests\Unit\SmartTags;

use App\Support\SmartTags\SmartTagVersion;
use PHPUnit\Framework\TestCase;

class SmartTagVersionTest extends TestCase
{
    /** @test */
    public function the_tagger_version_is_deterministic(): void
    {
        $this->assertSame(SmartTagVersion::taggerVersion(), SmartTagVersion::taggerVersion());
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', SmartTagVersion::taggerVersion());
    }

    /** @test */
    public function structured_input_hashes_ignore_key_order(): void
    {
        $this->assertSame(
            SmartTagVersion::structuredInputsHash(['a' => 1, 'b' => ['x', 'y']]),
            SmartTagVersion::structuredInputsHash(['b' => ['x', 'y'], 'a' => 1]),
        );
        $this->assertNotSame(
            SmartTagVersion::structuredInputsHash(['b' => ['x', 'y']]),
            SmartTagVersion::structuredInputsHash(['b' => ['y', 'x']]),
        );
    }

    /** @test */
    public function description_hashes_ignore_cosmetic_differences_and_null_means_no_text(): void
    {
        $this->assertSame(
            SmartTagVersion::descriptionHash('Quartz countertops. Move-in ready!'),
            SmartTagVersion::descriptionHash("  QUARTZ   countertops.\u{00A0}Move\u{2011}in ready!  "),
        );
        $this->assertNotSame(
            SmartTagVersion::descriptionHash('Quartz countertops.'),
            SmartTagVersion::descriptionHash('Granite countertops.'),
        );
        $this->assertNull(SmartTagVersion::descriptionHash(null));
        $this->assertNull(SmartTagVersion::descriptionHash('   '));
        $this->assertNull(SmartTagVersion::descriptionHash('<p></p>'));
    }
}
