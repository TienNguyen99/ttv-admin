<?php

namespace Tests\Unit;

use App\Services\InternalCustomerCatalogSync;
use PHPUnit\Framework\TestCase;

class InternalCustomerCatalogSyncTest extends TestCase
{
    public function test_customer_key_ignores_case_and_extra_spaces(): void
    {
        $this->assertSame(
            InternalCustomerCatalogSync::key('  UniPax  Viet   Nam '),
            InternalCustomerCatalogSync::key('UNIPAX VIET NAM')
        );
    }
}
