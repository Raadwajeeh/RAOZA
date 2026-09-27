<?php
namespace Tests\Feature;
use Tests\TestCase;
class FoundationTest extends TestCase { public function test_home_page_is_available(): void { $this->get('/')->assertOk(); } public function test_admin_requires_authentication(): void { $this->get('/admin')->assertRedirect('/admin/login'); } }
