<?php

namespace Tests\Unit;

use App\Services\PantoneColorMatcher;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class PantoneColorMatcherTest extends TestCase
{
    public function test_it_prefers_the_longest_color_name_in_text(): void
    {
        $reflection = new ReflectionClass(PantoneColorMatcher::class);
        /** @var PantoneColorMatcher $matcher */
        $matcher = $reflection->newInstanceWithoutConstructor();

        $byName = $reflection->getProperty('byName');
        $byName->setAccessible(true);
        $byName->setValue($matcher, [
            'black' => ['pantone' => 'BLACK', 'hex' => '#111111', 'name' => 'Black'],
            'deep black' => ['pantone' => 'BLACK 6 C', 'hex' => '#101820', 'name' => 'Deep Black'],
        ]);

        $maxNameWords = $reflection->getProperty('maxNameWords');
        $maxNameWords->setAccessible(true);
        $maxNameWords->setValue($matcher, 2);

        $match = $matcher->matchValues(['Day satin mau Deep Black']);

        $this->assertSame('BLACK 6 C', $match['pantone']);
        $this->assertSame('#101820', $match['hex']);
        $this->assertSame('color_name', $match['source']);
    }
}
