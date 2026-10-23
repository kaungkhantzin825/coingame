<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The web root is not a page — it redirects into the Filament admin panel
     * (see routes/web.php). Players only ever talk to /api.
     */
    public function test_the_web_root_redirects_to_the_admin_panel(): void
    {
        $this->get('/')->assertRedirect('/admin');
    }
}
